<?php
// Configuration sécurisée de l'application
session_start();

// Définir le fuseau horaire
date_default_timezone_set('Europe/Paris');

// Fichiers de données
define('FEEDBACK_FILE', 'data/feedback.csv');
define('ADMIN_HASH_FILE', 'data/admin_hash.txt');
define('RESET_DATE_FILE', 'data/reset_date.txt');
define('SESSIONS_FILE', 'data/sessions.json');

// ===================================
// CONSTANTES DE SÉCURITÉ
// ===================================

// Rate limiting
define('MAX_VOTES_PER_MINUTE', 10);
define('VOTE_RATE_LIMIT_WINDOW', 60); // secondes
define('MAX_ADMIN_ATTEMPTS', 5);
define('ADMIN_RATE_LIMIT_WINDOW', 300); // 5 minutes

// Sessions
define('ADMIN_SESSION_TIMEOUT', 1800); // 30 minutes
define('VOTE_COOLDOWN', 8); // secondes

// Fichiers
define('MAX_CSV_SIZE', 10 * 1024 * 1024); // 10 MB
define('MAX_SESSION_NAME_LENGTH', 100);
define('MIN_SESSION_NAME_LENGTH', 3);

// Patterns de validation
define('SESSION_ID_PATTERN', '/^(default_|session_)[a-z0-9_]{1,50}$/i');
define('ADMIN_CODE_PATTERN', '/^\d{4}$/');
define('SESSION_NAME_PATTERN', '/^[a-zA-Z0-9\sÀ-ÿ\-_\/()]{3,100}$/');

// Créer le dossier data s'il n'existe pas
if (!file_exists('data')) {
    mkdir('data', 0750, true);
}

// Fonction pour hasher le code admin
function hashAdminCode($code) {
    return password_hash($code, PASSWORD_BCRYPT);
}

// Fonction pour vérifier le code admin
function verifyAdminCode($code) {
    if (!file_exists(ADMIN_HASH_FILE)) {
        // Hash bcrypt du code par défaut
        $defaultHash = '$2a$12$PDeBYhvpG1wbLn/BiBIkleAPsa2EqJza1Wa63ia1n0aArwqbkiKCG';
        file_put_contents(ADMIN_HASH_FILE, $defaultHash);
        return password_verify($code, $defaultHash);
    }
    $storedHash = trim(file_get_contents(ADMIN_HASH_FILE));
    return password_verify($code, $storedHash);
}

// Fonction pour générer un token CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Générer automatiquement le token CSRF pour cette session
$csrfToken = generateCSRFToken();

// Fonction pour vérifier le token CSRF
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ===================================
// FONCTIONS DE VALIDATION
// ===================================

// Valider l'ID d'une session
function validateSessionId($sessionId) {
    if (empty($sessionId)) {
        return false;
    }
    return preg_match(SESSION_ID_PATTERN, $sessionId) === 1;
}

// Valider le nom d'une session
function validateSessionName($name) {
    if (empty($name)) {
        return false;
    }
    $length = strlen($name);
    if ($length < MIN_SESSION_NAME_LENGTH || $length > MAX_SESSION_NAME_LENGTH) {
        return false;
    }
    return preg_match(SESSION_NAME_PATTERN, $name) === 1;
}

// Valider le code admin
function validateAdminCode($code) {
    if (empty($code)) {
        return false;
    }
    return preg_match(ADMIN_CODE_PATTERN, $code) === 1;
}

// Vérifier la taille du fichier CSV
function checkCSVSize() {
    if (!file_exists(FEEDBACK_FILE)) {
        return true; // Fichier n'existe pas encore, OK
    }
    return filesize(FEEDBACK_FILE) < MAX_CSV_SIZE;
}

// Fonction pour nettoyer les entrées
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Protection contre les attaques par force brute (rate limiting simple)
function checkRateLimit($identifier, $maxAttempts = MAX_VOTES_PER_MINUTE, $timeWindow = VOTE_RATE_LIMIT_WINDOW) {
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    $now = time();
    $key = md5($identifier);
    
    // Nettoyer les anciennes tentatives
    if (isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = array_filter(
            $_SESSION['rate_limit'][$key],
            function($timestamp) use ($now, $timeWindow) {
                return ($now - $timestamp) < $timeWindow;
            }
        );
    } else {
        $_SESSION['rate_limit'][$key] = [];
    }
    
    // Vérifier le nombre de tentatives
    if (count($_SESSION['rate_limit'][$key]) >= $maxAttempts) {
        return false;
    }
    
    // Ajouter la tentative actuelle
    $_SESSION['rate_limit'][$key][] = $now;
    return true;
}

// Headers de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Initialiser les fichiers si nécessaire
if (!file_exists(FEEDBACK_FILE)) {
    file_put_contents(FEEDBACK_FILE, '');
}

if (!file_exists(SESSIONS_FILE)) {
    file_put_contents(SESSIONS_FILE, json_encode([]));
}
?>