<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$source = dirname(__DIR__);
$temporary = sys_get_temp_dir() . '/p3-install-routes-' . bin2hex(random_bytes(6));
mkdir($temporary, 0770, true);
$server = null;

function installRouteCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function copyInstallSource(string $source, string $target, string $relative = ''): void
{
    $excluded = ['.git', 'dist', 'database', 'sessions', 'tmp', 'var/private', 'var/sys', 'var/uploads', 'var/cache'];
    foreach (scandir($source) ?: [] as $name) {
        $path = ltrim($relative . '/' . $name, '/');
        if ($name === '.' || $name === '..' || in_array($path, $excluded, true) || is_link($source . '/' . $name)) {
            continue;
        }
        if (is_dir($source . '/' . $name)) {
            mkdir($target . '/' . $name, 0770, true);
            copyInstallSource($source . '/' . $name, $target . '/' . $name, $path);
        } else {
            copy($source . '/' . $name, $target . '/' . $name);
        }
    }
}

try {
    foreach (['', '/nested/site'] as $prefix) {
        $documentRoot = $temporary . ($prefix === '' ? '/root' : '/subdirectory');
        $site = $documentRoot . $prefix;
        mkdir($site, 0770, true);
        copyInstallSource($source, $site);
        // Apache rewrite rules are tested independently by test_private_http.sh.
        // This router supplies the script path a web server passes to PHP.
        $router = $documentRoot . '/test-router.php';
        file_put_contents($router, '<?php $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH); '
            . 'if ($path !== ' . var_export($prefix . '/', true) . ' && is_file(__DIR__ . $path)) { return false; } '
            . '$_SERVER["SCRIPT_NAME"] = ' . var_export($prefix . '/index.php', true) . '; '
            . 'require ' . var_export($site . '/index.php', true) . ';');
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new RuntimeException($error);
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $server = proc_open([PHP_BINARY, '-S', $address, '-t', $documentRoot, $router],
            [['pipe', 'r'], ['file', $temporary . '/server.log', 'a'], ['file', $temporary . '/server.log', 'a']],
            $pipes, $site);
        if (!is_resource($server)) {
            throw new RuntimeException('Cannot start the isolated PHP server.');
        }
        fclose($pipes[0]);
        $cookies = [];
        $request = static function (string $path, ?array $post = null) use ($address, $prefix, &$cookies): array {
            $headers = ['User-Agent: P3-Installation-Test/1.0', 'DNT: 1'];
            if ($cookies !== []) {
                $headers[] = 'Cookie: ' . implode('; ', $cookies);
            }
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $context = stream_context_create(['http' => [
                'method' => $post === null ? 'GET' : 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $post === null ? '' : http_build_query($post),
                'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15,
            ]]);
            $body = @file_get_contents('http://' . $address . $prefix . $path, false, $context);
            $responseHeaders = $http_response_header ?? [];
            foreach ($responseHeaders as $header) {
                if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i', $header, $match)) {
                    $cookies[$match[1]] = $match[1] . '=' . $match[2];
                }
            }
            preg_match('~HTTP/\S+ (\d+)~', $responseHeaders[0] ?? '', $match);
            return [(int) ($match[1] ?? 0), $body === false ? '' : $body, implode("\n", $responseHeaders)];
        };
        for ($attempt = 0; $attempt < 40; $attempt++) {
            $form = $request('/install.php');
            if ($form[0] !== 0) {
                break;
            }
            usleep(100000);
        }
        installRouteCheck($form[0] === 200, 'Installer is accessible at ' . $prefix);
        preg_match('/name="token" value="([^"]+)"/', $form[1], $token);
        installRouteCheck(isset($token[1]), 'Installer token is present.');
        $installed = $request('/install.php', [
            'token' => $token[1], 'title' => 'Installation test', 'admin_pseudo' => 'admin',
            'admin_email' => 'admin@example.test', 'admin_password' => 'test-install-password',
            'database_name' => $prefix === '' ? '' : 'custom-site',
        ]);
        installRouteCheck($installed[0] === 200 && str_contains($installed[1], 'href="./">Ouvrir P3'),
            'Installation completes with a link to the site root.');
        installRouteCheck($request('/')[0] === 200, 'Fresh home page is accessible.');
        $alias = $request('/accueil.html');
        installRouteCheck($alias[0] === 301 && preg_match('~^Location: ' . preg_quote($prefix . '/', '~') . '$~mi', $alias[2]) === 1,
            'Legacy home redirects inside its own installation.');
        $login = $request('/login.html');
        installRouteCheck($login[0] === 200, 'Login form is accessible.');
        installRouteCheck($request('/Admin.html')[0] === 403, 'Anonymous administration remains forbidden.');
        preg_match('/name="csrf_token" value="([^"]+)"/', $login[1], $token);
        installRouteCheck(isset($token[1]), 'Login token is present.');
        $authenticated = $request('/login.html', [
            'csrf_token' => $token[1], 'pseudo' => 'admin', 'pass' => 'test-install-password',
        ]);
        installRouteCheck($authenticated[0] === 302, 'Administrator can log in.');
        foreach (['Admin.html', 'Admin-writing.html', 'Admin-management.html', 'Admin-configuration.html'] as $route) {
            installRouteCheck($request('/' . $route)[0] === 200, 'Administrator can open ' . $route);
        }
        installRouteCheck($request('/var/templates/modern/style.min.css')[0] === 200, 'Public CSS is accessible.');
        installRouteCheck($request('/unknown-install-test.html')[0] === 404, 'Unknown page returns 404.');
        installRouteCheck($request('/install.php')[0] === 200, 'Installed site refuses a new installation gracefully.');
        proc_terminate($server);
        proc_close($server);
        $server = null;
        echo 'Installation HTTP, home, login and administration verified at ' . ($prefix ?: '/') . PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    if (is_file($temporary . '/server.log')) {
        fwrite(STDERR, substr((string) file_get_contents($temporary . '/server.log'), -3000));
    }
    $failed = true;
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($temporary);
}
exit(isset($failed) ? 1 : 0);
