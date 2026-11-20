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
cp "$root/.htaccess" "$fixture/.htaccess"
mkdir -p "$fixture/src" "$fixture/var/modules/example/database"
printf 'fake source fixture\n' > "$fixture/src/proof.txt"
printf 'fake schema fixture\n' > "$fixture/var/modules/example/database/proof.txt"
mkdir -p "$fixture/var/private/update-backups/fixture"
for directory in database sessions tmp var/sys var/private var/uploads/images; do
  mkdir -p "$fixture/$directory"
  cp "$root/$directory/.htaccess" "$fixture/$directory/.htaccess"
  printf 'fake private fixture\n' > "$fixture/$directory/proof.txt"
done
printf 'public image fixture\n' > "$fixture/var/uploads/images/0123456789abcdef0123456789abcdef.webp"
printf 'public fixture\n' > "$fixture/public.txt"
printf 'root route\n' > "$fixture/index.php"
mkdir -p "$fixture/nested"
cp "$root/.htaccess" "$fixture/nested/.htaccess"
printf 'nested route\n' > "$fixture/nested/index.php"
printf 'static route\n' > "$fixture/static.html"
mkdir -p "$fixture/nested/deeper/site"
cp "$root/.htaccess" "$fixture/nested/deeper/site/.htaccess"
printf 'deep route\n' > "$fixture/nested/deeper/site/index.php"
printf 'nested static route\n' > "$fixture/nested/static.html"

printf 'fake private fixture\n' > "$fixture/var/private/update-backups/fixture/proof.sqlite"
docker run -d --name "$name" --publish 127.0.0.1::80 \
  -v "$fixture:/usr/local/apache2/htdocs:ro" httpd:2.4 \
  sh -c 'sed -i "s/^#LoadModule rewrite_module/LoadModule rewrite_module/" /usr/local/apache2/conf/httpd.conf; printf "\n<Directory /usr/local/apache2/htdocs>\nAllowOverride All\nRequire all granted\n</Directory>\n" >> /usr/local/apache2/conf/httpd.conf; exec httpd-foreground' >/dev/null
port="$(docker port "$name" 80/tcp | head -n1)"
url="http://$port"
for i in $(seq 1 30); do
  if curl --fail --silent "$url/public.txt" >/dev/null; then break; fi
  sleep 1
done
test "$(curl --fail --silent "$url/public.txt")" = "public fixture"
test "$(curl --fail --silent "$url/login.html")" = "root route"
test "$(curl --fail --silent "$url/accueil.html")" = "root route"
test "$(curl --fail --silent "$url/nested/login.html")" = "nested route"
test "$(curl --fail --silent "$url/static.html")" = "static route"
for route in login.html accueil.html Admin.html Admin-writing.html Admin-management.html Admin-configuration.html 'search.html?q=guitare' 'article-%C3%A9tude.html'; do
  test "$(curl --fail --silent --path-as-is "$url/$route")" = "root route"
  test "$(curl --fail --silent --path-as-is "$url/nested/$route")" = "nested route"
  test "$(curl --fail --silent --path-as-is "$url/nested/deeper/site/$route")" = "deep route"
done
test "$(curl --fail --silent "$url/nested/static.html")" = "nested static route"
test "$(curl --fail --silent "$url/unknown.php")" = "root route"
test "$(curl --fail --silent "$url/robots.txt")" = "root route"
test "$(curl --fail --silent "$url/nested/sitemap.xml")" = "nested route"

for path in src/proof.txt var/modules/example/database/proof.txt; do
  test "$(curl --silent --output /dev/null --write-out '%{http_code}' "$url/$path")" = "403"
done
test "$(curl --silent --output /dev/null --write-out '%{http_code}' "$url/var/private/update-backups/fixture/proof.sqlite")" = "403"
mv "$fixture/.htaccess" "$fixture/.htaccess.saved"
for directory in database sessions tmp var/sys var/private var/uploads/images; do
  test "$(curl --silent --output /dev/null --write-out '%{http_code}' "$url/$directory/proof.txt")" = "403"
done
test "$(curl --fail --silent "$url/var/uploads/images/0123456789abcdef0123456789abcdef.webp")" = "public image fixture"
echo "Apache: six directory policies enforced; public files and permitted image remain readable."
