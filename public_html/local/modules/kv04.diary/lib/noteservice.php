<?php

namespace Kv04\Diary;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use CIBlockElement;
use CIBlockProperty;
use CFile;

class NoteService
{
	private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
	private const VIDEO_EXT = ['mp4', 'webm'];
	private const MAX_IMAGE = 8388608;
	private const MAX_VIDEO = 20971520;

	/** Сколько удалённое лежит в корзине до окончательного удаления. */
	public const TRASH_TTL = 604800;

	/** Обрывки заметки: отдельный файл или блок текста и кода. */
	public const TRASH_TABLE = 'kv04_diary_trash';

	/**
	 * Барьер зависимости. Полагаться на то, что iblock подтянул
	 * Installer::ensure(), нельзя: у него есть быстрый путь без
	 * includeModule. Ставим первой строкой каждого публичного метода —
	 * внутри iblockId() поздно: PHP резолвит CIBlockElement раньше,
	 * чем вычисляет аргументы вызова.
	 */
	private static function requireIblock(): void
	{
		if (!Loader::includeModule('iblock'))
		{
			throw new \RuntimeException('Модуль iblock недоступен');
		}
	}

	public static function iblockId(): int
	{
		self::requireIblock();

		return (int)Option::get(Installer::MODULE_ID, 'iblock_id', '0');
	}

	public static function list(string $ownerId, int $bookId = 0): array
	{
		self::requireIblock();

		$filter = [
			'IBLOCK_ID' => self::iblockId(),
			'ACTIVE' => 'Y',
			'PROPERTY_OWNER' => $ownerId,
			'CHECK_PERMISSIONS' => 'N',
		];
		if ($bookId > 0)
		{
			$filter['PROPERTY_BOOK'] = $bookId;
		}

		$items = [];
		$res = CIBlockElement::GetList(
			['ID' => 'DESC'],
			$filter,
			false,
			['nTopCount' => 100],
			['ID', 'NAME', 'DETAIL_TEXT', 'DATE_CREATE']
		);
		while ($fields = $res->GetNext())
		{
			$items[] = self::buildItem($fields);
		}
		return $items;
	}

	/**
	 * Одна заметка в том же формате, что и элемент list().
	 * Нужна, чтобы после add() отдать фронту готовый элемент и не
	 * перезагружать страницу и не перечитывать всю ленту.
	 */
	public static function get(string $ownerId, int $id): ?array
	{
		self::requireIblock();

		$res = CIBlockElement::GetList(
			[],
			[
				'IBLOCK_ID' => self::iblockId(),
				'ACTIVE' => 'Y',
				'ID' => $id,
				'PROPERTY_OWNER' => $ownerId,
				'CHECK_PERMISSIONS' => 'N',
			],
			false,
			['nTopCount' => 1],
			['ID', 'NAME', 'DETAIL_TEXT', 'DATE_CREATE']
		);
		$fields = $res->GetNext();

		return $fields ? self::buildItem($fields) : null;
	}

	private static function buildItem(array $fields): array
	{
		$id = (int)$fields['ID'];

		return [
			'id' => $id,
			'text' => (string)$fields['~DETAIL_TEXT'],
			'date' => (string)$fields['DATE_CREATE'],
			'media' => self::mediaFromFileIds(self::mediaFileIds($id)),
		];
	}

	public static function add(string $ownerId, string $text, array $files = [], int $bookId = 0): array
	{
		self::requireIblock();

		$html = Html::sanitize($text);
		$mediaIds = self::saveUploadedFiles($files);
		if ($html === '' && !$mediaIds)
		{
			return ['ok' => false, 'error' => 'Пустая заметка'];
		}

		$propertyValues = ['OWNER' => $ownerId];
		if ($bookId > 0)
		{
			$propertyValues['BOOK'] = $bookId;
		}
		if ($mediaIds)
		{
			$propertyValues['MEDIA'] = self::mediaPropertyPayload($mediaIds);
		}

		$el = new CIBlockElement();
		$id = $el->Add([
			'IBLOCK_ID' => self::iblockId(),
			'ACTIVE' => 'Y',
			'NAME' => Html::excerpt($html),
			'DETAIL_TEXT' => $html,
			'DETAIL_TEXT_TYPE' => 'html',
			'PROPERTY_VALUES' => $propertyValues,
		], false, false);
		if (!$id)
		{
			return ['ok' => false, 'error' => $el->LAST_ERROR ?: 'Не удалось сохранить'];
		}
		return ['ok' => true, 'id' => (int)$id, 'item' => self::get($ownerId, (int)$id)];
	}

