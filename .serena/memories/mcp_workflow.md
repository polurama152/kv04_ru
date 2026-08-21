# MCP workflow for kv04_ru

## Serena
Project `D:\kv04_ru`. PHP. Kernel ignored in `project.yml` (`public_html/bitrix/**`).
Flow: `list_dir` / `find_file` → `get_symbols_overview` → `find_symbol` / `search_for_pattern` → edit.
Bitrix pages are often procedural — `search_for_pattern` / `replace_content` over `find_symbol`.

## bitrix (camouf bxmcp)
Public Bitrix Framework index, not this site's DB. Use for signatures, events, ORM, `searchDocs`.
Do not use it for kv04 IBLOCK IDs or `include/` copy — that is files/Serena.

## context7
External libraries only. Not a substitute for Bitrix MCP.
