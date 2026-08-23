<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

// Разметку заметок рисует общий файл модуля: ту же ленту показывает
// страница, открытая по ссылке, и второй экземпляр этой разметки
// разъехался бы с первым на первой же правке.
require $_SERVER['DOCUMENT_ROOT'] . '/local/modules/kv04.diary/include/render-items.php';

$kv04Books = $arResult['BOOKS'] ?? [];
$kv04CurrentBook = (int)($arResult['CURRENT_BOOK'] ?? 0);
$kv04CurrentTitle = 'Мой дневник';
foreach ($kv04Books as $kv04Book)
{
	if ((int)$kv04Book['id'] === $kv04CurrentBook)
	{
		$kv04CurrentTitle = (string)$kv04Book['title'];
	}
}
?>
<div class="kv04-workspace" id="kv04-workspace">

<aside class="kv04-books" data-books aria-label="Дневники">
	<div class="kv04-books__head">
		<span class="kv04-books__title">Дневники</span>
		<button type="button" class="kv04-books__close" data-books-close aria-label="Закрыть список">&times;</button>
	</div>
	<div class="kv04-books__list" data-books-list>
		<?php foreach ($kv04Books as $kv04Book): ?>
			<?php $kv04IsCurrent = (int)$kv04Book['id'] === $kv04CurrentBook; ?>
			<div class="kv04-book<?=$kv04IsCurrent ? ' is-current' : ''?>" data-book="<?=(int)$kv04Book['id']?>">
				<button type="button" class="kv04-book__open" data-book-open title="<?=htmlspecialcharsbx($kv04Book['title'])?><?=$kv04IsCurrent ? ' — нажмите, чтобы переименовать' : ''?>"><?=htmlspecialcharsbx($kv04Book['title'])?></button>
				<button type="button" class="kv04-book__act kv04-book__act--danger" data-book-delete aria-label="Удалить дневник" title="Удалить дневник">&times;</button>
			</div>
		<?php endforeach; ?>
	</div>
	<button type="button" class="kv04-books__add" data-book-add>+ Новый дневник</button>
	<p class="kv04-books__limit" data-books-limit hidden></p>
</aside>

<div class="kv04-books__backdrop" data-books-backdrop></div>

<div class="kv04-feed" id="kv04-feed" data-max-books="<?=(int)($arResult['MAX_BOOKS'] ?? 50)?>">
	<div class="kv04-feed__head">
		<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm kv04-feed__books" data-books-open>Дневники</button>
		<h1 data-current-title title="Нажмите, чтобы переименовать"><?=htmlspecialcharsbx($kv04CurrentTitle)?></h1>
		<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-share-open>Поделиться</button>
		<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-trash-open>Корзина</button>
		<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-logout>Выйти</button>
	</div>

	<div class="kv04-share" data-share hidden>
		<div class="kv04-share__head">
			<span class="kv04-share__title">Ссылка на этот дневник</span>
			<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-share-close>Закрыть</button>
		</div>
		<p class="kv04-share__note">Ссылка живая: всё, что появится в этом дневнике дальше, тоже увидит тот, у кого она есть.</p>
		<input type="text" class="kv04-input kv04-share__url" data-share-url readonly spellcheck="false" aria-label="Ссылка">
		<div class="kv04-share__actions">
			<button type="button" class="kv04-btn kv04-btn--primary kv04-btn--sm" data-share-send>Отправить</button>
			<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-share-copy>Скопировать</button>
			<button type="button" class="kv04-btn kv04-btn--danger kv04-btn--sm" data-share-revoke>Закрыть доступ</button>
		</div>
	</div>

	<div class="kv04-trash" data-trash hidden>
		<div class="kv04-trash__head">
			<span class="kv04-trash__title">Корзина</span>
			<span class="kv04-trash__note">Хранится <?=(int)$arResult['TRASH_DAYS']?> дней, потом удаляется насовсем</span>
			<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-trash-close>Закрыть</button>
		</div>
		<div class="kv04-trash__list" data-trash-list></div>
	</div>

<?php if (!empty($arResult['NEEDS_EMAIL'])): ?>
	<div class="kv04-attach-email" data-attach-email>
		<p class="kv04-attach-email__text">
			Привяжите почту — она станет именем дневника.
			Пока её нет, дневник открывается одним пином, а такой пин может
			случайно подойти постороннему.
		</p>
		<div class="kv04-attach-email__row">
			<input type="email" class="kv04-input" autocomplete="email" inputmode="email"
				spellcheck="false" data-attach-input placeholder="you@example.com" aria-label="Почта">
			<button type="button" class="kv04-btn kv04-btn--primary kv04-btn--sm" data-attach-save>Привязать</button>
		</div>
		<p class="kv04-attach-email__status" data-attach-status hidden></p>
	</div>
<?php endif; ?>

	<form class="kv04-composer" data-composer>
		<div class="kv04-note__body kv04-composer__input"
			data-input
			contenteditable="true"
			role="textbox"
			aria-multiline="true"
			aria-label="Текст заметки"
			data-placeholder="Что у вас на уме? Можно код и файлы."></div>
		<div class="kv04-composer__bar">
			<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-code>Код</button>
			<label class="kv04-btn kv04-btn--muted kv04-btn--sm">
				Файл
				<input type="file" name="media[]" accept="image/*,video/mp4,video/webm" multiple hidden>
			</label>
			<label class="kv04-btn kv04-btn--muted kv04-btn--sm" data-shot title="Снять и сразу сохранить">
				Фото
				<input type="file" accept="image/*" capture="environment" hidden>
			</label>
			<label class="kv04-btn kv04-btn--muted kv04-btn--sm" data-clip title="Записать видео — в дневник ляжет короткое превью">
				Видео
				<input type="file" accept="video/*" capture="environment" hidden>
			</label>
			<button type="submit" class="kv04-btn kv04-btn--primary kv04-btn--sm" title="Готово (Ctrl+Enter)">Готово</button>
		</div>
		<div class="kv04-composer__preview" data-file-preview hidden></div>
		<p class="kv04-composer__status" data-shot-status hidden></p>
		<p class="kv04-feed__error" data-error hidden></p>
	</form>

	<div class="kv04-feed__list" data-list>
		<?php kv04DiaryRenderItems($arResult['ITEMS']); ?>
	</div>
</div>

</div><!-- /kv04-workspace -->

<div class="kv04-lightbox" id="kv04-lightbox" aria-hidden="true">
	<div class="kv04-lightbox__backdrop" data-lightbox-close tabindex="-1"></div>
	<button type="button" class="kv04-lightbox__close" data-lightbox-close aria-label="Закрыть">&times;</button>
	<div class="kv04-lightbox__stage" role="dialog" aria-modal="true" aria-label="Просмотр медиа"></div>
</div>

<div class="kv04-confirm" id="kv04-confirm" aria-hidden="true" hidden>
	<div class="kv04-confirm__backdrop"></div>
	<div class="kv04-confirm__box" role="alertdialog" aria-modal="true" aria-labelledby="kv04-confirm-title">
		<p class="kv04-confirm__title" id="kv04-confirm-title">Сохранить изменения?</p>
		<div class="kv04-confirm__actions">
			<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-confirm-no>Нет</button>
			<button type="button" class="kv04-btn kv04-btn--primary kv04-btn--sm" data-confirm-yes>Да</button>
		</div>
	</div>
</div>

<?php
/**
 * highlight.js лежит рядом с модулем, а не на cdnjs: сторонний домен стоил
 * браузеру отдельных DNS и TLS на первой загрузке дневника, до того как
 * покажется хоть строчка. Метка mtime даёт новый URL, когда файл обновится, —
 * так же, как у стилей в index.php.
 */