	public static function update(string $ownerId, int $id, string $text): array
	{
		self::requireIblock();

		if (!self::owns($ownerId, $id))
		{
			return ['ok' => false, 'error' => 'Нельзя править чужую запись'];
		}
		$html = Html::sanitize($text);
		$el = new CIBlockElement();
		$ok = $el->Update($id, [
			'NAME' => Html::excerpt($html),
			'DETAIL_TEXT' => $html,
			'DETAIL_TEXT_TYPE' => 'html',
		]);
		if (!$ok)
		{
			return ['ok' => false, 'error' => $el->LAST_ERROR ?: 'Не удалось сохранить'];
		}
		// Отдаём санитизированный HTML: фронт подставляет его вместо textarea
		// и обходится без перезагрузки страницы.
		return ['ok' => true, 'text' => $html];
	}

	/**
	 * Удаление мягкое: заметка становится неактивной и получает время
	 * попадания в корзину. Спрашивать подтверждение поэтому незачем —
	 * вернуть можно в течение срока хранения.
	 */
	public static function delete(string $ownerId, int $id): array
	{
		self::requireIblock();

		if (!self::owns($ownerId, $id))
		{
			return ['ok' => false, 'error' => 'Нельзя удалить чужую запись'];
		}

		$element = new CIBlockElement();
		if (!$element->Update($id, ['ACTIVE' => 'N']))
		{
			return ['ok' => false, 'error' => $element->LAST_ERROR ?: 'Не удалось удалить'];
		}
		CIBlockElement::SetPropertyValuesEx($id, self::iblockId(), ['DELETED_AT' => time()]);

		return ['ok' => true, 'trash_days' => (int)ceil(self::TRASH_TTL / 86400)];
	}

	/** Вернуть заметку из корзины. */
	public static function restore(string $ownerId, int $id): array
	{
		self::requireIblock();

		if (!self::owns($ownerId, $id))
		{
			return ['ok' => false, 'error' => 'Нельзя вернуть чужую запись'];
		}

		$element = new CIBlockElement();
		if (!$element->Update($id, ['ACTIVE' => 'Y']))
		{
			return ['ok' => false, 'error' => $element->LAST_ERROR ?: 'Не удалось вернуть'];
		}

		$fields = ['DELETED_AT' => false];
		// Дневник заметки мог быть удалён, пока она лежала в корзине. Тогда
		// возвращать её некуда: с чужим номером она не попадёт ни в одну
		// ленту. Кладём в открытый сейчас.
		if (!self::bookAlive($ownerId, $id))
		{
			$fields['BOOK'] = BookService::currentId($ownerId);
		}
		CIBlockElement::SetPropertyValuesEx($id, self::iblockId(), $fields);

		return ['ok' => true, 'item' => self::get($ownerId, $id)];
	}

	/** Содержимое корзины: то, что удалено и ещё не вычищено по сроку. */
	public static function trash(string $ownerId): array
	{
		self::requireIblock();

		$items = [];
		$now = time();
		$res = CIBlockElement::GetList(
			['ID' => 'DESC'],
			[
				'IBLOCK_ID' => self::iblockId(),
				'ACTIVE' => 'N',
				'PROPERTY_OWNER' => $ownerId,
				'CHECK_PERMISSIONS' => 'N',
			],
			false,
			['nTopCount' => 100],
			['ID', 'NAME', 'DETAIL_TEXT', 'DATE_CREATE', 'PROPERTY_DELETED_AT']
		);
		while ($fields = $res->GetNext())
		{
			$item = self::buildItem($fields);
			$deletedAt = (int)($fields['PROPERTY_DELETED_AT_VALUE'] ?? 0);
			$item['deleted_at'] = $deletedAt;
			$item['expires_in'] = $deletedAt > 0
				? max(0, $deletedAt + self::TRASH_TTL - $now)
				: self::TRASH_TTL;
			$items[] = $item;
		}

		return $items;
	}

