#!/usr/bin/env bash
# Смоук kv04.ru: быстрый ответ «работает / сломалось» после выкладки.
#
#   bin/smoke.sh
#
# Все проверки строго читающие, как у соседей (traktoristy/polurama):
# ничего не создаётся и не меняется, авторизаций нет. Код возврата 1 при
# любом провале — годится для автоматики.
set -u

HOST=infopolura@77.222.40.47
REMOTE_ROOT=/home/i/infopolura/kv04_ru
SITE=https://kv04.ru
# В консоли сервера PHP по умолчанию 5.2 — бинарь указываем явно.
# Веб kv04.ru работает на 8.4 (phpinfo, 2026-08-29) — линтуем той же версией.
PHP_BIN=/usr/bin/php8.4
# Путь дневника (опция kv04.diary → path). Пусто — дневник на корне.
# Менять синхронно с настройкой в приложении или админке.
DIARY_PATH=day

cd "$(dirname "$0")/.." || exit 2

pass=0; fail=0
ok()   { pass=$((pass+1)); printf 'ok   %s\n' "$1"; }
bad()  { fail=$((fail+1)); printf 'FAIL %s\n' "$1"; }

body=$(mktemp)
trap 'rm -f "$body"' EXIT

if [ -n "$DIARY_PATH" ]; then
	DIARY_URL="$SITE/$DIARY_PATH"
	# 1а. Корень вернулся магазину: прежняя главная, без следов дневника.
	code=$(curl -sS -o "$body" -w '%{http_code}' "$SITE/")
	if [ "$code" = 200 ] && grep -q 'Тренды сезона' "$body" && ! grep -q 'Мой дневник' "$body"; then
		ok "корень — магазинная главная"
	else
		bad "корень: код $code, магазинная главная не узнана (или в ней дневник)"
	fi
else
	DIARY_URL="$SITE"
fi

# 1б. Страница дневника отвечает и отдаёт гостю пин-пад (маркер из diary.pin).
code=$(curl -sSL -o "$body" -w '%{http_code}' "$DIARY_URL/")
[ "$code" = 200 ] && ok "дневник 200" || bad "дневник: код $code"
grep -q 'Мой дневник' "$body" && ok "пин-пад на месте" || bad "пин-пад не найден в ответе дневника"

# 2. PWA: воркер и манифест отвечают с пути дневника.
hdr=$(curl -sS -o "$body" -D - "$DIARY_URL/sw.js")
if echo "$hdr" | head -1 | grep -q ' 200' && echo "$hdr" | grep -qi '^cache-control: no-cache' && grep -q 'kv04-diary' "$body"; then
	ok "sw.js отдаётся с пути дневника, no-cache"
else
	bad "sw.js: нет 200/no-cache/содержимого"
fi
curl -sS -o "$body" "$DIARY_URL/manifest.webmanifest"
if grep -q '"start_url": *"'"${DIARY_PATH:+/$DIARY_PATH}"'/"' "$body"; then
	ok "манифест со start_url ${DIARY_PATH:+/$DIARY_PATH}/"
else
	bad "манифест: start_url не совпал с путём"
fi

# 3. Админка отдаёт форму логина (не 500 и не пустота).
code=$(curl -sSL -o "$body" -w '%{http_code}' "$SITE/bitrix/admin/")
if [ "$code" = 200 ] && grep -qi 'USER_LOGIN\|Авторизация' "$body"; then
	ok "админка просит логин"
else
	bad "админка: код $code, форма логина не найдена"
fi

# 4. Несуществующая share-ссылка отвечает 404 (и отозванная отвечает так же).
code=$(curl -sS -o /dev/null -w '%{http_code}' "$SITE/d/00000000000000000000000000000000")
[ "$code" = 404 ] && ok "фейковая share-ссылка -> 404" || bad "фейковая share-ссылка: код $code"

# 5. Исходники модуля не отдаются как текст.
curl -sS -o "$body" "$SITE/local/modules/kv04.diary/lib/auth.php"
if grep -q '<?php' "$body"; then
	bad "исходник lib/auth.php отдаётся наружу"
else
	ok "исходники модуля закрыты"
fi

# 6. Статика темы дневника доступна.
code=$(curl -sS -o /dev/null -w '%{http_code}' "$SITE/local/modules/kv04.diary/assets/diary-theme.css")
[ "$code" = 200 ] && ok "тема дневника отдаётся" || bad "diary-theme.css: код $code"

# 7. Синтаксис ключевых файлов — боевым PHP на сервере.
if ssh -o BatchMode=yes "$HOST" "cd $REMOTE_ROOT/public_html && $PHP_BIN -l index.php && $PHP_BIN -l local/modules/kv04.diary/include.php && $PHP_BIN -l local/modules/kv04.diary/lib/path.php && $PHP_BIN -l local/modules/kv04.diary/pub/index.php && $PHP_BIN -l local/modules/kv04.diary/options.php && $PHP_BIN -l local/components/kv04/diary.feed/class.php" > /dev/null 2>&1; then
	ok "php -l ключевых файлов ($PHP_BIN)"
else
	bad "php -l нашёл синтаксическую ошибку (или ssh недоступен)"
fi

# 8. Дрейф деплоя: прод совпадает с локальным кастомом.
if bin/deploy.sh --check > /dev/null 2>&1; then
	ok "прод совпадает с локальным кастомом"
else
	bad "дрейф деплоя — прогнать bin/deploy.sh"
fi

# 9. Журнал ошибок Bitrix — информационно, не роняет смоук.
ssh -o BatchMode=yes "$HOST" "f=$REMOTE_ROOT/public_html/bitrix/modules/error.log; [ -f \$f ] && echo \"журнал: \$(du -h \$f | cut -f1), хвост:\" && tail -2 \$f" 2>/dev/null | sed 's/^/     /'

echo "----"
echo "прошло $pass, провалено $fail"
[ "$fail" = 0 ]
