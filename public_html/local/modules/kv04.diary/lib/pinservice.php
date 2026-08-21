<?php

namespace Kv04\Diary;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;

class PinService
{
	public const PIN_LENGTH = 4;
	public const MAX_FAILS = 3;
	public const LOCK_SECONDS = 86400;

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

	public static function login(string $pin): array
	{
		$pin = self::normalize($pin);
		if (!self::isValidFormat($pin))
		{
			return ['ok' => false, 'error' => 'Введите 4 цифры'];
		}

		$ipKey = AttemptLimiter::ipKey(self::remoteAddress());
		$state = AttemptLimiter::state($ipKey);
		if ($state['locked'])
		{
			return self::lockedResult($state['wait']);
		}

		$row = self::findByHash(self::hashPin($pin));
		if (!$row)
		{
			$after = AttemptLimiter::registerFail($ipKey);

			return $after['locked']
				? self::lockedResult($after['wait'])
				: ['ok' => false, 'error' => 'Неверный пин'];
		}

		// Счётчик по IP при успехе не сбрасываем: это грубый предохранитель от
		// перебора, а не персональный счётчик. Владелец своего дневника иначе
		// обнулял бы его удачным входом и перебирал чужие пины бесконечно.
		// Протухнет сам через сутки без ошибок.
		Auth::login((string)$row['UF_OWNER_ID']);

		return ['ok' => true];
	}

	public static function create(string $pin, string $confirm): array
	{
		$pin = self::normalize($pin);
		$confirm = self::normalize($confirm);
		if (!self::isValidFormat($pin) || $pin !== $confirm)
		{
			return ['ok' => false, 'error' => 'Пины не совпадают'];
		}

		// Тот же счётчик, что и у входа. Без него create() был оракулом:
		// 10 000 запросов перечисляли все существующие дневники по ответу
		// «такой пин занят», а лимит входа не срабатывал ни разу, потому что
		// неверных входов при этом не происходило.
		$ipKey = AttemptLimiter::ipKey(self::remoteAddress());
		$state = AttemptLimiter::state($ipKey);
		if ($state['locked'])
		{
			return self::lockedResult($state['wait']);
		}

		$hash = self::hashPin($pin);
		if (self::findByHash($hash))
		{
			$after = AttemptLimiter::registerFail($ipKey);

			return $after['locked']
				? self::lockedResult($after['wait'])
				: ['ok' => false, 'error' => 'Такой пин занят'];
		}

		$dataClass = self::dataClass();
		$ownerId = bin2hex(random_bytes(16));
		$result = $dataClass::add([
			'UF_PIN_HASH' => $hash,
			'UF_OWNER_ID' => $ownerId,
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

	private static function findByHash(string $hash): ?array
	{
		$dataClass = self::dataClass();
		$row = $dataClass::getList([
			'filter' => ['=UF_PIN_HASH' => $hash],
			'limit' => 1,
		])->fetch();
		return $row ?: null;
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