	/**
	 * Окончательно стирает то, что пролежало в корзине дольше срока.
	 * Вызывается при открытии корзины и изредка при показе ленты: отдельного
	 * планировщика у модуля нет, а агенты Bitrix на этом хостинге не крутятся.
	 *
	 * @return int сколько заметок удалено насовсем
	 */
	public static function purgeExpired(): int
	{
		self::requireIblock();

		$threshold = time() - self::TRASH_TTL;
		$res = CIBlockElement::GetList(
			['ID' => 'ASC'],
			[
				'IBLOCK_ID' => self::iblockId(),
				'ACTIVE' => 'N',
				'!PROPERTY_DELETED_AT' => false,
				'<=PROPERTY_DELETED_AT' => $threshold,
				'CHECK_PERMISSIONS' => 'N',
			],
			false,
			['nTopCount' => 100],
			['ID']
		);

		$removed = 0;
		while ($row = $res->Fetch())
		{
			$id = (int)$row['ID'];
			// Файлы за элементом сами не уходят — иначе в upload/ копился бы мусор.
			foreach (self::mediaFileIds($id) as $fileId)
			{
				CFile::Delete($fileId);
			}
			CIBlockElement::Delete($id);
			$removed++;
		}

		return $removed + self::purgeFragments($threshold);
	}

	public static function attach(string $ownerId, int $id, array $files): array
	{
		self::requireIblock();

		if (!self::owns($ownerId, $id))
		{
			return ['ok' => false, 'error' => 'Нельзя править чужую запись'];
		}
		$newIds = self::saveUploadedFiles($files);
		if (!$newIds)
		{
			return ['ok' => false, 'error' => count($files) > 1 ? 'Файлы не приняты' : 'Файл не принят'];
		}
		$merged = array_merge(self::mediaFileIds($id), $newIds);
		self::setMediaProperty($id, $merged);
		return ['ok' => true, 'media' => self::mediaFromFileIds($merged)];
	}

	public static function detachMedia(string $ownerId, int $id, int $fileId): array
	{
		self::requireIblock();

		if (!self::owns($ownerId, $id))
		{
			return ['ok' => false, 'error' => 'Нельзя править чужую запись'];
		}
		if ($fileId <= 0)
		{
			return ['ok' => false, 'error' => 'Файл не указан'];
		}

		$fileIds = self::mediaFileIds($id);
		if (!in_array($fileId, $fileIds, true))
		{
			return ['ok' => false, 'error' => 'Файл не найден'];
		}

		$remaining = array_values(array_filter(
			$fileIds,
			static fn(int $currentId): bool => $currentId !== $fileId
		));

		self::setMediaProperty($id, $remaining);
		// Сам файл не трогаем: он понадобится, если вернут из корзины.
		// Окончательно уйдёт в purgeExpired() вместе со строкой корзины.
		$file = CFile::GetFileArray($fileId);
		$trashId = self::pushFragment($ownerId, $id, 'media', [
			'file_id' => $fileId,
			// Сорт записи показывает сам интерфейс, приставка тут лишняя.
			'excerpt' => (string)($file['ORIGINAL_NAME'] ?? $file['FILE_NAME'] ?? $fileId),
		]);

		return [
			'ok' => true,
			'media' => self::mediaFromFileIds($remaining),
			'trash_id' => $trashId,
			'trash_days' => (int)ceil(self::TRASH_TTL / 86400),
		];
	}

