<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

if (!function_exists('kv04DiaryRenderItems'))
{
	function kv04DiaryRenderItems(array $items): void
	{
		foreach ($items as $item)
		{
			$media = $item['media'] ?? [];
			$mediaCount = count($media);
			$gridCount = min(max($mediaCount, 1), 4);
			$extraCount = $mediaCount > 4 ? $mediaCount - 3 : 0;
			?>
			<article class="kv04-note" data-id="<?=(int)$item['id']?>">
				<?php if (!empty($item['text'])): ?>
					<div class="kv04-note__body"><?=$item['text']?></div>
				<?php endif; ?>
				<?php if ($mediaCount > 0): ?>
					<div class="kv04-note__media kv04-media-grid kv04-media-grid--<?=$gridCount?><?=$mediaCount > 4 ? ' kv04-media-grid--more' : ''?>">
						<?php foreach ($media as $index => $file): ?>
							<?php
							$isOverflow = $mediaCount > 4 && $index >= 3;
							if ($mediaCount > 4 && $index > 3)
							{
								continue;
							}
							?>
							<?php if (!empty($file['is_image'])): ?>
								<div class="kv04-media-item">
									<button type="button"
										class="kv04-media-thumb<?=$isOverflow ? ' kv04-media-thumb--more' : ''?>"
										data-lightbox="image"
										data-src="<?=htmlspecialcharsbx($file['src'])?>"
										data-file-id="<?=(int)$file['id']?>"
										aria-label="Открыть изображение">
										<img src="<?=htmlspecialcharsbx($file['src'])?>" alt="" loading="lazy" decoding="async">
										<?php if ($isOverflow): ?>
											<span class="kv04-media-thumb__count">+<?=$extraCount?></span>
										<?php endif; ?>
									</button>
									<?php if (!$isOverflow): ?>
										<button type="button"
											class="kv04-media-item__remove"
											data-media-delete
											data-file-id="<?=(int)$file['id']?>"
											aria-label="Удалить файл">&times;</button>
									<?php endif; ?>
								</div>
							<?php elseif (!empty($file['is_video'])): ?>
								<div class="kv04-media-item">
									<button type="button"
										class="kv04-media-thumb kv04-media-thumb--video<?=$isOverflow ? ' kv04-media-thumb--more' : ''?>"
										data-lightbox="video"
										data-src="<?=htmlspecialcharsbx($file['src'])?>"
										data-file-id="<?=(int)$file['id']?>"
										aria-label="Открыть видео">
										<video src="<?=htmlspecialcharsbx($file['src'])?>" muted playsinline preload="metadata"></video>
										<span class="kv04-media-thumb__play" aria-hidden="true"></span>
										<?php if ($isOverflow): ?>
											<span class="kv04-media-thumb__count">+<?=$extraCount?></span>
										<?php endif; ?>
									</button>
									<?php if (!$isOverflow): ?>
										<button type="button"
											class="kv04-media-item__remove"
											data-media-delete
											data-file-id="<?=(int)$file['id']?>"
											aria-label="Удалить файл">&times;</button>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<div class="kv04-note__footer">
					<time><?=htmlspecialcharsbx($item['date'])?></time>
					<div class="kv04-note__ops">
						<button type="button" class="kv04-btn kv04-btn--ghost kv04-btn--sm" data-edit>Изменить</button>
						<button type="button" class="kv04-btn kv04-btn--danger kv04-btn--sm" data-delete>Удалить</button>
					</div>
				</div>
			</article>
			<?php
		}
	}
}
?>
<div class="kv04-feed" id="kv04-feed">
	<div class="kv04-feed__head">
		<h1>Мой дневник</h1>
		<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-trash-open>Корзина</button>
		<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-logout>Выйти</button>
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
			<button type="submit" class="kv04-btn kv04-btn--primary kv04-btn--sm" title="Готово (Ctrl+Enter)">Готово</button>
		</div>
		<div class="kv04-composer__preview" data-file-preview hidden></div>
		<p class="kv04-feed__error" data-error hidden></p>
	</form>

	<div class="kv04-feed__list" data-list>
		<?php kv04DiaryRenderItems($arResult['ITEMS']); ?>
	</div>
</div>

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

