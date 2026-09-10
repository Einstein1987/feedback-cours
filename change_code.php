<?php
require_once __DIR__ . '/config.php';

requirePostRequest();
requireAdmin();
requireCSRFToken();

$newCode = $_POST['code'] ?? '';
if (!validateNewAdminCode($newCode)) {
    jsonResponse(['success' => false, 'message' => 'Le nouveau code doit contenir entre 6 et 12 chiffres.'], 400);
}

$hashedCode = hashAdminCode($newCode);
withDataLock(ADMIN_HASH_FILE, true, function () use ($hashedCode) {
    atomicWriteFile(ADMIN_HASH_FILE, $hashedCode . PHP_EOL);
});

session_start();
session_regenerate_id(true);
$_SESSION['is_admin'] = true;
$_SESSION['admin_time'] = time();
unset($_SESSION['csrf_token']);
session_write_close();

jsonResponse([
    'success' => true,
    'message' => 'Code administrateur modifié avec succès',
    'csrf_token' => generateCSRFToken(),
]);
