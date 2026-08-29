<?php

use Bitrix\Main\Config\Option;

// Этот файл CAdminMenu включает сам по факту установленности модуля
// (getLocalPath смотрит /local раньше /bitrix), поэтому никакой регистрации
// обработчиков не нужно — и в пакете Маркетплейса он поедет как есть.

/** @global CMain $APPLICATION */
global $APPLICATION;

if ($APPLICATION->GetGroupRight('kv04.diary') < 'R')
{
	return false;
}

if (!CModule::IncludeModule('kv04.diary'))
{
	return false;
}

IncludeModuleLangFile(__FILE__);

$kv04DiaryIblockId = (int)Option::get('kv04.diary', 'iblock_id', '0');
$kv04DiaryHlId = (int)Option::get('kv04.diary', 'hlblock_id', '0');
if ($kv04DiaryIblockId <= 0 || $kv04DiaryHlId <= 0)
{
	// Модуль зарегистрирован, но Installer::ensure() ещё не разворачивал
	// схему — вести пункты меню пока некуда.
	return false;
}

return [
	'parent_menu' => 'global_menu_services',
	'section' => 'kv04_diary',
	'sort' => 540,
	'module_id' => 'kv04.diary',
	'text' => GetMessage('KV04_DIARY_MENU_TEXT'),
	'title' => GetMessage('KV04_DIARY_MENU_TITLE'),
	'icon' => 'security_menu_icon',
	'page_icon' => 'security_page_icon',
	'items_id' => 'menu_kv04_diary',
	'items' => [
		[
			'text' => GetMessage('KV04_DIARY_MENU_OPEN'),
			'title' => GetMessage('KV04_DIARY_MENU_OPEN_TITLE'),
			'url' => '/',
		],
		[
			'text' => GetMessage('KV04_DIARY_MENU_NOTES'),
			'title' => GetMessage('KV04_DIARY_MENU_NOTES_TITLE'),
			'url' => 'iblock_element_admin.php?IBLOCK_ID=' . $kv04DiaryIblockId . '&type=kv04&lang=' . LANGUAGE_ID,
			'more_url' => ['iblock_element_edit.php?IBLOCK_ID=' . $kv04DiaryIblockId],
		],
		[
			'text' => GetMessage('KV04_DIARY_MENU_KEYS'),
			'title' => GetMessage('KV04_DIARY_MENU_KEYS_TITLE'),
			'url' => 'highloadblock_rows_list.php?ENTITY_ID=' . $kv04DiaryHlId . '&lang=' . LANGUAGE_ID,
			'more_url' => ['highloadblock_row_edit.php?ENTITY_ID=' . $kv04DiaryHlId],
		],
	],
];