	/**
	 * Разовый перенос заметок, заведённых до появления нескольких дневников:
	 * у них свойство BOOK пустое. Зовётся один раз на владельца, из
	 * BookService::ensureDefault().
	 *
	 * @return int сколько заметок подобрано
	 */
	public static function adoptOrphanNotes(string $ownerId, int $bookId): int
	{
		self::requireIblock();

		$iblockId = self::iblockId();
		$res = CIBlockElement::GetList(
			['ID' => 'ASC'],
			[
				'IBLOCK_ID' => $iblockId,
				'PROPERTY_OWNER' => $ownerId,
				'CHECK_PERMISSIONS' => 'N',
			],
			false,
			false,
			['ID', 'PROPERTY_BOOK']
		);

		$adopted = 0;
		while ($row = $res->Fetch())
		{
			if ((int)($row['PROPERTY_BOOK_VALUE'] ?? 0) > 0)
			{
				continue;
			}
			CIBlockElement::SetPropertyValuesEx((int)$row['ID'], $iblockId, ['BOOK' => $bookId]);
			$adopted++;
		}

		return $adopted;
	}

	/**
	 * Отправляет в корзину все заметки дневника — при его удалении. Записи не
	 * стираются: их можно вернуть те же семь дней, что и обычные.
	 *
	 * @return int сколько заметок уехало
	 */
	public static function trashBook(string $ownerId, int $bookId): int
	{
		self::requireIblock();

		$iblockId = self::iblockId();
		$res = CIBlockElement::GetList(
			['ID' => 'ASC'],
			[
				'IBLOCK_ID' => $iblockId,
				'ACTIVE' => 'Y',
				'PROPERTY_OWNER' => $ownerId,
				'PROPERTY_BOOK' => $bookId,
				'CHECK_PERMISSIONS' => 'N',
			],
			false,
			false,
			['ID']
		);

		$moved = 0;
		$element = new CIBlockElement();
		while ($row = $res->Fetch())
		{
			$id = (int)$row['ID'];
			if (!$element->Update($id, ['ACTIVE' => 'N']))
			{
				continue;
			}
			CIBlockElement::SetPropertyValuesEx($id, $iblockId, ['DELETED_AT' => time()]);
			$moved++;
		}

		return $moved;
	}

