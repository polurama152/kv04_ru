<?php

if (defined('KV04_DIARY_INCLUDED'))
{
	return;
}
define('KV04_DIARY_INCLUDED', true);

use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

$kv04DiaryId = 'kv04.diary';

if (class_exists(ModuleManager::class) && !ModuleManager::isModuleInstalled($kv04DiaryId))
{
	ModuleManager::registerModule($kv04DiaryId);
}

$libDir = __DIR__ . '/lib/';
require_once $libDir . 'installer.php';
require_once $libDir . 'attemptlimiter.php';
require_once $libDir . 'auth.php';
require_once $libDir . 'pinservice.php';
require_once $libDir . 'noteservice.php';
require_once $libDir . 'html.php';

if (class_exists(Loader::class))
{
	// Paths are relative to DOCUMENT_ROOT. Do not pass the module id here:
	// Loader::autoLoad() would then use $modulesHolders[id] ?? 'bitrix'
	// and look in /bitrix/modules/kv04.diary/ instead of /local/modules/.
	Loader::registerAutoLoadClasses(null, [
		'Kv04\\Diary\\Installer' => '/local/modules/kv04.diary/lib/installer.php',
		'Kv04\\Diary\\AttemptLimiter' => '/local/modules/kv04.diary/lib/attemptlimiter.php',
		'Kv04\\Diary\\Auth' => '/local/modules/kv04.diary/lib/auth.php',
		'Kv04\\Diary\\PinService' => '/local/modules/kv04.diary/lib/pinservice.php',
		'Kv04\\Diary\\NoteService' => '/local/modules/kv04.diary/lib/noteservice.php',
		'Kv04\\Diary\\Html' => '/local/modules/kv04.diary/lib/html.php',
	]);
}
