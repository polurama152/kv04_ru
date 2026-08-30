<?php

namespace Kv04\Diary;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;

class PinService
{
	public const PIN_LENGTH = 4;

	public static function pepper(): string
	{
		return (string)Option::get(Installer::MODULE_ID, 'pepper', '');
	}

	public static function normalize(string $pin): string
	{
		return preg_replace('/\D+/', '', $pin) ?? '';
	}

	public static function isValidFormat(string $pin): bool
	{
		return (bool)preg_match('/^\d{' . self::PIN_LENGTH . '}$/', $pin);
	}

	public static function hashPin(string $pin): string
	{
		return hash_hmac('sha256', $pin, self::pepper());
	}

	/**
	 * Вход. Аккаунт определяется по порядку: привязка устройства, затем почта,
	 * затем legacy-поиск по пину для записей, у которых почты ещё нет.
	 *
	 * Раньше запись искали по хэшу пина во всём пространстве 10 000 комбинаций,
	 * поэтому вероятность попасть в ЧУЖОЙ дневник равнялась N/10 000: при
	 * тысяче пользователей обычная опечатка открывала чужие записи в 10%
	 * случаев. Теперь пин проверяется внутри одного аккаунта.
	 *
	 * $slugOwnerId — вход с личного адреса: сам адрес и есть «чей дневник»,
	 * поэтому почта не нужна. Строка — известный владелец, пустая строка —
	 * адрес незнакомый. Пустую НЕ подменяем ни привязкой устройства, ни
	 * почтой намеренно: иначе на несуществующем адресе свой пин пускал бы
	 * внутрь, а на чужом — нет, и по этой разнице чужие адреса можно было бы
	 * перечислить. Незнакомый адрес обязан отвечать ровно как чужой.
	 */
	public static function login(string $pin, string $email = '', ?string $slugOwnerId = null): array
	{
		$pin = self::normalize($pin);
		if (!self::isValidFormat($pin))
		{
			return ['ok' => false, 'error' => 'Введите 4 цифры'];
		}

		$ipKey = AttemptLimiter::ipKey(self::remoteAddress());
		$ipState = AttemptLimiter::state($ipKey);
		if ($ipState['locked'])
		{
			return self::lockedResult($ipState['wait']);
		}

		if ($slugOwnerId === null)
		{
			$row = self::resolveAccount($email, $pin);
		}
		else
		{
			// Пустая строка — незнакомый адрес: искать некого, и подстановка
			// другого аккаунта была бы той самой утечкой.
			$row = $slugOwnerId === '' ? null : self::findByOwner($slugOwnerId);
		}
		if ($row)
		{
			$accountKey = AttemptLimiter::accountKey((string)$row['UF_OWNER_ID']);
			$accountState = AttemptLimiter::state($accountKey);
			if ($accountState['locked'])
			{
				return self::lockedResult($accountState['wait']);
			}

			if (hash_equals((string)$row['UF_PIN_HASH'], self::hashPin($pin)))
			{
				// Счётчик аккаунта сбрасываем: владелец доказал, что это он.
				// Счётчик по IP — нет, это грубый предохранитель от перебора,
				// иначе владелец обнулял бы его удачным входом к себе и
				// перебирал чужие пины по две догадки за цикл.
				AttemptLimiter::reset($accountKey);
				Auth::login((string)$row['UF_OWNER_ID']);

				return ['ok' => true];
			}

			$after = AttemptLimiter::registerFail($accountKey);
			AttemptLimiter::registerFail($ipKey);

			return $after['locked']
				? self::lockedResult($after['wait'])
				: ['ok' => false, 'error' => 'Неверный пин'];
		}

		$after = AttemptLimiter::registerFail($ipKey);

		return $after['locked']
			? self::lockedResult($after['wait'])
			: ['ok' => false, 'error' => self::wrongCredentialsMessage($email)];
	}

	public static function create(string $email, string $pin, string $confirm): array
	{
		$pin = self::normalize($pin);
		$confirm = self::normalize($confirm);
		if (!self::isValidFormat($pin) || $pin !== $confirm)
		{
			return ['ok' => false, 'error' => 'Пины не совпадают'];
		}

		$email = self::normalizeEmail($email);
		if (!self::isValidEmail($email))
		{
			return ['ok' => false, 'error' => 'Введите почту'];
		}

		$ipKey = AttemptLimiter::ipKey(self::remoteAddress());
		$ipState = AttemptLimiter::state($ipKey);
		if ($ipState['locked'])
		{
			return self::lockedResult($ipState['wait']);
		}

		// Занятость ПОЧТЫ сообщать приходится — иначе человек не поймёт, почему
		// не создаётся дневник. В отличие от прежнего «такой пин занят», это не
		// раскрывает секрет: зная почту, войти всё равно нельзя. Плюс попытка
		// стоит места в лестнице, так что перечислить чужие адреса не выйдет.
		if (self::findByEmail($email))
		{
			$after = AttemptLimiter::registerFail($ipKey);

			return $after['locked']
				? self::lockedResult($after['wait'])
				: ['ok' => false, 'error' => 'На эту почту дневник уже заведён'];
		}

		$dataClass = self::dataClass();
		$ownerId = bin2hex(random_bytes(16));
		$result = $dataClass::add([
			'UF_PIN_HASH' => self::hashPin($pin),
			'UF_OWNER_ID' => $ownerId,
			'UF_EMAIL' => $email,
			'UF_FAILS' => 0,
			'UF_LOCKED_UNTIL' => 0,
		]);
		if (!$result->isSuccess())
		{
			return ['ok' => false, 'error' => 'Не удалось создать дневник'];
		}

		Auth::login($ownerId);

		return ['ok' => true];
	}

