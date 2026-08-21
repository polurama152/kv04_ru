<?php

namespace Kv04\Diary;

use Bitrix\Main\Application;
use Bitrix\Main\Web\Cookie;

class Auth
{
	public const COOKIE = 'KV04_DIARY';
	public const SESSION_OWNER = 'KV04_DIARY_OWNER';
	public const SESSION_EXPIRES = 'KV04_DIARY_EXPIRES';
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
			return $owner;
		}

		$fromCookie = self::readCookie();
		if ($fromCookie && $fromCookie['expires'] > $now)
		{
			$_SESSION[self::SESSION_OWNER] = $fromCookie['owner'];
			$_SESSION[self::SESSION_EXPIRES] = $fromCookie['expires'];
			return $fromCookie['owner'];
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
		$_SESSION[self::SESSION_OWNER] = $ownerId;
		$_SESSION[self::SESSION_EXPIRES] = $expires;
		self::writeCookie(self::COOKIE, $ownerId, $expires);
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
		unset($_SESSION[self::SESSION_OWNER], $_SESSION[self::SESSION_EXPIRES]);
		self::dropCookie(self::COOKIE);
	}

	private static function dropCookie(string $name): void
	{
		$cookie = new Cookie($name, '', time() - 3600);
		$cookie->setHttpOnly(true);
		$cookie->setPath('/');
		Application::getInstance()->getContext()->getResponse()->addCookie($cookie);
		if (!headers_sent())
		{
			setcookie($name, '', [
				'expires' => time() - 3600,
				'path' => '/',
				'httponly' => true,
				'samesite' => 'Lax',
			]);
		}
	}

	private static function writeCookie(string $name, string $ownerId, int $expires): void
	{
		$value = self::sign($ownerId, $expires);
		$cookie = new Cookie($name, $value, $expires);
		$cookie->setHttpOnly(true);
		$cookie->setPath('/');
		Application::getInstance()->getContext()->getResponse()->addCookie($cookie);
		if (!headers_sent())
		{
			setcookie($name, $value, [
				'expires' => $expires,
				'path' => '/',
				'httponly' => true,
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
		if (count($parts) !== 3)
		{
			return null;
		}
		[$owner, $expires, $sig] = $parts;
		$expires = (int)$expires;
		$expected = hash_hmac('sha256', $owner . '|' . $expires, PinService::pepper());
		if (!hash_equals($expected, $sig))
		{
			return null;
		}
		return ['owner' => $owner, 'expires' => $expires];
	}

	private static function sign(string $ownerId, int $expires): string
	{
		$payload = $ownerId . '|' . $expires;
		return $payload . '|' . hash_hmac('sha256', $payload, PinService::pepper());
	}
}
