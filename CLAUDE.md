# kv04.ru

Bitrix-сайт. Кастом только в `public_html/local/`, ядро `public_html/bitrix/` не править.
Продукт — модуль `kv04.diary`: личный дневник по пину, UI как Telegram, без `$USER`.
Цель — модули `kv04.*` на [Маркетплейс](https://marketplace.1c-bitrix.ru/).

Этот файл — канон, он грузится сам. Отдельно читать ничего не нужно.

## Нельзя

- Править ядро `public_html/bitrix/**` (кроме явного запроса) и `D:\EDT_dev`.
- Класть кастом в `/bitrix/modules/` «чтобы завелось».
- Печатать пароли из `dbconn.php` / `.settings.php`.
- Править `docs/specs/`, `docs/JOURNAL.md` и память без явной команды. По умолчанию — код.

## Где что

| Слой | Где |
|------|-----|
| Что решили и почему | `docs/specs/` (индекс — `README.md`) |
| Замеры, закрытый долг, ловушки площадки | `docs/JOURNAL.md` |
| Правишь модуль или компонент | сначала открой `CLAUDE.md` в `local/modules/` или `local/components/` |
| Деплой и смоук | `bin/` |
| API Bitrix | MCP `bitrix` (`searchDocs`), **не** grep ядра |
| Внешние библиотеки | MCP `context7` |

## Состояние

**Идентичность — адрес дневника, пин — секрет внутри неё** (спека `0006`). Почта
необязательна: единственный путь назад, если забыты и адрес, и пин.

Дневник живёт на пути из опции `path` (на kv04.ru — `uday`), у владельца может быть
личный адрес `/<путь>/<адрес>/`. Корень сайта — прежняя магазинная главная.

**Загрузка:** `local/php_interface/init.php` → `load.php` (`kv04DiaryLoadModule()`) →
`boot.php` (register + `includeModule`) → **всегда** `include.php`.

**Данные.** HL `Kv04DiaryKey` / `kv04_diary_keys` (поля `UF_*` — в `installer.php`). Свои
таблицы `kv04_diary_`: `attempts` (лестница блокировок), `books` (дневники, до 50),
`trash`, `shares`, `slugs` (адреса).
Инфоблок тип `kv04`, код `diary`: `OWNER`, `BOOK`, `MEDIA` (файл, множественное),
`DELETED_AT`; удалённое — `ACTIVE = N`. Опции: `pepper`, `hlblock_id`, `iblock_id`,
`schema_version`, `path`, `owner_settings`, `legacy_paths`. Cookie `KV04_DIARY`
(сессия, 10 ч) и `KV04_DIARY_DEVICE` (браузер, год).

**Схема данных** — `Installer::SCHEMA_VERSION` (сейчас `10`). Поднимать при изменении
структуры HL или инфоблока: `ensure()` один раз переприменит её на каждом сервере.

**POST:** 18 действий в ленте, 4 на пин-паде — список в `processPost()` нужного
`class.php`. Всегда `check_bitrix_sessid()`, ответ — JSON.

## Роутинг: чего нет в репозитории

**`public_html/urlrewrite.php` в git содержит ноль правил `kv04.diary`.** Их пишет
`Path::applyRewrite()` (`lib/path.php:152`) прямо на сервере при сохранении настроек.
По коммиту кажется, что дневника в роутинге нет вовсе — это не так.

Все правила под `ID => kv04.diary`, все ведут в `pub/`:

- `/d/<32 hex>` → `pub/index.php?d=$1` — дневник по ссылке, при любом пути
- `<путь>/` и `<путь>/<адрес>/` → `pub/index.php` (второе с `u=$1`, одно правило на всех)
- `<путь>[/<адрес>]/sw.js` → `pub/sw.php`, `…/manifest.webmanifest` → `pub/manifest.php`
- прежние пути из `legacy_paths` → `301` на нынешний

Спецстроки `.htaccess`: `301` на https по `X-Forwarded-Proto` (не по `%{HTTPS}` — Apache
за nginx не знает о TLS), `[F]` на шесть имён отладочных скриптов, всё несуществующее
уходит в `bitrix/urlrewrite.php`. Синтаксис только Apache 2.2 — на площадке 2.2.29.

Живое значение `path` читает с сервера `bin/smoke.sh:20` — брать оттуда, не зашивать.
Магазинная часть сайта роутится файлами и находится обычным `find`.

## Карта файлов

Модуль `public_html/local/modules/kv04.diary/`:

- `pub/` — `index.php` (шелл страницы, сам поднимает пролог), `sw.php`, `manifest.php`
- `load.php`, `boot.php`, `include.php` — точка входа и автозагрузка
- `lib/` — `installer` (HL, инфоблок, pepper), `path` (путь, rewrite, переезды),
  `slugservice` (личные адреса), `pinservice` (вход и регистрация), `attemptlimiter`
  (лестница блокировок), `auth` (cookie, сессия), `bookservice`, `noteservice`
  (заметки, MEDIA, корзина), `shareservice`, `html` (sanitize, изоляция `<pre>`)
- `include/render-items.php` — разметка заметок, общая для ленты и страницы по ссылке
- `assets/` — тема, highlight.js 11.9.0 (свой, не cdnjs), PWA
- `options.php`, `admin/menu.php` — настройки и раздел в админке

Компоненты `public_html/local/components/kv04/`: `diary.pin` (вход и регистрация),
`diary.feed` (лента, AJAX), `diary.share` (по ссылке, только чтение).

Прочее: `local/php_interface/init.php` подключает `load.php`;
`public_html/index.php` — магазинная главная, дневника не касается.

## Как кодить

- Сначала Bitrix D7: `Loader`, компоненты, `Option`, ORM, `check_bitrix_sessid()`.
- Сигнатуры Bitrix — у MCP, не грепом ядра.
- Мутации только при `POST && check_bitrix_sessid()`; ответ AJAX — JSON.
- PHP: UTF-8, `<?php`, namespace `Kv04\...`. UI-тексты русские, ключи `$MESS` английские.
- Изящные идеи из других фреймворков можно; публичный контракт остаётся Bitrix
  (`partner.module`, `lib/`, `install/`, `lang/`).
- Публичные страницы магазина местами на коротких тегах — держать стиль файла.

**Спеки.** Одна спека = одно изменение продукта, `docs/specs/NNNN-kebab-title.md`, шаблон
рядом. Поведение изменилось — править ту же спеку, не плодить «0001b». Правил кодирования
в спеках нет. Только по явной команде.

## Deploy

PhpStorm, сервер `kv04`, `autoUpload=Always` — правка может уйти на прод сразу, но
только пока IDE открыта. Правки из CLI довозить `bin/deploy.sh` (`--check` — только
сверка), после — `bin/smoke.sh`. SSH `infopolura@77.222.40.47`, сайт
`/home/i/infopolura/kv04_ru/public_html/`. Веб на PHP 8.4; в консоли сервера по
умолчанию 5.2 — бинарь звать явно (`/usr/bin/php8.4`).

## Ловушки

Истории целиком — `docs/JOURNAL.md`.

- **PHP 8.1 и выше обязателен:** ядро содержит `enum`, на 8.0 весь сайт отдаёт 500
  ещё до нашего кода.
- **Автозагрузка.** `registerAutoLoadClasses('kv04.diary', относительный путь)` без
  успешного `includeModule()` ищет файл в `/bitrix/modules/` — fatal. Поэтому
  `include.php` регистрирует с `module = null` и путями `/local/modules/...`.
- **PHP 8.4 и MEDIA:** `SetPropertyValuesEx` с integer ID падает, запись —
  `NoteService::setMediaProperty()` прямо в `b_iblock_element_prop_m*`.
- **`Html::sanitize()`** изолирует `<pre>` до `strip_tags`; regex `on\w+=` к тексту
  кода не применять — съедает `<?` и `$переменные`.
- **`epilog_after.php` не подключать** — конфликт с `main.broken.*` на сервере.
- **AJAX «Нет связи» — обычно HTTP 500**, то есть HTML вместо JSON.
- **Гость и вошедший идут разными ветками.** Проверять оба: главная отвечает 200,
  пока лента падает.

## Git

Один репозиторий, одна постоянная ветка `main`; новые ветки — только по явному
согласованию, слитую удалять сразу и локально, и на remote. Remote —
`github.com/polurama152/kv04_ru`, после коммита `git push`.

**Репозиторий публичный.** Пароли, ключи и адреса внутренних сервисов не коммитятся
ни в каком виде. Личные адреса владельцев (`kv04_diary_slugs.SLUG`) — тоже: адрес
плюс четыре цифры пина это вся дверь.
