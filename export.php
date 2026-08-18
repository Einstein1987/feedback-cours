<?php
require_once __DIR__ . '/config.php';

if (!isAdminSession(true)) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    exit('Session administrateur expirée');
}

$sessionFilter = $_GET['session'] ?? 'all';
if ($sessionFilter !== 'all' && !validateSessionId($sessionFilter)) {
    http_response_code(400);
    exit('Format de session invalide');
}

$filename = $sessionFilter === 'all'
    ? 'feedback_export_' . date('Y-m-d_His') . '.csv'
    : 'feedback_' . $sessionFilter . '_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Date et Heure', 'Type', 'Valeur', 'Émotion', 'Session'], ';');

$emotions = [
    0 => 'Pas satisfait 😟',
    1 => 'Moyen 😐',
    2 => 'Bien 🙂',
    3 => 'Excellent 😃',
];
$typeLabels = [
    'liked' => 'J’ai aimé',
    'learned' => 'J’ai appris',
];

foreach (readFeedbackLines() as $line) {
    $parts = str_getcsv($line);
    if (count($parts) < 4) {
        continue;
    }

    list($timestamp, $type, $value, $sessionId) = $parts;
    if ($sessionFilter !== 'all' && $sessionId !== $sessionFilter) {
        continue;
    }

    $value = (int) $value;
    fputcsv($output, [
        $timestamp,
        $typeLabels[$type] ?? $type,
        $value,
        $emotions[$value] ?? (string) $value,
        $sessionId,
    ], ';');
}

fclose($output);
