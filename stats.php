<?php
require_once 'config.php';

header('Content-Type: application/json');

// Vérifier l'authentification admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Récupérer le filtre de session
$sessionFilter = isset($_GET['session']) ? sanitizeInput($_GET['session']) : 'all';

// Validation stricte si pas "all"
if ($sessionFilter !== 'all' && !validateSessionId($sessionFilter)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Format de session invalide']);
    exit;
}

// Charger les données
$data = file_exists(FEEDBACK_FILE) ? file(FEEDBACK_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$counts = ['liked' => [0, 0, 0, 0], 'learned' => [0, 0, 0, 0]];
$lastResetDate = file_exists(RESET_DATE_FILE) ? file_get_contents(RESET_DATE_FILE) : 'Aucune remise à zéro effectuée';
$totalResponses = 0;

// Pour la timeline
$timeline = [
    'labels' => [],
    'liked' => [],
    'learned' => []
];
$dailyData = [];

foreach ($data as $line) {
    // Parser la ligne CSV
    $parts = str_getcsv($line);
    
    if (count($parts) >= 3) {
        $timestamp = isset($parts[0]) ? trim($parts[0], '"') : '';
        $type = isset($parts[1]) ? trim($parts[1], '"') : '';
        $value = isset($parts[2]) ? intval(trim($parts[2], '"')) : 0;
        $session = isset($parts[3]) ? trim($parts[3], '"') : '';

        // Filtrer par session si nécessaire
        if ($sessionFilter !== 'all' && $session !== $sessionFilter) {
            continue;
        }

        // Compter les réponses
        if (isset($counts[$type]) && $value >= 0 && $value <= 3) {
            $counts[$type][$value]++;
            $totalResponses++;

            // Données pour la timeline (par jour)
            if (!empty($timestamp)) {
                $date = date('Y-m-d', strtotime($timestamp));
                if (!isset($dailyData[$date])) {
                    $dailyData[$date] = [
                        'liked' => [],
                        'learned' => []
                    ];
                }
                $dailyData[$date][$type][] = $value;
            }
        }
    }
}

// Construire la timeline
ksort($dailyData);
foreach ($dailyData as $date => $values) {
    $timeline['labels'][] = date('d/m', strtotime($date));
    
    // Moyenne du jour pour "liked"
    $likedAvg = !empty($values['liked']) ? array_sum($values['liked']) / count($values['liked']) : 0;
    $timeline['liked'][] = round($likedAvg, 2);
    
    // Moyenne du jour pour "learned"
    $learnedAvg = !empty($values['learned']) ? array_sum($values['learned']) / count($values['learned']) : 0;
    $timeline['learned'][] = round($learnedAvg, 2);
}

// Limiter la timeline aux 30 derniers jours
if (count($timeline['labels']) > 30) {
    $timeline['labels'] = array_slice($timeline['labels'], -30);
    $timeline['liked'] = array_slice($timeline['liked'], -30);
    $timeline['learned'] = array_slice($timeline['learned'], -30);
}

echo json_encode([
    'success' => true,
    'counts' => $counts,
    'lastResetDate' => $lastResetDate,
    'totalResponses' => $totalResponses,
    'timeline' => $timeline
]);
?>