<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
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
	var fileInput = composer.querySelector('input[type=file]');
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
			for (var i = 0; i < children.length; i++) {
				var child = children[i];

				if (child.nodeType === 3) {
					out += escapeForHtml(child.nodeValue);
					continue;
				}
				if (child.nodeType !== 1) continue;

				var tag = child.tagName.toLowerCase();

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
			body.innerHTML = item.text;
			note.appendChild(body);
		}

		var footer = document.createElement('div');
		footer.className = 'kv04-note__footer';
		var time = document.createElement('time');
		time.textContent = item.date || '';
		footer.appendChild(time);

		var ops = document.createElement('div');
		ops.className = 'kv04-note__ops';
		ops.innerHTML =
			'<button type="button" class="kv04-btn kv04-btn--ghost kv04-btn--sm" data-edit>Изменить</button>' +
			'<button type="button" class="kv04-btn kv04-btn--danger kv04-btn--sm" data-delete>Удалить</button>';
		footer.appendChild(ops);
		note.appendChild(footer);

		// renderNoteMedia ищет .kv04-note__footer, поэтому только после append.
		renderNoteMedia(note, item.media || []);

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
			// Раньше здесь был location.reload(): полный проход по стеку
			// ради одной новой заметки. Сервер уже вернул готовый элемент.
			if (data.item) {
				var note = createNoteElement(data.item);
				list.insertBefore(note, list.firstChild);
				highlight(note);
			}
		}).catch(function () {
			if (composerSubmit) composerSubmit.disabled = false;
			setError('Нет связи');
		});
	}

	composer.addEventListener('submit', function (e) {
		e.preventDefault();
		submitComposer();
	});

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
		var body = note.querySelector('.kv04-note__body');
		if (!body) return;
		var bar = note.querySelector('.kv04-edit-bar');
		if (bar) bar.remove();
		body.removeAttribute('contenteditable');
		if (hasBody || html) {
			body.innerHTML = html;
			highlight(body);
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
		if (e.target.closest('.kv04-media-thumb, .kv04-media-item__remove, .kv04-note__media, [data-edit], [data-delete], [data-media-delete], .kv04-note__ops, .kv04-edit-bar')) {
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
				video.src = file.src;
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

		note._editCtx = { originalHtml: current, hadBody: hadBody, doSave: doSave };
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
			if (!fileId || !confirm('Удалить файл?')) return;
			post({ action: 'detach_media', id: id, file_id: fileId }).then(function (data) {
				if (!data.ok) { alert(data.error || 'Ошибка'); return; }
				renderNoteMedia(note, data.media || []);
			}).catch(function () { alert('Нет связи'); });
			return;
		}
		if (e.target.closest('[data-delete]')) {
			// Без подтверждения: удаление обратимо, заметка уходит в корзину.
			// Вместо вопроса — строка с возвратом сразу после действия.
			post({ action: 'delete', id: id }).then(function (data) {
				if (!data.ok) { alert(data.error || 'Ошибка'); return; }
				note.remove();
				showUndoDelete(id, data.trash_days || 7);
				// Корзина открыта — показываем в ней свежеудалённое сразу.
				if (trashBox && !trashBox.hidden) openTrash();
			}).catch(function () { alert('Нет связи'); });
			return;
		}
		if (e.target.closest('[data-edit]') || isNoteEditClick(e, note)) {
			startEdit(note);
		}
	});

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

	// Короткая выжимка для строки корзины: показывать целую заметку там незачем.
	function excerptOf(item) {
		var probe = document.createElement('div');
		probe.innerHTML = item.text || '';
		var text = (probe.textContent || '').replace(/\s+/g, ' ').trim();
		if (!text) {
			return item.media && item.media.length
				? 'Вложений: ' + item.media.length
				: 'Пустая заметка';
		}
		return text.length > 90 ? text.slice(0, 90) + '…' : text;
	}

	function restoreNote(id, onDone) {
		return post({ action: 'restore', id: id }).then(function (data) {
			if (!data.ok) { alert(data.error || 'Ошибка'); return false; }
			if (data.item) {
				var note = createNoteElement(data.item);
				// Возвращаем на своё место: лента идёт свежими вверх, поэтому
				// встаём перед первой заметкой с меньшим номером.
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
			if (onDone) onDone();
			return true;
		}).catch(function () { alert('Нет связи'); return false; });
	}

	// Подтверждения перед удалением больше нет, поэтому возврат предлагаем
	// сразу после действия — это заметнее и быстрее, чем идти в корзину.
	function showUndoDelete(id, days) {
		var bar = document.createElement('div');
		bar.className = 'kv04-undo';
		var text = document.createElement('span');
		text.textContent = 'Заметка в корзине, хранится ' + days + ' дней';
		bar.appendChild(text);
		var back = document.createElement('button');
		back.type = 'button';
		back.className = 'kv04-btn kv04-btn--ghost kv04-btn--sm';
		back.textContent = 'Вернуть';
		bar.appendChild(back);
		list.parentNode.insertBefore(bar, list);

		// Подтверждения нет, поэтому окно возврата даём с запасом.
		var timer = setTimeout(function () { bar.remove(); }, 15000);
		back.addEventListener('click', function () {
			clearTimeout(timer);
			back.disabled = true;
			restoreNote(id, function () { bar.remove(); });
		});
	}

	function renderTrash(items, days) {
		trashList.innerHTML = '';
		if (!items.length) {
			var empty = document.createElement('p');
			empty.className = 'kv04-trash__empty';
			empty.textContent = 'Пусто. Удалённые заметки лежат здесь ' + days + ' дней, потом стираются насовсем.';
			trashList.appendChild(empty);
			return;
		}
		items.forEach(function (item) {
			var row = document.createElement('div');
			row.className = 'kv04-trash__item';

			var text = document.createElement('div');
			text.className = 'kv04-trash__text';
			text.textContent = excerptOf(item);
			row.appendChild(text);

			var meta = document.createElement('div');
			meta.className = 'kv04-trash__meta';
			meta.textContent = item.date + ' · ' + describeDays(item.expires_in);
			row.appendChild(meta);

			var back = document.createElement('button');
			back.type = 'button';
			back.className = 'kv04-btn kv04-btn--muted kv04-btn--sm';
			back.textContent = 'Вернуть';
			back.addEventListener('click', function () {
				back.disabled = true;
				restoreNote(item.id, function () {
					row.remove();
					if (!trashList.querySelector('.kv04-trash__item')) renderTrash([], days);
				});
			});
			row.appendChild(back);

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
			video.autoplay = true;
			lightboxStage.appendChild(video);
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
