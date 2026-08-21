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
		self::writeCookie($ownerId, $expires);
	}

	public static function clear(): void
	{
		unset($_SESSION[self::SESSION_OWNER], $_SESSION[self::SESSION_EXPIRES]);
		$cookie = new Cookie(self::COOKIE, '', time() - 3600);
		$cookie->setHttpOnly(true);
		$cookie->setPath('/');
		Application::getInstance()->getContext()->getResponse()->addCookie($cookie);
		if (!headers_sent())
		{
			setcookie(self::COOKIE, '', [
				'expires' => time() - 3600,
				'path' => '/',
				'httponly' => true,
				'samesite' => 'Lax',
			]);
		}
	}

	private static function writeCookie(string $ownerId, int $expires): void
	{
		$value = self::sign($ownerId, $expires);
		$cookie = new Cookie(self::COOKIE, $value, $expires);
		$cookie->setHttpOnly(true);
		$cookie->setPath('/');
		Application::getInstance()->getContext()->getResponse()->addCookie($cookie);
		if (!headers_sent())
		{
			setcookie(self::COOKIE, $value, [
				'expires' => $expires,
				'path' => '/',
				'httponly' => true,
				'samesite' => 'Lax',
			]);
		}
	}

	private static function readCookie(): ?array
	{
		$raw = (string)($_COOKIE[self::COOKIE] ?? '');
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
