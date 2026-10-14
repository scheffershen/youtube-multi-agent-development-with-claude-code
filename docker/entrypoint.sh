#!/bin/sh
# serve  -> migrate, then run the app on :8000 (default)
# test   -> run the PHPUnit suite
# <anything else> -> executed as-is (e.g. "php bin/console app:simulate-traffic --reset")
set -e
cd /app

case "${1:-serve}" in
  serve)
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    echo "TicketFlow listening on http://0.0.0.0:8000 (APP_ENV=$APP_ENV)"
    exec php -S 0.0.0.0:8000 -t public public/index.php
    ;;
  test)
    shift
    exec php vendor/bin/phpunit "$@"
    ;;
  *)
    exec "$@"
    ;;
esac
