<?php
require_once __DIR__ . '/config.php';

requirePostRequest();

if (!checkRateLimit(clientIdentifier('vote'), MAX_VOTES_PER_MINUTE, VOTE_RATE_LIMIT_WINDOW)) {
    jsonResponse(['success' => false, 'message' => 'Trop de votes rapprochés. Patientez quelques secondes.'], 429);
}

$type = $_POST['type'] ?? '';
$valueRaw = $_POST['value'] ?? null;
$sessionId = $_POST['session'] ?? '';

if (!in_array($type, ['liked', 'learned'], true)) {
    jsonResponse(['success' => false, 'message' => 'Type de feedback invalide'], 400);
}
if (filter_var($valueRaw, FILTER_VALIDATE_INT) === false || (int) $valueRaw < 0 || (int) $valueRaw > 3) {
    jsonResponse(['success' => false, 'message' => 'Valeur invalide'], 400);
}
if (!validateSessionId($sessionId)) {
    jsonResponse(['success' => false, 'message' => 'Format de session invalide'], 400);
}

try {
    $sessions = readJsonData(SESSIONS_FILE, []);
} catch (RuntimeException $exception) {
    error_log($exception->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erreur de lecture des sessions'], 500);
}
$sessionExists = false;
foreach ($sessions as $session) {
    if (($session['id'] ?? '') === $sessionId) {
        $sessionExists = true;
        break;
    }
}
if (!$sessionExists) {
    jsonResponse(['success' => false, 'message' => 'Session inexistante'], 400);
}

try {
    $stored = appendFeedbackRow([
        date('Y-m-d H:i:s'),
        $type,
        (int) $valueRaw,
        $sessionId,
    ]);
} catch (RuntimeException $exception) {
    error_log($exception->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erreur lors de l’enregistrement'], 500);
}

if (!$stored) {
    jsonResponse(['success' => false, 'message' => 'Stockage plein. Une archive est nécessaire.'], 507);
}

jsonResponse(['success' => true, 'message' => 'Feedback enregistré avec succès'], 201);
