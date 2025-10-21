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
        // Créer un code par défaut si le fichier n'existe pas
        $defaultHash = hashAdminCode('1945');
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

// Fonction pour vérifier le token CSRF
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Fonction pour nettoyer les entrées
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Protection contre les attaques par force brute (rate limiting simple)
function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
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
