<?php
require_once __DIR__ . '/config.php';

requirePostRequest();
requireAdmin();
requireCSRFToken();

$sessionFilter = $_POST['session'] ?? 'all';
if ($sessionFilter !== 'all' && !validateSessionId($sessionFilter)) {
    jsonResponse(['success' => false, 'message' => 'Format de session invalide'], 400);
}

if ($sessionFilter === 'all') {
    filterFeedbackRows(function () {
        return false;
    });
} else {
    filterFeedbackRows(function ($row) use ($sessionFilter) {
        return ($row[3] ?? '') !== $sessionFilter;
    });
}

$resetDate = date('Y-m-d H:i:s');
mutateJsonData(RESET_DATES_FILE, function (&$dates) use ($sessionFilter, $resetDate) {
    if ($sessionFilter === 'all') {
        $dates = ['all' => $resetDate];
    } else {
        $dates[$sessionFilter] = $resetDate;
    }
});

jsonResponse([
    'success' => true,
    'message' => 'Statistiques réinitialisées',
    'resetDate' => $resetDate,
]);
