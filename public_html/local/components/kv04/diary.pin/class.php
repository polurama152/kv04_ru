<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Kv04\Diary\Auth;
use Kv04\Diary\Installer;
use Kv04\Diary\Path;
use Kv04\Diary\PinService;
use Kv04\Diary\ResetService;

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
		// На знакомом устройстве форма остаётся прежней: только пин.
		// На личном адресе — тоже: адрес уже сказал, чей это дневник.
		// Незнакомый адрес выглядит так же, иначе по форме было бы видно,
		// существует он или нет.
		$this->arResult['BOUND'] = Auth::boundOwnerId() !== null || $this->slugOwner() !== null;
		$this->arResult['NEEDS_EMAIL'] = $this->slugOwner() === null && PinService::needsEmail();
		// Ссылка из письма. Она не пускает внутрь: открывает форму нового пина
		// и больше ничего — утёкшее письмо не должно читать записи тихо.
		$token = (string)($this->arParams['RESET_TOKEN'] ?? '');
		$this->arResult['RESET_TOKEN'] = $token;
		$this->arResult['RESET_VALID'] = $token !== '' && ResetService::ownerByToken($token) !== null;
		// На личном адресе спрашивать некого: адрес уже сказал, чей дневник.
		$this->arResult['RESET_NEEDS_LOGIN'] = $this->slugOwner() === null;
		// Адрес в регистрации обязателен, почта — нет: см. спеку 0006.
		$this->arResult['DIARY_URL'] = Path::url();
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

		if ($action === 'forget_device')
		{
			Auth::forgetDevice();
			$this->json(['ok' => true, 'reload' => true]);
			return;
		}

		if ($action === 'forgot')
		{
			$this->json(ResetService::request(
				(string)$this->request->getPost('email'),
				$this->slugOwner()
			));
			return;
		}

		$pin = (string)$this->request->getPost('pin');
		$email = (string)$this->request->getPost('email');

		if ($action === 'reset')
		{
			$this->json(ResetService::complete(
				(string)$this->request->getPost('token'),
				$pin,
				(string)$this->request->getPost('pin_confirm')
			));
			return;
		}

		if ($action === 'create')
		{
			$result = PinService::create(
				(string)$this->request->getPost('slug'),
				$pin,
				(string)$this->request->getPost('pin_confirm'),
				$email
			);
			// Дневник заводится сразу на своём адресе — туда и уходим:
			// с него ставится приложение, и он же дальше служит входом.
			$result['reload'] = !empty($result['ok']) && empty($result['url']);
			$this->json($result);
			return;
		}

		$result = PinService::login($pin, $email, $this->slugOwner());
		$result['reload'] = !empty($result['ok']);
		$this->json($result);
	}

	/**
	 * Владелец личного адреса: строка — известный, пустая строка — адрес есть,
	 * но ничей, null — обычная страница без адреса.
	 */
	private function slugOwner(): ?string
	{
		$owner = $this->arParams['SLUG_OWNER'] ?? null;

		return $owner === null ? null : (string)$owner;
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