	/** Привязать почту к дневнику, заведённому до появления идентичности. */
	public static function attachEmail(string $ownerId, string $email): array
	{
		$email = self::normalizeEmail($email);
		if (!self::isValidEmail($email))
		{
			return ['ok' => false, 'error' => 'Введите почту'];
		}

		$existing = self::findByEmail($email);
		if ($existing && (string)$existing['UF_OWNER_ID'] !== $ownerId)
		{
			return ['ok' => false, 'error' => 'На эту почту дневник уже заведён'];
		}

		$row = self::findByOwner($ownerId);
		if (!$row)
		{
			return ['ok' => false, 'error' => 'Дневник не найден'];
		}

		self::dataClass()::update((int)$row['ID'], ['UF_EMAIL' => $email]);

		return ['ok' => true];
	}

	public static function normalizeEmail(string $email): string
	{
		return mb_strtolower(trim($email));
	}

	public static function isValidEmail(string $email): bool
	{
		return $email !== '' && mb_strlen($email) <= 180 && (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
	}

	/** У дневника уже есть идентичность? Нужно ленте, чтобы попросить почту. */
	public static function hasEmail(string $ownerId): bool
	{
		$row = self::findByOwner($ownerId);

		return $row !== null && (string)$row['UF_EMAIL'] !== '';
	}

	/** Нужна ли форме почта: на знакомом устройстве обходимся пином. */
	public static function needsEmail(): bool
	{
		return Auth::boundOwnerId() === null && self::hasAnyEmail();
	}

	private static function resolveAccount(string $email, string $pin): ?array
	{
		$bound = Auth::boundOwnerId();
		if ($bound !== null)
		{
			$row = self::findByOwner($bound);
			if ($row)
			{
				return $row;
			}
		}

		$email = self::normalizeEmail($email);
		if ($email !== '')
		{
			return self::findByEmail($email);
		}

		// Переходный период: дневники, заведённые до появления почты, ищутся
		// по пину как раньше. Ветка исчезнет, когда у всех записей будет email.
		return self::findLegacyByPin(self::hashPin($pin));
	}

	private static function wrongCredentialsMessage(string $email): string
	{
		return self::normalizeEmail($email) !== '' ? 'Неверная почта или пин' : 'Неверный пин';
	}

	private static function findOne(array $filter): ?array
	{
		$row = self::dataClass()::getList([
			'select' => ['ID', 'UF_PIN_HASH', 'UF_OWNER_ID', 'UF_EMAIL'],
			'filter' => $filter,
			'limit' => 1,
		])->fetch();

		return $row ?: null;
	}

	private static function findByEmail(string $email): ?array
	{
		return self::findOne(['=UF_EMAIL' => $email]);
	}

	private static function findByOwner(string $ownerId): ?array
	{
		return self::findOne(['=UF_OWNER_ID' => $ownerId]);
	}

	/**
	 * Поиск по пину во всём пространстве — только для записей без почты.
	 * Именно эта выборка и порождала коллизии, поэтому ограничиваем её
	 * незамигрированными дневниками и уберём совсем после переходного периода.
	 */
	private static function findLegacyByPin(string $pinHash): ?array
	{
		$row = self::findOne(['=UF_PIN_HASH' => $pinHash, '=UF_EMAIL' => false]);

		return $row ?: self::findOne(['=UF_PIN_HASH' => $pinHash, '=UF_EMAIL' => '']);
	}

	/** Хоть у одного дневника уже есть почта — значит форме входа её спрашивать. */
	private static function hasAnyEmail(): bool
	{
		$row = self::dataClass()::getList([
			'select' => ['ID'],
			'filter' => ['!=UF_EMAIL' => false],
			'limit' => 1,
		])->fetch();

		return (bool)$row;
	}

	private static function lockedResult(int $wait): array
	{
		$human = AttemptLimiter::describeWait($wait);

		return [
			'ok' => false,
			'error' => $human !== ''
				? 'Слишком много попыток. Повторите через ' . $human
				: 'Слишком много попыток. Повторите позже.',
			'locked' => true,
		];
	}

	private static function remoteAddress(): string
	{
		return (string)Context::getCurrent()->getRequest()->getRemoteAddress();
	}

	private static function dataClass()
	{
		if (!Loader::includeModule('highloadblock'))
		{
			throw new \RuntimeException('highloadblock');
		}
		$hlId = (int)Option::get(Installer::MODULE_ID, 'hlblock_id', '0');
		$hl = HighloadBlockTable::getById($hlId)->fetch();
		if (!$hl)
		{
			throw new \RuntimeException('HL дневника не найден');
		}
		return HighloadBlockTable::compileEntity($hl)->getDataClass();
	}
}
