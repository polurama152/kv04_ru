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

		$ipLock = self::ipLockState();
		if ($ipLock['locked'])
		{
			return ['ok' => false, 'error' => 'Вход временно заблокирован. Попробуйте завтра.', 'locked' => true];
		}

		$hash = self::hashPin($pin);
		$row = self::findByHash($hash);

		if ($row && (int)$row['UF_LOCKED_UNTIL'] > time())
		{
			return ['ok' => false, 'error' => 'Вход временно заблокирован. Попробуйте завтра.', 'locked' => true];
		}

		if (!$row)
		{
			self::registerIpFail();
			if (self::ipLockState()['locked'])
			{
				return ['ok' => false, 'error' => 'Вход временно заблокирован. Попробуйте завтра.', 'locked' => true];
			}
			return ['ok' => false, 'error' => 'Неверный пин'];
		}

		self::resetFails((int)$row['ID']);
		self::resetIpFails();
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

		$hash = self::hashPin($pin);
		if (self::findByHash($hash))
		{
			return ['ok' => false, 'error' => 'Такой пин занят'];
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

	private static function resetFails(int $id): void
	{
		self::dataClass()::update($id, [
			'UF_FAILS' => 0,
			'UF_LOCKED_UNTIL' => 0,
		]);
	}

	private static function registerPinFail(array $row): void
	{
		$fails = (int)$row['UF_FAILS'] + 1;
		$fields = ['UF_FAILS' => $fails];
		if ($fails >= self::MAX_FAILS)
		{
			$fields['UF_LOCKED_UNTIL'] = time() + self::LOCK_SECONDS;
		}
		self::dataClass()::update((int)$row['ID'], $fields);
	}

	private static function registerIpFail(): void
	{
		$state = self::ipLockState();
		$count = $state['count'] + 1;
		$lockedUntil = $count >= self::MAX_FAILS ? time() + self::LOCK_SECONDS : 0;
		Option::set(Installer::MODULE_ID, self::ipOptionKey(), json_encode([
			'count' => $count,
			'locked_until' => $lockedUntil,
		]));
	}

	private static function resetIpFails(): void
	{
		Option::delete(Installer::MODULE_ID, ['name' => self::ipOptionKey()]);
	}

	private static function ipLockState(): array
	{
		$raw = (string)Option::get(Installer::MODULE_ID, self::ipOptionKey(), '');
		$data = $raw !== '' ? json_decode($raw, true) : [];
		$lockedUntil = (int)($data['locked_until'] ?? 0);
		$count = (int)($data['count'] ?? 0);
		if ($lockedUntil > 0 && $lockedUntil <= time())
		{
			self::resetIpFails();
			return ['count' => 0, 'locked' => false];
		}
		return ['count' => $count, 'locked' => $lockedUntil > time()];
	}

	private static function ipOptionKey(): string
	{
		$ip = Context::getCurrent()->getRequest()->getRemoteAddress();
		return 'ipfail_' . hash_hmac('sha256', (string)$ip, self::pepper());
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
