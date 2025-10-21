<?php
require_once 'config.php';

header('Content-Type: application/json');

// Charger les sessions
$sessions = json_decode(file_get_contents(SESSIONS_FILE), true);

// Si aucune session n'existe, créer une session par défaut
if (empty($sessions)) {
    $defaultSession = [
        'id' => 'default_' . date('Y'),
        'name' => 'Cours ' . date('Y') . '/' . (date('Y') + 1), // Format 2025/2026
        'created_at' => date('Y-m-d H:i:s'),
        'is_active' => true
    ];
    $sessions = [$defaultSession];
    file_put_contents(SESSIONS_FILE, json_encode($sessions, JSON_PRETTY_PRINT));
}

// Déterminer la session active (marquée is_active = true)
$activeSession = null;
foreach ($sessions as $session) {
    if (isset($session['is_active']) && $session['is_active'] === true) {
        $activeSession = $session;
        break;
    }
}

// Si aucune session active, prendre la plus récente
if (!$activeSession) {
    $activeSession = end($sessions);
}

echo json_encode([
    'success' => true,
    'sessions' => $sessions,
    'active_session' => $activeSession
]);
?>