	/**
	 * Разбор заметки на блоки. Блок — это то, что видно отдельным куском:
	 * <pre> с кодом либо связный кусок текста между ними. Разбор одинаковый
	 * на сервере и на фронте, поэтому номера блоков совпадают.
	 *
	 * @return array<int, array{type: string, html: string}>
	 */
	public static function splitBlocks(string $html): array
	{
		$parts = preg_split('#(<pre\b.*?</pre>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
		if ($parts === false)
		{
			return $html === '' ? [] : [['type' => 'text', 'html' => $html]];
		}

		$blocks = [];
		foreach ($parts as $part)
		{
			$isCode = stripos($part, '<pre') === 0;
			if (!$isCode && trim($part) === '')
			{
				continue;
			}
			$blocks[] = ['type' => $isCode ? 'code' : 'text', 'html' => $part];
		}

		return $blocks;
	}

	/** Удалить блок заметки целиком, отправив его в корзину. */
	public static function deleteBlock(string $ownerId, int $id, int $index): array
	{
		self::requireIblock();

		$note = self::get($ownerId, $id);
		if (!$note)
		{
			return ['ok' => false, 'error' => 'Заметка не найдена'];
		}

		$blocks = self::splitBlocks((string)$note['text']);
		if (!isset($blocks[$index]))
		{
			return ['ok' => false, 'error' => 'Блок не найден'];
		}

		$removed = $blocks[$index];

		// Запоминаем не номер блока, а позицию в символах: соседние куски
		// текста после удаления кода между ними сливаются в один, и номер
		// перестаёт указывать на то же место. Смещение переживает слияние.
		$beforeHtml = implode('', array_column(array_slice($blocks, 0, $index), 'html'));
		$afterHtml = implode('', array_column(array_slice($blocks, $index + 1), 'html'));
		$restPosition = mb_strlen($beforeHtml);
		$rest = rtrim($beforeHtml . $afterHtml);
		if ($index === 0)
		{
			// Сняли первый блок — обрезка слева ничего не сдвинет.
			$rest = ltrim($rest);
			$restPosition = 0;
		}

		// Ушёл последний блок текста, и медиа тоже нет — в корзину едет вся
		// заметка, иначе осталась бы пустая карточка. Если медиа есть, заметка
		// остаётся: она может состоять из одних файлов, это нормальная заметка.
		if ($rest === '' && !$note['media'])
		{
			return self::delete($ownerId, $id) + ['note_deleted' => true];
		}

		$element = new CIBlockElement();
		if (!$element->Update($id, [
			'NAME' => Html::excerpt($rest),
			'DETAIL_TEXT' => $rest,
			'DETAIL_TEXT_TYPE' => 'html',
		]))
		{
			return ['ok' => false, 'error' => $element->LAST_ERROR ?: 'Не удалось удалить блок'];
		}

		$trashId = self::pushFragment($ownerId, $id, 'block', [
			'block_pos' => $restPosition,
			'payload' => $removed['html'],
			'excerpt' => Html::excerpt($removed['html'], 70),
		]);

		return [
			'ok' => true,
			'text' => $rest,
			// Явный признак вместо догадок по пустому тексту: пустой текст при
			// живых медиа — это по-прежнему заметка, убирать её из ленты нельзя.
			'note_deleted' => false,
			'trash_id' => $trashId,
			'trash_days' => (int)ceil(self::TRASH_TTL / 86400),
		];
	}

	/** Корзина целиком: удалённые заметки и обрывки, ближе к концу срока — выше. */
	public static function trashAll(string $ownerId): array
	{
		$items = [];
		foreach (self::trash($ownerId) as $note)
		{
			$items[] = [
				'kind' => 'note',
				'id' => $note['id'],
				'excerpt' => Html::excerpt($note['text'], 90),
				'date' => $note['date'],
				'expires_in' => $note['expires_in'],
			];
		}

		$now = time();
		$connection = Application::getConnection();
		$rows = $connection->query(sprintf(
			'SELECT ID, ELEMENT_ID, KIND, EXCERPT, DELETED_AT FROM %s WHERE OWNER_ID = %s ORDER BY ID DESC LIMIT 200',
			self::TRASH_TABLE,
			$connection->getSqlHelper()->convertToDbString($ownerId)
		))->fetchAll();

		foreach ($rows as $row)
		{
			$deletedAt = (int)$row['DELETED_AT'];
			$items[] = [
				'kind' => (string)$row['KIND'],
				'trash_id' => (int)$row['ID'],
				'note_id' => (int)$row['ELEMENT_ID'],
				'excerpt' => (string)$row['EXCERPT'],
				'date' => date('d.m.Y H:i:s', $deletedAt),
				'expires_in' => max(0, $deletedAt + self::TRASH_TTL - $now),
			];
		}

		usort($items, static fn(array $a, array $b): int => $a['expires_in'] <=> $b['expires_in']);

		return $items;
	}

	/** Вернуть обрывок на место: файл — в медиа заметки, блок — в её текст. */
	public static function restoreFragment(string $ownerId, int $trashId): array
	{
		self::requireIblock();

		$connection = Application::getConnection();
		$row = $connection->query(sprintf(
			'SELECT * FROM %s WHERE ID = %d AND OWNER_ID = %s',
			self::TRASH_TABLE,
			$trashId,
			$connection->getSqlHelper()->convertToDbString($ownerId)
		))->fetch();

		if (!$row)
		{
			return ['ok' => false, 'error' => 'В корзине не найдено'];
		}

		$elementId = (int)$row['ELEMENT_ID'];
		$note = self::get($ownerId, $elementId);
		if (!$note)
		{
			return ['ok' => false, 'error' => 'Заметка удалена — сначала верните её'];
		}

		if ((string)$row['KIND'] === 'media')
		{
			$merged = array_merge(self::mediaFileIds($elementId), [(int)$row['FILE_ID']]);
			self::setMediaProperty($elementId, $merged);
		}
		else
		{
			// BLOCK_POS — смещение в символах, а не номер блока: так вернувшийся
			// кусок встаёт ровно туда, откуда его забрали, даже если соседние
			// куски текста успели слиться.
			$current = (string)$note['text'];
			$position = min((int)$row['BLOCK_POS'], mb_strlen($current));
			$text = mb_substr($current, 0, $position) . (string)$row['PAYLOAD'] . mb_substr($current, $position);

			$element = new CIBlockElement();
			$element->Update($elementId, [
				'NAME' => Html::excerpt($text),
				'DETAIL_TEXT' => $text,
				'DETAIL_TEXT_TYPE' => 'html',
			]);
		}

		self::dropFragment($trashId);

		return ['ok' => true, 'item' => self::get($ownerId, $elementId)];
	}

	/** @return int номер строки в корзине — по нему потом возвращают */
	private static function pushFragment(string $ownerId, int $elementId, string $kind, array $data): int
	{
		$connection = Application::getConnection();
		$helper = $connection->getSqlHelper();
		$connection->queryExecute(sprintf(
			'INSERT INTO %s (OWNER_ID, ELEMENT_ID, KIND, FILE_ID, BLOCK_POS, PAYLOAD, EXCERPT, DELETED_AT) '
			. 'VALUES (%s, %d, %s, %d, %d, %s, %s, %d)',
			self::TRASH_TABLE,
			$helper->convertToDbString($ownerId),
			$elementId,
			$helper->convertToDbString($kind),
			(int)($data['file_id'] ?? 0),
			(int)($data['block_pos'] ?? 0),
			$helper->convertToDbString((string)($data['payload'] ?? '')),
			$helper->convertToDbString(mb_substr((string)($data['excerpt'] ?? ''), 0, 250)),
			time()
		));

		return (int)$connection->getInsertedId();
	}

	private static function dropFragment(int $trashId): void
	{
		Application::getConnection()->queryExecute(sprintf(
			'DELETE FROM %s WHERE ID = %d',
			self::TRASH_TABLE,
			$trashId
		));
	}

	/** Просроченные обрывки: строку убираем, файл за ней — тоже. */
	private static function purgeFragments(int $threshold): int
	{
		$connection = Application::getConnection();
		$rows = $connection->query(sprintf(
			'SELECT ID, KIND, FILE_ID FROM %s WHERE DELETED_AT <= %d LIMIT 200',
			self::TRASH_TABLE,
			$threshold
		))->fetchAll();

		foreach ($rows as $row)
		{
			if ((string)$row['KIND'] === 'media' && (int)$row['FILE_ID'] > 0)
			{
				CFile::Delete((int)$row['FILE_ID']);
			}
			self::dropFragment((int)$row['ID']);
		}

		return count($rows);
	}

	private static function mediaFileIds(int $elementId): array
	{
		$fileIds = [];
		$propRes = CIBlockElement::GetProperty(self::iblockId(), $elementId, [], ['CODE' => 'MEDIA']);
		while ($prop = $propRes->Fetch())
		{
			if (!empty($prop['VALUE']))
			{
				$fileIds[] = (int)$prop['VALUE'];
			}
		}

		return $fileIds;
	}

	/** Существует ли ещё дневник, к которому приписана заметка. */
	private static function bookAlive(string $ownerId, int $elementId): bool
	{
		$res = CIBlockElement::GetList(
			[],
			['IBLOCK_ID' => self::iblockId(), 'ID' => $elementId, 'CHECK_PERMISSIONS' => 'N'],
			false,
			['nTopCount' => 1],
			['ID', 'PROPERTY_BOOK']
		);
		$row = $res->Fetch();
		$bookId = (int)($row['PROPERTY_BOOK_VALUE'] ?? 0);
		if ($bookId <= 0)
		{
			return false;
		}

		foreach (BookService::list($ownerId) as $book)
		{
			if ($book['id'] === $bookId)
			{
				return true;
			}
		}

		return false;
	}

	private static function owns(string $ownerId, int $id): bool
	{
		$res = CIBlockElement::GetList([], [
			'IBLOCK_ID' => self::iblockId(),
			'ID' => $id,
			'PROPERTY_OWNER' => $ownerId,
			'CHECK_PERMISSIONS' => 'N',
		], false, ['nTopCount' => 1], ['ID']);
		return (bool)$res->Fetch();
	}

	private static function setMediaProperty(int $elementId, array $fileIds): void
	{
		global $DB;

		$iblockId = self::iblockId();
		$propertyId = self::mediaPropertyId();
		if ($propertyId <= 0)
		{
			return;
		}

		$fileIds = array_values(array_unique(array_filter(array_map('intval', $fileIds), static fn(int $id): bool => $id > 0)));
		$table = 'b_iblock_element_prop_m' . $iblockId;

		$DB->Query(sprintf(
			'DELETE FROM %s WHERE IBLOCK_ELEMENT_ID = %d AND IBLOCK_PROPERTY_ID = %d',
			$table,
			$elementId,
			$propertyId
		));

		foreach ($fileIds as $fileId)
		{
			$DB->Add($table, [
				'IBLOCK_ELEMENT_ID' => $elementId,
				'IBLOCK_PROPERTY_ID' => $propertyId,
				'VALUE' => $fileId,
				'VALUE_NUM' => $fileId,
				'DESCRIPTION' => '',
			]);
		}
	}

	private static function mediaPropertyId(): int
	{
		static $propertyId = null;
		if ($propertyId !== null)
		{
			return $propertyId;
		}

		$row = CIBlockProperty::GetList([], [
			'IBLOCK_ID' => self::iblockId(),
			'CODE' => 'MEDIA',
		])->Fetch();
		$propertyId = $row ? (int)$row['ID'] : 0;

		return $propertyId;
	}

	private static function mediaPropertyPayload(array $fileIds): array
	{
		$payload = [];
		foreach ($fileIds as $fileId)
		{
			$fileId = (int)$fileId;
			if ($fileId > 0)
			{
				$payload[] = ['VALUE' => $fileId];
			}
		}

		return $payload;
	}

	private static function saveUploadedFiles(array $files): array
	{
		$ids = [];
		foreach (self::prepareFiles($files) as $file)
		{
			$fileId = (int)CFile::SaveFile($file, Installer::MODULE_ID);
			if ($fileId > 0)
			{
				$ids[] = $fileId;
			}
		}
		return $ids;
	}

	private static function mediaFromFileIds(array $fileIds): array
	{
		$media = [];
		foreach ($fileIds as $fileId)
		{
			$file = CFile::GetFileArray($fileId);
			if ($file)
			{
				$media[] = [
					'id' => (int)$fileId,
					'src' => $file['SRC'],
					'content_type' => (string)$file['CONTENT_TYPE'],
					'is_image' => strpos((string)$file['CONTENT_TYPE'], 'image/') === 0,
					'is_video' => strpos((string)$file['CONTENT_TYPE'], 'video/') === 0,
				];
			}
		}
		return $media;
	}

	private static function prepareFiles(array $files): array
	{
		$result = [];
		foreach ($files as $file)
		{
			if (
				!is_array($file)
				|| empty($file['tmp_name'])
				|| (int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK
			)
			{
				continue;
			}
			$name = (string)($file['name'] ?? '');
			$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
			$size = (int)($file['size'] ?? 0);
			$isImage = in_array($ext, self::IMAGE_EXT, true);
			$isVideo = in_array($ext, self::VIDEO_EXT, true);
			if (!$isImage && !$isVideo)
			{
				continue;
			}
			if ($isImage && $size > self::MAX_IMAGE)
			{
				continue;
			}
			if ($isVideo && $size > self::MAX_VIDEO)
			{
				continue;
			}
			$file['MODULE_ID'] = Installer::MODULE_ID;
			$result[] = $file;
		}
		return $result;
	}
}
