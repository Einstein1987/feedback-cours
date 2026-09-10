<?php
require_once __DIR__ . '/config.php';

requirePostRequest();

$identifier = clientIdentifier('admin');
if (!checkRateLimit($identifier, MAX_ADMIN_ATTEMPTS, ADMIN_RATE_LIMIT_WINDOW)) {
    jsonResponse(['success' => false, 'message' => 'Trop de tentatives. Réessayez dans quelques minutes.'], 429);
}

$code = $_POST['code'] ?? '';
if (!validateAdminCode($code)) {
    jsonResponse(['success' => false, 'message' => 'Le code doit contenir entre 4 et 12 chiffres.'], 400);
}

if (!file_exists(ADMIN_HASH_FILE)) {
    jsonResponse(['success' => false, 'message' => 'Le code administrateur doit être initialisé. Consultez le README.'], 503);
}

if (!verifyAdminCode($code)) {
    usleep(random_int(300000, 600000));
    jsonResponse(['success' => false, 'message' => 'Code incorrect'], 401);
}

clearRateLimit($identifier);
session_regenerate_id(true);
session_start();
$_SESSION['is_admin'] = true;
$_SESSION['admin_time'] = time();
unset($_SESSION['csrf_token']);
session_write_close();

jsonResponse([
    'success' => true,
    'message' => 'Authentification réussie',
    'csrf_token' => generateCSRFToken(),
]);
