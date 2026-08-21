# Codebase structure

```
D:\kv04_ru\
  AGENTS.md
  docs/ONBOARDING.md     agent onboarding
  docs/specs/            0001-homepage-private-diary.md
  .cursor/rules/         kv04-core, modules, components, specs
  public_html/           DOCUMENT ROOT
    index.php            diary shell (prolog_before, no shop template)
    local/
      modules/kv04.diary/
        load.php, boot.php, include.php
        lib/             installer, auth, pinservice, noteservice, html
        assets/diary-theme.css
        install/
      components/kv04/
        diary.pin/       PIN pad
        diary.feed/      notes feed
      php_interface/init.php
    bitrix/              kernel — do not edit
```

Edit: `public_html/local/**`, `index.php`. Do not edit: `bitrix/modules`, `main.broken.20260627`, `bitrix/tmp/restore.removed`.
Shop catalog IBLOCK_ID 2 still exists, not on homepage (spec 0001).
