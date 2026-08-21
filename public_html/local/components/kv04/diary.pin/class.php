<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Kv04\Diary\Auth;
use Kv04\Diary\Installer;
use Kv04\Diary\PinService;

class Kv04DiaryPinComponent extends CBitrixComponent
{
	public function executeComponent()
	{
		if (!$this->loadDiaryModule())
		{
			ShowError('Модуль дневника не найден.');
			return;
		}

		Installer::ensure();

		if ($this->request->isPost() && check_bitrix_sessid())
		{
			$this->processPost();
			return;
		}

		$this->arResult['SESSID'] = bitrix_sessid();
		$this->includeComponentTemplate();
	}

	private function processPost(): void
	{
		$action = (string)$this->request->getPost('action');
		if ($action === 'logout')
		{
			Auth::clear();
			$this->json(['ok' => true, 'reload' => true]);
			return;
		}

		$pin = (string)$this->request->getPost('pin');
		if ($action === 'create')
		{
			$result = PinService::create($pin, (string)$this->request->getPost('pin_confirm'));
			$result['reload'] = !empty($result['ok']);
			$this->json($result);
			return;
		}

		$result = PinService::login($pin);
		$result['reload'] = !empty($result['ok']);
		$this->json($result);
	}

	private function loadDiaryModule(): bool
	{
		$load = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/kv04.diary/load.php';
		if (!is_file($load))
		{
			return false;
		}

		require_once $load;
		return kv04DiaryLoadModule();
	}

	private function json(array $data): void
	{
		global $APPLICATION;
		$APPLICATION->RestartBuffer();
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode($data, JSON_UNESCAPED_UNICODE);
		die();
	}
}
