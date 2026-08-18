<?php
require_once __DIR__ . '/config.php';

requirePostRequest();
requireAdmin();
requireCSRFToken();

$sessionId = $_POST['session'] ?? '';
if (!validateSessionId($sessionId)) {
    jsonResponse(['success' => false, 'message' => 'Format de session invalide'], 400);
}

$result = mutateJsonData(SESSIONS_FILE, function (&$sessions) use ($sessionId) {
    $found = false;
    $deletedWasActive = false;
    $remaining = [];

    foreach ($sessions as $session) {
        if (($session['id'] ?? '') === $sessionId) {
            $found = true;
            $deletedWasActive = !empty($session['is_active']);
            continue;
        }
        $remaining[] = $session;
    }

    if (!$found) {
        return ['status' => 'missing'];
    }
    if (!$remaining) {
        return ['status' => 'last'];
    }

    if ($deletedWasActive) {
        foreach ($remaining as &$session) {
            $session['is_active'] = false;
        }
        unset($session);
        $remaining[count($remaining) - 1]['is_active'] = true;
    }

    $sessions = $remaining;
    return ['status' => 'deleted'];
}, []);

if ($result['status'] === 'missing') {
    jsonResponse(['success' => false, 'message' => 'Session non trouvée'], 404);
}
if ($result['status'] === 'last') {
    jsonResponse(['success' => false, 'message' => 'Impossible de supprimer la dernière session'], 400);
}

filterFeedbackRows(function ($row) use ($sessionId) {
    return ($row[3] ?? '') !== $sessionId;
});

mutateJsonData(RESET_DATES_FILE, function (&$dates) use ($sessionId) {
    unset($dates[$sessionId]);
});

jsonResponse(['success' => true, 'message' => 'Session supprimée avec succès']);
