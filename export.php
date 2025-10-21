<?php
require_once 'config.php';

// Vérifier l'authentification admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    die('Non autorisé');
}

// Récupérer le filtre de session
$sessionFilter = isset($_GET['session']) ? sanitizeInput($_GET['session']) : 'all';

// Charger les données
$data = file_exists(FEEDBACK_FILE) ? file(FEEDBACK_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

// Nom du fichier
$filename = 'feedback_export_' . date('Y-m-d_His') . '.csv';
if ($sessionFilter !== 'all') {
    $filename = 'feedback_' . $sessionFilter . '_' . date('Y-m-d_His') . '.csv';
}

// Headers pour le téléchargement
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Créer le flux de sortie
$output = fopen('php://output', 'w');

// Ajouter le BOM UTF-8 pour Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// En-têtes du CSV
fputcsv($output, ['Date et Heure', 'Type', 'Valeur', 'Émotion', 'Session'], ';');

// Mapper les valeurs aux émotions
$emotions = [
    0 => 'Pas satisfait 😟',
    1 => 'Moyen 😐',
    2 => 'Bien 🙂',
    3 => 'Excellent 😃'
];

$typeLabels = [
    'liked' => 'J\'ai aimé',
    'learned' => 'J\'ai appris'
];

// Écrire les données
foreach ($data as $line) {
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

        $typeLabel = isset($typeLabels[$type]) ? $typeLabels[$type] : $type;
        $emotion = isset($emotions[$value]) ? $emotions[$value] : $value;

        fputcsv($output, [
            $timestamp,
            $typeLabel,
            $value,
            $emotion,
            $session
        ], ';');
    }
}

fclose($output);
exit;
?>
