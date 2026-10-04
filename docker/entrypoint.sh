#!/bin/sh
#
# Runs the bundled PHPStan with the namespace visibility rules against the
# project mounted at /app.
#
# Environment:
#   PHPSTAN_CONFIG        config defining `namespaceVisibility`
#                         (defaults to /app/namespace-visibility.neon if present)
#   PHPSTAN_LEVEL         analysis level (default: max)
#   PHPSTAN_MEMORY_LIMIT  e.g. 1G
#
# Extra arguments are forwarded to `phpstan analyse` (e.g. paths).

set -eu

if [ -z "${PHPSTAN_CONFIG:-}" ] && [ -f /app/namespace-visibility.neon ]; then
    PHPSTAN_CONFIG=/app/namespace-visibility.neon
fi

GENERATED_CONFIG="${TMPDIR:-/tmp}/visibility-phpstan.neon"

{
    echo 'includes:'
    echo '    - /opt/visibility/extension.neon'
    if [ -n "${PHPSTAN_CONFIG:-}" ]; then
        echo "    - ${PHPSTAN_CONFIG}"
    fi
} > "$GENERATED_CONFIG"

set -- --configuration "$GENERATED_CONFIG" --level="${PHPSTAN_LEVEL:-max}" "$@"

if [ -f /app/vendor/autoload.php ]; then
    set -- "$@" --autoload-file=/app/vendor/autoload.php
fi

if [ -n "${PHPSTAN_MEMORY_LIMIT:-}" ]; then
    set -- "$@" --memory-limit="${PHPSTAN_MEMORY_LIMIT}"
fi

exec /opt/visibility/vendor/bin/phpstan analyse "$@"
