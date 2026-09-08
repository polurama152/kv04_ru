/**
 * PDF → Markdown для языковой модели.
 *
 * Разбор идёт целиком в браузере: файл не уезжает на сервер, общий хостинг не
 * получает нагрузку, а модулю не нужны сторонние бинари на площадке клиента.
 *
 * Цель формата — не верность вёрстке, а то, что прочтёт модель. Поэтому
 * колонтитулы выбрасываются, переносы снимаются, абзацы склеиваются, а таблица,
 * в которой детектор не уверен, выводится обычным текстом: кривая таблица врёт
 * модели про то, к какому столбцу относится число, а абзац просто скучный.
 *
 * Модуль не знает про дневник и не трогает DOM, кроме одной инъекции скрипта.
 * Наружу — window.KV04PdfMarkdown.
 */
(function () {
	'use strict';

	var BASE = '/local/modules/kv04.diary/assets/pdfjs/';

	// Свою метку версии берём из адреса, которым загрузили этот файл: шаблон
	// уже проставил её там, и передавать второй раз незачем. Так библиотека и
	// разбор всегда одного поколения — а без метки сервис-воркер закэширует
	// их навсегда, и правки до вернувшегося посетителя не дойдут.
	var VERSION = (function () {
		var el = document.currentScript;
		if (!el || !el.src) return '';
		try {
			return new URL(el.src, location.href).searchParams.get('v') || '';
		} catch (err) {
			return '';
		}
	})();

	function versioned(path) {
		return VERSION ? path + '?v=' + encodeURIComponent(VERSION) : path;
	}

	// --- Пороги ------------------------------------------------------------
	//
	// Ни одного числа в пикселях: всё либо в долях кегля (em), либо в долях
	// контентной ширины. Иначе A5 и A4, 8pt и 14pt требовали бы разных наборов.

	// Индексы и знаки сносок сидят на ~0.33 em от базовой линии и обязаны
	// остаться в своей строке; следующая строка не бывает ближе ~0.9 em.
	var LINE_Y_TOL = 0.35;
	// Пробельный глиф — 0.25–0.33 em, но выключка его сжимает. Ниже 0.15 em
	// живут кернинг и подгонка, то есть внутрисловные зазоры.
	var GAP_SPACE = 0.2;
	// Шире любого межсловного пробела, уже межколонной канавки: намеренная
	// пустота. Из этих разрывов потом собираются колонки таблиц.
	var GAP_TAB = 1.6;
	// PDF пишет 9.96 и 10.0 для одного кегля — округляем, иначе кластеры
	// заголовков рассыпаются на десятки «уровней».
	var SIZE_ROUND = 0.5;
	// Фейковый жир рисует те же глифы со сдвигом ~0.02 em.
	var BOLD_DUP_EPS = 0.06;

	// Двести корзин — это ~3 пункта на корзину при ширине A4. На сотне
	// корзина шире самой канавки, и мерить нечем.
	var COL_BINS = 200;
	// ≈12 пунктов на A4. Меньше не бывает даже у плотной журнальной вёрстки, а
	// в одноколоночном тексте такой полосы не возникает вовсе: там каждая
	// строка проходит через середину.
	var GUTTER_MIN = 0.02;
	var COL_MIN_LINES = 12;      // титул и шмуцтитул дают ложные канавки
	var COL_CENTER = [0.3, 0.7]; // смещённая пустота — иллюстрация, не канавка
	var COL_SIDE_RATIO = 0.25;   // «колонка» из трёх строк — маргиналия
	// Заголовок во всю ширину, подпись к иллюстрации и таблица на всю полосу
	// пересекают канавку законно, и на странице, где текст идёт в две колонки, а
	// таблица во всю ширину, их набирается заметно больше десятой доли. Ложного
	// срабатывания порог не даёт: в одноколоночном тексте канавку пересекают не
	// четверть строк, а все.
	var COL_CROSS_MAX = 0.25;
	var COL_MAX_DEPTH = 2;
	var COL_VOTE = 0.6;

	var REPEAT_MIN_PAGES = 4;    // на трёх страницах повтор неотличим от текста
	var HEADER_BAND = 0.1;       // ≈30 мм на A4 — больше любого колонтитула
	var REPEAT_RATIO = 0.5;      // колонтитул чередуется чёт/нечет: 0.8 его упустит
	var HEAD_MAX_CHARS = 120;    // повторяющийся абзац — это дисклеймер, не колонтитул
	var FOOTNOTE_RATIO = 0.85;

	// Минимальный живой шаг типографики — 1.15 (10pt тело → 12pt подзаголовок).
	// 1.12 его ловит и отсекает шум округления метрик.
	var HEAD_MIN_RATIO = 1.12;
	var HEAD_MAX_WORDS = 14;
	var HEAD_MAX_CHARS_LINE = 100;
	var SIZE_MERGE = 0.6;        // один уровень, набранный двумя кеглями
	var MAX_LEVELS = 3;          // глубже трёх модели ничего не даёт
	var GAP_HEAD_ABOVE = 1.6;

	var PARA_GAP = 1.35;         // внутри абзаца зазор равен интерлиньяжу
	var INDENT_MIN = 0.9;        // типографский абзацный отступ — 1–2 em
	var SHORT_LINE = 0.15;
	var RAGGED_STDEV = 0.06;

	var HYPH_MIN_HEAD = 3;       // правила переноса не оставляют меньше двух букв

	var INDENT_EPS = 0.5;
	var LIST_MAX_DEPTH = 4;
	var LIST_SEQ_MIN = 2;
	var BULLET_GAP = 0.3;
	var DASH_LIST_MIN = 2;

	var TABLE_MIN_ROWS = 3;
	var TABLE_MAX_COLS = 6;      // больше шести колонок в markdown нечитаемо
	var COL_EPS = 0.02;
	var TABLE_FIT = 0.8;
	var TABLE_CELL_MAX = 200;    // «ячейка» с абзацем — это вёрстка в колонки
	var TABLE_EMPTY_MAX = 0.4;
	// Колонка, пустая в большинстве строк, колонкой не является: это разрез,
	// придуманный кластеризацией на разбросанных подписях рисунка. Таблица,
	// собранная из такого, врёт модели про то, к чему относится число, — а
	// это ровно тот вред, ради предотвращения которого отказ и существует.
	var TABLE_COL_FILL_MIN = 0.4;
	var TABLE_BASELINE_STDEV = 0.4;

	// Три строки, а не две: формулы в научных статьях набраны шрифтами, которые
	// pdf.js честно объявляет моноширинными, и пара таких строк подряд набирается
	// на любой странице с математикой.
	var CODE_MIN_LINES = 3;
	// Листинг держит левый край. Подписи внутри рисунка разбросаны по всей
	// ширине — по этому разбросу рисунок от кода и отличается.
	var CODE_MAX_SPREAD = 0.4;
	var CODE_MIN_AVG_CHARS = 8;
	// Отступ внутри блока: больше двадцати знаков не бывает у кода, зато бывает
	// у рисунка, и тогда отбивка раздувает вывод впустую.
	var CODE_MAX_PAD = 20;
	var FENCE = String.fromCharCode(96, 96, 96);
	var MONO_CHAR_W = 0.6;       // ширина знака моноширинных — ровно 0.6 em
	var MONO_STDEV = 0.02;

	var TOC_LINE_RATIO = 0.6;
	var GARBAGE_PAGE = 0.2;
	var NO_TEXT_PROBE = 5;
	// Ниже двадцати знаков на страницу текстового слоя считай что нет. Порог
	// низкий намеренно: колонцифра и колонтитул на пустой скан-странице тоже
	// дают десяток знаков, и принять их за текст нельзя.
	var NO_TEXT_CHARS_PER_PAGE = 20;
	var MAX_ITEMS_PER_PAGE = 20000;
	var YIELD_EVERY = 5;

	var DEFAULTS = {
		maxPages: 100,
		maxChars: 400000,
		timeoutMs: 60000,
		columns: true,
		tables: true,
		code: true,
		dropToc: true,
		joinPages: true,
		pageMarks: true,
		frontMatter: true,
		onProgress: null,
		onPassword: null,
		signal: null
	};

	// --- Мелочи ------------------------------------------------------------

	function extend(base, over) {
		var out = {}, k;
		for (k in base) { if (base.hasOwnProperty(k)) out[k] = base[k]; }
		if (over) { for (k in over) { if (over.hasOwnProperty(k)) out[k] = over[k]; } }
		return out;
	}

	function fail(code, message) {
		var err = new Error(message);
		err.code = code;
		return err;
	}

	function median(nums) {
		if (!nums.length) return 0;
		var a = nums.slice().sort(function (x, y) { return x - y; });
		var m = Math.floor(a.length / 2);
		return a.length % 2 ? a[m] : (a[m - 1] + a[m]) / 2;
	}

	function stdev(nums) {
		if (nums.length < 2) return 0;
		var mean = 0, i;
		for (i = 0; i < nums.length; i++) mean += nums[i];
		mean /= nums.length;
		var acc = 0;
		for (i = 0; i < nums.length; i++) acc += (nums[i] - mean) * (nums[i] - mean);
		return Math.sqrt(acc / (nums.length - 1));
	}

	function percentile(nums, p) {
		if (!nums.length) return 0;
		var a = nums.slice().sort(function (x, y) { return x - y; });
		return a[Math.min(a.length - 1, Math.max(0, Math.round((a.length - 1) * p)))];
	}

	// Уступаем событийному циклу. Именно setTimeout, а не requestAnimationFrame:
	// в фоновой вкладке rAF не вызывается вовсе, а конвертация должна доехать.
	function yieldToUi() {
		return new Promise(function (resolve) { setTimeout(resolve, 0); });
	}

	// --- Загрузка pdf.js ---------------------------------------------------
	//
	// Лениво и один раз на страницу: библиотека весит под два мегабайта и не
	// нужна ничему, кроме конвертации. Промис кэшируется, повторный выбор файла
	// уже ничего не грузит.

	var libPromise = null;

	function ensureLib() {
		if (libPromise) return libPromise;

		libPromise = new Promise(function (resolve, reject) {
			if (window.pdfjsLib) { resolve(window.pdfjsLib); return; }

			// Браузер без модулей просто проигнорирует <script type="module">:
			// ни load, ни error не придёт, и мы бы висели вечно.
			if (!('noModule' in HTMLScriptElement.prototype)) {
				reject(fail('lib-failed', 'Браузер слишком старый: разбор PDF ему не по силам.'));
				return;
			}

			var el = document.createElement('script');
			el.type = 'module';
			el.src = versioned(BASE + 'pdfjs-loader.js');
			el.onload = function () {
				// Шим догружает саму библиотеку динамическим import, поэтому к
				// моменту load она ещё в пути — ждём его обещание, а не голый
				// window.pdfjsLib.
				var ready = window.kv04PdfjsReady;
				if (!ready) {
					reject(fail('lib-failed', 'Библиотека разбора PDF не загрузилась.'));
					return;
				}
				ready.then(function (lib) {
					lib.GlobalWorkerOptions.workerSrc = versioned(BASE + 'pdf.worker.min.js');
					resolve(lib);
				}, function () {
					libPromise = null;
					reject(fail('lib-failed', 'Библиотека разбора PDF не загрузилась.'));
				});
			};
			el.onerror = function () {
				libPromise = null; // сеть могла моргнуть — дать повторить
				reject(fail('lib-failed', 'Не удалось загрузить библиотеку разбора PDF.'));
			};
			document.head.appendChild(el);
		});

		return libPromise;
	}

	// --- Чистка символов ---------------------------------------------------
	//
	// Применяется к строке сразу после сборки и до всех структурных детекторов:
	// иначе ﬁ-лигатура ломает сверку заголовка с закладкой оглавления.

	var LIGATURES = { '\uFB00': 'ff', '\uFB01': 'fi', '\uFB02': 'fl', '\uFB03': 'ffi', '\uFB04': 'ffl', '\uFB05': 'st', '\uFB06': 'st' };
	var PUA = /[\uE000-\uF8FF]/g;

	function cleanText(text) {
		var out = text
			.replace(/[\uFB00-\uFB06]/g, function (ch) { return LIGATURES[ch] || ch; })
			// Мягкий перенос — метка «здесь можно рвать», в тексте ему не место.
			.replace(/\u00AD/g, '')
			.replace(/[\u200B-\u200D\uFEFF]/g, '')
			// NBSP и типографские пробелы: в contenteditable NBSP потом даёт
			// настоящие баги, а модели он не приносит ничего.
			.replace(/[\u00A0\u202F\u2000-\u200A\u2028\u2029]/g, ' ')
			.replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, '')
			// Отточия оглавления и разрядка точками.
			.replace(/\.{4,}/g, ' ')
			.replace(/\u00B7{3,}/g, ' ');

		try {
			// Чинит разложенное «и» + U+0306, которое иначе токенизируется в кашу.
			out = out.normalize('NFC');
		} catch (err) {}

		return out;
	}

	// Кавычки «» „“, тире, «ё» и регистр не трогаем: в русском они несут смысл,
	// а замена только ломает. Markdown-спецсимволы в теле тоже не экранируем —
	// обратные слэши стоят токенов и путают модель сильнее случайного курсива.

	function garbageRatio(text) {
		if (!text.length) return 0;
		var m = text.match(/[\uE000-\uF8FF\uFFFD]/g);
		return m ? m.length / text.length : 0;
	}

	// --- Items → строки ----------------------------------------------------
	//
	// Порядок items в потоке содержимого — это порядок отрисовки, а не чтения.
	// Доверять ему нельзя никогда, геометрию собираем сами.

	function itemsToRaw(pdfjsLib, items, styles, viewport) {
		var raw = [], i;

		for (i = 0; i < items.length && i < MAX_ITEMS_PER_PAGE; i++) {
			var it = items[i];
			if (!it || typeof it.str !== 'string' || it.str === '') continue;

			var m = pdfjsLib.Util.transform(viewport.transform, it.transform);
			// Кегль через матрицу, а не it.height: на повёрнутой странице height врёт.
			var size = Math.hypot(m[2], m[3]) || 1;
			var style = styles && styles[it.fontName];

			raw.push({
				str: it.str,
				x: m[4],
				y: m[5],
				w: it.width || it.str.length * size * 0.5,
				size: size,
				font: it.fontName,
				mono: !!(style && style.fontFamily === 'monospace')
			});
		}

		raw.sort(function (a, b) { return a.y - b.y || a.x - b.x; });
		return raw;
	}

	// Группировка по базовой линии. Якорь — самый широкий item строки, а не
	// первый: буквица или знак сноски не должны утащить полосу за собой.
	function groupByY(raw) {
		var groups = [], cur = null, i;
		for (i = 0; i < raw.length; i++) {
			var r = raw[i];
			if (cur && Math.abs(r.y - cur.anchorY) <= LINE_Y_TOL * Math.min(r.size, cur.anchorSize)) {
				cur.parts.push(r);
				if (r.w > cur.anchorW) { cur.anchorY = r.y; cur.anchorSize = r.size; cur.anchorW = r.w; }
				continue;
			}
			cur = { anchorY: r.y, anchorSize: r.size, anchorW: r.w, parts: [r] };
			groups.push(cur);
		}
		return groups;
	}

	function linesFromRaw(raw) {
		var groups = groupByY(raw), lines = [], i;
		for (i = 0; i < groups.length; i++) {
			var line = joinLine(groups[i]);
			if (line) lines.push(line);
		}
		return lines;
	}

	function joinLine(group) {
		var parts = group.parts.slice().sort(function (a, b) { return a.x - b.x; });
		var text = '', gaps = [], prev = null, sizes = {}, monoChars = 0, chars = 0, j;
		// Ячейки собираем здесь же, по живым координатам фрагментов. Восстановить
		// их потом из готовой строки нельзя: ширину знака придётся оценивать, и
		// содержимое расползается по соседним колонкам.
		var cells = [], cellText = '', cellX = parts.length ? parts[0].x : 0;

		for (j = 0; j < parts.length; j++) {
			var p = parts[j];
			var piece = p.str;

			if (prev) {
				var gap = p.x - (prev.x + prev.w);
				var em = Math.min(p.size, prev.size) || 1;

				// Фейковый жир печатает те же глифы со сдвигом в сотые доли em.
				if (gap < 0 && Math.abs(gap) < BOLD_DUP_EPS * em && text.slice(-piece.length) === piece) {
					continue;
				}
				if (/\s$/.test(text) || /^\s/.test(piece)) {
					// пробел уже есть в самом PDF
				} else if (gap >= GAP_TAB * em) {
					gaps.push(p.x);
					cells.push({ x: cellX, text: cellText });
					cellText = '';
					cellX = p.x;
					text += ' ';
				} else if (gap >= GAP_SPACE * em) {
					text += ' ';
					cellText += ' ';
				}
				// Иначе склейка без пробела — здесь и живёт разрезанное слово:
				// ["Прив","ет"] приходят с нулевым зазором.
			}

			text += piece;
			cellText += piece;
			// Кегль строки — мода по числу символов, а не среднее: знак сноски
			// не должен занижать кегль заголовка.
			var key = Math.round(p.size / SIZE_ROUND) * SIZE_ROUND;
			sizes[key] = (sizes[key] || 0) + piece.length;
			chars += piece.length;
			if (p.mono) monoChars += piece.length;
			prev = p;
		}

		// Отточие оглавления надо заметить до чистки: cleanText схлопывает
		// точки в пробел, и после него «Введение .... 5» уже неотличимо от
		// обычной строки с числом на конце.
		var leader = /\.{2,}\s*\d{1,4}\s*$/.test(text) || gaps.length > 0;

		cells.push({ x: cellX, text: cellText });
		for (j = 0; j < cells.length; j++) {
			cells[j].text = cleanText(cells[j].text).replace(/\s+/g, ' ').trim();
		}

		text = cleanText(text).replace(/\s+/g, ' ').trim();
		if (text === '') return null;

		var bestSize = 0, bestWeight = -1, k;
		for (k in sizes) {
			if (sizes.hasOwnProperty(k) && sizes[k] > bestWeight) { bestWeight = sizes[k]; bestSize = parseFloat(k); }
		}

		var last = parts[parts.length - 1];
		return {
			text: text,
			y: group.anchorY,
			x0: parts[0].x,
			x1: last.x + last.w,
			size: bestSize || group.anchorSize,
			gaps: gaps,
			cells: cells,
			leader: leader,
			mono: chars > 0 && monoChars / chars > 0.8,
			garbage: garbageRatio(text)
		};
	}

	// --- Колонки -----------------------------------------------------------
	//
	// Две колонки, прочитанные построчно, для модели не просто шумны — они
	// меняют смысл: половина фразы слева, половина справа, и модель уверенно
	// отвечает по получившемуся. Именно статьи и отчёты чаще всего и несут в
	// языковую модель, поэтому детектор здесь, а не в списке «потом».
	//
	// Резать надо items, а не готовые строки, и это не деталь. Группировка по
	// базовой линии сама по себе слепа к колонкам: строка слева и строка справа
	// стоят на одной высоте и склеиваются в одну — после этого канавки уже нет,
	// искать её поздно, а текст перемешан.

	function coverageBins(groups, left, right) {
		var width = right - left;
		var bins = new Array(COL_BINS), i, j, k;
		for (i = 0; i < COL_BINS; i++) bins[i] = 0;

		for (i = 0; i < groups.length; i++) {
			// Голос у строки один на корзину: иначе строка из тридцати мелких
			// фрагментов перекрикивает страницу.
			var seen = {};
			for (j = 0; j < groups[i].parts.length; j++) {
				var part = groups[i].parts[j];
				// Начало и конец округляем одинаково, вниз. Округлять конец
				// вверх значило бы приписывать каждому фрагменту лишнюю
				// корзину справа — на двух колонках это съедает всю канавку.
				var a = Math.max(0, Math.floor((part.x - left) / width * COL_BINS));
				var b = Math.min(COL_BINS - 1, Math.floor((part.x + part.w - left) / width * COL_BINS));
				for (k = a; k <= Math.max(a, b); k++) seen[k] = true;
			}
			for (k in seen) { if (seen.hasOwnProperty(k)) bins[k]++; }
		}
		return bins;
	}

	// Возвращает не точку разреза, а саму канавку: полосу от from до to. Разница
	// принципиальная — по точке нельзя отличить строку, набранную во всю ширину,
	// от двух строк соседних колонок, слипшихся при группировке по базовой
	// линии. По полосе можно: настоящая полноширинная строка лезет глифами
	// внутрь канавки, а слипшаяся пара оставляет её пустой.
	function findGutter(groups, left, right) {
		if (groups.length < COL_MIN_LINES) return null;

		var bins = coverageBins(groups, left, right);
		// Канавка — не обязательно пустота: заголовок во всю ширину и подпись к
		// иллюстрации её законно пересекают. Порог тот же, которым мы потом
		// проверяем долю пересекающих строк.
		var tol = Math.floor(COL_CROSS_MAX * groups.length);

		var best = null, run = 0, i;
		for (i = 0; i < COL_BINS; i++) {
			if (bins[i] <= tol) { run++; continue; }
			if (run > 0) {
				var cand = { from: i - run, to: i - 1, len: run };
				if (cand.from > 0 && (!best || run > best.len)) best = cand;
			}
			run = 0;
		}
		if (!best || best.len < GUTTER_MIN * COL_BINS) return null;

		var centerRatio = ((best.from + best.to + 1) / 2) / COL_BINS;
		// Смещённая пустая полоса — это иллюстрация или висячий отступ.
		if (centerRatio < COL_CENTER[0] || centerRatio > COL_CENTER[1]) return null;

		var width = right - left;
		return {
			cut: left + centerRatio * width,
			from: left + (best.from / COL_BINS) * width,
			to: left + ((best.to + 1) / COL_BINS) * width
		};
	}

	// Ноль — строка полноширинная (её глифы внутри канавки). Иначе строка живёт
	// в колонках, и её фрагменты надо разложить по сторонам поштучно: строка
	// слева и строка справа стоят на одной высоте и пришли сюда одной группой.
	function crossesGutter(group, gutter) {
		for (var i = 0; i < group.parts.length; i++) {
			var part = group.parts[i];
			if (part.x < gutter.to && part.x + part.w > gutter.from) return true;
		}
		return false;
	}

	// Пересекающие канавку строки — полноширинные полосы (заголовок статьи,
	// шапка таблицы). Страница склеивается: полоса, левая колонка целиком,
	// правая колонка целиком.
	function splitColumns(raw, left, right, depth) {
		var groups = groupByY(raw);
		var gutter = findGutter(groups, left, right);
		if (gutter === null) return [raw];

		var band = [], leftRaw = [], rightRaw = [];
		var leftLines = 0, rightLines = 0, bandLines = 0, i, j;

		for (i = 0; i < groups.length; i++) {
			var parts = groups[i].parts;
			if (crossesGutter(groups[i], gutter)) {
				bandLines++;
				band.push.apply(band, parts);
				continue;
			}
			var hitLeft = false, hitRight = false;
			for (j = 0; j < parts.length; j++) {
				if (parts[j].x < gutter.cut) { leftRaw.push(parts[j]); hitLeft = true; }
				else { rightRaw.push(parts[j]); hitRight = true; }
			}
			if (hitLeft) leftLines++;
			if (hitRight) rightLines++;
		}

		// «Колонка» из трёх строк — это маргиналия, а не колонка.
		if (leftLines < COL_SIDE_RATIO * groups.length) return [raw];
		if (rightLines < COL_SIDE_RATIO * groups.length) return [raw];
		if (bandLines > COL_CROSS_MAX * groups.length) return [raw];

		var pieces = [];
		if (band.length) pieces.push(band);

		if (depth < COL_MAX_DEPTH) {
			pieces = pieces.concat(splitColumns(leftRaw, left, gutter.cut, depth + 1));
			pieces = pieces.concat(splitColumns(rightRaw, gutter.cut, right, depth + 1));
		} else {
			if (leftRaw.length) pieces.push(leftRaw);
			if (rightRaw.length) pieces.push(rightRaw);
		}

		return pieces;
	}

	// Готовые строки страницы: сначала решение о колонках, потом сборка строк
	// внутри каждой. Порядок обратный — и текст перемешан.
	function pageLines(pdfjsLib, items, styles, viewport, useColumns) {
		var raw = itemsToRaw(pdfjsLib, items, styles, viewport);
		if (!useColumns) return { lines: linesFromRaw(raw), columns: 1 };

		var pieces = splitColumns(raw, 0, viewport.width, 0);
		var lines = [], i;
		for (i = 0; i < pieces.length; i++) {
			lines = lines.concat(linesFromRaw(pieces[i]));
		}
		return { lines: lines, columns: pieces.length };
	}

	// --- Колонтитулы и номера страниц --------------------------------------

	function normalizeRepeat(text) {
		return text.toLowerCase()
			// Номера страниц и годы схлопываем, иначе повтор не опознать.
			.replace(/\d+/g, '#')
			.replace(/[^a-zа-яё#]+/g, ' ')
			.replace(/\s+/g, ' ')
			.trim();
	}

	var PAGE_NUM_RE = /^[\s\-\u2013\u2014[(]*\d{1,4}[\s\-\u2013\u2014)\]]*$/;
	var PAGE_NUM_WORDS = /^(стр\.?|страница|page|с\.)\s*\d{1,4}(\s*(из|of|\/)\s*\d{1,4})?$/i;
	var ROMAN_RE = /^[ivxlcdm]{1,7}$/i;

	function collectRepeats(pages) {
		if (pages.length < REPEAT_MIN_PAGES) return {};

		var seen = {}, i, j;
		for (i = 0; i < pages.length; i++) {
			var page = pages[i];
			var band = HEADER_BAND * page.height;
			var local = {};
			for (j = 0; j < page.lines.length; j++) {
				var ln = page.lines[j];
				if (ln.y > band && ln.y < page.height - band) continue;
				if (ln.text.length > HEAD_MAX_CHARS) continue;
				var key = normalizeRepeat(ln.text);
				if (key === '') continue;
				local[key] = true;
			}
			for (var k in local) { if (local.hasOwnProperty(k)) seen[k] = (seen[k] || 0) + 1; }
		}

		var floor = Math.max(3, Math.ceil(REPEAT_RATIO * pages.length));
		var repeats = {};
		for (var key2 in seen) {
			if (seen.hasOwnProperty(key2) && seen[key2] >= floor) repeats[key2] = true;
		}
		return repeats;
	}

	function isFurniture(line, page, repeats) {
		var band = HEADER_BAND * page.height;
		if (line.y > band && line.y < page.height - band) return false;

		if (repeats[normalizeRepeat(line.text)]) return true;

		// Чистый номер страницы — даже в единственном экземпляре. Страховка от
		// страницы-разделителя, где цифра и есть всё содержимое.
		if (page.lines.length >= 5) {
			var t = line.text.trim();
			if (PAGE_NUM_RE.test(t)) return true;
			if (PAGE_NUM_WORDS.test(t)) return true;
			if (ROMAN_RE.test(t) && t.length <= 7) return true;
		}
		return false;
	}

	// Страницы оглавления выбрасываем только когда заголовки и так попадут в
	// вывод из закладок. Нет закладок — оглавление может быть единственной
	// структурой документа, и трогать его нельзя.
	var TOC_TAIL_RE = /\s\d{1,4}$/;

	function isTocPage(page) {
		if (page.lines.length < 5) return false;
		var hits = 0;
		for (var i = 0; i < page.lines.length; i++) {
			var ln = page.lines[i];
			// Номер страницы на конце плюс отбивка перед ним: либо отточие
			// (метка снята до чистки), либо широкий разрыв.
			if (ln.leader && TOC_TAIL_RE.test(ln.text)) hits++;
		}
		return hits / page.lines.length > TOC_LINE_RATIO;
	}

	// --- Кегль тела и интерлиньяж ------------------------------------------

	function measureBody(pages) {
		var weight = {}, gaps = [], i, j;

		for (i = 0; i < pages.length; i++) {
			var lines = pages[i].lines;
			for (j = 0; j < lines.length; j++) {
				var key = lines[j].size.toFixed(1);
				// Взвешиваем по символам, а не по строкам: документ с частыми
				// заголовками перекосил бы счёт по строкам.
				weight[key] = (weight[key] || 0) + lines[j].text.length;
				if (j > 0) {
					var d = lines[j].y - lines[j - 1].y;
					if (d > 0 && d < lines[j].size * 4) gaps.push(d);
				}
			}
		}

		var bodySize = 0, best = -1, k;
		for (k in weight) {
			if (weight.hasOwnProperty(k) && weight[k] > best) { best = weight[k]; bodySize = parseFloat(k); }
		}

		var leading = median(gaps);
		return {
			bodySize: bodySize || 10,
			leading: leading > 0 ? leading : (bodySize || 10) * 1.2
		};
	}

	// --- Заголовки ---------------------------------------------------------

	var NUMBERED_RE = /^(\d{1,3}(?:\.\d{1,3}){0,3})\.?\s+\S/;

	function looksUpper(text) {
		var letters = text.replace(/[^a-zA-Zа-яёА-ЯЁ]/g, '');
		if (letters.length < 4) return false;
		return letters === letters.toUpperCase() && letters !== letters.toLowerCase();
	}

	function headingSizeLevels(pages, bodySize) {
		var sizes = {}, i, j;
		for (i = 0; i < pages.length; i++) {
			for (j = 0; j < pages[i].lines.length; j++) {
				var ln = pages[i].lines[j];
				if (ln.size < bodySize * HEAD_MIN_RATIO) continue;
				if (!headingShaped(ln)) continue;
				sizes[ln.size.toFixed(1)] = true;
			}
		}

		var list = Object.keys(sizes).map(parseFloat).sort(function (a, b) { return b - a; });

		// Сливаем кегли, различающиеся меньше чем на SIZE_MERGE: один визуальный
		// уровень нередко набран двумя номинальными кеглями.
		var merged = [], i2;
		for (i2 = 0; i2 < list.length; i2++) {
			if (merged.length && merged[merged.length - 1] - list[i2] < SIZE_MERGE) continue;
			merged.push(list[i2]);
		}

		var levels = {};
		for (i2 = 0; i2 < merged.length; i2++) {
			levels[merged[i2].toFixed(1)] = Math.min(MAX_LEVELS, i2 + 1);
		}
		return { order: merged, levels: levels };
	}

	function measureOutlineSizes(pages, outline, bodySize) {
		var matched = 0, biggest = 0, i, j;
		for (i = 0; i < pages.length; i++) {
			for (j = 0; j < pages[i].lines.length; j++) {
				var ln = pages[i].lines[j];
				if (outlineLevelOf(ln.text, outline)) {
					matched = Math.max(matched, ln.size);
					continue;
				}
				if (ln.size >= bodySize * HEAD_MIN_RATIO && headingShaped(ln)) {
					biggest = Math.max(biggest, ln.size);
				}
			}
		}
		return { outlineMaxSize: matched, shift: biggest > matched + SIZE_MERGE ? 1 : 0 };
	}

	function levelForSize(size, sizeLevels) {
		var order = sizeLevels.order;
		for (var i = 0; i < order.length; i++) {
			if (size >= order[i] - SIZE_MERGE) return sizeLevels.levels[order[i].toFixed(1)];
		}
		return MAX_LEVELS;
	}

	function headingShaped(line) {
		if (line.mono) return false;
		var words = line.text.split(/\s+/).length;
		// Длинная крупная строка — это выносная цитата или аннотация на титуле.
		return words <= HEAD_MAX_WORDS || line.text.length <= HEAD_MAX_CHARS_LINE;
	}

	// Запасной путь для документов с одним кеглем на всё — очень частый случай
	// экспорта из Word, где заголовки отличаются только жирностью.
	function scoreHeading(line, prevGap, nextGap, ctx) {
		var score = 0, level = 0;

		var num = NUMBERED_RE.exec(line.text);
		if (num) {
			score += ctx.numberedConsistent ? 2 : 1;
			level = Math.min(MAX_LEVELS, num[1].split('.').filter(Boolean).length);
		}
		if (!ctx.allUpper && looksUpper(line.text)) score += 1;

		// Несущий сигнал — асимметрия отбивки: заголовок жмётся к тексту,
		// который вводит, и отстоит от того, что закончилось выше.
		if (line.text.split(/\s+/).length <= 10
			&& prevGap >= GAP_HEAD_ABOVE * ctx.leading
			&& nextGap > 0 && nextGap < prevGap) {
			score += 1;
		}

		return score >= 2 ? { level: level || MAX_LEVELS } : null;
	}

	// --- Закладки: старший авторитет над кеглями ---------------------------

	function flattenOutline(nodes, depth, out) {
		for (var i = 0; i < nodes.length; i++) {
			var node = nodes[i];
			if (node && node.title) out.push({ title: node.title, depth: depth });
			if (node && node.items && node.items.length) flattenOutline(node.items, depth + 1, out);
		}
		return out;
	}

	function normalizeTitle(text) {
		return cleanText(String(text))
			.toLowerCase()
			.replace(/\s+/g, ' ')
			.replace(/[.,:;!?\u2013\u2014-]+$/, '')
			.trim();
	}

	// Закладка почти никогда не повторяет номер раздела, напечатанный на
	// странице: в PDF лежит «Introduction», на бумаге — «1 Introduction».
	// Поэтому сверяем оба варианта, иначе не совпадёт вообще ничего.
	function outlineLevelOf(text, outline) {
		if (!outline) return 0;
		var direct = outline.map[normalizeTitle(text)];
		if (direct) return direct;
		var stripped = text.replace(/^\d{1,3}(?:\.\d{1,3})*\.?\s+/, '');
		return stripped === text ? 0 : (outline.map[normalizeTitle(stripped)] || 0);
	}

	function buildOutlineIndex(outline) {
		var index = {}, deepest = 1;
		for (var i = 0; i < outline.length; i++) {
			var key = normalizeTitle(outline[i].title);
			if (key === '') continue;
			var level = Math.min(MAX_LEVELS, outline[i].depth + 1);
			if (!index[key]) index[key] = level;
			if (outline[i].depth + 1 > deepest) deepest = outline[i].depth + 1;
		}
		return { map: index, deepest: Math.min(MAX_LEVELS, deepest) };
	}

	// --- Списки ------------------------------------------------------------
	//
	// Symbol и Wingdings отдают маркеры символами приватной зоны (U+F0B7 и что
	// угодно ещё). Правило простое и надёжное: одиночный символ из приватной
	// зоны в начале строки — это маркер. Всё прочее оттуда — мусор кодировки, и
	// до вывода он не доживает.
	var BULLET_RE = /^([\u2022\u2023\u25AA\u25AB\u25E6\u2219\u00B7\u25CF\u25CB\u25A0\u25A1\uE000-\uF8FF]|[-*+])\s+/;
	var DASH_RE = /^[\u2013\u2014]\s+/;
	var ORDERED_RE = /^(\d{1,3})[.)]\s+/;
	var ALPHA_RE = /^([a-zа-я])[.)]\s+/;

	function bulletOf(line) {
		var m = BULLET_RE.exec(line.text);
		if (m) return { marker: m[1], rest: line.text.slice(m[0].length), ordered: false };
		return null;
	}

	function orderedOf(line) {
		var m = ORDERED_RE.exec(line.text);
		if (m) return { num: parseInt(m[1], 10), rest: line.text.slice(m[0].length), ordered: true };
		m = ALPHA_RE.exec(line.text);
		if (m) return { num: null, rest: line.text.slice(m[0].length), ordered: true };
		return null;
	}

	// --- Переносы ----------------------------------------------------------

	var PARTICLES = /(-то|-либо|-нибудь|-ка|-таки)$/i;

	// Возвращает склеенную строку или null, если строки соединяются пробелом.
	//
	// Развилки две, и вторая важнее первой. Сначала решаем, дефис ли это вообще
	// (U+2013 и U+2014 — тире, их не трогаем никогда). Потом — снимать его или
	// оставить. Но соединяем без пробела в обоих случаях: «English- to-German»
	// не лучше «Englishto-German», а «English-to-German» верно.
	function dehyphenate(head, tail) {
		var m = /([^\s])[-\u2010]$/.exec(head);
		if (!m) return null;
		if (!/[a-zа-яёA-ZА-ЯЁ]/.test(m[1])) return null;
		if (!/^[a-zа-яё]/.test(tail)) return null;

		var word = /([^\s]+)[-\u2010]$/.exec(head)[1];
		var keep = false;

		// Правила переноса запрещают оставлять на строке меньше двух букв,
		// поэтому короткая голова — это часть сложного слова: «по-русски»,
		// «во-первых», «кое-как». Одно число закрывает весь класс.
		if (word.length < HYPH_MIN_HEAD) keep = true;
		if (PARTICLES.test(word + '-')) keep = true;
		if (word === word.toUpperCase() && word !== word.toLowerCase()) keep = true;
		// Хвост сам через дефис — значит, это цепочка сложного слова, и дефис
		// перед разрывом такой же настоящий: «English-» + «to-German».
		if (/^[^\s]*[-\u2010]/.test(tail.split(/\s/)[0])) keep = true;

		return keep ? head + tail : head.slice(0, -1) + tail;
	}

	// --- Таблицы -----------------------------------------------------------

	function clusterColumns(rows, contentWidth) {
		var starts = [], i, j;
		for (i = 0; i < rows.length; i++) {
			for (j = 0; j < rows[i].cells.length; j++) starts.push(rows[i].cells[j].x);
		}
		starts.sort(function (a, b) { return a - b; });

		var eps = COL_EPS * contentWidth;
		var clusters = [], cur = null;
		for (i = 0; i < starts.length; i++) {
			if (cur && starts[i] - cur.last <= eps) { cur.last = starts[i]; cur.count++; continue; }
			cur = { start: starts[i], last: starts[i], count: 1 };
			clusters.push(cur);
		}
		return clusters.map(function (c) { return (c.start + c.last) / 2; });
	}

	function splitRow(line, columns, contentWidth) {
		var eps = COL_EPS * contentWidth;
		var cells = new Array(columns.length), i;
		for (i = 0; i < columns.length; i++) cells[i] = '';

		for (i = 0; i < line.cells.length; i++) {
			var cell = line.cells[i];
			if (cell.text === '') continue;
			var idx = nearestColumn(cell.x, columns, eps);
			cells[idx] = cells[idx] === '' ? cell.text : cells[idx] + ' ' + cell.text;
		}
		return cells;
	}

	function nearestColumn(x, columns, eps) {
		var best = 0, bestD = Infinity;
		for (var i = 0; i < columns.length; i++) {
			var d = Math.abs(columns[i] - x);
			if (d < bestD) { bestD = d; best = i; }
		}
		return best;
	}

	function buildTable(rows, contentWidth, leading) {
		if (rows.length < TABLE_MIN_ROWS) return null;

		var columns = clusterColumns(rows, contentWidth);
		if (columns.length < 2 || columns.length > TABLE_MAX_COLS) return null;

		// Равномерность базовых линий — это ровно тот тест, который должен
		// валить многострочные ячейки: они нам не по зубам.
		var deltas = [], i;
		for (i = 1; i < rows.length; i++) deltas.push(rows[i].y - rows[i - 1].y);
		if (stdev(deltas) > TABLE_BASELINE_STDEV * leading) return null;

		var matrix = [], fitted = 0, empty = 0, total = 0;
		for (i = 0; i < rows.length; i++) {
			var cells = splitRow(rows[i], columns, contentWidth);
			var filled = 0, j;
			for (j = 0; j < cells.length; j++) {
				cells[j] = cells[j].trim();
				if (cells[j].length > TABLE_CELL_MAX) return null;
				if (cells[j] !== '') filled++;
				total++;
				if (cells[j] === '') empty++;
			}
			if (filled >= 2) fitted++;
			matrix.push(cells);
		}

		if (fitted / rows.length < TABLE_FIT) return null;
		if (total > 0 && empty / total > TABLE_EMPTY_MAX) return null;

		for (var c = 0; c < columns.length; c++) {
			var filledInCol = 0;
			for (i = 0; i < matrix.length; i++) { if (matrix[i][c] !== '') filledInCol++; }
			if (filledInCol / matrix.length < TABLE_COL_FILL_MIN) return null;
		}

		return { columns: columns.length, rows: matrix };
	}

	function renderTable(table) {
		var out = [], i, j;
		for (i = 0; i < table.rows.length; i++) {
			var cells = [];
			for (j = 0; j < table.columns; j++) {
				// Без экранирования вертикальной черты таблица просто не
				// распарсится — это одно из двух мест, где экранирование нужно.
				cells.push(String(table.rows[i][j] || '').replace(/\|/g, '\|'));
			}
			out.push('| ' + cells.join(' | ') + ' |');
			if (i === 0) {
				var sep = [];
				for (j = 0; j < table.columns; j++) sep.push('---');
				out.push('| ' + sep.join(' | ') + ' |');
			}
		}
		return out.join('\n');
	}

	// --- Строки колонки → блоки --------------------------------------------
	//
	// Порядок детекторов здесь несущий, а не косметический. Код раньше таблиц:
	// в листинге широкие пробелы, и он прикидывается таблицей. Таблицы раньше
	// списков: ячейка «1.» — не пункт. Заголовки раньше списков: «1.2. Введение»
	// — заголовок, а не нумерованный пункт.

	function stripPua(text, ctx) {
		PUA.lastIndex = 0;
		if (!PUA.test(text)) { PUA.lastIndex = 0; return text; }
		PUA.lastIndex = 0;
		ctx.puaSeen = true;
		return text.replace(PUA, '').replace(/\s{2,}/g, ' ').trim();
	}

	function monoRunAt(lines, start, ctx) {
		var end = start;
		while (end < lines.length && lines[end].mono) end++;
		if (end - start < CODE_MIN_LINES) return start;

		var minX = Infinity, maxX = -Infinity, chars = 0, i;
		for (i = start; i < end; i++) {
			minX = Math.min(minX, lines[i].x0);
			maxX = Math.max(maxX, lines[i].x0);
			chars += lines[i].text.length;
		}
		if (maxX - minX > CODE_MAX_SPREAD * ctx.contentWidth) return start;
		if (chars / (end - start) < CODE_MIN_AVG_CHARS) return start;

		return end;
	}

	function tableRunAt(lines, start) {
		var end = start;
		while (end < lines.length && lines[end].cells.length > 1 && !lines[end].mono) end++;
		return end - start >= TABLE_MIN_ROWS ? end : start;
	}

	function blocksFromLines(lines, ctx) {
		var blocks = [], i = 0;

		while (i < lines.length) {
			var line = lines[i];

			if (ctx.options.code) {
				var monoEnd = monoRunAt(lines, i, ctx);
				if (monoEnd > i) {
					blocks.push(makeCodeBlock(lines.slice(i, monoEnd)));
					i = monoEnd;
					continue;
				}
			}

			if (ctx.options.tables) {
				var tableEnd = tableRunAt(lines, i);
				if (tableEnd > i) {
					var table = buildTable(lines.slice(i, tableEnd), ctx.contentWidth, ctx.leading);
					if (table) {
						blocks.push({ type: 'table', text: renderTable(table) });
						i = tableEnd;
						continue;
					}
					// Отказ — штатный путь: блок уедет обычными абзацами, а
					// пользователь узнает из предупреждения, что здесь было.
					ctx.warn('table-skipped', ctx.page);
				}
			}

			var heading = headingAt(lines, i, ctx);
			if (heading) {
				blocks.push({ type: 'heading', level: heading.level, text: stripPua(line.text, ctx) });
				i++;
				continue;
			}

			var listEnd = listRunAt(lines, i, ctx);
			if (listEnd > i) {
				blocks.push(makeListBlock(lines.slice(i, listEnd), ctx));
				i = listEnd;
				continue;
			}

			var paraEnd = paragraphRunAt(lines, i, ctx);
			blocks.push(makeParagraph(lines.slice(i, paraEnd), ctx));
			i = paraEnd;
		}

		return blocks;
	}

	function makeCodeBlock(lines) {
		var left = Infinity, i;
		for (i = 0; i < lines.length; i++) left = Math.min(left, lines[i].x0);

		var out = [];
		for (i = 0; i < lines.length; i++) {
			// Ширина знака моноширинных — ровно 0.6 em, отсюда и восстановление
			// отступа. Язык не указываем: неверная метка вводит модель в
			// заблуждение сильнее, чем её отсутствие.
			var pad = Math.max(0, Math.round((lines[i].x0 - left) / (MONO_CHAR_W * lines[i].size)));
			pad = Math.min(CODE_MAX_PAD, pad);
			out.push(new Array(pad + 1).join(' ') + lines[i].text);
		}
		return { type: 'code', text: FENCE + '\n' + out.join('\n') + '\n' + FENCE };
	}

	function headingAt(lines, i, ctx) {
		var line = lines[i];
		if (line.mono) return null;

		// Закладки — старший авторитет: когда они есть, структура уже описана.
		if (ctx.outline) {
			var level = outlineLevelOf(line.text, ctx.outline);
			// Сдвиг нужен, когда над всеми закладками есть строка крупнее их
			// всех: это заглавие документа, и оно должно встать выше разделов,
			// а не рядом с ними.
			if (level) return { level: Math.min(MAX_LEVELS, level + ctx.headingShift) };
		}

		if (line.size >= ctx.bodySize * HEAD_MIN_RATIO && headingShaped(line)) {
			if (ctx.outline) {
				if (line.size > ctx.outlineMaxSize + SIZE_MERGE) return { level: 1 };
				// Строка мимо закладок заголовком остаётся, но своей глубиной
				// не перебивает настоящую структуру документа.
				return { level: Math.min(MAX_LEVELS, ctx.outline.deepest + ctx.headingShift + 1) };
			}
			return { level: levelForSize(line.size, ctx.sizeLevels) };
		}

		if (ctx.uniformSize) {
			var prevGap = i > 0 ? line.y - lines[i - 1].y : ctx.leading * 2;
			var nextGap = i + 1 < lines.length ? lines[i + 1].y - line.y : 0;
			return scoreHeading(line, prevGap, nextGap, ctx);
		}
		return null;
	}

	function listRunAt(lines, start, ctx) {
		if (!bulletOf(lines[start]) && !orderedOf(lines[start])) {
			if (!DASH_RE.test(lines[start].text)) return start;
			// Тире в начале строки чаще диалог, чем маркер: требуем повтор.
			var dashes = 0, k = start;
			while (k < lines.length && DASH_RE.test(lines[k].text)) { dashes++; k++; }
			if (dashes < DASH_LIST_MIN) return start;
		}

		var end = start, seen = 0, lastNum = null, ordered = 0;
		while (end < lines.length) {
			var ln = lines[end];
			var b = bulletOf(ln), o = orderedOf(ln), dash = DASH_RE.test(ln.text);
			if (b || dash) { seen++; end++; continue; }
			if (o) {
				// Страховка от ложных списков: номера должны возрастать.
				// «1998, 2001» и «5, 5, 5» списком не являются.
				if (o.num !== null && lastNum !== null && o.num <= lastNum) break;
				if (o.num !== null) lastNum = o.num;
				ordered++;
				seen++;
				end++;
				continue;
			}
			// Висячий отступ: продолжение пункта, а не новый пункт. Без этого
			// правила каждая перенесённая строка раздувает список втрое.
			if (seen > 0 && ln.x0 > lines[end - 1].x0 + INDENT_EPS * ctx.bodySize * 0.5
				&& ln.y - lines[end - 1].y < PARA_GAP * ctx.leading) {
				end++;
				continue;
			}
			break;
		}

		if (ordered > 0 && ordered < LIST_SEQ_MIN && seen === ordered) return start;
		return seen >= 1 ? end : start;
	}

	function makeListBlock(lines, ctx) {
		var items = [], levels = [], i;

		for (i = 0; i < lines.length; i++) {
			var ln = lines[i];
			var b = bulletOf(ln), o = orderedOf(ln), dash = DASH_RE.test(ln.text);

			if (b || o || dash) {
				var rest = b ? b.rest : (o ? o.rest : ln.text.replace(DASH_RE, ''));
				items.push({ x0: ln.x0, ordered: !!o, num: o ? o.num : null, text: stripPua(rest, ctx) });
				levels.push(ln.x0);
				continue;
			}
			if (items.length) {
				var prev = items[items.length - 1];
				var piece = stripPua(ln.text, ctx);
				var glued = dehyphenate(prev.text, piece);
				prev.text = glued !== null ? glued : prev.text + ' ' + piece;
			}
		}

		// Уровень вложенности — позиция отступа в кластеризованном списке.
		var eps = INDENT_EPS * ctx.bodySize;
		var stops = [];
		levels.slice().sort(function (a, b2) { return a - b2; }).forEach(function (x) {
			if (!stops.length || x - stops[stops.length - 1] > eps) stops.push(x);
		});

		var out = [];
		for (i = 0; i < items.length; i++) {
			var depth = 0;
			for (var s = 0; s < stops.length; s++) { if (items[i].x0 >= stops[s] - eps) depth = s; }
			depth = Math.min(LIST_MAX_DEPTH - 1, depth);
			var marker = items[i].ordered ? ((items[i].num !== null ? items[i].num : i + 1) + '.') : '-';
			var indent = new Array(depth * (items[i].ordered ? 3 : 2) + 1).join(' ');
			out.push(indent + marker + ' ' + items[i].text);
		}
		return { type: 'list', text: out.join('\n') };
	}

	function paragraphRunAt(lines, start, ctx) {
		// Границы берём по самому абзацу, а не по колонке. Аннотация, врезка и
		// цитата у́же основного текста: меряя их полем страницы, каждую строку
		// принимаешь за последнюю и абзац рассыпается построчно.
		var runLeft = lines[start].x0;
		var runRight = lines[start].x1;
		var end = start + 1;

		while (end < lines.length) {
			var prev = lines[end - 1], ln = lines[end];
			if (ln.mono) break;
			if (bulletOf(ln) || orderedOf(ln)) break;
			if (headingAt(lines, end, ctx)) break;
			if (ln.y - prev.y > PARA_GAP * ctx.leading) break;
			// Абзацный отступ — единственный признак в книгах без отбивок.
			// Сравниваем с левым краем набранного абзаца: первая строка сама
			// бывает с отступом, и мерить надо от тех строк, что уже вошли.
			if (ln.x0 > runLeft + INDENT_MIN * ctx.bodySize) break;
			// Короткая последняя строка. Только на выключенном наборе (на
			// рваном правом крае правило врёт) и только когда есть с чем
			// сравнивать: по одной строке ширину абзаца не узнать.
			if (!ctx.ragged && end - start >= 2
				&& prev.x1 < runRight - SHORT_LINE * Math.max(1, runRight - runLeft)) {
				break;
			}
			runLeft = Math.min(runLeft, ln.x0);
			runRight = Math.max(runRight, ln.x1);
			end++;
		}
		return end;
	}

	function makeParagraph(lines, ctx) {
		var text = '';
		for (var i = 0; i < lines.length; i++) {
			var piece = stripPua(lines[i].text, ctx);
			if (text === '') { text = piece; continue; }
			var glued = dehyphenate(text, piece);
			text = glued !== null ? glued : text + ' ' + piece;
		}
		return { type: 'para', text: text };
	}

	// --- Сборка страницы ---------------------------------------------------

	function pageBlocks(page, ctx, repeats) {
		var kept = [], i;
		for (i = 0; i < page.lines.length; i++) {
			var ln = page.lines[i];
			if (isFurniture(ln, page, repeats)) continue;
			kept.push(ln);
		}
		if (!kept.length) return [];

		// Колонки уже разрезаны при сборке строк. Здесь остаётся разбить
		// страницу на связные куски по вертикальным разрывам, чтобы поля
		// врезки не мерились полем основного текста.
		var pieces = [kept];

		var blocks = [];
		for (i = 0; i < pieces.length; i++) {
			var col = pieces[i];
			if (!col.length) continue;

			var x0s = [], x1s = [], j;
			for (j = 0; j < col.length; j++) { x0s.push(col[j].x0); x1s.push(col[j].x1); }

			// Левая граница — десятый перцентиль, а не минимум: одиночный вынос
			// не должен сдвигать всю колонку и ломать разбор абзацных отступов.
			ctx.colLeft = percentile(x0s, 0.1);
			ctx.colRight = percentile(x1s, 0.9);
			ctx.contentWidth = Math.max(1, ctx.colRight - ctx.colLeft);
			// Рваный правый край — значит, правило короткой строки врёт.
			ctx.ragged = stdev(x1s) > RAGGED_STDEV * ctx.contentWidth;

			blocks = blocks.concat(blocksFromLines(col, ctx));
		}
		return blocks;
	}

	// --- Склейка абзацев через границу страницы ----------------------------

	var SENTENCE_END = /[.!?…:;»"]$/;

	function joinAcrossPages(pages) {
		for (var i = 1; i < pages.length; i++) {
			var prev = pages[i - 1].blocks, cur = pages[i].blocks;
			if (!prev.length || !cur.length) continue;

			var tail = prev[prev.length - 1], head = cur[0];
			if (tail.type !== 'para' || head.type !== 'para') continue;
			if (SENTENCE_END.test(tail.text)) continue;
			if (!/^[a-zа-яё]/.test(head.text)) continue;

			var glued = dehyphenate(tail.text, head.text);
			tail.text = glued !== null ? glued : tail.text + ' ' + head.text;
			cur.shift();
			// Отметка страницы встаёт перед следующим блоком, а не в середину
			// склеенного абзаца: разрыв внутри фразы модели только мешает.
			pages[i].markShifted = true;
		}
	}

	// --- Итоговый markdown -------------------------------------------------

	function renderBlock(block) {
		if (block.type === 'heading') {
			return new Array(block.level + 1).join('#') + ' ' + block.text;
		}
		if (block.type === 'para') {
			// Ведущий спецсимвол в строке, которую мы заголовком или списком не
			// признали, пришлось бы экранировать — иначе markdown прочтёт её
			// не так, как мы решили.
			return block.text.replace(/^([#>])/, '\\$1');
		}
		return block.text;
	}

	function assemble(pages, meta, options, state) {
		var out = [], i, j;

		if (options.frontMatter) {
			var head = ['---'];
			if (meta.file) head.push('file: ' + meta.file);
			head.push('pages: ' + meta.pagesTotal);
			if (meta.title) head.push('title: ' + meta.title);
			if (meta.author) head.push('author: ' + meta.author);
			head.push('---');
			out.push(head.join('\n'));
		}

		for (i = 0; i < pages.length; i++) {
			var page = pages[i];
			if (!page.blocks.length) continue;
			if (options.pageMarks) {
				out.push('<!-- стр. ' + page.number + ' -->');
			}
			for (j = 0; j < page.blocks.length; j++) {
				out.push(renderBlock(page.blocks[j]));
			}
		}

		if (state.truncated) {
			out.push('> Документ обрезан: обработано ' + state.pages
				+ ' страниц из ' + meta.pagesTotal + '.');
		}

		return out.join('\n\n').replace(/\n{3,}/g, '\n\n').trim();
	}

	// --- Конвейер ----------------------------------------------------------

	function convert(file, userOptions) {
		var options = extend(DEFAULTS, userOptions);
		var state = { pages: 0, truncated: false, chars: 0 };
		var warnings = [], warned = {};

		function warn(code, page) {
			// По одному предупреждению на код и страницу: сотня одинаковых
			// строк в статусе ничего не сообщает.
			var key = code + ':' + (page || 0);
			if (warned[key]) return;
			warned[key] = true;
			warnings.push({ code: code, page: page || 0 });
		}

		function aborted() {
			return !!(options.signal && options.signal.aborted);
		}

		var started = Date.now();
		var pdfjsLib = null, pdf = null, loadingTask = null;
		var pages = [], meta = { file: file && file.name ? file.name : '', title: '', author: '', pagesTotal: 0 };

		function report(phase, page, total) {
			if (!options.onProgress) return;
			options.onProgress({
				phase: phase,
				page: page || 0,
				pages: total || 0,
				percent: total ? Math.round((page / total) * 100) : 0
			});
		}

		report('lib', 0, 0);

		return ensureLib().then(function (lib) {
			pdfjsLib = lib;
			return readFile(file);
		}).then(function (data) {
			report('load', 0, 0);
			loadingTask = pdfjsLib.getDocument({
				data: data,
				// Мы ничего не рисуем, только читаем текстовый слой. eval здесь
				// не нужен ни для чего, а без него исчезает целый класс дыр в
				// разборе шрифтов.
				isEvalSupported: false,
				disableFontFace: true,
				useSystemFonts: false
			});

			if (options.onPassword) {
				loadingTask.onPassword = function (updatePassword, reason) {
					Promise.resolve(options.onPassword(reason)).then(function (pin) {
						updatePassword(pin);
					}, function () {
						loadingTask.destroy();
					});
				};
			}

			return loadingTask.promise;
		}).then(function (doc) {
			pdf = doc;
			meta.pagesTotal = doc.numPages;
			return Promise.all([
				doc.getMetadata().catch(function () { return null; }),
				doc.getOutline().catch(function () { return null; })
			]);
		}).then(function (both) {
			var info = both[0] && both[0].info;
			if (info) {
				meta.title = info.Title ? cleanText(String(info.Title)).trim() : '';
				meta.author = info.Author ? cleanText(String(info.Author)).trim() : '';
			}
			var outline = both[1] && both[1].length ? buildOutlineIndex(flattenOutline(both[1], 0, [])) : null;

			var limit = Math.min(pdf.numPages, options.maxPages);
			if (limit < pdf.numPages) state.truncated = true;

			return readPages(limit, outline);
		}).then(function (outline) {
			// Зонд выше обрывает длинный скан рано, но документ короче зонда до
			// него не доживает — проверяем ещё раз по итогу.
			if (!pages.length) throw noTextError();
			if (state.chars < NO_TEXT_CHARS_PER_PAGE * pages.length) throw noTextError();

			var metrics = measureBody(pages);
			var sizeLevels = headingSizeLevels(pages, metrics.bodySize);
			var repeats = collectRepeats(pages);

			var ctx = {
				options: options,
				bodySize: metrics.bodySize,
				leading: metrics.leading,
				sizeLevels: sizeLevels,
				// Документ с одним кеглем на всё — экспорт из Word, где
				// заголовки только жирные. Включаем запасной путь.
				uniformSize: sizeLevels.order.length === 0,
				numberedConsistent: countNumbered(pages) >= 3,
				allUpper: upperRatio(pages) > 0.6,
				outline: outline,
				outlineMaxSize: 0,
				headingShift: 0,
				warn: warn,
				puaSeen: false,
				page: 0,
				colLeft: 0,
				colRight: 0,
				contentWidth: 1,
				ragged: false
			};

			if (outline) {
				var om = measureOutlineSizes(pages, outline, metrics.bodySize);
				ctx.outlineMaxSize = om.outlineMaxSize;
				ctx.headingShift = om.shift;
			}

			var dropToc = options.dropToc && !!outline;
			for (var i = 0; i < pages.length; i++) {
				ctx.page = pages[i].number;
				if (dropToc && isTocPage(pages[i])) { pages[i].blocks = []; continue; }
				pages[i].blocks = pageBlocks(pages[i], ctx, repeats);
			}

			if (options.joinPages) joinAcrossPages(pages);
			if (ctx.puaSeen) warn('pua-dropped', 0);
			if (state.truncated) warn('truncated', 0);

			var markdown = assemble(pages, meta, options, state);
			if (markdown.length > options.maxChars) {
				markdown = markdown.slice(0, options.maxChars);
				state.truncated = true;
				warn('truncated', 0);
			}

			return {
				markdown: markdown,
				meta: { title: meta.title, author: meta.author },
				pages: state.pages,
				pagesTotal: meta.pagesTotal,
				truncated: state.truncated,
				warnings: warnings
			};
		}).catch(function (err) {
			if (pdf) { try { pdf.destroy(); } catch (e) {} }
			throw translateError(err);
		});

		// Страницы строго по одной: пятьсот параллельных getPage кладут воркер
		// по памяти. Цепочка промисов, а не Promise.all.
		function readPages(limit, outline) {
			var chain = Promise.resolve();
			for (var n = 1; n <= limit; n++) {
				chain = chain.then(makeStep(n, limit));
			}
			return chain.then(function () { return outline; });
		}

		function makeStep(n, limit) {
			return function () {
				if (aborted()) throw fail('aborted', 'Разбор отменён.');
				if (Date.now() - started > options.timeoutMs) {
					state.truncated = true;
					return null;
				}
				if (state.truncated && state.chars === 0 && n > NO_TEXT_PROBE) return null;

				report('text', n, limit);

				return pdf.getPage(n).then(function (page) {
					var viewport = page.getViewport({ scale: 1 });
					return page.getTextContent().then(function (content) {
						var built = pageLines(pdfjsLib, content.items, content.styles, viewport, options.columns);
						var lines = built.lines;
						var garbage = 0, chars = 0, i;
						for (i = 0; i < lines.length; i++) {
							chars += lines[i].text.length;
							garbage += lines[i].garbage * lines[i].text.length;
						}

						// Сломанная кодировка отравляет контекст модели сильнее,
						// чем пропущенная страница.
						if (chars > 0 && garbage / chars > GARBAGE_PAGE) {
							warn('unmapped-font', n);
							lines = [];
						}
						if (!lines.length) warn('no-text-page', n);
						if (content.items.length > MAX_ITEMS_PER_PAGE) warn('slow', n);
						if (built.columns > 1) warn('columns-guessed', n);

						state.chars += chars;
						state.pages = n;
						pages.push({
							number: n,
							lines: lines,
							width: viewport.width,
							height: viewport.height,
							blocks: []
						});

						page.cleanup();

						// Скан читать до конца незачем: пятистраничного зонда
						// хватает, чтобы понять, что текстового слоя нет.
						if (n >= NO_TEXT_PROBE && state.chars < NO_TEXT_CHARS_PER_PAGE * n) throw noTextError();

						return n % YIELD_EVERY === 0 ? yieldToUi() : null;
					});
				});
			};
		}
	}

	function noTextError() {
		return fail('no-text', 'В PDF нет текстового слоя — похоже, это скан. Распознавание здесь не делается.');
	}

	function translateError(err) {
		if (err && err.code) return err;
		var name = err && err.name ? err.name : '';
		if (name === 'PasswordException') {
			return fail('encrypted', 'PDF защищён паролем.');
		}
		if (name === 'InvalidPDFException') {
			return fail('corrupt', 'Файл повреждён или это не PDF.');
		}
		return fail('failed', 'Не удалось разобрать PDF: ' + (err && err.message ? err.message : 'неизвестная ошибка'));
	}

	function readFile(file) {
		if (file instanceof ArrayBuffer) return Promise.resolve(new Uint8Array(file));
		if (file && file.arrayBuffer) {
			return file.arrayBuffer().then(function (buf) { return new Uint8Array(buf); });
		}
		return new Promise(function (resolve, reject) {
			var reader = new FileReader();
			reader.onload = function () { resolve(new Uint8Array(reader.result)); };
			reader.onerror = function () { reject(fail('corrupt', 'Файл не читается.')); };
			reader.readAsArrayBuffer(file);
		});
	}

	function countNumbered(pages) {
		var n = 0;
		for (var i = 0; i < pages.length; i++) {
			for (var j = 0; j < pages[i].lines.length; j++) {
				if (NUMBERED_RE.test(pages[i].lines[j].text)) n++;
			}
		}
		return n;
	}

	function upperRatio(pages) {
		var upper = 0, total = 0;
		for (var i = 0; i < pages.length; i++) {
			for (var j = 0; j < pages[i].lines.length; j++) {
				total++;
				if (looksUpper(pages[i].lines[j].text)) upper++;
			}
		}
		return total ? upper / total : 0;
	}

	window.KV04PdfMarkdown = { convert: convert, ensureLib: ensureLib };
})();
