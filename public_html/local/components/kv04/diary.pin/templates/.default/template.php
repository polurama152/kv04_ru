<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}
?>
<div class="kv04-pin" id="kv04-pin">
	<h1 class="kv04-pin__title">Мой дневник</h1>
	<p class="kv04-pin__hint">Четыре цифры — ключ только для вас</p>

	<div class="kv04-pin__dots" data-dots>
		<span></span><span></span><span></span><span></span>
	</div>
	<input type="password" inputmode="numeric" maxlength="4" autocomplete="off" class="kv04-pin__hidden" data-pin autofocus aria-label="Пин-код">

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
		<input type="password" inputmode="numeric" maxlength="4" autocomplete="off" class="kv04-input" data-pin-confirm>
	</div>

	<p class="kv04-pin__error" data-error hidden></p>

	<div class="kv04-pin__actions">
		<button type="button" class="kv04-btn kv04-btn--primary" data-login>Войти</button>
		<button type="button" class="kv04-btn kv04-btn--ghost" data-create>Создать дневник</button>
	</div>
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
	var creating = false;

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
	function appendDigit(d) {
		if (pinInput.value.length < 4) {
			pinInput.value += d;
			renderDots();
		}
	}
	function clearPin() {
		pinInput.value = '';
		renderDots();
	}
	function deleteDigit() {
		pinInput.value = pinInput.value.slice(0, -1);
		renderDots();
	}
	function handleEnter() {
		if (creating) {
			send('create');
		} else {
			send('login');
		}
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
		setError('');
		var body = new FormData();
		body.append('sessid', '<?=CUtil::JSEscape($arResult['SESSID'])?>');
		body.append('action', action);
		body.append('pin', pinInput.value);
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
				setError(data.error || 'Ошибка');
			})
			.catch(function () { setError('Нет связи'); });
	}
	root.querySelector('[data-pad]').addEventListener('click', function (e) {
		var btn = e.target.closest('button');
		if (!btn) return;
		if (btn.dataset.digit != null) {
			appendDigit(btn.dataset.digit);
		} else if (btn.dataset.action === 'clear') {
			clearPin();
		} else if (btn.dataset.action === 'del') {
			deleteDigit();
		}
		pinInput.focus();
	});
	root.querySelector('[data-dots]').addEventListener('click', function () {
		pinInput.focus();
	});
	root.querySelector('[data-login]').addEventListener('click', function () { send('login'); });
	root.querySelector('[data-create]').addEventListener('click', function () {
		if (!creating) {
			creating = true;
			confirmBox.hidden = false;
			setError('Придумайте пин и повторите его');
			return;
		}
		send('create');
	});
	pinInput.addEventListener('input', function () {
		pinInput.value = pinInput.value.replace(/\D/g, '').slice(0, 4);
		renderDots();
	});
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
	pinInput.focus();
})();
</script>
