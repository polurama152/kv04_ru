<?php

use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

if (defined('KV04_DIARY_INCLUDED'))
{
	return;
}

$kv04DiaryId = 'kv04.diary';

if (class_exists(ModuleManager::class) && !ModuleManager::isModuleInstalled($kv04DiaryId))
{
	ModuleManager::registerModule($kv04DiaryId);
}

if (class_exists(Loader::class))
{
	Loader::includeModule($kv04DiaryId);
}

// include.php is the canonical loader (lib require + registerAutoLoadClasses with /local/ paths).
// Must run even when includeModule() succeeded — otherwise autoload resolves to /bitrix/modules/.
require_once __DIR__ . '/include.php';
