<?php

namespace Kv04\Diary;

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

	/** Сколько заметка лежит в корзине до окончательного удаления. */
	public const TRASH_TTL = 604800;

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

	public static function list(string $ownerId): array
	{
		self::requireIblock();

		$items = [];
		$res = CIBlockElement::GetList(
			['ID' => 'DESC'],
			[
				'IBLOCK_ID' => self::iblockId(),
				'ACTIVE' => 'Y',
				'PROPERTY_OWNER' => $ownerId,
				'CHECK_PERMISSIONS' => 'N',
			],
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

	public static function add(string $ownerId, string $text, array $files = []): array
	{
		self::requireIblock();

		$html = Html::sanitize($text);
		$mediaIds = self::saveUploadedFiles($files);
		if ($html === '' && !$mediaIds)
		{
			return ['ok' => false, 'error' => 'Пустая заметка'];
		}

		$propertyValues = ['OWNER' => $ownerId];
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
		CIBlockElement::SetPropertyValuesEx($id, self::iblockId(), ['DELETED_AT' => false]);

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

		return $removed;
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
		CFile::Delete($fileId);

		return ['ok' => true, 'media' => self::mediaFromFileIds($remaining)];
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
