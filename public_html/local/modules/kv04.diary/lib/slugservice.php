<?php

namespace Kv04\Diary;

use Bitrix\Main\Application;

/**
 * Личный адрес дневника: /<путь>/<адрес>/. Он же идентичность владельца —
 * его спрашивают при регистрации вместо почты, — и он же адрес приложения:
 * по нему браузер ставит ОТДЕЛЬНЫЙ PWA со своим значком.
 *
 * Адрес публичен и потому не секрет: он говорит «чей это дневник», пускает
 * по-прежнему пин. Занятость адреса сообщаем прямо (решение владельца от
 * 2026-08-31): адреса — публичные факты, как ники, и прятать их, ломая
 * понятность регистрации, смысла нет.
 *
 * Прежние адреса не отдаются другим: строка остаётся с MOVED_AT > 0 и ведёт
 * на общую страницу. Так установленное приложение переживает переезд, а
 * новый адрес посторонним не выдаётся.
 */
class SlugService
{
	public const TABLE = 'kv04_diary_slugs';
	private const MIN = 2;
	private const MAX = 32;
	/** Занято страницами самого дневника и служебными адресами. */
	private const RESERVED = ['sw', 'd', 'manifest', 'index', 'admin', 'bitrix', 'local', 'upload'];

	/** Адрес владельца или пусто, если он его не заводил. */
	public static function forOwner(string $ownerId): string
	{
		if ($ownerId === '')
		{
			return '';
		}

		$row = Application::getConnection()->query(
			'SELECT SLUG FROM ' . self::TABLE . " WHERE OWNER_ID = '" . self::escape($ownerId) . "' AND MOVED_AT = 0 LIMIT 1"
		)->fetch();

		return $row ? (string)$row['SLUG'] : '';
	}

	/** Чей это адрес. null — такого адреса нет. */
	public static function ownerBySlug(string $slug): ?string
	{
		$slug = self::normalize($slug);
		if ($slug === null)
		{
			return null;
		}

		$row = Application::getConnection()->query(
			'SELECT OWNER_ID FROM ' . self::TABLE . " WHERE SLUG = '" . self::escape($slug) . "' AND MOVED_AT = 0 LIMIT 1"
		)->fetch();

		return $row ? (string)$row['OWNER_ID'] : null;
	}

	/** Адрес, с которого владелец переехал: ведёт на общую страницу. */
	public static function isMoved(string $slug): bool
	{
		$slug = self::normalize($slug);
		if ($slug === null)
		{
			return false;
		}

		$row = Application::getConnection()->query(
			'SELECT ID FROM ' . self::TABLE . " WHERE SLUG = '" . self::escape($slug) . "' AND MOVED_AT > 0 LIMIT 1"
		)->fetch();

		return (bool)$row;
	}

	/** Адрес занят кем угодно — действующим владельцем или переехавшим. */
	public static function isTaken(string $slug, string $exceptOwnerId = ''): bool
	{
		$slug = self::normalize($slug);
		if ($slug === null)
		{
			return false;
		}

		$sql = 'SELECT OWNER_ID FROM ' . self::TABLE . " WHERE SLUG = '" . self::escape($slug) . "' LIMIT 1";
		$row = Application::getConnection()->query($sql)->fetch();

		return $row && (string)$row['OWNER_ID'] !== $exceptOwnerId;
	}

	/**
	 * Канон адреса: нижний регистр, латиница с цифрами, дефис и подчёркивание
	 * внутри. null — не годится. Пустая строка сюда не приходит: её проверяет
	 * save() отдельно, потому что «стереть адрес» — законное действие.
	 */
	public static function normalize(string $input): ?string
	{
		$slug = strtolower(trim($input, " \t\n\r/"));
		if ($slug === '')
		{
			return null;
		}
		if (!preg_match('#^[a-z0-9][a-z0-9_-]{' . (self::MIN - 1) . ',' . (self::MAX - 1) . '}$#', $slug))
		{
			return null;
		}
		if (in_array($slug, self::RESERVED, true))
		{
			return null;
		}

		return $slug;
	}

	/**
	 * Завести или сменить адрес. Прежний не стирается и не достаётся другим:
	 * он помечается переехавшим и дальше ведёт на общую страницу — иначе
	 * установленное приложение владельца открывало бы чужую дверь.
	 *
	 * Пустой ввод — «жить на общей странице»: действующий адрес тоже уходит
	 * в переехавшие.
	 */
	public static function save(string $ownerId, string $input): array
	{
		if ($ownerId === '')
		{
			return ['ok' => false, 'error' => 'Дневник не найден'];
		}

		$connection = Application::getConnection();
		$owner = self::escape($ownerId);
		$current = self::forOwner($ownerId);

		if (trim($input) === '')
		{
			self::retire($ownerId);

			return ['ok' => true, 'slug' => ''];
		}

		$slug = self::normalize($input);
		if ($slug === null)
		{
			return ['ok' => false, 'error' => 'Адрес: латиница и цифры, от ' . self::MIN . ' до ' . self::MAX . ' знаков, дефис и подчёркивание внутри'];
		}

		if ($slug === $current)
		{
			return ['ok' => true, 'slug' => $slug];
		}

		if (self::isTaken($slug, $ownerId))
		{
			return ['ok' => false, 'error' => 'Такой адрес уже занят'];
		}

		$escaped = self::escape($slug);
		// Свой же прежний адрес можно занять обратно: снимаем с него метку
		// переезда, а не заводим вторую строку — SLUG уникален.
		$connection->queryExecute(
			'UPDATE ' . self::TABLE . " SET MOVED_AT = 0, CREATED_AT = " . time()
			. " WHERE OWNER_ID = '" . $owner . "' AND SLUG = '" . $escaped . "'"
		);
		self::retire($ownerId, $slug);
		$connection->queryExecute(
			'INSERT IGNORE INTO ' . self::TABLE . " (OWNER_ID, SLUG, CREATED_AT, MOVED_AT)"
			. " VALUES ('" . $owner . "', '" . $escaped . "', " . time() . ', 0)'
		);

		return ['ok' => true, 'slug' => $slug];
	}

	/** Отправить действующий адрес владельца в переехавшие. */
	private static function retire(string $ownerId, string $keepSlug = ''): void
	{
		$sql = 'UPDATE ' . self::TABLE . ' SET MOVED_AT = ' . time()
			. " WHERE OWNER_ID = '" . self::escape($ownerId) . "' AND MOVED_AT = 0";
		if ($keepSlug !== '')
		{
			$sql .= " AND SLUG <> '" . self::escape($keepSlug) . "'";
		}

		Application::getConnection()->queryExecute($sql);
	}

	/** Владелец ушёл — адрес освобождается. */
	public static function forget(string $ownerId): void
	{
		if ($ownerId === '')
		{
			return;
		}

		Application::getConnection()->queryExecute(
			'DELETE FROM ' . self::TABLE . " WHERE OWNER_ID = '" . self::escape($ownerId) . "'"
		);
	}

	private static function escape(string $value): string
	{
		return Application::getConnection()->getSqlHelper()->forSql($value);
	}
}
