<?php

namespace Kv04\Diary;

use Bitrix\Main\Application;
use Bitrix\Main\Web\Cookie;

class Auth
{
	public const COOKIE = 'KV04_DIARY';
	public const SESSION_OWNER = 'KV04_DIARY_OWNER';
	public const SESSION_EXPIRES = 'KV04_DIARY_EXPIRES';
	/**
	 * Метка смены пина, с которой эта сессия начиналась. Пин сменили — все
	 * прочие устройства держат уже неверную метку и перестают пускать.
	 */
	public const SESSION_PIN_AT = 'KV04_DIARY_PIN_AT';
	public const LIFETIME = 36000;

	/**
	 * Привязка браузера к дневнику. Живёт год и переживает выход: на знакомом
	 * устройстве достаточно пина, почта нужна только на новом. Сама по себе
	 * доступа не даёт — говорит лишь, чей пин проверять.
	 */
	public const DEVICE_COOKIE = 'KV04_DIARY_DEVICE';
	public const DEVICE_LIFETIME = 31536000;

	public static function getOwnerId(): ?string
	{
		$now = time();
		$owner = (string)($_SESSION[self::SESSION_OWNER] ?? '');
		$expires = (int)($_SESSION[self::SESSION_EXPIRES] ?? 0);
		if ($owner !== '' && $expires > $now)
		{
			return self::withCurrentPin($owner, (int)($_SESSION[self::SESSION_PIN_AT] ?? 0));
		}

		$fromCookie = self::readCookie();
		if ($fromCookie && $fromCookie['expires'] > $now)
		{
			if (self::withCurrentPin($fromCookie['owner'], $fromCookie['pin_at']) === null)
			{
				return null;
			}
			$_SESSION[self::SESSION_OWNER] = $fromCookie['owner'];
			$_SESSION[self::SESSION_EXPIRES] = $fromCookie['expires'];
			$_SESSION[self::SESSION_PIN_AT] = $fromCookie['pin_at'];
			return $fromCookie['owner'];
		}

		self::clear();
		return null;
	}

	/**
	 * Сессия открыта нынешним пином? Смена пина обязана выкидывать все
	 * остальные устройства — иначе менять его после утечки бессмысленно:
	 * чужая cookie пускала бы ещё десять часов, а привязка устройства — год.
	 *
	 * Ноль у записей, где пин ни разу не меняли, совпадает с нулём в старых
	 * сессиях и cookie без метки — те переживают обновление молча.
	 */
	private static function withCurrentPin(string $owner, int $pinAt): ?string
	{
		if ($pinAt === PinService::changedAt($owner))
		{
			return $owner;
		}

		self::clear();
		return null;
	}

	public static function isLoggedIn(): bool
	{
		return self::getOwnerId() !== null;
	}

	public static function login(string $ownerId): void
	{
		$expires = time() + self::LIFETIME;
		$pinAt = PinService::changedAt($ownerId);
		$_SESSION[self::SESSION_OWNER] = $ownerId;
		$_SESSION[self::SESSION_EXPIRES] = $expires;
		$_SESSION[self::SESSION_PIN_AT] = $pinAt;
		self::writeCookie(self::COOKIE, $ownerId, $expires, $pinAt);
		self::rememberDevice($ownerId);
	}

	/** Чей дневник открывали в этом браузере в прошлый раз. */
	public static function boundOwnerId(): ?string
	{
		$device = self::readCookie(self::DEVICE_COOKIE);

		return $device && $device['expires'] > time() ? $device['owner'] : null;
	}

	public static function rememberDevice(string $ownerId): void
	{
		self::writeCookie(self::DEVICE_COOKIE, $ownerId, time() + self::DEVICE_LIFETIME);
	}

	/** «Это не мой дневник» — снять привязку, дальше снова спросим почту. */
	public static function forgetDevice(): void
	{
		self::dropCookie(self::DEVICE_COOKIE);
	}

	/** Выход из сессии. Привязку устройства намеренно оставляем. */
	public static function clear(): void
	{
		unset($_SESSION[self::SESSION_OWNER], $_SESSION[self::SESSION_EXPIRES], $_SESSION[self::SESSION_PIN_AT]);
		self::dropCookie(self::COOKIE);
	}

	private static function dropCookie(string $name): void
	{
		$cookie = new Cookie($name, '', time() - 3600);
		$cookie->setHttpOnly(true);
		$cookie->setSecure(true);
		$cookie->setPath('/');
		Application::getInstance()->getContext()->getResponse()->addCookie($cookie);
		if (!headers_sent())
		{
			setcookie($name, '', [
				'expires' => time() - 3600,
				'path' => '/',
				'httponly' => true,
				'secure' => true,
				'samesite' => 'Lax',
			]);
		}
	}

	private static function writeCookie(string $name, string $ownerId, int $expires, int $pinAt = 0): void
	{
		$value = self::sign($ownerId, $expires, $pinAt);
		$cookie = new Cookie($name, $value, $expires);
		$cookie->setHttpOnly(true);
		// Только по https: сайт весь на https, а эта cookie сама по себе
		// открывает записи — getOwnerId() поднимает по ней сессию заново.
		$cookie->setSecure(true);
		$cookie->setPath('/');
		Application::getInstance()->getContext()->getResponse()->addCookie($cookie);
		if (!headers_sent())
		{
			setcookie($name, $value, [
				'expires' => $expires,
				'path' => '/',
				'httponly' => true,
				'secure' => true,
				'samesite' => 'Lax',
			]);
		}
	}

	private static function readCookie(string $name = self::COOKIE): ?array
	{
		$raw = (string)($_COOKIE[$name] ?? '');
		if ($raw === '')
		{
			return null;
		}
		$parts = explode('|', $raw);
		// Три части — cookie, выписанная до появления метки смены пина.
		// Читаем её как метку «ноль»: у не менявших пин она и есть ноль,
		// и обновление модуля никого не разлогинивает.
		if (count($parts) === 3)
		{
			[$owner, $expires, $sig] = $parts;
			$pinAt = 0;
			$payload = $owner . '|' . $expires;
		}
		elseif (count($parts) === 4)
		{
			[$owner, $expires, $pinAt, $sig] = $parts;
			$payload = $owner . '|' . $expires . '|' . $pinAt;
		}
		else
		{
			return null;
		}
		$expected = hash_hmac('sha256', $payload, PinService::pepper());
		if (!hash_equals($expected, $sig))
		{
			return null;
		}
		return ['owner' => $owner, 'expires' => (int)$expires, 'pin_at' => (int)$pinAt];
	}

	private static function sign(string $ownerId, int $expires, int $pinAt): string
	{
		$payload = $ownerId . '|' . $expires . '|' . $pinAt;
		return $payload . '|' . hash_hmac('sha256', $payload, PinService::pepper());
	}
}
