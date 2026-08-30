<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}
?>
<div class="kv04-pin" id="kv04-pin" data-needs-email="<?=$arResult['NEEDS_EMAIL'] ? '1' : '0'?>">
	<h1 class="kv04-pin__title">Мой дневник</h1>
	<p class="kv04-pin__hint"><?=$arResult['BOUND']
		? 'С возвращением — введите пин'
		: 'Четыре цифры — ключ только для вас'?></p>

	<div class="kv04-pin__email" data-email-box<?=$arResult['NEEDS_EMAIL'] ? '' : ' hidden'?>>
		<label for="kv04-pin-email">Почта</label>
		<input type="email" id="kv04-pin-email" class="kv04-input" autocomplete="email"
			inputmode="email" spellcheck="false" data-email placeholder="you@example.com">
	</div>

	<div class="kv04-pin__dots" data-dots>
		<span></span><span></span><span></span><span></span>
	</div>
	<?php /* inputmode="none": цифры на телефоне набирают экранным падом, и
	   выезжающая клавиатура только закрывала бы его. Физической клавиатуре
	   на десктопе inputmode не мешает — фокус и autofocus остаются. */ ?>
	<input type="password" inputmode="none" maxlength="4"
		autocomplete="one-time-code" data-lpignore="true" data-1p-ignore data-bwignore
		class="kv04-pin__hidden" data-pin autofocus aria-label="Пин-код">

	<div class="kv04-pin__pad" data-pad>
		<button type="button" data-digit="1">1</button>
		<button type="button" data-digit="2">2</button>
		<button type="button" data-digit="3">3</button>
		<button type="button" data-digit="4">4</button>
		<button type="button" data-digit="5">5</button>
		<button type="button" data-digit="6">6</button>
		<button type="button" data-digit="7">7</button>
		<button type="button" data-digit="8">8</button>
		<button type="button" data-digit="9">9</button>
		<button type="button" data-action="clear">C</button>
		<button type="button" data-digit="0">0</button>
		<button type="button" data-action="del">⌫</button>
	</div>

	<div class="kv04-pin__confirm" data-confirm hidden>
		<label>Повторите пин</label>
		<input type="password" inputmode="none" maxlength="4"
			autocomplete="one-time-code" data-lpignore="true" data-1p-ignore data-bwignore
			class="kv04-input" data-pin-confirm>
	</div>

	<p class="kv04-pin__error" data-error hidden></p>

	<div class="kv04-pin__actions">
		<button type="button" class="kv04-btn kv04-btn--ghost" data-create>Создать дневник</button>
	</div>
<?php if ($arResult['BOUND']): ?>
	<button type="button" class="kv04-pin__forget" data-forget>Это не мой дневник</button>
<?php endif; ?>
</div>

