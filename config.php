<?php
/**
 * Configuration commune, sécurité HTTP et accès concurrents aux fichiers.
 */

date_default_timezone_set('Europe/Paris');

function isHttpsRequest()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isHttpsRequest(),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), geolocation=(), microphone=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
if (isHttpsRequest()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

define('DATA_DIR', __DIR__ . '/data');
define('FEEDBACK_FILE', DATA_DIR . '/feedback.csv');
define('ADMIN_HASH_FILE', DATA_DIR . '/admin_hash.txt');
define('RESET_DATES_FILE', DATA_DIR . '/reset_dates.json');
define('SESSIONS_FILE', DATA_DIR . '/sessions.json');
define('RATE_LIMIT_FILE', DATA_DIR . '/rate_limits.json');
define('RATE_LIMIT_SECRET_FILE', DATA_DIR . '/rate_limit_secret.txt');

define('MAX_VOTES_PER_MINUTE', 30);
define('VOTE_RATE_LIMIT_WINDOW', 60);
define('MAX_ADMIN_ATTEMPTS', 5);
define('ADMIN_RATE_LIMIT_WINDOW', 300);
define('ADMIN_SESSION_TIMEOUT', 1800);
define('MAX_CSV_SIZE', 10 * 1024 * 1024);
define('MAX_SESSION_NAME_LENGTH', 100);
define('MIN_SESSION_NAME_LENGTH', 3);
define('SESSION_ID_PATTERN', '/^(default_|session_)[a-z0-9_]{1,50}$/i');
define('ADMIN_CODE_PATTERN', '/^\d{6,12}$/');
define('SESSION_NAME_PATTERN', '/^[\p{L}\p{N}\s\-_\/()’\'.,:]{3,100}$/u');

if (!is_dir(DATA_DIR) && !mkdir(DATA_DIR, 0750, true) && !is_dir(DATA_DIR)) {
    throw new RuntimeException('Impossible de créer le dossier de données.');
}

function initializeDataFile($path, $content)
{
    if (!file_exists($path)) {
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException('Impossible d’initialiser un fichier de données.');
        }
        @chmod($path, 0640);
    }
}

initializeDataFile(FEEDBACK_FILE, '');
initializeDataFile(SESSIONS_FILE, '[]');
initializeDataFile(RESET_DATES_FILE, '{}');
initializeDataFile(RATE_LIMIT_FILE, '{}');
if (!file_exists(RATE_LIMIT_SECRET_FILE)) {
    initializeDataFile(RATE_LIMIT_SECRET_FILE, bin2hex(random_bytes(32)));
}

function jsonResponse($payload, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requirePostRequest()
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        jsonResponse(['success' => false, 'message' => 'Méthode non autorisée'], 405);
    }
}

function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token)
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function requireCSRFToken()
{
    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        jsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 403);
    }
}

function isAdminSession($touch = false)
{
    if (empty($_SESSION['is_admin']) || empty($_SESSION['admin_time'])) {
        return false;
    }

    if ((time() - (int) $_SESSION['admin_time']) > ADMIN_SESSION_TIMEOUT) {
        unset($_SESSION['is_admin'], $_SESSION['admin_time'], $_SESSION['csrf_token']);
        session_regenerate_id(true);
        return false;
    }

    if ($touch) {
        $_SESSION['admin_time'] = time();
    }
    return true;
}

function requireAdmin()
{
    if (!isAdminSession(true)) {
        jsonResponse(['success' => false, 'message' => 'Session administrateur expirée'], 401);
    }
}

function hashAdminCode($code)
{
    return password_hash($code, PASSWORD_DEFAULT);
}

function verifyAdminCode($code)
{
    if (!file_exists(ADMIN_HASH_FILE)) {
        return false;
    }

    $storedHash = trim((string) file_get_contents(ADMIN_HASH_FILE));
    return $storedHash !== '' && password_verify($code, $storedHash);
}

function validateSessionId($sessionId)
{
    return is_string($sessionId) && preg_match(SESSION_ID_PATTERN, $sessionId) === 1;
}

function validateSessionName($name)
{
    if (!is_string($name)) {
        return false;
    }

    $name = trim($name);
    $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    return $length >= MIN_SESSION_NAME_LENGTH
        && $length <= MAX_SESSION_NAME_LENGTH
        && preg_match(SESSION_NAME_PATTERN, $name) === 1;
}

function validateAdminCode($code)
{
    return is_string($code) && preg_match(ADMIN_CODE_PATTERN, $code) === 1;
}

function validateNewAdminCode($code)
{
    return is_string($code) && preg_match(NEW_ADMIN_CODE_PATTERN, $code) === 1;
}

