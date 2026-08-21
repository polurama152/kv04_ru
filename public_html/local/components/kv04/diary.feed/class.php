<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Kv04\Diary\Auth;
use Kv04\Diary\Installer;
use Kv04\Diary\NoteService;

class Kv04DiaryFeedComponent extends CBitrixComponent
{
	public function executeComponent()
	{
		if (!$this->loadDiaryModule())
		{
			ShowError('Модуль дневника не найден.');
			return;
		}

		Installer::ensure();

		$ownerId = Auth::getOwnerId();
		if (!$ownerId)
		{
			LocalRedirect(SITE_DIR);
			return;
		}

		if ($this->request->isPost() && check_bitrix_sessid())
		{
			$this->processPost($ownerId);
			return;
		}

		$this->arResult['OWNER'] = $ownerId;
		$this->arResult['SESSID'] = bitrix_sessid();
		$this->arResult['ITEMS'] = NoteService::list($ownerId);
		$this->includeComponentTemplate();
	}

	private function processPost(string $ownerId): void
	{
		$action = (string)$this->request->getPost('action');
		if ($action === 'logout')
		{
			Auth::clear();
			$this->json(['ok' => true, 'reload' => true]);
			return;
		}

		if ($action === 'add')
		{
			$files = $this->normalizeFiles($_FILES['media'] ?? []);
			$result = NoteService::add($ownerId, (string)$this->request->getPost('text'), $files);
			if (!empty($result['ok']))
			{
				$result['items'] = NoteService::list($ownerId);
			}
			$this->json($result);
			return;
		}

		$id = (int)$this->request->getPost('id');
		if ($action === 'edit')
		{
			$this->json(NoteService::update($ownerId, $id, (string)$this->request->getPost('text')));
			return;
		}
		if ($action === 'delete')
		{
			$this->json(NoteService::delete($ownerId, $id));
			return;
		}
		if ($action === 'attach')
		{
			$files = $this->normalizeFiles($_FILES['media'] ?? []);
			$this->json(NoteService::attach($ownerId, $id, $files));
			return;
		}
		if ($action === 'detach_media')
		{
			$this->json(NoteService::detachMedia($ownerId, $id, (int)$this->request->getPost('file_id')));
			return;
		}

		$this->json(['ok' => false, 'error' => 'Неизвестное действие']);
	}

	private function normalizeFiles(array $files): array
	{
		if (empty($files['name']))
		{
			return [];
		}
		if (!is_array($files['name']))
		{
			return [$files];
		}
		$out = [];
		foreach ($files['name'] as $i => $name)
		{
			if ($name === '' && (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)
			{
				continue;
			}
			$out[] = [
				'name' => $name,
				'type' => $files['type'][$i] ?? '',
				'tmp_name' => $files['tmp_name'][$i] ?? '',
				'error' => $files['error'][$i] ?? 0,
				'size' => $files['size'][$i] ?? 0,
			];
		}
		return $out;
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
