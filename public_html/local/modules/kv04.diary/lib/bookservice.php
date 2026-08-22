<?php

namespace Kv04\Diary;

use Bitrix\Main\Application;

/**
 * Дневники одного владельца.
 *
 * Под одним пином живёт до пятидесяти дневников со своими заголовками; лента
 * показывает один из них. Дневники лежат в своей таблице, а не в инфоблоке:
 * список читается на каждый показ ленты, и лишний слой инфоблока тут ничего
 * не даёт, кроме запросов.
 *
 * Заметка знает свой дневник через свойство BOOK. У записей, заведённых до
 * появления нескольких дневников, оно пустое — их разово подбирает
 * ensureDefault().
 */
class BookService
{
	public const TABLE = 'kv04_diary_books';

	/** Потолок на владельца. */
	public const MAX_BOOKS = 50;

	public const DEFAULT_TITLE = 'Мой дневник';

	private const TITLE_LIMIT = 120;
	private const SESSION_CURRENT = 'KV04_DIARY_BOOK';

	/** @return array<int, array{id: int, title: string}> */
	public static function list(string $ownerId): array
	{
		$connection = Application::getConnection();
		$rows = $connection->query(sprintf(
			'SELECT ID, TITLE FROM %s WHERE OWNER_ID = %s ORDER BY SORT, ID',
			self::TABLE,
			$connection->getSqlHelper()->convertToDbString($ownerId)
		))->fetchAll();

		$books = [];
		foreach ($rows as $row)
		{
			$books[] = ['id' => (int)$row['ID'], 'title' => (string)$row['TITLE']];
		}

		return $books;
	}

	/**
	 * Гарантирует, что у владельца есть хотя бы один дневник, и возвращает
	 * его номер. Первый вызов заодно переносит в него все старые заметки —
	 * те, что заведены до появления нескольких дневников.
	 */
	public static function ensureDefault(string $ownerId): int
	{
		$books = self::list($ownerId);
		if ($books)
		{
			return $books[0]['id'];
		}

		$id = self::insert($ownerId, self::DEFAULT_TITLE);
		NoteService::adoptOrphanNotes($ownerId, $id);

		return $id;
	}

	public static function create(string $ownerId, string $title): array
	{
		$books = self::list($ownerId);
		if (count($books) >= self::MAX_BOOKS)
		{
			return ['ok' => false, 'error' => 'Больше ' . self::MAX_BOOKS . ' дневников не поместится'];
		}

		$title = self::normalizeTitle($title);
		if ($title === '')
		{
			return ['ok' => false, 'error' => 'Введите заголовок'];
		}

		$id = self::insert($ownerId, $title);

		return ['ok' => true, 'id' => $id, 'title' => $title, 'books' => self::list($ownerId)];
	}

	public static function rename(string $ownerId, int $id, string $title): array
	{
		if (!self::owns($ownerId, $id))
		{
			return ['ok' => false, 'error' => 'Дневник не найден'];
		}

		$title = self::normalizeTitle($title);
		if ($title === '')
		{
			return ['ok' => false, 'error' => 'Введите заголовок'];
		}

		$connection = Application::getConnection();
		$connection->queryExecute(sprintf(
			'UPDATE %s SET TITLE = %s WHERE ID = %d',
			self::TABLE,
			$connection->getSqlHelper()->convertToDbString($title),
			$id
		));

		return ['ok' => true, 'books' => self::list($ownerId)];
	}

	/**
	 * Удаление дневника. Заметки не стираются — уезжают в корзину, откуда их
	 * можно вернуть те же семь дней. Последний дневник удалить нельзя: ленте
	 * нужно что-то показывать.
	 */
	public static function delete(string $ownerId, int $id): array
	{
		if (!self::owns($ownerId, $id))
		{
			return ['ok' => false, 'error' => 'Дневник не найден'];
		}

		$books = self::list($ownerId);
		if (count($books) <= 1)
		{
			return ['ok' => false, 'error' => 'Это единственный дневник'];
		}

		$moved = NoteService::trashBook($ownerId, $id);

		Application::getConnection()->queryExecute(sprintf(
			'DELETE FROM %s WHERE ID = %d',
			self::TABLE,
			$id
		));

		$rest = self::list($ownerId);
		self::setCurrent($ownerId, $rest[0]['id']);

		return [
			'ok' => true,
			'moved' => $moved,
			'books' => $rest,
			'current' => $rest[0]['id'],
			'trash_days' => (int)ceil(NoteService::TRASH_TTL / 86400),
		];
	}

	/** Открытый сейчас дневник. Всегда возвращает существующий номер. */
	public static function currentId(string $ownerId): int
	{
		$chosen = (int)($_SESSION[self::SESSION_CURRENT] ?? 0);
		if ($chosen > 0 && self::owns($ownerId, $chosen))
		{
			return $chosen;
		}

		return self::ensureDefault($ownerId);
	}

	public static function setCurrent(string $ownerId, int $id): bool
	{
		if (!self::owns($ownerId, $id))
		{
			return false;
		}

		$_SESSION[self::SESSION_CURRENT] = $id;

		return true;
	}

	private static function insert(string $ownerId, string $title): int
	{
		$connection = Application::getConnection();
		$helper = $connection->getSqlHelper();
		$connection->queryExecute(sprintf(
			'INSERT INTO %s (OWNER_ID, TITLE, SORT, CREATED_AT) VALUES (%s, %s, %d, %d)',
			self::TABLE,
			$helper->convertToDbString($ownerId),
			$helper->convertToDbString($title),
			500,
			time()
		));

		return (int)$connection->getInsertedId();
	}

	private static function owns(string $ownerId, int $id): bool
	{
		if ($id <= 0)
		{
			return false;
		}

		$connection = Application::getConnection();
		$row = $connection->query(sprintf(
			'SELECT ID FROM %s WHERE ID = %d AND OWNER_ID = %s',
			self::TABLE,
			$id,
			$connection->getSqlHelper()->convertToDbString($ownerId)
		))->fetch();

		return (bool)$row;
	}

	/** Заголовок — обычный текст: разметке на плитке делать нечего. */
	private static function normalizeTitle(string $title): string
	{
		$title = strip_tags($title);
		$title = preg_replace('/\s+/u', ' ', $title) ?? $title;
		$title = trim($title);

		return mb_substr($title, 0, self::TITLE_LIMIT);
	}
}
