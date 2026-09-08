#!/usr/bin/env bash
# Фолбэк-деплой kv04.ru: сверка md5 кастома с продом и доставка расхождений.
#
#   bin/deploy.sh          сверить и довезти расхождения по scp
#   bin/deploy.sh --check  только сверить, ничего не копируя (exit 1 при дрифте)
#
# Штатный путь выкладки — autoUpload PhpStorm (см. .cursor/rules/kv04-core.mdc).
# Этот скрипт для случая, когда правки сделаны из CLI при закрытой IDE и на
# прод не уехали. Возит ТОЛЬКО кастом: public_html/local/, index.php,
# .htaccess. Ядро Bitrix не трогает никогда — оно обновляется на сервере.
set -u

HOST=infopolura@77.222.40.47
REMOTE_ROOT=/home/i/infopolura/kv04_ru
SITE=https://kv04.ru

cd "$(dirname "$0")/.." || exit 2

CUSTOM=(public_html/local public_html/index.php public_html/.htaccess)

CHECK_ONLY=0
[ "${1-}" = "--check" ] && CHECK_ONLY=1

# Манифест «md5  путь» по кастому; сверку делает серверный `md5sum -c`,
# поэтому наружу уходит один ssh, а не по вызову на файл.
manifest=$(mktemp)
trap 'rm -f "$manifest"' EXIT
# CLAUDE.md лежат рядом с кодом (правила подтягиваются по адресу задачи),
# но на сервере им делать нечего — из манифеста исключаем.
find "${CUSTOM[@]}" -type f -not -name 'CLAUDE.md' -print0 | sort -z | xargs -0 md5sum > "$manifest"

# Код возврата важен: md5sum -c отвечает 0 при полном совпадении и 1 при
# расхождениях, всё прочее — упавший ssh. Без этой проверки тишина упавшего
# ssh неотличима от «всё совпало», и деплой рапортует OK, ничего не сверив
# (случилось 2026-09-08 под load average 17).
drift() {
	local out rc
	out=$(ssh -o BatchMode=yes -o ConnectTimeout=30 "$HOST" "cd $REMOTE_ROOT && md5sum -c --quiet -" < "$manifest" 2>&1)
	rc=$?
	if [ "$rc" -gt 1 ]; then
		printf '%s\n' "$out" >&2
		return 2
	fi
	printf '%s\n' "$out" | sed -n 's/^\(.*\): FAILED.*$/\1/p' | sort -u
}

diverged=$(drift) || { echo 'Сверка с продом не удалась (ssh/md5sum): прод не проверен.'; exit 2; }

if [ -z "$diverged" ]; then
	echo "OK: прод совпадает с локальным кастомом ($(wc -l < "$manifest") файлов)."
	exit 0
fi

echo "Расхождения с продом:"
echo "$diverged" | sed 's/^/  /'

if [ "$CHECK_ONLY" = 1 ]; then
	exit 1
fi

# Каталоги создаём заранее одним ssh: scp сам их не заводит.
dirs=$(echo "$diverged" | xargs -n1 dirname | sort -u | sed "s|^|$REMOTE_ROOT/|" | tr '\n' ' ')
ssh -o BatchMode=yes "$HOST" "mkdir -p $dirs"

fail=0
while IFS= read -r f; do
	if scp -o BatchMode=yes -q "$f" "$HOST:$REMOTE_ROOT/$f"; then
		echo "  -> $f"
	else
		echo "  !! не скопирован: $f"
		fail=1
	fi
done <<< "$diverged"

left=$(drift) || { echo 'Повторная сверка не удалась (ssh/md5sum): прод не проверен.'; exit 2; }
if [ "$fail" = 0 ] && [ -z "$left" ]; then
	# Корень отвечает 200 (дневник на корне) или 301 (дневник переехал на
	# свой путь, см. спеку 0004) — живы оба варианта.
	code=$(curl -sS -o /dev/null -w '%{http_code}' "$SITE/")
	echo "Готово, прод совпадает. $SITE/ -> $code"
	case "$code" in 200|301) ;; *) exit 1;; esac
else
	echo "Остались расхождения:"
	echo "$left" | sed 's/^/  /'
	exit 1
fi
