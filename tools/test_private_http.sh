#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d)"
name="p3-private-http-$$"
cleanup() {
  docker rm -f "$name" >/dev/null 2>&1 || true
  rm -rf "$fixture"
}
trap cleanup EXIT
chmod 755 "$fixture"
mkdir -p "$fixture/var/private/update-backups/fixture"
cp "$root/var/private/.htaccess" "$fixture/var/private/.htaccess"
printf 'public fixture\n' > "$fixture/public.txt"
printf 'fake private fixture\n' > "$fixture/var/private/update-backups/fixture/proof.sqlite"
docker run -d --name "$name" --publish 127.0.0.1::80 \
  -v "$fixture:/usr/local/apache2/htdocs:ro" httpd:2.4 \
  sh -c 'printf "\n<Directory /usr/local/apache2/htdocs>\nAllowOverride All\nRequire all granted\n</Directory>\n" >> /usr/local/apache2/conf/httpd.conf; exec httpd-foreground' >/dev/null
port="$(docker port "$name" 80/tcp | head -n1)"
url="http://$port"
for i in $(seq 1 30); do
  if curl --fail --silent "$url/public.txt" >/dev/null; then break; fi
  sleep 1
done
test "$(curl --fail --silent "$url/public.txt")" = "public fixture"
test "$(curl --silent --output /dev/null --write-out '%{http_code}' "$url/var/private/update-backups/fixture/proof.sqlite")" = "403"
echo "Apache: public fixture readable, private snapshot forbidden."