$kv04HljsSrc = '/local/modules/kv04.diary/assets/highlight/highlight.min.js';
$kv04HljsVersion = (int)@filemtime($_SERVER['DOCUMENT_ROOT'] . $kv04HljsSrc);
?>
<script src="<?=htmlspecialcharsbx($kv04HljsSrc . ($kv04HljsVersion > 0 ? '?v=' . $kv04HljsVersion : ''))?>"></script>
<script>
(function () {
	var root = document.getElementById('kv04-feed');
	if (!root) return;
	var sessid = '<?=CUtil::JSEscape($arResult['SESSID'])?>';
	var composer = root.querySelector('[data-composer]');
	var input = composer.querySelector('[data-input]');
	var list = root.querySelector('[data-list]');
	var error = root.querySelector('[data-error]');
	var filePreview = root.querySelector('[data-file-preview]');
	// Именно поле «Файл»: в панели есть второй file input — у кнопки съёмки,
	// и первый попавшийся селектор однажды подцепил бы не тот.
	var fileInput = composer.querySelector('input[name="media[]"]');
	var pendingFiles = new DataTransfer();
	var previewUrls = [];

	// --- Редактор «что вижу, то и получаю» --------------------------------
	//
	// Заметка правится прямо в том узле, который вы читаете: contenteditable
	// вешается на .kv04-note__body, разметка и стили не подменяются. Поэтому
	// отступы, переносы и блоки кода при правке выглядят ровно как в ленте.

	var NEWLINE = String.fromCharCode(10);

	function closestPre(node) {
		var el = node && node.nodeType === 1 ? node : (node ? node.parentNode : null);
		return el && el.closest ? el.closest('pre') : null;
	}

	function currentRange(root) {
		var sel = window.getSelection();
		if (!sel || !sel.rangeCount) return null;
		var range = sel.getRangeAt(0);
		return root.contains(range.commonAncestorContainer) ? range : null;
	}

	function escapeForHtml(text) {
		return String(text)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	// Текст узла, где <br> считается переводом строки. Contenteditable ставит
	// именно <br>, а textContent их проглатывает — строки склеились бы.
	// Заодно проходит сквозь span-ы подсветки.
	function textWithBreaks(node) {
		var out = '';
		var children = node.childNodes;
		for (var i = 0; i < children.length; i++) {
			var child = children[i];
			if (child.nodeType === 3) {
				out += child.nodeValue;
				continue;
			}
			if (child.nodeType !== 1) continue;
			if (child.tagName.toLowerCase() === 'br') {
				out += NEWLINE;
				continue;
			}
			out += textWithBreaks(child);
		}
		return out;
	}

	function codeTextOf(pre) {
		return textWithBreaks(pre.querySelector('code') || pre);
	}

	// Подсветка накладывается, когда каретка ушла из блока. Красить под
	// курсором нельзя: hljs пересобирает содержимое узла, и каретка уезжает.
	function highlightCodeBlock(pre) {
		var code = pre.querySelector('code');
		if (!code) return;
		var text = codeTextOf(pre);
		resetCodeBlock(code);
		code.textContent = text;
		if (!window.hljs) return;
		try { hljs.highlightElement(code); } catch (err) {}
	}

	// Приводим содержимое к тому виду, в котором заметки и хранятся: обычный
	// текст с переносами плюс блоки кода. Contenteditable по своей воле
	// заворачивает строки в <div> и <br> — превращаем их обратно в переносы,
	// иначе разметка редактора протекла бы в базу. Подсветку снимаем здесь же,
	// иначе span-ы hljs осели бы в тексте и наложились сами на себя.
	function serializeForSave(root) {
		var out = '';

		function walk(node) {
			var children = node.childNodes;
			// Между обёртками блоков осмысленного текста быть не может —
			// только отступы разметки. Забирать их в заметку нельзя, иначе
			// они осели бы в базе и копились с каждым сохранением.
			var betweenBlocks = node.querySelector
				&& node.querySelector(':scope > .kv04-note__block') !== null;

			for (var i = 0; i < children.length; i++) {
				var child = children[i];

				if (child.nodeType === 3) {
					if (betweenBlocks && child.nodeValue.trim() === '') continue;
					out += escapeForHtml(child.nodeValue);
					continue;
				}
				if (child.nodeType !== 1) continue;

				var tag = child.tagName.toLowerCase();

				if (tag === 'button') {
					// Крестик удаления блока — часть интерфейса, в тексте ему не место.
					continue;
				}
				if (child.classList && child.classList.contains('kv04-note__block')) {
					// Обёртка блока нужна только для показа: проходим насквозь,
					// не добавляя перенос, иначе он копился бы с каждой правкой.
					walk(child);
					continue;
				}

				if (tag === 'pre') {
					out += '<pre><code>' + escapeForHtml(codeTextOf(child)) + '</code></pre>';
					continue;
				}
				if (tag === 'br') {
					out += NEWLINE;
					continue;
				}
				if (tag === 'div' || tag === 'p') {
					if (out !== '' && out.slice(-1) !== NEWLINE) {
						out += NEWLINE;
					}
					// <div><br></div> — это одна пустая строка, а не две.
					var onlyBreak = child.childNodes.length === 1
						&& child.firstChild.nodeName === 'BR';
					if (!onlyBreak) walk(child);
					continue;
				}

				walk(child);
			}
		}

		walk(root);

		while (out.slice(-1) === NEWLINE) {
			out = out.slice(0, -1);
		}
		return out;
	}

	function bindEditor(root, options) {
		var opts = options || {};
		var lastPre = null;

		// Ушли из блока кода — красим тот, который покинули.
		function syncHighlight() {
			var range = currentRange(root);
			var pre = range ? closestPre(range.startContainer) : null;
			if (lastPre && lastPre !== pre && root.contains(lastPre)) {
				highlightCodeBlock(lastPre);
			}
			lastPre = pre;
		}

		document.addEventListener('selectionchange', syncHighlight);

		root.addEventListener('blur', function () {
			if (lastPre && root.contains(lastPre)) highlightCodeBlock(lastPre);
			lastPre = null;
		});

		root.addEventListener('keydown', function (e) {
			if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
				e.preventDefault();
				// Тот же хоткей ловит и вся заметка целиком — сообщаем ей, что
				// здесь уже сохранили, иначе правка уедет на сервер дважды.
				e.stopPropagation();
				if (opts.onSave) opts.onSave();
				return;
			}
			if (e.key !== 'Enter') return;

			var sel = window.getSelection();
			if (!closestPre(sel && sel.anchorNode)) {
				// Снаружи блока кода браузеру не мешаем: его <div> и <br>
				// сериализатор всё равно превратит обратно в переносы.
				return;
			}
			// Внутри кода нужен перенос строки, а не новый абзац. Родная
			// команда браузера делает это корректно и сохраняет отмену —
			// ручная правка DOM здесь сбивала каретку через раз.
			e.preventDefault();
			document.execCommand('insertLineBreak');
		});

		root.addEventListener('paste', function (e) {
			var images = collectClipboardImages(e.clipboardData && e.clipboardData.items);
			if (images.length && opts.onImages) {
				e.preventDefault();
				opts.onImages(images);
				return;
			}
			// Только текст: чужой HTML в заметку не пускаем, да и вставленное
			// форматирование всё равно срезал бы санитайзер на сервере.
			var text = e.clipboardData ? e.clipboardData.getData('text/plain') : '';
			e.preventDefault();
			document.execCommand('insertText', false, text);
		});
	}

	// Кнопка «Код»: снаружи блока — завернуть выделение, внутри — выйти следом.
	function toggleCodeBlock(root) {
		root.focus();
		var range = currentRange(root);
		if (!range) return;

		var sel = window.getSelection();
		var pre = closestPre(range.startContainer);

		if (pre) {
			highlightCodeBlock(pre);
			var after = pre.nextSibling;
			if (!after || after.nodeType !== 3) {
				after = document.createTextNode(NEWLINE);
				pre.parentNode.insertBefore(after, pre.nextSibling);
			}
			var out = document.createRange();
			out.setStart(after, Math.min(1, after.nodeValue.length));
			out.collapse(true);
			sel.removeAllRanges();
			sel.addRange(out);
			return;
		}

		var text = range.toString();
		var block = document.createElement('pre');
		var code = document.createElement('code');
		// Пустой <code> — негодная цель для каретки: браузер выносит ввод
		// наружу, и набранное оказывается в <pre> мимо блока. Поэтому у блока
		// всегда есть текстовый узел.
		var holder = document.createTextNode(text !== '' ? text : NEWLINE);
		code.appendChild(holder);
		block.appendChild(code);
		range.deleteContents();
		range.insertNode(block);

		var inside = document.createRange();
		inside.setStart(holder, text !== '' ? holder.nodeValue.length : 0);
		inside.collapse(true);
		sel.removeAllRanges();
		sel.addRange(inside);
	}

	function inCodeBlock(root) {
		var range = currentRange(root);
		return !!(range && closestPre(range.startContainer));
	}

	function resetCodeBlock(code) {
		code.removeAttribute('data-highlighted');
		code.className = '';
		// Возвращаем чистый текст: hljs оборачивает подсветку в span-ы, и без
		// сброса повторный проход красит поверх старой разметки — отсюда его
		// предупреждение про unescaped HTML и лишние вложенные теги.
		code.textContent = textWithBreaks(code);
		var pre = code.closest('pre');
		if (pre) {
			pre.classList.remove('hljs');
			pre.removeAttribute('data-highlighted');
		}
	}

	function highlight(scope) {
		if (!window.hljs) return;
		var rootEl = scope && scope.querySelectorAll ? scope : list;
		rootEl.querySelectorAll('pre code').forEach(function (el) {
			if (el.closest('[data-composer]')) return;
			resetCodeBlock(el);
			try {
				hljs.highlightElement(el);
			} catch (err) {}
		});
	}

	window.kv04DiaryHighlight = highlight;
	function setError(msg) {
		error.hidden = !msg;
		error.textContent = msg || '';
	}
	function post(data, isForm) {
		var body = isForm ? data : new FormData();
		if (!isForm) {
			Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
		}
		body.append('sessid', sessid);
		return fetch(location.href, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); });
	}

	function revokePreviewUrls() {
		previewUrls.forEach(function (url) { URL.revokeObjectURL(url); });
		previewUrls = [];
	}

	function collectClipboardImages(items) {
		var files = [];
		if (!items) return files;
		for (var i = 0; i < items.length; i++) {
			if (items[i].type.indexOf('image') === 0) {
				var file = items[i].getAsFile();
				if (file) files.push(file);
			}
		}
		return files;
	}

	function syncFileInput() {
		fileInput.files = pendingFiles.files;
	}

	function setPendingFiles(fileList, append) {
		if (!append) {
			pendingFiles = new DataTransfer();
		}
		for (var i = 0; i < fileList.length; i++) {
			pendingFiles.items.add(fileList[i]);
		}
		syncFileInput();
		renderComposerPreview();
	}

	function removePendingFile(index) {
		var dt = new DataTransfer();
		for (var i = 0; i < pendingFiles.files.length; i++) {
			if (i !== index) dt.items.add(pendingFiles.files[i]);
		}
		pendingFiles = dt;
		syncFileInput();
		renderComposerPreview();
	}

	function renderComposerPreview() {
		revokePreviewUrls();
		filePreview.innerHTML = '';
		var count = pendingFiles.files.length;
		filePreview.hidden = !count;
		if (!count) return;

		var grid = document.createElement('div');
		grid.className = 'kv04-composer__preview-grid';
		for (var i = 0; i < count; i++) {
			var file = pendingFiles.files[i];
			var item = document.createElement('div');
			item.className = 'kv04-composer__preview-item';

			if (file.type.indexOf('image/') === 0) {
				var img = document.createElement('img');
				var url = URL.createObjectURL(file);
				previewUrls.push(url);
				img.src = url;
				img.alt = file.name;
				item.appendChild(img);
			} else if (file.type.indexOf('video/') === 0) {
				var video = document.createElement('video');
				var videoUrl = URL.createObjectURL(file);
				previewUrls.push(videoUrl);
				video.src = videoUrl;
				video.muted = true;
				video.playsInline = true;
				item.appendChild(video);
				var badge = document.createElement('span');
				badge.className = 'kv04-composer__preview-badge';
				badge.textContent = 'видео';
				item.appendChild(badge);
			} else {
				var name = document.createElement('span');
				name.className = 'kv04-composer__preview-name';
				name.textContent = file.name;
				item.appendChild(name);
			}

			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'kv04-composer__preview-remove';
			remove.setAttribute('aria-label', 'Убрать файл');
			remove.innerHTML = '&times;';
			(function (idx) {
				remove.addEventListener('click', function () { removePendingFile(idx); });
			})(i);
			item.appendChild(remove);
			grid.appendChild(item);
		}
		filePreview.appendChild(grid);
	}

	function clearComposerFiles() {
		pendingFiles = new DataTransfer();
		syncFileInput();
		renderComposerPreview();
	}

	fileInput.addEventListener('change', function () {
		setPendingFiles(fileInput.files, false);
	});

	var codeBtn = composer.querySelector('[data-code]');
	codeBtn.addEventListener('click', function () {
		toggleCodeBlock(input);
		syncCodeButton();
	});

	// Подпись зависит от того, где каретка: снаружи блока кнопка заворачивает
	// выделение, внутри — выводит наружу. Без этого из блока было бы не выйти.
	function syncCodeButton() {
		if (document.activeElement !== input && !input.contains(document.activeElement)) return;
		var inside = inCodeBlock(input);
		codeBtn.textContent = inside ? 'Текст' : 'Код';
		codeBtn.title = inside ? 'Выйти из блока кода' : 'Обернуть выделение в код';
	}
	document.addEventListener('selectionchange', syncCodeButton);

	var saveShortcutLabel = (navigator.platform || '').indexOf('Mac') !== -1 ? '\u2318+Enter' : 'Ctrl+Enter';
	var composerSubmit = composer.querySelector('[type=submit]');
	if (composerSubmit) {
		composerSubmit.title = 'Готово (' + saveShortcutLabel + ')';
	}

	function buildComposerFormData() {
		var body = new FormData();
		body.append('action', 'add');
		body.append('text', serializeForSave(input));
		for (var i = 0; i < pendingFiles.files.length; i++) {
			body.append('media[]', pendingFiles.files[i]);
		}
		return body;
	}

	// --- Ссылки ------------------------------------------------------------
	//
	// Адреса ищем по текстовым узлам, а не регуляркой по HTML: так не нужно
	// заботиться об экранировании (текст кладётся через textContent) и нельзя
	// случайно залезть внутрь тега. Внутри <pre> и <code> ссылки не трогаем —
	// там адрес часть кода. Разметка ссылок нигде не хранится: в базе лежит
	// обычный текст, ссылки навешиваются при показе, а сериализатор при
	// сохранении отдаёт обратно только их текст.

	// Зоны, в которых голый домен без схемы считается адресом. Список ручной
	// и намеренно неполный: зоны, совпадающие с расширениями файлов, сюда не
	// входят. Иначе в дневнике с кодом readme.md (Молдова), build.sh (Святая
	// Елена), main.py (Парагвай), lib.so (Сомали) и model.ai (Ангилья)
	// превращались бы в ссылки. Дополнять по мере надобности.
	var BARE_TLD = [
		'ru', 'рф', 'su', 'by', 'kz', 'ua', 'uz', 'am', 'ge', 'az', 'kg', 'tj',
		'com', 'net', 'org', 'info', 'biz', 'pro', 'name', 'edu', 'gov', 'int',
		'online', 'site', 'store', 'shop', 'tech', 'space', 'website', 'cloud',
		'app', 'dev', 'io', 'me', 'tv', 'cc', 'xyz', 'top', 'club', 'live',
		'life', 'news', 'blog', 'wiki', 'help', 'link', 'click', 'email',
		'host', 'press', 'agency', 'digital', 'studio', 'design', 'art', 'fun',
		'games', 'guru', 'team', 'today', 'tools', 'works', 'world', 'zone'
	].join('|');

	// Буквы домена: латиница, цифры, дефис и кириллица — ради зоны рф.
	var DOMAIN_CHAR = 'a-z0-9\\u0430-\\u044f\\u0451-';

	// Порядок ветвей важен: почта раньше голого домена, иначе kto@to.ru
	// распался бы на адрес и домен. Схема раньше www по той же причине.
	function urlPattern() {
		return new RegExp(
			'(?:https?:\\/\\/|www\\.)[^\\s<>"\']+'
			+ '|(?:mailto:)?[\\w.+-]+@[\\w-]+(?:\\.[\\w-]+)+'
			+ '|(?:[' + DOMAIN_CHAR + ']+\\.)+(?:' + BARE_TLD + ')'
			// Хвост зоны проверяем сами: \b в JS не знает кириллицы,
			// и «полурама.рфы» прошло бы как ссылка.
			+ '(?![' + DOMAIN_CHAR + '])'
			+ '(?:[\\/?#][^\\s<>"\']*)?',
			'gi'
		);
	}

	function probePattern() {
		return new RegExp(
			'(?:https?:\\/\\/|www\\.)[^\\s<>"\']'
			+ '|@[\\w-]+\\.[\\w-]'
			+ '|\\.(?:' + BARE_TLD + ')(?![' + DOMAIN_CHAR + '])',
			'i'
		);
	}

	function linkifyTextNode(node) {
		var text = node.nodeValue;
		var re = urlPattern();
		var frag = document.createDocumentFragment();
		var last = 0;
		var match;

		while ((match = re.exec(text)) !== null) {
			var url = match[0];
			var trail = '';

			// Хвостовая пунктуация принадлежит фразе, а не адресу:
			// «зайди на example.com.» — точка тут не часть ссылки.
			var trimmed = url.replace(/[.,!?:;)\]}»"']+$/, '');
			if (trimmed !== url) {
				trail = url.slice(trimmed.length);
				url = trimmed;
			}
			if (!url) continue;

			if (match.index > last) {
				frag.appendChild(document.createTextNode(text.slice(last, match.index)));
			}

			var link = document.createElement('a');
			// Схему проверять не нужно: шаблон пропускает только http, https,
			// www и почту, поэтому javascript: сюда не попадёт.
			if (url.indexOf('@') !== -1 && url.indexOf('//') === -1) {
				link.href = 'mailto:' + url.replace(/^mailto:/i, '');
			} else if (/^https?:\/\//i.test(url)) {
				link.href = url;
			} else {
				// www.example.com и голый polurama.ru — схемы нет, добавляем.
				link.href = 'https://' + url;
			}
			link.textContent = url;
			link.target = '_blank';
			link.rel = 'noopener noreferrer nofollow';
			frag.appendChild(link);

			if (trail) frag.appendChild(document.createTextNode(trail));
			last = match.index + match[0].length;
		}

		if (!frag.childNodes.length) return;
		if (last < text.length) {
			frag.appendChild(document.createTextNode(text.slice(last)));
		}
		node.parentNode.replaceChild(frag, node);
	}

	function linkify(root) {
		if (!root || !root.querySelectorAll) return;

		var probe = probePattern();
		var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
			acceptNode: function (node) {
				var parent = node.parentNode;
				if (!parent || !parent.closest) return NodeFilter.FILTER_REJECT;
				if (parent.closest('pre, code, a')) return NodeFilter.FILTER_REJECT;
				return probe.test(node.nodeValue)
					? NodeFilter.FILTER_ACCEPT
					: NodeFilter.FILTER_REJECT;
			}
		});

		// Сначала собираем, потом заменяем: правка DOM во время обхода
		// сбивает обходчик.
		var targets = [];
		var node;
		while ((node = walker.nextNode()) !== null) targets.push(node);
		targets.forEach(linkifyTextNode);
	}

	// --- Блоки заметки -----------------------------------------------------
	//
	// Блок — то, что видно отдельным куском: <pre> с кодом либо текст между
	// такими блоками. Разбор повторяет NoteService::splitBlocks() на сервере,
	// поэтому номера блоков совпадают и их можно передавать как есть.

	function splitBlocksHtml(html) {
		var parts = String(html || '').split(/(<pre\b[\s\S]*?<\/pre>)/i);
		var blocks = [];
		parts.forEach(function (part) {
			var isCode = /^<pre\b/i.test(part);
			if (!isCode && part.trim() === '') return;
			blocks.push({ type: isCode ? 'code' : 'text', html: part });
		});
		return blocks;
	}

	function buildBlock(html, index) {
		var wrap = document.createElement('div');
		wrap.className = 'kv04-note__block';
		wrap.setAttribute('data-block', String(index));
		wrap.innerHTML = html;
		linkify(wrap);

		var share = document.createElement('button');
		share.type = 'button';
		share.className = 'kv04-block-share';
		share.setAttribute('data-share-block', '');
		share.setAttribute('aria-label', 'Поделиться блоком');
		share.title = 'Поделиться';
		share.innerHTML = '&#8599;';
		wrap.appendChild(share);

		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'kv04-block-remove';
		remove.setAttribute('data-block-delete', '');
		remove.setAttribute('aria-label', 'Удалить блок');
		remove.title = 'Удалить блок';
		remove.innerHTML = '&times;';
		wrap.appendChild(remove);

		return wrap;
	}

	function renderBody(body, html) {
		body.innerHTML = '';
		splitBlocksHtml(html).forEach(function (block, index) {
			body.appendChild(buildBlock(block.html, index));
		});
		highlight(body);
	}

	// Разметка должна совпадать с kv04DiaryRenderItems() в этом же файле:
	// одна и та же заметка приходит либо с сервера при загрузке страницы,
	// либо отсюда после сохранения.
	function createNoteElement(item) {
		var note = document.createElement('article');
		note.className = 'kv04-note';
		note.setAttribute('data-id', String(item.id));

		if (item.text) {
			var body = document.createElement('div');
			body.className = 'kv04-note__body';
			renderBody(body, item.text);
			note.appendChild(body);
		}

		var footer = document.createElement('div');
		footer.className = 'kv04-note__footer';
		var time = document.createElement('time');
		time.textContent = item.date || '';
		footer.appendChild(time);

		note.appendChild(footer);

		// renderNoteMedia ищет .kv04-note__footer, поэтому только после append.
		renderNoteMedia(note, item.media || []);

		var shareNoteBtn = document.createElement('button');
		shareNoteBtn.type = 'button';
		shareNoteBtn.className = 'kv04-note__share';
		shareNoteBtn.setAttribute('data-share-note', '');
		shareNoteBtn.setAttribute('aria-label', 'Поделиться заметкой');
		shareNoteBtn.title = 'Поделиться';
		shareNoteBtn.innerHTML = '&#8599;';
		note.appendChild(shareNoteBtn);

		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'kv04-note__remove';
		remove.setAttribute('data-delete', '');
		remove.setAttribute('aria-label', 'Удалить заметку');
		remove.title = 'Удалить заметку';
		remove.innerHTML = '&times;';
		note.appendChild(remove);

		return note;
	}

	// Новая заметка показывается одинаково, кто бы её ни завёл — отправка
	// композером или съёмка. Сервер возвращает готовый элемент, поэтому
	// ленту не перечитываем и страницу не перезагружаем.
	function insertNewNote(item) {
		var note = createNoteElement(item);
		list.insertBefore(note, list.firstChild);
		highlight(note);
		return note;
	}

	function submitComposer() {
		setError('');
		if (composerSubmit) composerSubmit.disabled = true;
		post(buildComposerFormData(), true).then(function (data) {
			if (composerSubmit) composerSubmit.disabled = false;
			if (!data.ok) { setError(data.error || 'Ошибка'); return; }
			input.innerHTML = '';
			syncPlaceholder();
			clearComposerFiles();
			if (data.item) insertNewNote(data.item);
		}).catch(function () {
			if (composerSubmit) composerSubmit.disabled = false;
			setError('Нет связи');
		});
	}

	composer.addEventListener('submit', function (e) {
		e.preventDefault();
		submitComposer();
	});

	// --- Съёмка ------------------------------------------------------------
	//
	// Кнопка «Фото» открывает камеру и отправляет снимок сразу, без превью и
	// без подтверждения: момент, ради которого достали телефон, проходит
	// быстрее, чем пять шагов через «Файл». Передумать можно после — полоской
	// «Отменить», она же живёт у удаления.

	// Столько же принимает сервер (NoteService::MAX_IMAGE) и такие расширения
	// он пропускает (IMAGE_EXT). Клиент обязан уложиться в оба ограничения:
	// иначе файл молча отбросится и в ответ придёт «Пустая заметка».
	var SHOT_MAX_BYTES = 8388608;
	var SHOT_MAX_SIDE = 2560;
	var SHOT_SAFE_EXT = /\.(jpe?g|png|gif|webp)$/i;

	var shotLabel = composer.querySelector('[data-shot]');
	var shotInput = shotLabel ? shotLabel.querySelector('input[type=file]') : null;
	var shotStatus = composer.querySelector('[data-shot-status]');

	function setShotStatus(text, isError) {
		if (!shotStatus) return;
		shotStatus.textContent = text || '';
		shotStatus.classList.toggle('is-error', !!isError);
		shotStatus.hidden = !text;
	}

	function setBusy(label, busy) {
		if (label) label.classList.toggle('is-busy', !!busy);
	}

	// Снимок с телефона бывает тяжелее серверного потолка, а iPhone изредка
	// отдаёт heic, которого сервер не принимает. И то и другое лечится одним
	// приёмом — перерисовать в canvas и отдать jpeg. Снимок полегче и в
	// понятном формате не трогаем: пусть лежит как снят.
	function needsShrink(file) {
		return file.size > SHOT_MAX_BYTES || !SHOT_SAFE_EXT.test(file.name || '');
	}

	function drawToJpeg(source, width, height, prefix) {
		var scale = Math.min(1, SHOT_MAX_SIDE / Math.max(width, height));
		var canvas = document.createElement('canvas');
		canvas.width = Math.max(1, Math.round(width * scale));
		canvas.height = Math.max(1, Math.round(height * scale));
		canvas.getContext('2d').drawImage(source, 0, 0, canvas.width, canvas.height);

		return new Promise(function (resolve, reject) {
			canvas.toBlob(function (blob) {
				if (!blob) { reject(); return; }
				resolve(new File([blob], (prefix || 'photo') + '-' + Date.now() + '.jpg', { type: 'image/jpeg' }));
			}, 'image/jpeg', 0.85);
		});
	}

	function shrinkViaImage(file) {
		return new Promise(function (resolve, reject) {
			var url = URL.createObjectURL(file);
			var img = new Image();
			img.onload = function () {
				drawToJpeg(img, img.naturalWidth, img.naturalHeight).then(function (out) {
					URL.revokeObjectURL(url);
					resolve(out);
				}, function () { URL.revokeObjectURL(url); reject(); });
			};
			img.onerror = function () { URL.revokeObjectURL(url); reject(); };
			img.src = url;
		});
	}

	function shrinkShot(file) {
		if (!window.createImageBitmap) return shrinkViaImage(file);

		// Ориентацию из EXIF применяет сам декодер — иначе снятое боком легло бы
		// боком и в дневник. Старые браузеры такой параметр не знают, поэтому
		// второй заход без него, а третий — вовсе через <img>.
		return Promise.resolve()
			.then(function () { return createImageBitmap(file, { imageOrientation: 'from-image' }); })
			.catch(function () { return createImageBitmap(file); })
			.then(function (bitmap) { return drawToJpeg(bitmap, bitmap.width, bitmap.height); })
			.catch(function () { return shrinkViaImage(file); });
	}

	function undoShot(id) {
		clipDrop(id).catch(function () {});
		return post({ action: 'delete', id: id }).then(function (data) {
			if (!data.ok) return;
			var node = list.querySelector('.kv04-note[data-id="' + id + '"]');
			if (node) node.remove();
		});
	}

	// Общая дорога для съёмки: подготовить файл в браузере, отправить, показать
	// заметку, дать полоску возврата. Фото и видео расходятся только в том, что
	// готовится, и в надписях.
	function sendCapture(prepare, ui) {
		setError('');
		setShotStatus(ui.working, false);
		setBusy(ui.busy, true);

		return prepare.catch(function () { return null; }).then(function (ready) {
			if (!ready) {
				setBusy(ui.busy, false);
				setShotStatus(ui.failed, true);
				return;
			}

			setShotStatus(ui.sending || ui.working, false);

			var body = new FormData();
			body.append('action', 'add');
			body.append('text', typeof ui.text === 'function' ? ui.text() : (ui.text || ''));
			body.append('media[]', ready);

			return post(body, true).then(function (data) {
				setBusy(ui.busy, false);
				if (!data.ok) { setShotStatus(data.error || 'Ошибка', true); return; }
				setShotStatus('', false);
				if (!data.item) return;
				insertNewNote(data.item);
				if (ui.after) ui.after(data.item);
				var id = data.item.id;
				// Подтверждения перед съёмкой нет намеренно, поэтому возврат даём
				// после: случайный кадр убирается одним нажатием, не целясь в
				// крестик. Сама заметка при этом уходит в корзину, как обычно.
				showUndo(ui.saved, 0, function () { return undoShot(id); }, 'Отменить');
			}).catch(function () {
				setBusy(ui.busy, false);
				setShotStatus('Нет связи', true);
			});
		});
	}

	function sendShot(file) {
		return sendCapture(needsShrink(file) ? shrinkShot(file) : Promise.resolve(file), {
			busy: shotLabel,
			working: 'Отправляю фото…',
			saved: 'Фото сохранено',
			failed: 'Не удалось обработать снимок'
		});
	}

	if (shotInput) {
		shotInput.addEventListener('change', function () {
			var file = shotInput.files && shotInput.files[0];
			// Значение сбрасываем сразу: иначе второй кадр с тем же именем не
			// вызовет change, и кнопка покажется сломанной.
			shotInput.value = '';
			if (file) sendShot(file);
		});
	}

	// --- Видео -------------------------------------------------------------
	//
	// Ролик с телефона весит десятки мегабайт, и на сервер он не уезжает. В
	// дневник ложится кадр и приметы записи — имя файла, длина, вес, дата, —
	// чтобы её было по чему узнать. Сам ролик остаётся на телефоне, в памяти
	// браузера: по клику играет, кнопкой сохраняется в «Загрузки», откуда его
	// видно в галерее и файловом менеджере.
	//
	// Ссылку прямо на файл в галерее дать нельзя, и это не недоделка: браузер
	// получает от камеры файл без пути, ссылки file:// со страницы по https
	// заблокированы, доступа к галерее у страницы нет вовсе. Поэтому «в
	// галерею» здесь — это скачивание из памяти браузера, а не ссылка на файл.

	var CLIP_DB = 'kv04-diary-clips';
	var CLIP_STORE = 'clips';
	var clipDbPromise = null;
	var clipObjectUrl = null;

	var clipLabel = composer.querySelector('[data-clip]');
	var clipInput = clipLabel ? clipLabel.querySelector('input[type=file]') : null;

	function clipDb() {
		if (clipDbPromise) return clipDbPromise;
		clipDbPromise = new Promise(function (resolve, reject) {
			if (!window.indexedDB) { reject(); return; }
			var req = indexedDB.open(CLIP_DB, 1);
			req.onupgradeneeded = function () {
				var db = req.result;
				if (!db.objectStoreNames.contains(CLIP_STORE)) {
					db.createObjectStore(CLIP_STORE, { keyPath: 'id' });
				}
			};
			req.onsuccess = function () { resolve(req.result); };
			req.onerror = function () { reject(); };
		});
		return clipDbPromise;
	}

	function clipTx(mode, run) {
		return clipDb().then(function (db) {
			return new Promise(function (resolve, reject) {
				var req = run(db.transaction(CLIP_STORE, mode).objectStore(CLIP_STORE));
				req.onsuccess = function () { resolve(req.result); };
				req.onerror = function () { reject(); };
			});
		});
	}

	function clipPut(record) { return clipTx('readwrite', function (store) { return store.put(record); }); }
	function clipGet(id) { return clipTx('readonly', function (store) { return store.get(Number(id)); }); }
	function clipDrop(id) { return clipTx('readwrite', function (store) { return store['delete'](Number(id)); }); }
	function clipKeys() { return clipTx('readonly', function (store) { return store.getAllKeys(); }); }

	function clipDuration(seconds) {
		var total = Math.max(1, Math.round(seconds || 0));
		var ss = total % 60;
		return Math.floor(total / 60) + ':' + (ss < 10 ? '0' : '') + ss;
	}

	function clipSize(bytes) {
		var mb = (bytes || 0) / 1048576;
		return (mb >= 10 ? Math.round(mb) : Math.round(mb * 10) / 10) + ' МБ';
	}

	// Приметы ложатся текстом заметки, а не рядом с ней: текст виден на любом
	// устройстве и переживает любую чистку памяти браузера. По имени файла
	// ролик находится в галерее телефона руками.
	function clipCaption(file, duration) {
		var at = new Date(file.lastModified || Date.now());
		var pad = function (n) { return (n < 10 ? '0' : '') + n; };
		return [
			file.name || 'видео',
			clipDuration(duration),
			clipSize(file.size),
			pad(at.getDate()) + '.' + pad(at.getMonth() + 1) + '.' + at.getFullYear() + ' ' + pad(at.getHours()) + ':' + pad(at.getMinutes())
		].join(' · ');
	}

	// Видео нужно живое: часть браузеров не декодирует элемент вне документа.
	// Прячем за краем экрана и убираем, как только кадр взят.
	function loadVideoFile(file) {
		return new Promise(function (resolve, reject) {
			var video = document.createElement('video');
			video.muted = true;
			video.playsInline = true;
			video.setAttribute('playsinline', '');
			video.preload = 'auto';
			video.style.cssText = 'position:fixed;left:-9999px;top:0;width:2px;height:2px;opacity:0;';
			video.src = URL.createObjectURL(file);
			video.onloadeddata = function () { resolve(video); };
			video.onerror = function () { reject(); };
			document.body.appendChild(video);
		});
	}

	function dropVideo(video) {
		try { video.pause(); } catch (e) {}
		URL.revokeObjectURL(video.src);
		if (video.parentNode) video.parentNode.removeChild(video);
	}

	function clipFrame(video) {
		return new Promise(function (resolve, reject) {
			function grab() {
				video.onseeked = null;
				drawToJpeg(video, video.videoWidth, video.videoHeight, 'clip').then(resolve, reject);
			}
			video.onseeked = grab;
			try {
				video.currentTime = Math.min(1, (video.duration || 2) / 2);
			} catch (e) { grab(); }
		});
	}

	function prepareClip(file) {
		return loadVideoFile(file).then(function (video) {
			var duration = video.duration || 0;
			return clipFrame(video).then(function (frame) {
				dropVideo(video);
				return { frame: frame, duration: duration };
			}, function (err) {
				dropVideo(video);
				throw err;
			});
		});
	}

	function storeClip(item, file, info) {
		// Просим систему беречь хранилище: кроме галереи телефона другой копии
		// ролика нет, и тихая чистка была бы потерей.
		if (navigator.storage && navigator.storage.persist) {
			try { navigator.storage.persist(); } catch (e) {}
		}

		return clipPut({
			id: Number(item.id),
			blob: file,
			name: file.name || 'video.mp4',
			size: file.size,
			duration: info ? info.duration : 0,
			at: file.lastModified || Date.now()
		}).then(function () {
			markClipNote(item.id);
		}, function () {
			setShotStatus('Видео не поместилось в память браузера — в дневнике остался кадр', true);
		});
	}

	// Заметка, чей ролик лежит на этом телефоне, показывает поверх кадра
	// треугольник: клик по ней играет видео, а не открывает картинку.
	function markClipNote(id) {
		var note = list.querySelector('.kv04-note[data-id="' + id + '"]');
		if (!note) return;
		note.setAttribute('data-clip-id', String(id));
		var thumb = note.querySelector('.kv04-media-thumb');
		if (thumb && !thumb.querySelector('.kv04-media-thumb__play')) {
			var play = document.createElement('span');
			play.className = 'kv04-media-thumb__play';
			play.setAttribute('aria-hidden', 'true');
			thumb.appendChild(play);
		}
	}

	function markStoredClips() {
		clipKeys().then(function (keys) {
			(keys || []).forEach(markClipNote);
		}).catch(function () {});
	}

	function openStoredClip(id, trigger) {
		clipGet(id).then(function (record) {
			if (!record || !record.blob) {
				setShotStatus('Ролика нет в памяти этого браузера — остались кадр и приметы', true);
				return;
			}

			clipObjectUrl = URL.createObjectURL(record.blob);
			openLightbox('video', clipObjectUrl, trigger);
			if (!lightboxStage) return;

			// Скачивание кладёт файл в «Загрузки» — оттуда его видно галерее и
			// файловому менеджеру. Прямее в галерею со страницы не попасть.
			var save = document.createElement('a');
			save.className = 'kv04-btn kv04-btn--muted kv04-btn--sm kv04-lightbox__save';
			save.textContent = 'Сохранить в телефон';
			save.href = clipObjectUrl;
			save.download = record.name || 'video.mp4';
			lightboxStage.appendChild(save);
		}).catch(function () {
			setShotStatus('Не добраться до памяти браузера', true);
		});
	}

	// Слушатель стоит раньше просмотрщика и, когда ролик есть, обрывает
	// дальнейшую обработку: иначе следом открылась бы картинка-кадр.
	root.addEventListener('click', function (e) {
		var thumb = e.target.closest('.kv04-media-thumb');
		if (!thumb || !root.contains(thumb)) return;
		var note = thumb.closest('.kv04-note[data-clip-id]');
		if (!note) return;
		e.preventDefault();
		e.stopImmediatePropagation();
		openStoredClip(note.getAttribute('data-clip-id'), thumb);
	});

	function sendClip(file) {
		var prepared = null;
		var prepare = prepareClip(file).then(function (info) {
			prepared = info;
			return info.frame;
		});

		return sendCapture(prepare, {
			busy: clipLabel,
			working: 'Готовлю кадр…',
			sending: 'Отправляю кадр…',
			saved: 'Кадр в дневнике, видео на телефоне',
			failed: 'Не удалось обработать запись',
			text: function () { return prepared ? clipCaption(file, prepared.duration) : ''; },
			after: function (item) { return storeClip(item, file, prepared); }
		});
	}

	if (clipInput) {
		clipInput.addEventListener('change', function () {
			var file = clipInput.files && clipInput.files[0];
			clipInput.value = '';
			if (file) sendClip(file);
		});
	}

	markStoredClips();

	// --- Поделиться --------------------------------------------------------
	//
	// Заметка, блок и файл ссылок не заводят вовсе: вызывается системное меню
	// телефона, и артефакт уходит в выбранное приложение как есть. Это и
	// честнее — дневник не начинает раздавать наружу адреса, — и ближе к тому,
	// как обмен устроен на самом телефоне.
	//
	// Ссылка есть только у дневника целиком: она живая и работает до отзыва.
	//
	// Там, где системного меню нет (десктоп), текст уходит в буфер обмена, а
	// файл открывается соседней вкладкой: копировать картинку в буфер умеют
	// не все браузеры, а показать её — все.

	function canShareFiles(files) {
		return !!(navigator.canShare && files.length && navigator.canShare({ files: files }));
	}

	function copyOut(text) {
		if (!text || !navigator.clipboard || !navigator.clipboard.writeText) {
			setShotStatus('Здесь нечем поделиться', true);
			return Promise.resolve(false);
		}

		return navigator.clipboard.writeText(text).then(function () {
			setShotStatus('Скопировано', false);
			return true;
		}, function () {
			setShotStatus('Не удалось скопировать', true);
			return false;
		});
	}

	function shareOut(payload, fallbackText) {
		if (!navigator.share) return copyOut(fallbackText);

		return navigator.share(payload).then(function () {
			setShotStatus('', false);
			return true;
		}, function (err) {
			// Человек закрыл системное меню — это не ошибка, и говорить об
			// этом не надо.
			if (err && err.name === 'AbortError') { setShotStatus('', false); return true; }
			return copyOut(fallbackText);
		});
	}

	// Текст узла без кнопок: крестик и стрелка живут внутри блока, и без
	// такой прополки они уехали бы в отправленное сообщение.
	function plainText(node) {
		if (!node) return '';
		var parts = [];
		Array.prototype.forEach.call(node.childNodes, function (child) {
			if (child.nodeType === 1 && child.tagName === 'BUTTON') return;
			parts.push(child.nodeType === 1 ? (child.innerText || child.textContent || '') : (child.textContent || ''));
		});

		return parts.join('').replace(/\n{3,}/g, '\n\n').trim();
	}

	function noteText(note) {
		var body = note ? note.querySelector('.kv04-note__body') : null;
		if (!body) return '';
		var blocks = body.querySelectorAll('.kv04-note__block');
		if (!blocks.length) return plainText(body);

		var parts = [];
		Array.prototype.forEach.call(blocks, function (block) {
			var text = plainText(block);
			if (text) parts.push(text);
		});

		return parts.join('\n\n');
	}

	function fileFromThumb(thumb) {
		var src = thumb.getAttribute('data-src');
		if (!src) return Promise.reject();

		return fetch(src).then(function (r) { return r.blob(); }).then(function (blob) {
			var name = src.split('/').pop().split('?')[0] || 'file';
			return new File([blob], name, { type: blob.type || 'application/octet-stream' });
		});
	}

	function shareMedia(thumb) {
		if (!thumb) return;
		var src = thumb.getAttribute('data-src');
		var url = location.origin + src;
		setShotStatus('Готовлю файл…', false);

		fileFromThumb(thumb).then(function (file) {
			if (canShareFiles([file])) return shareOut({ files: [file] }, url);
			// Браузер не умеет отдавать файл в системное меню — отдаём адрес.
			return copyOut(url);
		}).catch(function () {
			setShotStatus('Не удалось забрать файл', true);
		});
	}

	function shareNote(note) {
		if (!note) return;
		var text = noteText(note);
		var thumbs = note.querySelectorAll('.kv04-media-thumb');

		if (!thumbs.length) {
			if (!text) { setShotStatus('В этой заметке нечем поделиться', true); return; }
			shareOut({ text: text }, text);
			return;
		}

		setShotStatus('Готовлю файлы…', false);
		Promise.all(Array.prototype.map.call(thumbs, fileFromThumb)).then(function (files) {
			var payload = canShareFiles(files) ? { files: files } : {};
			if (text) payload.text = text;
			if (!payload.files && !payload.text) { setShotStatus('В этой заметке нечем поделиться', true); return; }
			return shareOut(payload, text || (location.origin + thumbs[0].getAttribute('data-src')));
		}).catch(function () {
			// Файлы не дались — уходит хотя бы текст.
			if (text) shareOut({ text: text }, text);
			else setShotStatus('Не удалось забрать файлы', true);
		});
	}

	root.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-share-note], [data-share-block], [data-share-media]');
		if (!btn || !root.contains(btn)) return;
		e.preventDefault();
		e.stopPropagation();

		if (btn.hasAttribute('data-share-media')) {
			var item = btn.closest('.kv04-media-item');
			shareMedia(item ? item.querySelector('.kv04-media-thumb') : null);
			return;
		}

		if (btn.hasAttribute('data-share-block')) {
			var text = plainText(btn.closest('.kv04-note__block'));
			if (!text) { setShotStatus('В этом блоке нечем поделиться', true); return; }
			shareOut({ text: text }, text);
			return;
		}

		shareNote(btn.closest('.kv04-note'));
	});

	// --- Ссылка на дневник --------------------------------------------------

	var shareBox = root.querySelector('[data-share]');
	var shareUrlField = shareBox ? shareBox.querySelector('[data-share-url]') : null;
	var shareOpen = root.querySelector('[data-share-open]');
	var shareUrl = '<?=CUtil::JSEscape((string)($arResult['SHARE_URL'] ?? ''))?>';

	function showShareBox(url) {
		shareUrl = url || '';
		if (!shareBox || !shareUrlField) return;
		shareUrlField.value = shareUrl;
		shareBox.hidden = false;
		shareUrlField.focus();
		shareUrlField.select();
	}

	if (shareOpen) {
		shareOpen.addEventListener('click', function () {
			// Ссылка на дневник одна: если она уже есть, показываем ту же, а не
			// заводим вторую. Отозванная не воскресает — будет новая.
			if (shareUrl) { showShareBox(shareUrl); return; }

			setShotStatus('Готовлю ссылку…', false);
			post({ action: 'share_book' }).then(function (data) {
				if (!data.ok) { setShotStatus(data.error || 'Ошибка', true); return; }
				setShotStatus('', false);
				showShareBox(data.url);
			}).catch(function () { setShotStatus('Нет связи', true); });
		});
	}

	if (shareBox) {
		shareBox.addEventListener('click', function (e) {
			if (e.target.closest('[data-share-close]')) { shareBox.hidden = true; return; }

			if (e.target.closest('[data-share-copy]')) { copyOut(shareUrl); return; }

			if (e.target.closest('[data-share-send]')) {
				var title = currentTitle ? currentTitle.textContent : 'Дневник';
				shareOut({ title: title, url: shareUrl }, shareUrl);
				return;
			}

			if (e.target.closest('[data-share-revoke]')) {
				post({ action: 'share_revoke' }).then(function (data) {
					if (!data.ok) { setShotStatus(data.error || 'Ошибка', true); return; }
					shareUrl = '';
					shareBox.hidden = true;
					setShotStatus('Доступ закрыт. Прежняя ссылка больше не откроется', false);
				}).catch(function () { setShotStatus('Нет связи', true); });
			}
		});
	}

	bindEditor(input, {
		onSave: submitComposer,
		onImages: function (files) { setPendingFiles(files, true); }
	});

	// Подсказку показываем по классу, а не по :empty: браузер сам кладёт <br>
	// в пустой contenteditable, и селектор перестаёт срабатывать.
	function syncPlaceholder() {
		var empty = (input.textContent || '').trim() === '' && !input.querySelector('pre, img, video');
		input.classList.toggle('is-empty', empty);
	}
	input.addEventListener('input', syncPlaceholder);
	syncPlaceholder();

	root.querySelector('[data-logout]').addEventListener('click', function () {
		post({ action: 'logout' }).then(function () { location.reload(); });
	});

	var confirmEl = document.getElementById('kv04-confirm');
	var confirmActive = false;
	var confirmFinish = null;

	function showSaveConfirm() {
		if (!confirmEl || confirmActive) {
			return Promise.resolve(false);
		}
		return new Promise(function (resolve) {
			confirmActive = true;
			confirmFinish = resolve;
			confirmEl.hidden = false;
			confirmEl.classList.add('is-open');
			confirmEl.setAttribute('aria-hidden', 'false');
			var yesBtn = confirmEl.querySelector('[data-confirm-yes]');
			if (yesBtn) yesBtn.focus();
		});
	}

	function closeSaveConfirm(result) {
		if (!confirmActive || !confirmFinish) return;
		var finish = confirmFinish;
		confirmActive = false;
		confirmFinish = null;
		confirmEl.classList.remove('is-open');
		confirmEl.setAttribute('aria-hidden', 'true');
		confirmEl.hidden = true;
		finish(result);
	}

	if (confirmEl) {
		if (confirmEl.parentNode !== document.body) {
			document.body.appendChild(confirmEl);
		}
		confirmEl.querySelector('[data-confirm-yes]').addEventListener('click', function () {
			closeSaveConfirm(true);
		});
		confirmEl.querySelector('[data-confirm-no]').addEventListener('click', function () {
			closeSaveConfirm(false);
		});
	}

	function closeEdit(note, html, hasBody) {
		if (note._editCtx && note._editCtx.onKeydown) {
			note.removeEventListener('keydown', note._editCtx.onKeydown);
		}
		var body = note.querySelector('.kv04-note__body');
		if (!body) return;
		var bar = note.querySelector('.kv04-edit-bar');
		if (bar) bar.remove();
		body.removeAttribute('contenteditable');
		if (hasBody || html) {
			renderBody(body, html);
		} else {
			body.remove();
		}
		note.classList.remove('is-editing');
		delete note._editCtx;
	}

	function promptSaveOnExit(note) {
		var ctx = note._editCtx;
		if (!ctx) return;
		showSaveConfirm().then(function (save) {
			if (save) {
				ctx.doSave();
			} else {
				closeEdit(note, ctx.originalHtml, ctx.hadBody);
			}
		});
	}

	function isNoteEditClick(e, note) {
		if (note.classList.contains('is-editing')) return false;
		if (e.target.closest('.kv04-media-thumb, .kv04-media-item__remove, .kv04-note__media, [data-delete], [data-media-delete], [data-block-delete], [data-share-note], [data-share-block], [data-share-media], .kv04-edit-bar, a[href]')) {
			return false;
		}
		return e.target.closest('.kv04-note') === note;
	}

	function renderNoteMedia(note, media) {
		var existing = note.querySelector('.kv04-note__media');
		if (existing) existing.remove();
		if (!media || !media.length) return;

		var mediaCount = media.length;
		var gridCount = Math.min(Math.max(mediaCount, 1), 4);
		var extraCount = mediaCount > 4 ? mediaCount - 3 : 0;
		var grid = document.createElement('div');
		grid.className = 'kv04-note__media kv04-media-grid kv04-media-grid--' + gridCount;
		if (mediaCount > 4) grid.classList.add('kv04-media-grid--more');

		media.forEach(function (file, index) {
			if (mediaCount > 4 && index > 3) return;
			var isOverflow = mediaCount > 4 && index >= 3;
			var item = document.createElement('div');
			item.className = 'kv04-media-item';

			var thumb = document.createElement('button');
			thumb.type = 'button';
			thumb.className = 'kv04-media-thumb' + (file.is_video ? ' kv04-media-thumb--video' : '') + (isOverflow ? ' kv04-media-thumb--more' : '');
			thumb.setAttribute('data-lightbox', file.is_video ? 'video' : 'image');
			thumb.setAttribute('data-src', file.src);
			thumb.setAttribute('data-file-id', String(file.id));
			thumb.setAttribute('aria-label', file.is_video ? 'Открыть видео' : 'Открыть изображение');

			if (file.is_image) {
				var img = document.createElement('img');
				img.src = file.src;
				img.alt = '';
				img.loading = 'lazy';
				img.decoding = 'async';
				thumb.appendChild(img);
			} else if (file.is_video) {
				var video = document.createElement('video');
				// #t=0.1 — чтобы Safari показал кадр, а не чёрный прямоугольник:
				// без метки времени он не декодирует первый кадр до воспроизведения.
				video.src = file.src + '#t=0.1';
				video.muted = true;
				video.playsInline = true;
				video.preload = 'metadata';
				thumb.appendChild(video);
				var play = document.createElement('span');
				play.className = 'kv04-media-thumb__play';
				play.setAttribute('aria-hidden', 'true');
				thumb.appendChild(play);
			}

			if (isOverflow) {
				var count = document.createElement('span');
				count.className = 'kv04-media-thumb__count';
				count.textContent = '+' + extraCount;
				thumb.appendChild(count);
			}

			item.appendChild(thumb);
			if (!isOverflow) {
				var shareFile = document.createElement('button');
				shareFile.type = 'button';
				shareFile.className = 'kv04-media-item__share';
				shareFile.setAttribute('data-share-media', '');
				shareFile.setAttribute('aria-label', 'Поделиться файлом');
				shareFile.title = 'Поделиться';
				shareFile.innerHTML = '&#8599;';
				item.appendChild(shareFile);

				var remove = document.createElement('button');
				remove.type = 'button';
				remove.className = 'kv04-media-item__remove';
				remove.setAttribute('data-media-delete', '');
				remove.setAttribute('data-file-id', String(file.id));
				remove.setAttribute('aria-label', 'Удалить файл');
				remove.innerHTML = '&times;';
				item.appendChild(remove);
			}
			grid.appendChild(item);
		});

		var footer = note.querySelector('.kv04-note__footer');
		note.insertBefore(grid, footer);
	}

	function setEditAttachStatus(bar, msg, isError) {
		var status = bar.querySelector('.kv04-edit-bar__status');
		if (!status) return;
		status.hidden = !msg;
		status.textContent = msg || '';
		status.classList.toggle('is-error', !!isError);
	}

	function uploadAttach(note, fileList, bar) {
		var id = note.getAttribute('data-id');
		var body = new FormData();
		body.append('action', 'attach');
		body.append('id', id);
		for (var i = 0; i < fileList.length; i++) {
			body.append('media[]', fileList[i]);
		}
		setEditAttachStatus(bar, 'Загрузка…', false);
		bar.classList.add('is-uploading');
		return post(body, true).then(function (data) {
			bar.classList.remove('is-uploading');
			if (!data.ok) {
				setEditAttachStatus(bar, data.error || 'Ошибка', true);
				return;
			}
			renderNoteMedia(note, data.media || []);
			setEditAttachStatus(bar, '', false);
			// Файл прикреплён — курсор возвращается в текст, чтобы можно было
			// дописывать и сохранять с клавиатуры, не целясь мышью в «Готово».
			var editable = note.querySelector('.kv04-note__body[contenteditable="true"]');
			if (editable) editable.focus();
		}).catch(function () {
			bar.classList.remove('is-uploading');
			setEditAttachStatus(bar, 'Нет связи', true);
		});
	}

	function startEdit(note) {
		if (note.classList.contains('is-editing')) return;
		var id = note.getAttribute('data-id');
		var body = note.querySelector('.kv04-note__body');
		var hadBody = !!body;
		if (!body) {
			body = document.createElement('div');
			body.className = 'kv04-note__body';
			var anchor = note.querySelector('.kv04-note__media') || note.querySelector('.kv04-note__footer');
			note.insertBefore(body, anchor);
		}
		// На время правки крестики блоков убираем: они часть показа, а не текста.
		body.querySelectorAll('[data-block-delete]').forEach(function (btn) { btn.remove(); });
		// Правим ровно тот узел, который читали: ни разметка, ни стили не
		// подменяются, поэтому отступы и блоки кода выглядят как в ленте.
		var current = serializeForSave(body);
		body.setAttribute('contenteditable', 'true');
		note.classList.add('is-editing');

		var bar = document.createElement('div');
		bar.className = 'kv04-edit-bar';
		bar.innerHTML =
			'<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-edit-code>Код</button>' +
			// Те же классы и та же подпись, что и в композере: кнопка делает
			// одно и то же, выглядеть по-разному ей незачем.
			'<label class="kv04-btn kv04-btn--muted kv04-btn--sm">' +
				'Файл' +
				'<input type="file" accept="image/*,video/mp4,video/webm" multiple hidden>' +
			'</label>' +
			'<span class="kv04-edit-bar__status" hidden></span>' +
			'<button type="button" class="kv04-btn kv04-btn--primary kv04-btn--sm" data-edit-save>Готово</button>';
		body.after(bar);

		var save = bar.querySelector('[data-edit-save]');
		var attachInput = bar.querySelector('input[type=file]');
		var editCode = bar.querySelector('[data-edit-code]');
		save.title = 'Готово (' + saveShortcutLabel + ')';

		function doSave() {
			// Сохранение уже идёт: хоткей и клик по «Готово» не должны отправить
			// правку дважды.
			if (save.disabled) return;
			save.disabled = true;
			var html = serializeForSave(body);
			post({ action: 'edit', id: id, text: html }).then(function (data) {
				save.disabled = false;
				if (!data.ok) { alert(data.error || 'Ошибка'); return; }
				// Сервер вернул санитизированный HTML — ставим его вместо
				// набранного. Раньше здесь была перезагрузка всей страницы.
				var saved = typeof data.text === 'string' ? data.text : html;
				closeEdit(note, saved, saved !== '');
			}).catch(function () {
				save.disabled = false;
				alert('Нет связи');
			});
		}

		// Хоткей «Готово» слушает всю заметку, а не только её текст. Раньше он
		// висел на редактируемом теле, и стоило фокусу уйти на «Файл» или «Код»,
		// как Ctrl+Enter ловить становилось некому. Заметнее всего это было там,
		// куда только что прикрепили картинку: фокус оставался на кнопке файла.
		function onNoteKeydown(e) {
			if (!(e.ctrlKey || e.metaKey) || e.key !== 'Enter') return;
			e.preventDefault();
			doSave();
		}
		note.addEventListener('keydown', onNoteKeydown);

		note._editCtx = { originalHtml: current, hadBody: hadBody, doSave: doSave, onKeydown: onNoteKeydown };
		save.addEventListener('click', doSave);

		editCode.addEventListener('click', function () {
			toggleCodeBlock(body);
			editCode.textContent = inCodeBlock(body) ? 'Текст' : 'Код';
		});

		attachInput.addEventListener('change', function () {
			if (!attachInput.files || !attachInput.files.length) return;
			uploadAttach(note, attachInput.files, bar).then(function () {
				attachInput.value = '';
			});
		});

		bindEditor(body, {
			onSave: doSave,
			onImages: function (files) { uploadAttach(note, files, bar); }
		});

		body.focus();
		// Каретка в конец, а не в начало — правят обычно хвост заметки.
		var range = document.createRange();
		range.selectNodeContents(body);
		range.collapse(false);
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
	}

	list.addEventListener('click', function (e) {
		var note = e.target.closest('.kv04-note');
		if (!note) return;
		var id = note.getAttribute('data-id');
		if (e.target.closest('[data-media-delete]')) {
			e.preventDefault();
			e.stopPropagation();
			var removeBtn = e.target.closest('[data-media-delete]');
			var fileId = removeBtn.getAttribute('data-file-id');
			if (!fileId) return;
			// Без подтверждения: файл уходит в корзину и возвращается оттуда.
			post({ action: 'detach_media', id: id, file_id: fileId }).then(function (data) {
				if (!data.ok) { alert(data.error || 'Ошибка'); return; }
				renderNoteMedia(note, data.media || []);
				if (data.trash_id) {
					showUndo('Файл в корзине', data.trash_days || 7, function () {
						return restoreFragment(data.trash_id);
					});
				}
				if (trashBox && !trashBox.hidden) openTrash();
			}).catch(function () { alert('Нет связи'); });
			return;
		}
		if (e.target.closest('[data-block-delete]')) {
			e.preventDefault();
			e.stopPropagation();
			var block = e.target.closest('.kv04-note__block');
			if (!block) return;
			var index = parseInt(block.getAttribute('data-block'), 10);
			post({ action: 'delete_block', id: id, block: index }).then(function (data) {
				if (!data.ok) { alert(data.error || 'Ошибка'); return; }

				if (data.note_deleted) {
					// Текста не осталось и медиа не было — ушла вся заметка.
					note.remove();
					showUndo('Заметка в корзине', data.trash_days || 7, function () {
						return restoreNote(id);
					});
				} else {
					var body = note.querySelector('.kv04-note__body');
					if (data.text === '') {
						// Текст кончился, но остались файлы: заметка из одних
						// медиа — обычная заметка, убирать её из ленты нельзя.
						if (body) body.remove();
					} else {
						// Номера блоков после удаления сдвигаются, поэтому тело
						// перерисовываем целиком, а не убираем один узел.
						renderBody(body, data.text);
					}
					showUndo('Блок в корзине', data.trash_days || 7, function () {
						return restoreFragment(data.trash_id);
					});
				}

				if (trashBox && !trashBox.hidden) openTrash();
			}).catch(function () { alert('Нет связи'); });
			return;
		}
		if (e.target.closest('[data-delete]')) {
			// Без подтверждения: удаление обратимо, заметка уходит в корзину.
			// Вместо вопроса — строка с возвратом сразу после действия.
			post({ action: 'delete', id: id }).then(function (data) {
				if (!data.ok) { alert(data.error || 'Ошибка'); return; }
				note.remove();
				showUndo('Заметка в корзине', data.trash_days || 7, function () {
					return restoreNote(id);
				});
				// Корзина открыта — показываем в ней свежеудалённое сразу.
				if (trashBox && !trashBox.hidden) openTrash();
			}).catch(function () { alert('Нет связи'); });
			return;
		}
		if (isNoteEditClick(e, note)) {
			startEdit(note);
		}
	});

	// --- Дневники ----------------------------------------------------------
	//
	// Под одним пином живёт до пятидесяти дневников. Плитки слева на широком
	// экране стоят постоянно, на телефоне выезжают поверх ленты: узкий экран
	// нужен ленте целиком, там код и картинки.

	var workspace = document.getElementById('kv04-workspace');
	var booksPanel = root.querySelector('[data-books]') || document.querySelector('[data-books]');
	var booksList = document.querySelector('[data-books-list]');
	var booksBackdrop = document.querySelector('[data-books-backdrop]');
	var booksAdd = document.querySelector('[data-book-add]');
	var booksLimit = document.querySelector('[data-books-limit]');
	var currentTitle = root.querySelector('[data-current-title]');
	var maxBooks = parseInt(root.getAttribute('data-max-books'), 10) || 50;

	function openBooks() {
		if (!workspace) return;
		workspace.classList.add('is-books-open');
	}

	function closeBooks() {
		if (!workspace) return;
		workspace.classList.remove('is-books-open');
	}

	function currentBookId() {
		var tile = booksList && booksList.querySelector('.kv04-book.is-current');
		return tile ? parseInt(tile.getAttribute('data-book'), 10) : 0;
	}

	function syncBooksLimit() {
		if (!booksAdd) return;
		var count = booksList ? booksList.querySelectorAll('.kv04-book').length : 0;
		var full = count >= maxBooks;
		booksAdd.hidden = full;
		if (booksLimit) {
			booksLimit.hidden = !full;
			booksLimit.textContent = full ? 'Больше ' + maxBooks + ' дневников не поместится' : '';
		}
	}

	function buildBookTile(book, isCurrent) {
		var tile = document.createElement('div');
		tile.className = 'kv04-book' + (isCurrent ? ' is-current' : '');
		tile.setAttribute('data-book', String(book.id));

		var open = document.createElement('button');
		open.type = 'button';
		open.className = 'kv04-book__open';
		open.setAttribute('data-book-open', '');
		open.textContent = book.title;
		open.title = isCurrent ? book.title + ' — нажмите, чтобы переименовать' : book.title;
		tile.appendChild(open);

		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'kv04-book__act kv04-book__act--danger';
		remove.setAttribute('data-book-delete', '');
		remove.setAttribute('aria-label', 'Удалить дневник');
		remove.title = 'Удалить дневник';
		remove.innerHTML = '&times;';
		tile.appendChild(remove);

		return tile;
	}

	function renderBooks(books, currentId) {
		if (!booksList) return;
		booksList.innerHTML = '';
		books.forEach(function (book) {
			booksList.appendChild(buildBookTile(book, book.id === currentId));
			if (book.id === currentId && currentTitle) currentTitle.textContent = book.title;
		});
		syncBooksLimit();
	}

	// Правка заголовка на месте: узел подменяется полем, Enter сохраняет,
	// Escape отменяет, потеря фокуса тоже сохраняет. Одинаково работает и на
	// плитке, и в шапке ленты — заголовок правится там, где его видно, как и
	// сама заметка.
	function editInPlace(node, initial, className, onDone) {
		var input = document.createElement('input');
		input.type = 'text';
		input.className = className;
		input.value = initial;
		input.maxLength = 120;
		node.replaceWith(input);
		input.focus();
		input.select();

		var finished = false;
		function finish(save) {
			if (finished) return;
			finished = true;
			input.replaceWith(node);
			onDone(save ? input.value : null);
		}

		input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') { e.preventDefault(); finish(true); }
			else if (e.key === 'Escape') { e.preventDefault(); finish(false); }
		});
		input.addEventListener('blur', function () { finish(true); });

		return input;
	}

	function renameBook(id, labelNode, className) {
		var was = labelNode.textContent;
		var tile = labelNode.closest ? labelNode.closest('.kv04-book') : null;
		if (tile) tile.classList.add('is-editing');

		editInPlace(labelNode, was, className, function (title) {
			if (tile) tile.classList.remove('is-editing');
			if (title === null) return;
			title = title.trim();
			if (title === '' || title === was) return;

			post({ action: 'book_rename', book: id, title: title }).then(function (data) {
				if (!data.ok) { alert(data.error || 'Ошибка'); return; }
				renderBooks(data.books || booksSnapshot(), currentBookId());
			}).catch(function () { alert('Нет связи'); });
		});
	}

	function switchBook(id) {
		if (id === currentBookId()) { closeBooks(); return; }
		post({ action: 'book_switch', book: id }).then(function (data) {
			if (!data.ok) { alert(data.error || 'Ошибка'); return; }
			// Лента приходит готовой — перезагружать страницу ради смены
			// дневника незачем.
			// Ссылка у каждого дневника своя: показывать чужую после
			// переключения было бы прямой ошибкой.
			shareUrl = data.share_url || '';
			if (shareBox) shareBox.hidden = true;
			list.innerHTML = '';
			(data.items || []).forEach(function (item) {
				list.appendChild(createNoteElement(item));
			});
			highlight(list);
			linkify(list);

			booksList.querySelectorAll('.kv04-book').forEach(function (tile) {
				var isCurrent = parseInt(tile.getAttribute('data-book'), 10) === id;
				tile.classList.toggle('is-current', isCurrent);
				if (isCurrent && currentTitle) {
					currentTitle.textContent = tile.querySelector('.kv04-book__open').textContent;
				}
			});
			closeBooks();
		}).catch(function () { alert('Нет связи'); });
	}

	if (booksAdd) {
		booksAdd.addEventListener('click', function () {
			var tile = buildBookTile({ id: 0, title: '' }, false);
			tile.classList.add('is-new', 'is-editing');
			booksList.appendChild(tile);
			editInPlace(tile.querySelector('.kv04-book__open'), '', 'kv04-book__input', function (title) {
				if (title === null || !title.trim()) { tile.remove(); syncBooksLimit(); return; }
				post({ action: 'book_create', title: title }).then(function (data) {
					if (!data.ok) { tile.remove(); syncBooksLimit(); alert(data.error || 'Ошибка'); return; }
					renderBooks(data.books || [], currentBookId());
				}).catch(function () { tile.remove(); syncBooksLimit(); alert('Нет связи'); });
			});
		});
	}

	if (booksList) {
		booksList.addEventListener('click', function (e) {
			var tile = e.target.closest('.kv04-book');
			if (!tile || tile.classList.contains('is-editing')) return;
			var id = parseInt(tile.getAttribute('data-book'), 10);

			if (e.target.closest('[data-book-delete]')) {
				post({ action: 'book_delete', book: id }).then(function (data) {
					if (!data.ok) { alert(data.error || 'Ошибка'); return; }
					renderBooks(data.books || [], data.current || 0);
					if (data.moved) {
						// Подпись честная: сам дневник не вернуть, но его заметки
						// лежат в корзине и достаются оттуда поштучно — каждая
						// ложится в открытый сейчас дневник.
						showUndo('Дневник удалён, заметок в корзине: ' + data.moved, data.trash_days || 7, function () {
							if (trashBox) trashBox.hidden = true;
							openTrash();
							return true;
						}, 'Открыть корзину');
					}
					if (data.current) switchBook(data.current);
				}).catch(function () { alert('Нет связи'); });
				return;
			}

			// Дальше — клик по плитке где угодно, кроме крестика: попадать надо
			// в дневник, а не в буквы его названия. Промежутки между кнопками
			// внутри плитки раньше проваливались.
			//
			// Клик по открытому дневнику переключать нечего — значит правим
			// заголовок. По чужому — переходим в него.
			if (id === currentBookId()) {
				renameBook(id, tile.querySelector('.kv04-book__open'), 'kv04-book__input');
			} else {
				switchBook(id);
			}
		});
	}

	// Заголовок в шапке ленты правится тем же кликом.
	if (currentTitle) {
		currentTitle.addEventListener('click', function () {
			var id = currentBookId();
			if (id > 0) renameBook(id, currentTitle, 'kv04-feed__title-input');
		});
	}

	// Список плиток на момент вызова — чтобы вернуть подпись, если правку
	// заголовка отменили.
	function booksSnapshot() {
		if (!booksList) return [];
		return [].map.call(booksList.querySelectorAll('.kv04-book'), function (tile) {
			var label = tile.querySelector('.kv04-book__open');
			return {
				id: parseInt(tile.getAttribute('data-book'), 10),
				title: label ? label.textContent : ''
			};
		}).filter(function (b) { return b.id > 0; });
	}

	var booksOpenBtn = root.querySelector('[data-books-open]');
	if (booksOpenBtn) booksOpenBtn.addEventListener('click', openBooks);
	var booksCloseBtn = document.querySelector('[data-books-close]');
	if (booksCloseBtn) booksCloseBtn.addEventListener('click', closeBooks);
	if (booksBackdrop) booksBackdrop.addEventListener('click', closeBooks);

	syncBooksLimit();

	// --- Корзина -----------------------------------------------------------

	var trashBox = root.querySelector('[data-trash]');
	var trashList = root.querySelector('[data-trash-list]');

	function describeDays(seconds) {
		var days = Math.ceil(seconds / 86400);
		if (days <= 0) return 'удалится сегодня';
		if (days === 1) return 'остался 1 день';
		if (days >= 2 && days <= 4) return 'осталось ' + days + ' дня';
		return 'осталось ' + days + ' дней';
	}

	function insertNoteInOrder(note, id) {
		var before = null;
		var existing = list.querySelectorAll('.kv04-note');
		for (var i = 0; i < existing.length; i++) {
			if (parseInt(existing[i].getAttribute('data-id'), 10) < parseInt(id, 10)) {
				before = existing[i];
				break;
			}
		}
		list.insertBefore(note, before);
		highlight(note);
	}

	// Заметка могла остаться в ленте (вернули её кусок) или исчезнуть
	// (вернули её саму) — обрабатываем оба случая одинаково.
	function putNote(item) {
		var fresh = createNoteElement(item);
		var existing = list.querySelector('.kv04-note[data-id="' + item.id + '"]');
		if (existing) {
			existing.replaceWith(fresh);
			highlight(fresh);
			return;
		}
		insertNoteInOrder(fresh, item.id);
	}

	function restoreNote(id) {
		return post({ action: 'restore', id: id }).then(function (data) {
			if (!data.ok) { alert(data.error || 'Ошибка'); return false; }
			if (data.item) putNote(data.item);
			return true;
		}).catch(function () { alert('Нет связи'); return false; });
	}

	function restoreFragment(trashId) {
		return post({ action: 'restore_fragment', trash_id: trashId }).then(function (data) {
			if (!data.ok) { alert(data.error || 'Ошибка'); return false; }
			if (data.item) putNote(data.item);
			return true;
		}).catch(function () { alert('Нет связи'); return false; });
	}

	// Подтверждения перед удалением больше нет, поэтому возврат предлагаем
	// сразу после действия — это заметнее и быстрее, чем идти в корзину.
	function showUndo(message, days, onRestore, actionLabel) {
		var bar = document.createElement('div');
		bar.className = 'kv04-undo';

		var text = document.createElement('span');
		// Срок хранения дописываем только там, где он есть: у съёмки полоска
		// говорит «Фото сохранено», и хвост про корзину был бы не про то.
		text.textContent = days ? message + ', хранится ' + days + ' дней' : message;
		bar.appendChild(text);

		var back = document.createElement('button');
		back.type = 'button';
		back.className = 'kv04-btn kv04-btn--ghost kv04-btn--sm';
		back.textContent = actionLabel || 'Вернуть';
		bar.appendChild(back);

		list.parentNode.insertBefore(bar, list);

		// Подтверждения нет, поэтому окно возврата даём с запасом.
		var timer = setTimeout(function () { bar.remove(); }, 15000);
		back.addEventListener('click', function () {
			clearTimeout(timer);
			back.disabled = true;
			Promise.resolve(onRestore()).then(function () {
				bar.remove();
				if (trashBox && !trashBox.hidden) openTrash();
			});
		});
	}


	// В корзине лежат вещи трёх сортов: целая заметка, отдельный файл и блок
	// текста или кода. Возвращаются они по-разному, но для человека это одно
	// действие — ткнуть в запись, поэтому кнопки у строк нет.
	function trashLabel(item) {
		if (item.kind === 'note') return 'Заметка';
		if (item.kind === 'media') return 'Файл';
		return 'Блок';
	}

	function renderTrash(items, days) {
		trashList.innerHTML = '';
		if (!items.length) {
			var empty = document.createElement('p');
			empty.className = 'kv04-trash__empty';
			empty.textContent = 'Пусто. Удалённое лежит здесь ' + days + ' дней, потом стирается насовсем.';
			trashList.appendChild(empty);
			return;
		}

		items.forEach(function (item) {
			var row = document.createElement('div');
			row.className = 'kv04-trash__item';
			row.setAttribute('role', 'button');
			row.setAttribute('tabindex', '0');
			row.title = 'Вернуть на место';

			var text = document.createElement('div');
			text.className = 'kv04-trash__text';
			var kind = document.createElement('span');
			kind.className = 'kv04-trash__kind';
			kind.textContent = trashLabel(item);
			text.appendChild(kind);
			text.appendChild(document.createTextNode(item.excerpt || ''));
			row.appendChild(text);

			var meta = document.createElement('div');
			meta.className = 'kv04-trash__meta';
			meta.textContent = item.date + ' · ' + describeDays(item.expires_in);
			row.appendChild(meta);

			function restoreThis() {
				if (row.classList.contains('is-busy')) return;
				row.classList.add('is-busy');
				var task = item.kind === 'note'
					? restoreNote(item.id)
					: restoreFragment(item.trash_id);
				Promise.resolve(task).then(function (ok) {
					if (!ok) { row.classList.remove('is-busy'); return; }
					row.remove();
					if (!trashList.querySelector('.kv04-trash__item')) renderTrash([], days);
				});
			}

			row.addEventListener('click', restoreThis);
			row.addEventListener('keydown', function (e) {
				// Строка работает кнопкой, значит и с клавиатуры должна.
				if (e.key !== 'Enter' && e.key !== ' ') return;
				e.preventDefault();
				restoreThis();
			});

			trashList.appendChild(row);
		});
	}

	function openTrash() {
		trashBox.hidden = false;
		trashList.innerHTML = '';
		var loading = document.createElement('p');
		loading.className = 'kv04-trash__empty';
		loading.textContent = 'Загрузка…';
		trashList.appendChild(loading);

		post({ action: 'trash' }).then(function (data) {
			if (!data.ok) { trashList.innerHTML = ''; alert(data.error || 'Ошибка'); return; }
			renderTrash(data.items || [], data.days || 7);
		}).catch(function () {
			loading.textContent = 'Нет связи';
		});
	}

	root.querySelector('[data-trash-open]').addEventListener('click', function () {
		if (trashBox.hidden) { openTrash(); } else { trashBox.hidden = true; }
	});
	root.querySelector('[data-trash-close]').addEventListener('click', function () {
		trashBox.hidden = true;
	});

	var attachBox = root.querySelector('[data-attach-email]');
	if (attachBox) {
		var attachInput = attachBox.querySelector('[data-attach-input]');
		var attachSave = attachBox.querySelector('[data-attach-save]');
		var attachStatus = attachBox.querySelector('[data-attach-status]');
		var setAttachStatus = function (msg, isError) {
			attachStatus.hidden = !msg;
			attachStatus.textContent = msg || '';
			attachStatus.classList.toggle('is-error', !!isError);
		};
		var submitAttach = function () {
			var value = (attachInput.value || '').trim();
			if (!value) { setAttachStatus('Введите почту', true); attachInput.focus(); return; }
			attachSave.disabled = true;
			setAttachStatus('Сохраняю…', false);
			post({ action: 'attach_email', email: value }).then(function (data) {
				attachSave.disabled = false;
				if (!data.ok) { setAttachStatus(data.error || 'Ошибка', true); return; }
				setAttachStatus('Готово — почта привязана', false);
				attachBox.classList.add('is-done');
				setTimeout(function () { attachBox.remove(); }, 1500);
			}).catch(function () {
				attachSave.disabled = false;
				setAttachStatus('Нет связи', true);
			});
		};
		attachSave.addEventListener('click', submitAttach);
		attachInput.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') { e.preventDefault(); submitAttach(); }
		});
	}

	highlight();
	// Лента приходит с сервера обычным текстом: ссылки навешиваем здесь.
	linkify(list);

	// Подсвечиваем только добавленные узлы. Пока заметки появлялись через
	// перезагрузку страницы, наблюдатель почти не срабатывал; теперь они
	// добавляются динамически, и highlight(list) на каждой вставке
	// перекрашивал бы всю ленту.
	if (window.MutationObserver && list) {
		var highlightTimer = null;
		var pendingNodes = [];
		var observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				Array.prototype.forEach.call(mutation.addedNodes, function (node) {
					if (node.nodeType === 1) pendingNodes.push(node);
				});
			});
			if (!pendingNodes.length) return;
			if (highlightTimer) clearTimeout(highlightTimer);
			highlightTimer = setTimeout(function () {
				var nodes = pendingNodes;
				pendingNodes = [];
				nodes.forEach(function (node) {
					if (node.isConnected) highlight(node);
				});
			}, 50);
		});
		observer.observe(list, { childList: true });
	}

	var lightbox = document.getElementById('kv04-lightbox');
	if (lightbox && lightbox.parentNode !== document.body) {
		document.body.appendChild(lightbox);
	}
	var lightboxStage = lightbox && lightbox.querySelector('.kv04-lightbox__stage');
	var lastFocus = null;
	var activeThumb = null;

	function closeLightbox() {
		if (!lightbox || !lightbox.classList.contains('is-open')) return;
		var video = lightboxStage.querySelector('video');
		if (video) {
			video.pause();
			video.removeAttribute('src');
			video.load();
		}
		lightboxStage.innerHTML = '';
		if (clipObjectUrl) {
			URL.revokeObjectURL(clipObjectUrl);
			clipObjectUrl = null;
		}
		lightbox.classList.remove('is-open');
		lightbox.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('kv04-lightbox-open');
		activeThumb = null;
		if (lastFocus && lastFocus.focus) lastFocus.focus();
		lastFocus = null;
	}

	function openLightbox(type, src, trigger) {
		if (!lightbox || !lightboxStage) return;
		if (activeThumb === trigger && lightbox.classList.contains('is-open')) {
			closeLightbox();
			return;
		}
		activeThumb = trigger || null;
		lastFocus = trigger || document.activeElement;
		lightboxStage.innerHTML = '';
		if (type === 'video') {
			var video = document.createElement('video');
			video.src = src;
			video.controls = true;
			video.playsInline = true;
			video.setAttribute('playsinline', '');
			video.autoplay = true;
			video.preload = 'auto';

			// Короткое зациклим: трейлер на три секунды иначе успевает мигнуть и
			// кончиться раньше, чем на него посмотрели. Длинное видео крутить по
			// кругу незачем.
			video.addEventListener('loadedmetadata', function () {
				if (video.duration && video.duration <= 6) video.loop = true;
			});

			lightboxStage.appendChild(video);

			// Браузер отказывается запускать со звуком без прямого нажатия. У
			// трейлера звука нет вовсе, поэтому второй заход без него: лучше
			// показать видео молча, чем чёрный кадр с кнопкой.
			var started = video.play();
			if (started && started.catch) {
				started.catch(function () {
					video.muted = true;
					var again = video.play();
					if (again && again.catch) again.catch(function () {});
				});
			}
		} else {
			var img = document.createElement('img');
			img.src = src;
			img.alt = '';
			lightboxStage.appendChild(img);
		}
		lightbox.classList.add('is-open');
		lightbox.setAttribute('aria-hidden', 'false');
		document.body.classList.add('kv04-lightbox-open');
		lightbox.querySelector('.kv04-lightbox__close').focus();
	}

	root.addEventListener('click', function (e) {
		if (e.target.closest('[data-media-delete]')) return;
		var thumb = e.target.closest('.kv04-media-thumb');
		if (!thumb || !root.contains(thumb)) return;
		e.preventDefault();
		openLightbox(thumb.getAttribute('data-lightbox'), thumb.getAttribute('data-src'), thumb);
	});

	if (lightbox) {
		lightbox.addEventListener('click', function (e) {
			if (e.target.closest('[data-lightbox-close]') || e.target === lightbox.querySelector('.kv04-lightbox__backdrop')) {
				closeLightbox();
				return;
			}
			if (e.target.matches('.kv04-lightbox__stage img')) {
				closeLightbox();
			}
		});
	}

	document.addEventListener('keydown', function (e) {
		if (confirmActive) {
			var yesBtn = confirmEl.querySelector('[data-confirm-yes]');
			var noBtn = confirmEl.querySelector('[data-confirm-no]');
			if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
				e.preventDefault();
				if (noBtn) noBtn.focus();
			} else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
				e.preventDefault();
				if (yesBtn) yesBtn.focus();
			} else if (e.key === 'Enter') {
				e.preventDefault();
				closeSaveConfirm(document.activeElement === noBtn ? false : true);
			} else if (e.key === 'Escape') {
				e.preventDefault();
				closeSaveConfirm(false);
			}
			return;
		}
		if (e.key === 'Escape') {
			if (lightbox && lightbox.classList.contains('is-open')) {
				e.preventDefault();
				closeLightbox();
				return;
			}
			var editingNote = list.querySelector('.kv04-note.is-editing');
			if (editingNote) {
				e.preventDefault();
				promptSaveOnExit(editingNote);
			}
		}
	});
})();
</script>
