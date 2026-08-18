<?php
require_once __DIR__ . '/config.php';

$sessions = readJsonData(SESSIONS_FILE, []);

if (!$sessions) {
    mutateJsonData(SESSIONS_FILE, function (&$storedSessions) {
        if (!$storedSessions) {
            $storedSessions[] = [
                'id' => 'default_' . date('Y'),
                'name' => 'Cours ' . date('Y') . '/' . ((int) date('Y') + 1),
                'created_at' => date('Y-m-d H:i:s'),
                'is_active' => true,
            ];
        }
    });
    $sessions = readJsonData(SESSIONS_FILE, []);
}

$activeSession = null;
foreach ($sessions as $session) {
    if (!empty($session['is_active'])) {
        $activeSession = $session;
        break;
    }
}
if ($activeSession === null && $sessions) {
    $activeSession = end($sessions);
}

$isAdmin = isAdminSession(false);
$response = [
    'success' => true,
    'is_admin' => $isAdmin,
    'active_session' => $activeSession,
];

if ($isAdmin) {
    $response['sessions'] = $sessions;
    $response['csrf_token'] = generateCSRFToken();
}

jsonResponse($response);
