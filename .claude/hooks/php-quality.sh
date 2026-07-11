#!/usr/bin/env bash
# PostToolUse hook (Edit/Write): roda php-cs-fixer + phpstan no arquivo PHP editado,
# dentro do container da aplicação. Sai silenciosamente se o container não estiver de pé.
set -uo pipefail

INPUT=$(cat)
FILE_PATH=$(jq -r '.tool_input.file_path // empty' <<<"$INPUT")

PROJECT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)

case "$FILE_PATH" in
    "$PROJECT_DIR"/*.php) ;;
    *) exit 0 ;;
esac

REL_PATH=${FILE_PATH#"$PROJECT_DIR"/}

# Só nos diretórios cobertos pelas ferramentas de qualidade.
case "$REL_PATH" in
    app/* | config/* | test/*) ;;
    *) exit 0 ;;
esac

cd "$PROJECT_DIR" || exit 0

if ! docker compose ps --status running app 2>/dev/null | grep -q app; then
    exit 0
fi

docker compose exec -T app vendor/bin/php-cs-fixer fix --quiet "$REL_PATH" >/dev/null 2>&1

STAN_OUTPUT=$(docker compose exec -T app vendor/bin/phpstan analyse --no-progress --memory-limit 300M "$REL_PATH" 2>&1)
if [ $? -ne 0 ]; then
    echo "phpstan found issues in $REL_PATH:" >&2
    echo "$STAN_OUTPUT" >&2
    exit 2
fi

exit 0
