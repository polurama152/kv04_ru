# 0001 — Личный дневник на главной

- Статус: `done`
- Дата: 2026-08-21
- Модуль: `kv04.diary`

## Зачем

Главная kv04.ru — закрытый микро-дневник посетителя (формат Telegram), без аккаунта Bitrix `$USER`. Витрина магазина с главной снята.

## Решение

- **Идентичность = 4-значный PIN.** «Войти» / «Создать дневник» (PIN дважды). Занятый PIN → «такой пин занят».
- **Лок как в банке:** 3 неверных входа → 24 часа. Неизвестный PIN — счётчик по IP (`Option` `ipfail_*`).
- **Сессия 10 часов** с момента входа, без idle-timeout. Cookie `KV04_DIARY` (HMAC + pepper) + PHP-сессия.
- **Заметки только свои:** AJAX CRUD, текст без заголовка, код в `<pre><code>` + Highlight.js, медиа jpg/png/gif/webp/mp4/webm, вставка из буфера, несколько файлов за раз.
- Гость видит только PIN-пад.
- PIN: HMAC-SHA256(PIN, pepper), не `password_hash` (нужен lookup). Защита — лок по записи PIN и по IP.

## Реализованный UX

- Оболочка: `prolog_before.php` + свой HTML; **не** `bitrix/header.php` / `footer.php`. Без хедера/футера/сайдбара магазина.
- Тема Telegram dark: `local/modules/kv04.diary/assets/diary-theme.css`. Фон `#0e1621`, акцент `#2AABEE`. Контейнер до ~1040px на desktop.
- PIN: экранный pad + клавиатура (0–9, Backspace, Enter, Escape).
- Лента: клик по пузырю = «Изменить»; Ctrl+Enter = «Готово» (composer и edit); Esc = диалог «Сохранить изменения?» (Enter = Да, стрелки = Да/Нет).
- Медиа: сетка превью, lightbox (повторный клик сворачивает), × удаление, «Прикрепить» в edit, `multiple` на file input.
- Код: Highlight.js `atom-one-dark`; sanitize сохраняет `<?`, `<?php`, `$a < $b`.

## Вне скоупа

- Магазин, каталог, реклама на главной
- Восстановление PIN, email, Bitrix-пользователь
- Публичные / общие дневники
- Выкладка на Маркетплейс (долг ниже)

## Контракт

**Страница:** `/` (`public_html/index.php`) — PIN-пад или лента.

**Компоненты:** `kv04:diary.pin`, `kv04:diary.feed` (`/local/components/kv04/`).

**POST + `sessid`:**

| action | Где | Тело |
|--------|-----|------|
| `login` / `create` | pin | `pin`; create — ещё подтверждение |
| `logout` | pin, feed | — |
| `add` | feed | `text`, `media[]` |
| `edit` | feed | `id`, `text` |
| `delete` | feed | `id` |
| `attach` | feed | `id`, `media[]` |
| `detach_media` | feed | `id`, `file_id` |

**HL** `Kv04DiaryKey` / `kv04_diary_keys`: `UF_PIN_HASH`, `UF_OWNER_ID`, `UF_FAILS`, `UF_LOCKED_UNTIL`.

**Инфоблок** тип `kv04`, код `diary`: `OWNER` (строка), `MEDIA` (файл, множественное). Гости `GROUP_ID` 2 = `W`.

**Опции** `kv04.diary`: `pepper`, `hlblock_id`, `iblock_id`, `ipfail_*`.

**Сессия:** cookie `KV04_DIARY`, TTL 36000 с. Лента `CACHE_TYPE=N`.

**Автозагрузка:** `load.php` → `kv04DiaryLoadModule()` → `boot.php` → **всегда** `include.php`. Автозагрузка с `module=null` и путями `/local/modules/...`.

## Технические ограничения

- `epilog_after.php` не подключаем — конфликт с `main.broken.*` на сервере.
- MEDIA: payload `[['VALUE' => id], …]`. На PHP 8.4 `SetPropertyValuesEx` с integer ID падает — запись через `NoteService::setMediaProperty()` в `b_iblock_element_prop_m*`.
- `Html::sanitize()` изолирует `<pre>` до `strip_tags`; regex `on\w+=` к тексту кода не применять.
- Заметки, сохранённые до фикса sanitize, могут содержать мусор `KV04PRE0` — только ручная правка.
- AJAX «Нет связи» часто = HTTP 500 (HTML вместо JSON).

## Файлы

- `local/modules/kv04.diary/` — `load.php`, `boot.php`, `include.php`, `lib/*`, `assets/diary-theme.css`, `install/`
- `local/components/kv04/diary.pin/`, `diary.feed/`
- `local/php_interface/init.php`
- `index.php`

## Долг (Маркетплейс)

- Компоненты в `install/components/kv04/` + `InstallFiles` / `CopyDirFiles`.
- `lang/ru/`, `.description.php`, `.parameters.php`, `PARTNER_NAME` / `PARTNER_URI`.
- У клиента: только установка + `includeModule`, без `boot.php`.
- AJAX — контроллеры D7 (`.settings.php`), не POST в `class.php`.
- PIN из 4 цифр: модель угроз или более длинный секрет.
- Проверить, что `GROUP_ID 2 => W` не даёт читать/менять чужие элементы в публичке.
- `options.php` и админ-страница модуля.
