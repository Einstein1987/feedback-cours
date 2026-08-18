<?php
require_once __DIR__ . '/config.php';

requireAdmin();

$sessionFilter = $_GET['session'] ?? 'all';
if ($sessionFilter !== 'all' && !validateSessionId($sessionFilter)) {
    jsonResponse(['success' => false, 'message' => 'Format de session invalide'], 400);
}

$counts = ['liked' => [0, 0, 0, 0], 'learned' => [0, 0, 0, 0]];
$totalResponses = 0;
$dailyData = [];

foreach (readFeedbackLines() as $line) {
    $parts = str_getcsv($line);
    if (count($parts) < 4) {
        continue;
    }

    list($timestamp, $type, $value, $sessionId) = $parts;
    $value = (int) $value;

    if ($sessionFilter !== 'all' && $sessionId !== $sessionFilter) {
        continue;
    }
    if (!isset($counts[$type]) || $value < 0 || $value > 3) {
        continue;
    }

    $counts[$type][$value]++;
    $totalResponses++;

    $date = date('Y-m-d', strtotime($timestamp));
    if (!isset($dailyData[$date])) {
        $dailyData[$date] = ['liked' => [], 'learned' => []];
    }
    $dailyData[$date][$type][] = $value;
}

ksort($dailyData);
$timeline = ['labels' => [], 'liked' => [], 'learned' => []];
foreach (array_slice($dailyData, -30, 30, true) as $date => $values) {
    $timeline['labels'][] = date('d/m', strtotime($date));
    $timeline['liked'][] = $values['liked'] ? round(array_sum($values['liked']) / count($values['liked']), 2) : null;
    $timeline['learned'][] = $values['learned'] ? round(array_sum($values['learned']) / count($values['learned']), 2) : null;
}

$resetDates = readJsonData(RESET_DATES_FILE, []);
$lastResetDate = $resetDates[$sessionFilter] ?? $resetDates['all'] ?? 'Aucune remise à zéro effectuée';

jsonResponse([
    'success' => true,
    'counts' => $counts,
    'lastResetDate' => $lastResetDate,
    'totalResponses' => $totalResponses,
    'timeline' => $timeline,
]);
