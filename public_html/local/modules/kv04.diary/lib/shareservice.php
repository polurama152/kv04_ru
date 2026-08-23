<?php

namespace Kv04\Diary;

use Bitrix\Main\Application;

/**
 * Ссылки на дневник целиком.
 *
 * На дневник живёт одна ссылка: повторное «Поделиться» отдаёт ту же самую,
 * отзыв закрывает её навсегда, а следующая будет уже новой. Отозванную не
 * воскрешаем — иначе «закрыть доступ» означало бы «закрыть до следующего
 * раза», а от этой кнопки ждут обратного.
 *
 * Заметки, блоки и файлы ссылок не заводят вовсе: ими делятся системным меню
 * телефона, и на сервере от этого не остаётся следа.
 */
class ShareService
{
	public const TABLE = 'kv04_diary_shares';

	/** Живая ссылка на дневник: уже выданная или только что заведённая. */
	public static function linkFor(string $ownerId, int $bookId): string
	{
		if ($ownerId === '' || $bookId <= 0)
		{
			return '';
		}

		$existing = self::liveToken($ownerId, $bookId);
		if ($existing !== '')
		{
			return $existing;
		}

		$connection = Application::getConnection();
		$helper = $connection->getSqlHelper();
		// 16 байт случайности — 32 знака в адресе. Перебором не берётся, а
		// глазами такую ссылку не подсмотришь через плечо.
		$token = bin2hex(random_bytes(16));

		$connection->queryExecute(sprintf(
			'INSERT INTO %s (OWNER_ID, BOOK_ID, TOKEN, CREATED_AT, REVOKED_AT) VALUES (%s, %d, %s, %d, 0)',
			self::TABLE,
			$helper->convertToDbString($ownerId),
			$bookId,
			$helper->convertToDbString($token),
			time()
		));

		return $token;
	}

	/** Закрыть доступ. Возвращает true, если было что закрывать. */
	public static function revoke(string $ownerId, int $bookId): bool
	{
		if ($ownerId === '' || $bookId <= 0)
		{
			return false;
		}

		$connection = Application::getConnection();
		$helper = $connection->getSqlHelper();

		$connection->queryExecute(sprintf(
			'UPDATE %s SET REVOKED_AT = %d WHERE OWNER_ID = %s AND BOOK_ID = %d AND REVOKED_AT = 0',
			self::TABLE,
			time(),
			$helper->convertToDbString($ownerId),
			$bookId
		));

		return $connection->getAffectedRowsCount() > 0;
	}

	/**
	 * Чей это дневник и как он называется — по токену из адреса.
	 *
	 * Дневник подтягиваем join-ом, а не отдельным запросом: если дневник
	 * удалили, строка не найдётся и ссылка перестанет открываться сама,
	 * без отдельной уборки.
	 *
	 * @return array{owner: string, book: int, title: string}|null
	 */
	public static function resolve(string $token): ?array
	{
		if (!preg_match('/^[0-9a-f]{32}$/', $token))
		{
			return null;
		}

		$connection = Application::getConnection();
		$row = $connection->query(sprintf(
			'SELECT s.OWNER_ID, s.BOOK_ID, b.TITLE FROM %s s'
			. ' INNER JOIN %s b ON b.ID = s.BOOK_ID AND b.OWNER_ID = s.OWNER_ID'
			. ' WHERE s.TOKEN = %s AND s.REVOKED_AT = 0 LIMIT 1',
			self::TABLE,
			BookService::TABLE,
			$connection->getSqlHelper()->convertToDbString($token)
		))->fetch();

		if (!$row)
		{
			return null;
		}

		return [
			'owner' => (string)$row['OWNER_ID'],
			'book' => (int)$row['BOOK_ID'],
			'title' => (string)$row['TITLE'],
		];
	}

	/** Адрес ссылки целиком — его и показываем владельцу. */
	public static function url(string $token): string
	{
		$host = (string)($_SERVER['HTTP_HOST'] ?? 'kv04.ru');

		return 'https://' . $host . '/d/' . $token;
	}

	/** Адрес живой ссылки или пусто, если делиться этим дневником не начинали. */
	public static function liveUrl(string $ownerId, int $bookId): string
	{
		$token = $ownerId === '' || $bookId <= 0 ? '' : self::liveToken($ownerId, $bookId);

		return $token === '' ? '' : self::url($token);
	}

	private static function liveToken(string $ownerId, int $bookId): string
	{
		$connection = Application::getConnection();
		$row = $connection->query(sprintf(
			'SELECT TOKEN FROM %s WHERE OWNER_ID = %s AND BOOK_ID = %d AND REVOKED_AT = 0 ORDER BY ID DESC LIMIT 1',
			self::TABLE,
			$connection->getSqlHelper()->convertToDbString($ownerId),
			$bookId
		))->fetch();

		return $row ? (string)$row['TOKEN'] : '';
	}
}
