set -eu
attempt=0
until php /scripts/check-install.php; do
  attempt=$((attempt + 1))
  if [ "$attempt" -ge 180 ]; then
    printf '%s\n' 'PrestaShop did not finish installing before the timeout.' >&2
    exit 1
  fi
  sleep 2
done
