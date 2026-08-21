<?php

namespace Kv04\Diary;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use CUserTypeEntity;
use CIBlock;
use CIBlockProperty;
use CIBlockType;

class Installer
{
	public const MODULE_ID = 'kv04.diary';
	public const HL_NAME = 'Kv04DiaryKey';
	public const HL_TABLE = 'kv04_diary_keys';
	public const IBLOCK_TYPE = 'kv04';
	public const IBLOCK_CODE = 'diary';

	public static function ensure(): void
	{
		if (!Loader::includeModule('highloadblock') || !Loader::includeModule('iblock'))
		{
			return;
		}

		if (!ModuleManager::isModuleInstalled(self::MODULE_ID))
		{
			ModuleManager::registerModule(self::MODULE_ID);
		}

		self::ensurePepper();
		$hlId = self::ensureHighload();
		$iblockId = self::ensureIblock();

		Option::set(self::MODULE_ID, 'hlblock_id', (string)$hlId);
		Option::set(self::MODULE_ID, 'iblock_id', (string)$iblockId);
	}

	private static function ensurePepper(): void
	{
		$pepper = (string)Option::get(self::MODULE_ID, 'pepper', '');
		if ($pepper === '')
		{
			Option::set(self::MODULE_ID, 'pepper', bin2hex(random_bytes(32)));
		}
	}

	private static function ensureHighload(): int
	{
		$row = HighloadBlockTable::getList([
			'filter' => ['=NAME' => self::HL_NAME],
			'limit' => 1,
		])->fetch();

		if ($row)
		{
			$hlId = (int)$row['ID'];
		}
		else
		{
			$result = HighloadBlockTable::add([
				'NAME' => self::HL_NAME,
				'TABLE_NAME' => self::HL_TABLE,
			]);
			if (!$result->isSuccess())
			{
				throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
			}
			$hlId = (int)$result->getId();
		}

		$entityId = HighloadBlockTable::compileEntityId($hlId);
		self::ensureUserField($entityId, 'UF_PIN_HASH', 'string', ['SIZE' => 64, 'ROWS' => 1]);
		self::ensureUserField($entityId, 'UF_OWNER_ID', 'string', ['SIZE' => 40, 'ROWS' => 1]);
		self::ensureUserField($entityId, 'UF_FAILS', 'integer', []);
		self::ensureUserField($entityId, 'UF_LOCKED_UNTIL', 'integer', []);

		return $hlId;
	}

	private static function ensureUserField(string $entityId, string $fieldName, string $type, array $settings): void
	{
		$existing = CUserTypeEntity::GetList([], [
			'ENTITY_ID' => $entityId,
			'FIELD_NAME' => $fieldName,
		])->Fetch();
		if ($existing)
		{
			return;
		}

		$userType = new CUserTypeEntity();
		$id = $userType->Add([
			'ENTITY_ID' => $entityId,
			'FIELD_NAME' => $fieldName,
			'USER_TYPE_ID' => $type,
			'XML_ID' => $fieldName,
			'SORT' => 100,
			'MULTIPLE' => 'N',
			'MANDATORY' => 'N',
			'SHOW_FILTER' => 'I',
			'SHOW_IN_LIST' => 'Y',
			'EDIT_IN_LIST' => 'Y',
			'IS_SEARCHABLE' => 'N',
			'SETTINGS' => $settings,
		]);
		if (!$id)
		{
			throw new \RuntimeException('Не удалось создать поле ' . $fieldName);
		}
	}

	private static function ensureIblock(): int
	{
		$type = CIBlockType::GetByID(self::IBLOCK_TYPE)->Fetch();
		if (!$type)
		{
			$iblockType = new CIBlockType();
			$result = $iblockType->Add([
				'ID' => self::IBLOCK_TYPE,
				'SECTIONS' => 'N',
				'IN_RSS' => 'N',
				'SORT' => 500,
				'LANG' => [
					'ru' => ['NAME' => 'KV04', 'ELEMENT_NAME' => 'Заметка'],
					'en' => ['NAME' => 'KV04', 'ELEMENT_NAME' => 'Note'],
				],
			]);
			if (!$result)
			{
				throw new \RuntimeException($iblockType->LAST_ERROR ?: 'Не удалось создать тип инфоблока');
			}
		}

		$existing = \CIBlock::GetList([], [
			'TYPE' => self::IBLOCK_TYPE,
			'CODE' => self::IBLOCK_CODE,
			'CHECK_PERMISSIONS' => 'N',
		])->Fetch();

		$siteId = defined('SITE_ID') ? SITE_ID : 's1';

		if ($existing)
		{
			$iblockId = (int)$existing['ID'];
		}
		else
		{
			$iblock = new CIBlock();
			$iblockId = (int)$iblock->Add([
				'ACTIVE' => 'Y',
				'NAME' => 'Дневник',
				'CODE' => self::IBLOCK_CODE,
				'IBLOCK_TYPE_ID' => self::IBLOCK_TYPE,
				'SITE_ID' => [$siteId],
				'GROUP_ID' => ['1' => 'X', '2' => 'W'],
				'INDEX_ELEMENT' => 'N',
				'WORKFLOW' => 'N',
				'BIZPROC' => 'N',
				'VERSION' => 2,
			]);
			if ($iblockId <= 0)
			{
				throw new \RuntimeException($iblock->LAST_ERROR ?: 'Не удалось создать инфоблок дневника');
			}
		}

		self::ensureProperty($iblockId, 'OWNER', 'Владелец', 'S', 'N');
		self::ensureProperty($iblockId, 'MEDIA', 'Медиа', 'F', 'Y');

		return $iblockId;
	}

	private static function ensureProperty(int $iblockId, string $code, string $name, string $type, string $multiple): void
	{
		$existing = CIBlockProperty::GetList([], [
			'IBLOCK_ID' => $iblockId,
			'CODE' => $code,
		])->Fetch();
		if ($existing)
		{
			return;
		}

		$property = new CIBlockProperty();
		$result = $property->Add([
			'IBLOCK_ID' => $iblockId,
			'NAME' => $name,
			'CODE' => $code,
			'PROPERTY_TYPE' => $type,
			'MULTIPLE' => $multiple,
			'IS_REQUIRED' => 'N',
			'SEARCHABLE' => 'N',
			'FILTRABLE' => $code === 'OWNER' ? 'Y' : 'N',
		]);
		if (!$result)
		{
			throw new \RuntimeException($property->LAST_ERROR ?: 'Не удалось создать свойство ' . $code);
		}
	}
}
