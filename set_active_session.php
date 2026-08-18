<?php
require_once __DIR__ . '/config.php';

requirePostRequest();
requireAdmin();
requireCSRFToken();

$sessionId = $_POST['session_id'] ?? '';
if (!validateSessionId($sessionId)) {
    jsonResponse(['success' => false, 'message' => 'Format de session invalide'], 400);
}

$found = mutateJsonData(SESSIONS_FILE, function (&$sessions) use ($sessionId) {
    $found = false;
    foreach ($sessions as &$session) {
        $session['is_active'] = ($session['id'] ?? '') === $sessionId;
        $found = $found || $session['is_active'];
    }
    unset($session);
    return $found;
}, []);

if (!$found) {
    jsonResponse(['success' => false, 'message' => 'Session non trouvée'], 404);
}

jsonResponse(['success' => true, 'message' => 'Session active mise à jour']);
