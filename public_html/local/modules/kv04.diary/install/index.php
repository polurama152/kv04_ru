<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\ModuleManager;
use Kv04\Diary\Installer;

class kv04_diary extends CModule
{
	public $MODULE_ID = 'kv04.diary';
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME = 'KV04 дневник';
	public $MODULE_DESCRIPTION = 'Личный дневник с входом по пину';

	public function __construct()
	{
		$arModuleVersion = [];
		include __DIR__ . '/version.php';
		$this->MODULE_VERSION = $arModuleVersion['VERSION'];
		$this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
	}

	public function DoInstall()
	{
		ModuleManager::registerModule($this->MODULE_ID);
		// Свежая установка узнаётся по пустым опциям: у живого сайта уже
		// есть hlblock_id. Дефолт 'diary' не даёт коробке Маркетплейса
		// захватить главную страницу клиента.
		$fresh = (string)Option::get($this->MODULE_ID, 'hlblock_id', '') === '';
		$load = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/kv04.diary/load.php';
		if (is_file($load))
		{
			require_once $load;
			kv04DiaryLoadModule();
		}
		else
		{
			require_once dirname(__DIR__) . '/include.php';
		}
		if ($fresh && (string)Option::get($this->MODULE_ID, 'path', '') === '')
		{
			Option::set($this->MODULE_ID, 'path', 'diary');
		}
		Installer::ensure();
	}

	public function DoUninstall()
	{
		ModuleManager::unRegisterModule($this->MODULE_ID);
	}
}
