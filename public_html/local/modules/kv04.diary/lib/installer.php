<?php

namespace Kv04\Diary;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Application;
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

	/**
	 * Версия схемы данных. Поднимать при изменении структуры HL, инфоблока или
	 * своих таблиц — это заставит ensure() один раз переприменить схему на
	 * каждом сервере. 2: таблица попыток входа. 3: UF_EMAIL.
	 * 4: свойство DELETED_AT под корзину. 5: таблица обрывков заметок.
	 * 6: таблица дневников и свойство BOOK у заметок.
	 * 7: таблица ссылок, которыми делятся дневником.
	 * 8: опция пути дневника и rewrite-правила под неё.
	 * 9: таблица личных адресов владельцев.
	 */
	private const SCHEMA_VERSION = '9';
	private const OPTION_SCHEMA = 'schema_version';

	/** Схема уже проверена в этом процессе. */
	private static bool $ensured = false;

	/**
	 * Идемпотентно создаёт HL, инфоблок и pepper.
	 *
	 * Быстрый путь: если схема нужной версии уже применена, метод не делает
	 * ни одного запроса — только чтение опций модуля, которые Bitrix и так
	 * держит в памяти после первого Option::get за запрос.
	 */
	public static function ensure(bool $force = false): void
	{
		if (self::$ensured && !$force)
		{
			return;
		}

		if (!$force && self::isSchemaApplied())
		{
			self::$ensured = true;
			return;
		}

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
		self::ensureAttemptsTable();
		self::ensureTrashTable();
		self::ensureBooksTable();
		self::ensureSharesTable();
		self::ensureSlugsTable();
		// Опция пути: живой сайт без неё продолжает жить на корне ('').
		// Правила перекладываются здесь же, чтобы клиенту Маркетплейса
		// хватило установки модуля без ручных шагов.
		if ((string)Option::get(self::MODULE_ID, Path::OPTION, "\0") === "\0")
		{
			Option::set(self::MODULE_ID, Path::OPTION, '');
		}
		Path::applyRewrite();

		self::setOption('hlblock_id', (string)$hlId);
		self::setOption('iblock_id', (string)$iblockId);
		self::setOption(self::OPTION_SCHEMA, self::SCHEMA_VERSION);

		self::$ensured = true;
	}

	/**
	 * Таблица счётчиков неудачных попыток. Своя, а не b_option: Bitrix тянет
	 * все опции модуля в память при первом Option::get и чистит их кэш на
	 * каждую запись, см. AttemptLimiter.
	 *
	 * PRIMARY KEY по LOCK_KEY нужен апсерту в registerFail(), индекс по
	 * LAST_FAIL — чистке протухших строк.
	 */
	private static function ensureAttemptsTable(): void
	{
		$connection = Application::getConnection();
		if ($connection->isTableExists(AttemptLimiter::TABLE))
		{
			return;
		}

		$connection->queryExecute(
			'CREATE TABLE IF NOT EXISTS ' . AttemptLimiter::TABLE . ' ('
			. 'LOCK_KEY VARCHAR(64) NOT NULL,'
			. 'FAILS INT NOT NULL DEFAULT 0,'
			. 'LOCKED_UNTIL INT NOT NULL DEFAULT 0,'
			. 'LAST_FAIL INT NOT NULL DEFAULT 0,'
			. 'PRIMARY KEY (LOCK_KEY),'
			. 'INDEX IX_KV04_DIARY_ATTEMPTS_LAST (LAST_FAIL)'
			. ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
		);
	}

	/**
	 * Корзина для обрывков заметки: отдельного файла или блока текста и кода.
	 * Целая заметка в корзину не сюда — она просто становится неактивной, а
	 * здесь лежат куски, которым не на чем «побыть неактивными».
	 */
	private static function ensureTrashTable(): void
	{
		$connection = Application::getConnection();
		if ($connection->isTableExists(NoteService::TRASH_TABLE))
		{
			return;
		}

		$connection->queryExecute(
			'CREATE TABLE IF NOT EXISTS ' . NoteService::TRASH_TABLE . ' ('
			. 'ID INT NOT NULL AUTO_INCREMENT,'
			. 'OWNER_ID VARCHAR(40) NOT NULL,'
			. 'ELEMENT_ID INT NOT NULL,'
			. 'KIND VARCHAR(8) NOT NULL,'
			. 'FILE_ID INT NOT NULL DEFAULT 0,'
			. 'BLOCK_POS INT NOT NULL DEFAULT 0,'
			. 'PAYLOAD MEDIUMTEXT NULL,'
			. 'EXCERPT VARCHAR(255) NULL,'
			. 'DELETED_AT INT NOT NULL DEFAULT 0,'
			. 'PRIMARY KEY (ID),'
			. 'INDEX IX_KV04_DIARY_TRASH_OWNER (OWNER_ID, DELETED_AT),'
			. 'INDEX IX_KV04_DIARY_TRASH_AGE (DELETED_AT)'
			. ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
		);
	}

	/**
	 * Дневники одного владельца. Своя таблица, а не инфоблок: список читается
	 * на каждый показ ленты, и лишний слой инфоблока тут ничего не даёт.
	 */
	private static function ensureBooksTable(): void
	{
		$connection = Application::getConnection();
		if ($connection->isTableExists(BookService::TABLE))
		{
			return;
		}

		$connection->queryExecute(
			'CREATE TABLE IF NOT EXISTS ' . BookService::TABLE . ' ('
			. 'ID INT NOT NULL AUTO_INCREMENT,'
			. 'OWNER_ID VARCHAR(40) NOT NULL,'
			. 'TITLE VARCHAR(120) NOT NULL,'
			. 'SORT INT NOT NULL DEFAULT 500,'
			. 'CREATED_AT INT NOT NULL DEFAULT 0,'
			. 'PRIMARY KEY (ID),'
			. 'INDEX IX_KV04_DIARY_BOOKS_OWNER (OWNER_ID, SORT, ID)'
			. ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
		);
	}

	/**
	 * Ссылки, которыми поделились дневником. Токен уникален: по нему идёт
	 * единственный поиск, и он же не даёт завести две одинаковые ссылки.
	 * Отозванные строки не удаляем — по ним видно, что доступ был и когда
	 * его закрыли.
	 */
	private static function ensureSharesTable(): void
	{
		$connection = Application::getConnection();
		if ($connection->isTableExists(ShareService::TABLE))
		{
			return;
		}

		$connection->queryExecute(
			'CREATE TABLE IF NOT EXISTS ' . ShareService::TABLE . ' ('
			. 'ID INT NOT NULL AUTO_INCREMENT,'
			. 'OWNER_ID VARCHAR(40) NOT NULL,'
			. 'BOOK_ID INT NOT NULL,'
			. 'TOKEN CHAR(32) NOT NULL,'
			. 'CREATED_AT INT NOT NULL DEFAULT 0,'
			. 'REVOKED_AT INT NOT NULL DEFAULT 0,'
			. 'PRIMARY KEY (ID),'
			. 'UNIQUE INDEX UX_KV04_DIARY_SHARES_TOKEN (TOKEN),'
			. 'INDEX IX_KV04_DIARY_SHARES_BOOK (OWNER_ID, BOOK_ID, REVOKED_AT)'
			. ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
		);
	}

	/**
	 * Личные адреса дневников. Уникальны оба ключа: адрес — потому что по нему
	 * идёт поиск и два одинаковых развели бы владельцев по одной двери,
	 * владелец — потому что адрес у него один (апсерт в SlugService::save
	 * опирается на этот индекс).
	 */
	private static function ensureSlugsTable(): void
	{
		$connection = Application::getConnection();
		if ($connection->isTableExists(SlugService::TABLE))
		{
			return;
		}

		$connection->queryExecute(
			'CREATE TABLE IF NOT EXISTS ' . SlugService::TABLE . ' ('
			. 'ID INT NOT NULL AUTO_INCREMENT,'
			. 'OWNER_ID VARCHAR(40) NOT NULL,'
			. 'SLUG VARCHAR(32) NOT NULL,'
			. 'CREATED_AT INT NOT NULL DEFAULT 0,'
			. 'PRIMARY KEY (ID),'
			. 'UNIQUE INDEX UX_KV04_DIARY_SLUGS_OWNER (OWNER_ID),'
			. 'UNIQUE INDEX UX_KV04_DIARY_SLUGS_SLUG (SLUG)'
			. ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
		);
	}

	/**
	 * Схема применена, если совпала версия и на месте все три опции.
	 * Идентификаторы проверяем отдельно: если HL или инфоблок удалили руками,
	 * ensure() отработает заново, а не сочтёт себя молча выполненным.
	 */
	private static function isSchemaApplied(): bool
	{
		return (string)Option::get(self::MODULE_ID, self::OPTION_SCHEMA, '') === self::SCHEMA_VERSION
			&& (int)Option::get(self::MODULE_ID, 'hlblock_id', '0') > 0
			&& (int)Option::get(self::MODULE_ID, 'iblock_id', '0') > 0
			&& (string)Option::get(self::MODULE_ID, 'pepper', '') !== '';
	}

	/** Option::set пишет в b_option безусловно, поэтому сверяем значение сами. */
	private static function setOption(string $name, string $value): void
	{
		if ((string)Option::get(self::MODULE_ID, $name, '') === $value)
		{
			return;
		}

		Option::set(self::MODULE_ID, $name, $value);
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
		// Идентичность. Хранится приведённой к нижнему регистру: по ней идёт
		// поиск, а HL не даёт ни индексов, ни регистронезависимого сравнения
		// на уровне схемы.
		self::ensureUserField($entityId, 'UF_EMAIL', 'string', ['SIZE' => 180, 'ROWS' => 1]);
		// Осталось от прежней модели «пин = идентификатор»: счётчики теперь
		// в kv04_diary_attempts. Поля не удаляем — снос UF роняет колонку.
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
		// Время попадания в корзину. Заметка при удалении не стирается, а
		// становится неактивной; отсюда же считается срок хранения.
		self::ensureProperty($iblockId, 'DELETED_AT', 'Удалено', 'N', 'N');
		// К какому дневнику относится заметка. Пусто у записей, заведённых
		// до появления нескольких дневников — их подхватывает миграция.
		self::ensureProperty($iblockId, 'BOOK', 'Дневник', 'N', 'N');

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
