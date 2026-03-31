#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-candygrill}"

if [[ ! -f .env ]] && [[ -f .env.example ]]; then
    echo "candygrill: create .env (e.g. cp .env.example .env)" >&2
    exit 1
fi

# Convenience: run toolchain inside the app service (similar to Laravel Sail).
# Raw compose commands (up, down, exec, …) are passed through unchanged.
if [[ $# -gt 0 ]]; then
    case "$1" in
        compose)
            echo "candygrill: use ./sail up, ./sail exec … — do not prefix with 'compose'" >&2
            exit 1
            ;;
        composer|php|bash|sh)
            set -- exec app "$@"
            ;;
        phpunit)
            shift
            set -- exec app vendor/bin/phpunit "$@"
            ;;
        vendor/bin/phpunit)
            set -- exec app "$@"
            ;;
    esac
fi

exec docker compose "$@"
