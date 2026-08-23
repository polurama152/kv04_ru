<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Kv04\Diary\NoteService;

/**
 * Дневник, открытый по ссылке.
 *
 * Только чтение и ничего сверх того: POST компонент не принимает вовсе, вход
 * не спрашивает, соседние дневники и корзину не показывает. Заметки берёт той
 * же выборкой, что и лента владельца, — она сама отсекает лишнее: фильтрует
 * по владельцу, по дневнику и по ACTIVE = Y, поэтому ни удалённое, ни чужое
 * сюда не попадёт.
 *
 * Ссылку разбирает оболочка (index.php): ей же решать, что показать, когда
 * ссылки нет, — компонент получает уже готовые владельца и дневник.
 */
class Kv04DiaryShareComponent extends CBitrixComponent
{
	public function executeComponent()
	{
		$owner = (string)($this->arParams['OWNER'] ?? '');
		$book = (int)($this->arParams['BOOK'] ?? 0);
		if ($owner === '' || $book <= 0)
		{
			return;
		}

		$this->arResult['TITLE'] = (string)($this->arParams['TITLE'] ?? 'Дневник');
		$this->arResult['ITEMS'] = NoteService::list($owner, $book);
		$this->includeComponentTemplate();
	}
}
