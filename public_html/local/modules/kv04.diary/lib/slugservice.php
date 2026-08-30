<?php

namespace Kv04\Diary;

use Bitrix\Main\Application;

/**
 * Личный адрес дневника: /<путь>/<адрес>/. Нужен не для красоты — по нему
 * браузер ставит ОТДЕЛЬНОЕ приложение (свой scope, свой значок), поэтому на
 * общем телефоне у каждого владельца свой дневник на экране.
 *
 * Адрес публичен и потому не секрет: он говорит «чей это дневник», а пускает
 * по-прежнему пин. Незнакомый адрес обязан вести себя как чужой — см.
 * PinService::login() и комментарий про неотличимость.
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
			'SELECT SLUG FROM ' . self::TABLE . " WHERE OWNER_ID = '" . self::escape($ownerId) . "' LIMIT 1"
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
			'SELECT OWNER_ID FROM ' . self::TABLE . " WHERE SLUG = '" . self::escape($slug) . "' LIMIT 1"
		)->fetch();

		return $row ? (string)$row['OWNER_ID'] : null;
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
	 * Завести или сменить адрес. Пустой ввод стирает его: дневник остаётся на
	 * общем адресе. Занятость чужого адреса сообщаем — иначе непонятно, почему
	 * не сохраняется, а секрета в этом нет: адреса и так публичны.
	 */
	public static function save(string $ownerId, string $input): array
	{
		if ($ownerId === '')
		{
			return ['ok' => false, 'error' => 'Дневник не найден'];
		}

		$connection = Application::getConnection();
		$owner = self::escape($ownerId);

		if (trim($input) === '')
		{
			$connection->queryExecute('DELETE FROM ' . self::TABLE . " WHERE OWNER_ID = '" . $owner . "'");

			return ['ok' => true, 'slug' => ''];
		}

		$slug = self::normalize($input);
		if ($slug === null)
		{
			return ['ok' => false, 'error' => 'Адрес: латиница и цифры, от ' . self::MIN . ' до ' . self::MAX . ' знаков, дефис и подчёркивание внутри'];
		}

		$taken = self::ownerBySlug($slug);
		if ($taken !== null && $taken !== $ownerId)
		{
			return ['ok' => false, 'error' => 'Такой адрес уже занят'];
		}

		$escaped = self::escape($slug);
		$connection->queryExecute(
			'INSERT INTO ' . self::TABLE . " (OWNER_ID, SLUG, CREATED_AT) VALUES ('" . $owner . "', '" . $escaped . "', " . time() . ')'
			. " ON DUPLICATE KEY UPDATE SLUG = '" . $escaped . "'"
		);

		return ['ok' => true, 'slug' => $slug];
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
