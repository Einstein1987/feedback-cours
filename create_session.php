<?php
require_once __DIR__ . '/config.php';

requirePostRequest();
requireAdmin();
requireCSRFToken();

$name = trim((string) ($_POST['name'] ?? ''));
if (!validateSessionName($name)) {
    jsonResponse(['success' => false, 'message' => 'Nom invalide : 3 à 100 caractères usuels sont autorisés.'], 400);
}

$newSession = mutateJsonData(SESSIONS_FILE, function (&$sessions) use ($name) {
    $id = 'session_' . bin2hex(random_bytes(10));
    $session = [
        'id' => $id,
        'name' => $name,
        'created_at' => date('Y-m-d H:i:s'),
        'is_active' => false,
    ];
    $sessions[] = $session;
    return $session;
}, []);

jsonResponse([
    'success' => true,
    'message' => 'Session créée avec succès',
    'session' => $newSession,
]);
