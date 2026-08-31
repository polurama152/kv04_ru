# Модули `/local/modules`

Общие правила проекта — `/CLAUDE.md` в корне репозитория.

Партнёрский id: `kv04.<name>`. Класс установки: `kv04_<name>`. NS: `Kv04\<Name>`.
Файлы в `lib/` — lowercase (`installer.php` → `Kv04\Diary\Installer`).

```
kv04.example/
  install/index.php, version.php, components/, lang/
  lib/          # D7-классы
  lang/ru/      # зеркало путей, $MESS
  include.php   # канон: lib + registerAutoLoadClasses(null, /local/ paths)
  boot.php      # register + includeModule, затем всегда include.php
  .settings.php # контроллеры
  options.php   # админ-настройки
```

## Автозагрузка

`includeModule()` ставит holder в `local`. `registerAutoLoadClasses('kv04.x', относительный
путь)` **без** успешного `includeModule()` ищет файл в `/bitrix/modules/` — fatal.

- Сайт: `load.php` → `kv04DiaryLoadModule()` (`boot.php` → **всегда** `include.php`).
- `registerAutoLoadClasses(null, ['Kv04\\Diary\\X' => '/local/modules/kv04.diary/lib/x.php'])`.
- Маркетплейс: `DoInstall` → `registerModule` + `InstallFiles`. Не полагаться на `boot.php`
  у клиента.

## PHP 8.4 / инфоблок

Множественное файловое свойство: не `SetPropertyValuesEx` с integer ID (TypeError на
PHP 8.4). Формат payload `[['VALUE' => id], …]`; запись MEDIA — `NoteService::setMediaProperty()`
(прямая запись в `b_iblock_element_prop_m*`).

`Html::sanitize()`: блоки `<pre>` изолировать до `strip_tags`; не применять regex `on\w+=`
к тексту кода (`<?`, `$sSectionName` ломаются).

Компоненты модуля — в `install/components/kv04/` (долг, см. спеку `0001`). Сейчас дневник
в `/local/components/kv04/`.