function withDataLock($path, $exclusive, callable $callback)
{
    $lockPath = $path . '.lock';
    $lock = fopen($lockPath, 'c+');
    if ($lock === false) {
        throw new RuntimeException('Impossible d’ouvrir le verrou de données.');
    }
    @chmod($lockPath, 0640);

    try {
        if (!flock($lock, $exclusive ? LOCK_EX : LOCK_SH)) {
            throw new RuntimeException('Impossible de verrouiller les données.');
        }
        return $callback();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function atomicWriteFile($path, $content)
{
    $temporary = tempnam(DATA_DIR, '.tmp_');
    if ($temporary === false) {
        throw new RuntimeException('Impossible de créer le fichier temporaire.');
    }

    try {
        if (file_put_contents($temporary, $content, LOCK_EX) === false) {
            throw new RuntimeException('Impossible d’écrire les données.');
        }
        @chmod($temporary, 0640);
        if (!rename($temporary, $path)) {
            throw new RuntimeException('Impossible de remplacer le fichier de données.');
        }
    } finally {
        if (file_exists($temporary)) {
            @unlink($temporary);
        }
    }
}

function readJsonData($path, $default = [])
{
    return withDataLock($path, false, function () use ($path, $default) {
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : $default;
    });
}

function mutateJsonData($path, callable $mutator, $default = [])
{
    return withDataLock($path, true, function () use ($path, $mutator, $default) {
        $decoded = json_decode((string) file_get_contents($path), true);
        $data = is_array($decoded) ? $decoded : $default;
        $result = $mutator($data);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Impossible d’encoder les données JSON.');
        }
        atomicWriteFile($path, $json . PHP_EOL);
        return $result;
    });
}

function readFeedbackLines()
{
    return withDataLock(FEEDBACK_FILE, false, function () {
        return file(FEEDBACK_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    });
}

function appendFeedbackRow(array $row)
{
    return withDataLock(FEEDBACK_FILE, true, function () use ($row) {
        clearstatcache(true, FEEDBACK_FILE);
        if (file_exists(FEEDBACK_FILE) && filesize(FEEDBACK_FILE) >= MAX_CSV_SIZE) {
            return false;
        }

        $stream = fopen(FEEDBACK_FILE, 'ab');
        if ($stream === false) {
            throw new RuntimeException('Impossible d’ouvrir le fichier de feedback.');
        }

        try {
            if (fputcsv($stream, $row) === false) {
                throw new RuntimeException('Impossible d’écrire le feedback.');
            }
            fflush($stream);
            if (function_exists('fsync')) {
                fsync($stream);
            }
        } finally {
            fclose($stream);
        }
        return true;
    });
}

function filterFeedbackRows(callable $keepRow)
{
    return withDataLock(FEEDBACK_FILE, true, function () use ($keepRow) {
        $lines = file(FEEDBACK_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $kept = [];

        foreach ($lines as $line) {
            $row = str_getcsv($line);
            if ($keepRow($row)) {
                $kept[] = $line;
            }
        }

        atomicWriteFile(FEEDBACK_FILE, $kept ? implode(PHP_EOL, $kept) . PHP_EOL : '');
        return count($lines) - count($kept);
    });
}

function rateLimitKey($identifier)
{
    $secret = trim((string) file_get_contents(RATE_LIMIT_SECRET_FILE));
    return hash_hmac('sha256', $identifier, $secret);
}

function checkRateLimit($identifier, $maxAttempts, $timeWindow)
{
    $key = rateLimitKey($identifier);
    $now = time();

    return mutateJsonData(RATE_LIMIT_FILE, function (&$limits) use ($key, $now, $maxAttempts, $timeWindow) {
        foreach ($limits as $storedKey => $timestamps) {
            $recent = array_values(array_filter((array) $timestamps, function ($timestamp) use ($now, $timeWindow) {
                return ($now - (int) $timestamp) < $timeWindow;
            }));
            if ($recent) {
                $limits[$storedKey] = $recent;
            } else {
                unset($limits[$storedKey]);
            }
        }

        $attempts = $limits[$key] ?? [];
        if (count($attempts) >= $maxAttempts) {
            return false;
        }

        $attempts[] = $now;
        $limits[$key] = $attempts;
        return true;
    }, []);
}

function clearRateLimit($identifier)
{
    $key = rateLimitKey($identifier);
    mutateJsonData(RATE_LIMIT_FILE, function (&$limits) use ($key) {
        unset($limits[$key]);
        return true;
    }, []);
}

function clientIdentifier($prefix)
{
    return $prefix . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}
