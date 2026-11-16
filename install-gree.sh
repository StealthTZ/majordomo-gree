#!/bin/sh
# ---------------------------------------------------------------------------
# Установка модуля GREE 1.0.0 в существующую установку MajorDoMo.
#
#   sudo sh install-gree.sh                       # найти корень автоматически
#   sudo sh install-gree.sh /var/www/majordomo    # указать корень явно
#
# Скрипт делает резервную копию текущих файлов модуля, копирует новые и
# выставляет владельца и права такими же, как у соседних файлов MajorDoMo.
# Базу данных он не трогает: таблицы создаёт сам модуль при первом открытии
# в панели управления.
# ---------------------------------------------------------------------------
set -e

SRC=$(cd "$(dirname "$0")" && pwd)
ROOT="$1"

say() { printf '%s\n' "$*"; }
die() { printf 'Ошибка: %s\n' "$*" >&2; exit 1; }

# --- проверка, что рядом лежат файлы модуля --------------------------------
if [ ! -f "$SRC/modules/gree/gree.class.php" ]; then
    die "рядом со скриптом нет modules/gree/. Распакуйте архив и запускайте скрипт из полученного каталога."
fi

# --- поиск корня MajorDoMo -------------------------------------------------
if [ -z "$ROOT" ]; then
    for candidate in /var/www/majordomo /var/www/html /var/www /home/pi/majordomo /opt/majordomo; do
        if [ -f "$candidate/config.php" ] && [ -f "$candidate/lib/loader.php" ]; then
            ROOT="$candidate"
            break
        fi
    done
fi
[ -n "$ROOT" ] || die "не удалось найти корень MajorDoMo. Укажите его аргументом: sh install-gree.sh /путь/к/majordomo"

[ -f "$ROOT/config.php" ] && [ -f "$ROOT/lib/loader.php" ] && [ -d "$ROOT/modules" ] \
    || die "$ROOT не похож на корень MajorDoMo (нет config.php, lib/loader.php или modules/)."

say "Корень MajorDoMo: $ROOT"

# --- версия PHP ------------------------------------------------------------
if command -v php >/dev/null 2>&1; then
    say "PHP (CLI):        $(php -r 'echo PHP_VERSION;')"
    for ext in sockets openssl json; do
        php -r "exit(extension_loaded('$ext') ? 0 : 1);" \
            || say "ВНИМАНИЕ: расширение PHP '$ext' не загружено — модуль без него не заработает."
    done
else
    say "ВНИМАНИЕ: php в PATH не найден, проверку версии пропускаю."
fi

# --- резервная копия -------------------------------------------------------
STAMP=$(date +%Y%m%d-%H%M%S)
# каталог для копии выбираем вне веб-корня, чтобы архив не раздавался наружу
BACKUP_DIR=""
for d in /var/backups "$(dirname "$ROOT")" "$HOME"; do
    if [ -d "$d" ] && [ -w "$d" ]; then BACKUP_DIR="$d"; break; fi
done
[ -n "$BACKUP_DIR" ] || BACKUP_DIR="$ROOT"
BACKUP="$BACKUP_DIR/gree-backup-$STAMP.tar.gz"
EXISTING=""
for p in modules/gree templates/gree img/modules/gree.png scripts/cycle_gree.php; do
    [ -e "$ROOT/$p" ] && EXISTING="$EXISTING $p"
done

if [ -n "$EXISTING" ]; then
    # shellcheck disable=SC2086
    tar -czf "$BACKUP" -C "$ROOT" $EXISTING
    say "Резервная копия:  $BACKUP"
else
    say "Резервная копия:  не требуется, модуль ещё не установлен"
fi

# --- у кого забирать владельца и права -------------------------------------
REF="$ROOT/lib/loader.php"

copy_tree() {
    src="$1"; dst="$2"
    mkdir -p "$dst"
    cp -R "$src/." "$dst/"
}

copy_tree "$SRC/modules/gree"   "$ROOT/modules/gree"
copy_tree "$SRC/templates/gree" "$ROOT/templates/gree"
mkdir -p "$ROOT/img/modules" "$ROOT/scripts"
cp "$SRC/img/modules/gree.png"  "$ROOT/img/modules/gree.png"
cp "$SRC/scripts/cycle_gree.php" "$ROOT/scripts/cycle_gree.php"

say "Файлы скопированы."

# --- владелец и права ------------------------------------------------------
if [ -e "$REF" ]; then
    OWNER=$(stat -c '%U:%G' "$REF" 2>/dev/null || echo "")
    if [ -n "$OWNER" ]; then
        for p in modules/gree templates/gree img/modules/gree.png scripts/cycle_gree.php; do
            chown -R "$OWNER" "$ROOT/$p" 2>/dev/null || true
        done
        say "Владелец:         $OWNER"
    fi
fi
# MajorDoMo сам дописывает файлы в modules/ и templates/ (install-linux.sh ставит 777/666)
find "$ROOT/modules/gree" "$ROOT/templates/gree" -type d -exec chmod 777 {} \; 2>/dev/null || true
find "$ROOT/modules/gree" "$ROOT/templates/gree" -type f -exec chmod 666 {} \; 2>/dev/null || true
chmod 644 "$ROOT/scripts/cycle_gree.php" 2>/dev/null || true

# --- синтаксическая проверка ----------------------------------------------
if command -v php >/dev/null 2>&1; then
    for f in "$ROOT"/modules/gree/*.php "$ROOT"/scripts/cycle_gree.php; do
        php -l "$f" >/dev/null || die "файл $f не прошёл проверку синтаксиса"
    done
    say "Синтаксис:        проверен"
fi

say ""
say "Готово. Дальше:"
say "  1. Откройте панель управления -> Устройства -> GREE."
say "     При первом открытии модуль создаст свои таблицы."
say "  2. Перезапустите цикл опроса: Панель управления -> Система -> Циклы ->"
say "     cycle_gree -> перезапустить. Либо перезапустите cycle.php целиком."
say "  3. Проверьте, что вверху страницы модуля горит «Опрос работает»."
say ""
say "Откатиться, если что-то пошло не так:"
if [ -n "$EXISTING" ]; then
    say "  sudo tar -xzf $BACKUP -C $ROOT"
else
    say "  sudo rm -rf $ROOT/modules/gree $ROOT/templates/gree $ROOT/img/modules/gree.png $ROOT/scripts/cycle_gree.php"
fi
