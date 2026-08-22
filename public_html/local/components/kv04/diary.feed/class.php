<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Kv04\Diary\Auth;
use Kv04\Diary\Installer;
use Kv04\Diary\BookService;
use Kv04\Diary\NoteService;
use Kv04\Diary\PinService;

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
		// Дневник, заведённый до появления идентичности, просим привязать почту:
		// без неё он остаётся в переходной ветке, где пин ищется глобально.
		$this->arResult['NEEDS_EMAIL'] = !PinService::hasEmail($ownerId);
		$this->arResult['TRASH_DAYS'] = (int)ceil(NoteService::TRASH_TTL / 86400);
		// Планировщика у модуля нет, агенты Bitrix на этом хостинге не крутятся,
		// поэтому просроченное чистим изредка на показе ленты — и всегда при
		// открытии корзины.
		if (random_int(1, 20) === 1)
		{
			NoteService::purgeExpired();
		}
		// Дневников под одним пином может быть несколько; лента показывает
		// открытый. currentId() сам заводит первый и подбирает в него старые
		// заметки, если их ещё не разложили.
		$currentBook = BookService::currentId($ownerId);
		$this->arResult['BOOKS'] = BookService::list($ownerId);
		$this->arResult['CURRENT_BOOK'] = $currentBook;
		$this->arResult['MAX_BOOKS'] = BookService::MAX_BOOKS;
		$this->arResult['ITEMS'] = NoteService::list($ownerId, $currentBook);
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

		if ($action === 'book_switch')
		{
			$bookId = (int)$this->request->getPost('book');
			if (!BookService::setCurrent($ownerId, $bookId))
			{
				$this->json(['ok' => false, 'error' => 'Дневник не найден']);
				return;
			}
			// Отдаём сразу ленту нового дневника: перезагружать страницу
			// ради смены вкладки незачем.
			$this->json([
				'ok' => true,
				'book' => $bookId,
				'items' => NoteService::list($ownerId, $bookId),
			]);
			return;
		}
		if ($action === 'book_create')
		{
			$this->json(BookService::create($ownerId, (string)$this->request->getPost('title')));
			return;
		}
		if ($action === 'book_rename')
		{
			$this->json(BookService::rename($ownerId, (int)$this->request->getPost('book'), (string)$this->request->getPost('title')));
			return;
		}
		if ($action === 'book_delete')
		{
			$this->json(BookService::delete($ownerId, (int)$this->request->getPost('book')));
			return;
		}

		if ($action === 'trash')
		{
			NoteService::purgeExpired();
			$this->json([
				'ok' => true,
				'items' => NoteService::trashAll($ownerId),
				'days' => (int)ceil(NoteService::TRASH_TTL / 86400),
			]);
			return;
		}

		if ($action === 'attach_email')
		{
			$this->json(PinService::attachEmail($ownerId, (string)$this->request->getPost('email')));
			return;
		}

		if ($action === 'add')
		{
			$files = $this->normalizeFiles($_FILES['media'] ?? []);
			$result = NoteService::add(
				$ownerId,
				(string)$this->request->getPost('text'),
				$files,
				BookService::currentId($ownerId)
			);
			// Перечитывать всю ленту не нужно: add() уже вернул готовый
			// элемент, фронт дорисовывает его сам.
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
		if ($action === 'restore')
		{
			$this->json(NoteService::restore($ownerId, $id));
			return;
		}
		if ($action === 'delete_block')
		{
			$this->json(NoteService::deleteBlock($ownerId, $id, (int)$this->request->getPost('block')));
			return;
		}
		if ($action === 'restore_fragment')
		{
			$this->json(NoteService::restoreFragment($ownerId, (int)$this->request->getPost('trash_id')));
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
