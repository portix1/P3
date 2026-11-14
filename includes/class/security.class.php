<?php
/** Shared HTTP security rules for Portix. */
final class PortixSecurity
{
    private static ?\Portix\Security\RateLimiter $rateLimiter = null;

    public static function configureRateLimiter(\PDO $pdo)
    {
        self::$rateLimiter = new \Portix\Security\RateLimiter($pdo);
        if (mt_rand(1, 1000) === 1) {
            self::$rateLimiter->purge();
        }
    }

    private static function rateLimiter()
    {
        if (!self::$rateLimiter instanceof \Portix\Security\RateLimiter) {
            throw new \RuntimeException('Le limiteur de requêtes Portix n’est pas initialisé.');
        }
        return self::$rateLimiter;
    }

    private static function clientIp()
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }

    private static function rateKey($scope, $value)
    {
        return (string) $scope . ':' . hash('sha256', (string) $value);
    }

    public static function csrfToken()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (!is_string($_SESSION['csrf_token'] ?? null) || $_SESSION['csrf_token'] === '') {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    public static function rotateCsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session inactive.');
        }
        return $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function csrfIsValid($token = null)
    {
        if ($token === null) {
            $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        }
        return isset($_SESSION['csrf_token'])
            && is_string($_SESSION['csrf_token']) && $_SESSION['csrf_token'] !== ''
            && is_string($token)
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function requirePostWithCsrf()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new RuntimeException('Cette action exige une requête POST.');
        }
        if (!self::csrfIsValid()) {
            throw new RuntimeException('Jeton de formulaire invalide. Rechargez la page.');
        }
    }

    public static function hasRole($user, $role)
    {
        if (!is_object($user)) {
            return false;
        }
        $methods = array(
            'admin' => 'isAdmin',
            'redactor' => 'isRedactor',
        );
        if (!isset($methods[$role]) || !method_exists($user, $methods[$role])) {
            return false;
        }
        return (bool) $user->{$methods[$role]}();
    }

    public static function requireRole($user, $role)
    {
        if (!self::hasRole($user, $role)) {
            throw new RuntimeException('Accès refusé.');
        }
    }

    public static function loginDelayRemaining($pseudo = '')
    {
        return self::rateLimiter()->maxRemaining([
            self::rateKey('login-account', strtolower(trim((string) $pseudo))),
            self::rateKey('login-ip', self::clientIp()),
        ]);
    }

    public static function recordLoginFailure($pseudo = '')
    {
        return self::rateLimiter()->recordFailures([
            self::rateKey('login-account', strtolower(trim((string) $pseudo))),
            self::rateKey('login-ip', self::clientIp()),
        ], 10, 1, 30, 900);
    }

    public static function clearLoginFailures($pseudo = '')
    {
        self::rateLimiter()->clear(
            self::rateKey('login-account', strtolower(trim((string) $pseudo)))
        );
    }

    public static function consumeContactRateLimit()
    {
        return self::rateLimiter()->consumeMany([
            self::rateKey('contact-ip', self::clientIp()),
        ], 60, 1);
    }

    public static function consumePasswordResetRateLimit($email)
    {
        return self::rateLimiter()->consumeMany([
            self::rateKey('reset-email', strtolower(trim((string) $email))),
            self::rateKey('reset-ip', self::clientIp()),
        ], 60, 1);
    }

    public static function safeFilename($name)
    {
        $name = trim(str_replace('\\', '/', (string) $name));
        if ($name === '' || basename($name) !== $name || strpos($name, "\0") !== false) {
            throw new InvalidArgumentException('Nom de fichier invalide.');
        }
        return $name;
    }

    public static function sendHeaders()
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: DENY');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
	header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
  	. "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
    	. "font-src 'self' https://fonts.gstatic.com; "
    	. "script-src 'self' https://cdn.jsdelivr.net; "
        . "frame-src https://www.youtube-nocookie.com https://player.vimeo.com https://www.dailymotion.com; "
    	. "object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }
}