<script>
(function () {
	var root = document.getElementById('kv04-pin');
	if (!root) return;
	var pinInput = root.querySelector('[data-pin]');
	var confirmBox = root.querySelector('[data-confirm]');
	var confirmInput = root.querySelector('[data-pin-confirm]');
	var dots = root.querySelectorAll('[data-dots] span');
	var error = root.querySelector('[data-error]');
	var emailBox = root.querySelector('[data-email-box]');
	var emailInput = root.querySelector('[data-email]');
	var forgetBtn = root.querySelector('[data-forget]');
	var needsEmail = root.getAttribute('data-needs-email') === '1';
	var creating = false;
	var sending = false;
	// Длина пина на прошлом шаге. Нужна, чтобы отличить набор от подстановки:
	// менеджер паролей Chrome вставляет все четыре цифры разом и шлёт один
	// input — по такому событию входить нельзя.
	var lastPinLength = 0;
	var lastConfirmLength = 0;

	function renderDots() {
		var v = pinInput.value;
		for (var i = 0; i < 4; i++) {
			dots[i].classList.toggle('is-filled', i < v.length);
		}
	}
	function setError(msg) {
		error.hidden = !msg;
		error.textContent = msg || '';
	}
	/**
	 * Единая точка после любого изменения пина.
	 * Отправляем только когда длина выросла ровно на единицу и достигла
	 * четырёх: скачок с нуля до четырёх — это подстановка браузера или
	 * вставка из буфера, а не осознанный ввод.
	 */
	function syncPin(allowSubmit) {
		var length = pinInput.value.length;
		var typedOneMore = length === lastPinLength + 1;
		lastPinLength = length;
		renderDots();
		if (allowSubmit && typedOneMore) {
			maybeSubmit(false);
		}
	}
	/**
	 * Куда падать цифре с экранного пада. Клавиатура телефона заглушена
	 * (inputmode="none"), поэтому пад обслуживает оба пин-поля: как только
	 * при создании основной пин полон — цифры идут в подтверждение.
	 * По фокусу решать нельзя: тап по кнопке пада сам забирает фокус.
	 */
	function padTargetsConfirm() {
		return creating && !confirmBox.hidden && pinInput.value.length >= 4;
	}
	function appendDigit(d) {
		if (padTargetsConfirm()) {
			if (confirmInput.value.length < 4) {
				confirmInput.value += d;
				var length = confirmInput.value.length;
				var typedOneMore = length === lastConfirmLength + 1;
				lastConfirmLength = length;
				if (typedOneMore) maybeSubmit(false);
			}
			return;
		}
		if (pinInput.value.length < 4) {
			pinInput.value += d;
			syncPin(true);
		}
	}
	function clearPin() {
		// При создании «C» — начать ввод заново целиком: полупустое
		// подтверждение при полном пине только путало бы.
		if (creating) {
			confirmInput.value = '';
			lastConfirmLength = 0;
		}
		pinInput.value = '';
		lastPinLength = 0;
		renderDots();
	}
	function deleteDigit() {
		if (padTargetsConfirm() && confirmInput.value.length > 0) {
			confirmInput.value = confirmInput.value.slice(0, -1);
			lastConfirmLength = confirmInput.value.length;
			return;
		}
		pinInput.value = pinInput.value.slice(0, -1);
		lastPinLength = pinInput.value.length;
		renderDots();
	}
	function emailFilled() {
		return !emailInput || emailInput.value.trim() !== '';
	}

	/**
	 * Кнопки «Войти» нет: набранный пин и есть действие. Вызывается после
	 * каждой цифры, поэтому молча выходит, пока вводить ещё нечего.
	 * explicit = запрос пришёл от Enter, тогда объясняем, чего не хватает.
	 */
	function maybeSubmit(explicit) {
		if (sending) return;

		// Порядок важен: сначала пин, потом всё остальное. Проверять почту
		// раньше нельзя — метод зовётся на каждую цифру, и фокус уезжал бы
		// из пин-поля уже на первой.
		if (pinInput.value.length < 4) {
			if (explicit) setError('Введите 4 цифры');
			return;
		}

		if (creating) {
			if (confirmInput.value.length < 4) {
				if (explicit) setError('Повторите пин');
				confirmInput.focus();
				return;
			}
			if (!emailFilled()) {
				setError('Укажите почту');
				if (emailInput) emailInput.focus();
				return;
			}
			send('create');
			return;
		}

		if (needsEmail && !emailFilled()) {
			setError('Сначала укажите почту');
			if (emailInput) emailInput.focus();
			return;
		}

		send('login');
	}

	function handleEnter() {
		maybeSubmit(true);
	}
	function handleEscape() {
		if (!error.hidden && error.textContent) {
			setError('');
		} else {
			clearPin();
		}
	}
	function isEditableTarget(el) {
		return el !== pinInput && (
			el.tagName === 'INPUT' ||
			el.tagName === 'TEXTAREA' ||
			el.tagName === 'SELECT' ||
			el.isContentEditable
		);
	}
	function send(action) {
		if (sending) return;
		sending = true;
		setError('');
		var body = new FormData();
		body.append('sessid', '<?=CUtil::JSEscape($arResult['SESSID'])?>');
		body.append('action', action);
		body.append('pin', pinInput.value);
		// На знакомом устройстве почта не нужна: аккаунт известен по привязке.
		if (emailInput && (action === 'create' || needsEmail)) {
			body.append('email', emailInput.value);
		}
		if (action === 'create') {
			body.append('pin_confirm', confirmInput.value);
		}
		fetch(location.href, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.ok && data.reload) {
					location.reload();
					return;
				}
				sending = false;
				setError(data.error || 'Ошибка');
				// Отправка идёт сама, поэтому после отказа освобождаем поле —
				// иначе следующая цифра просто не влезет в уже полный пин.
				failReset();
			})
			.catch(function () {
				sending = false;
				setError('Нет связи');
				failReset();
			});
	}

	function failReset() {
		clearPin();
		if (creating) {
			confirmInput.value = '';
			lastConfirmLength = 0;
		}
		pinInput.focus();
	}
	root.querySelector('[data-pad]').addEventListener('click', function (e) {
		var btn = e.target.closest('button');
		if (!btn) return;
		if (btn.dataset.digit != null) {
			var before = document.activeElement;
			appendDigit(btn.dataset.digit);
			// Последняя цифра могла увести фокус — на подтверждение или на
			// почту. Отбирать его обратно в скрытый пин нельзя.
			if (document.activeElement === before) {
				pinInput.focus();
			}
			return;
		}
		if (btn.dataset.action === 'clear') {
			clearPin();
		} else if (btn.dataset.action === 'del') {
			deleteDigit();
		}
		pinInput.focus();
	});
	root.querySelector('[data-dots]').addEventListener('click', function () {
		pinInput.focus();
	});
	root.querySelector('[data-create]').addEventListener('click', function () {
		if (!creating) {
			creating = true;
			if (emailBox) emailBox.hidden = false;
			confirmBox.hidden = false;
			setError('Укажите почту, придумайте пин и повторите его');
			if (emailInput) emailInput.focus();
			return;
		}
		send('create');
	});
	if (forgetBtn) {
		forgetBtn.addEventListener('click', function () {
			send('forget_device');
		});
	}
	pinInput.addEventListener('input', function () {
		pinInput.value = pinInput.value.replace(/\D/g, '').slice(0, 4);
		syncPin(true);
	});
	confirmInput.addEventListener('input', function () {
		confirmInput.value = confirmInput.value.replace(/\D/g, '').slice(0, 4);
		var length = confirmInput.value.length;
		// Та же защита, что и у пина: подстановку целиком не принимаем.
		var typedOneMore = length === lastConfirmLength + 1;
		lastConfirmLength = length;
		if (typedOneMore) {
			maybeSubmit(false);
		}
	});
	if (emailInput) {
		emailInput.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter') return;
			e.preventDefault();
			// С почтой разобрались — дальше пин.
			if (pinInput.value.length < 4) {
				pinInput.focus();
				return;
			}
			maybeSubmit(true);
		});
	}
	document.addEventListener('keydown', function (e) {
		if (isEditableTarget(e.target)) return;
		if (e.key >= '0' && e.key <= '9') {
			e.preventDefault();
			appendDigit(e.key);
			return;
		}
		if (e.key === 'Backspace') {
			e.preventDefault();
			deleteDigit();
			return;
		}
		if (e.key === 'Enter') {
			e.preventDefault();
			handleEnter();
			return;
		}
		if (e.key === 'Escape') {
			e.preventDefault();
			handleEscape();
		}
	});
	renderDots();
	if (needsEmail && emailInput) {
		emailInput.focus();
	} else {
		pinInput.focus();
	}
})();
</script>
