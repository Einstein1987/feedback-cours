<?php
require_once 'config.php';

header('Content-Type: application/json');

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Rate limiting - max 10 votes par minute par IP
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit('vote_' . $clientIP, MAX_VOTES_PER_MINUTE, VOTE_RATE_LIMIT_WINDOW)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Trop de requêtes. Veuillez patienter.']);
    exit;
}

// Vérifier la taille du fichier CSV AVANT d'ajouter
if (!checkCSVSize()) {
    http_response_code(507); // Insufficient Storage
    echo json_encode([
        'success' => false, 
        'message' => 'Stockage plein. Veuillez contacter l\'administrateur pour archiver les données.'
    ]);
    exit;
}

// Valider et nettoyer les entrées
if (!isset($_POST['type']) || !isset($_POST['value']) || !isset($_POST['session'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$type = sanitizeInput($_POST['type']);
$value = intval($_POST['value']);
$session = sanitizeInput($_POST['session']);

// Validation stricte du type
$validTypes = ['liked', 'learned'];
if (!in_array($type, $validTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Type de feedback invalide']);
    exit;
}

// Validation stricte de la valeur (0-3)
if ($value < 0 || $value > 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valeur invalide']);
    exit;
}

// Validation de la session
if (!validateSessionId($session)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Format de session invalide']);
    exit;
}

// Vérifier que la session existe
$sessions = json_decode(file_get_contents(SESSIONS_FILE), true);
$sessionExists = false;
foreach ($sessions as $s) {
    if ($s['id'] === $session) {
        $sessionExists = true;
        break;
    }
}

if (!$sessionExists) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Session inexistante']);
    exit;
}

// Préparer l'entrée CSV (échapper les données)
$timestamp = date('Y-m-d H:i:s');
$entry = sprintf(
    '"%s","%s","%d","%s"' . PHP_EOL,
    $timestamp,
    $type,
    $value,
    $session
);

// Écrire dans le fichier avec verrouillage
$fp = fopen(FEEDBACK_FILE, 'a');
if ($fp) {
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, $entry);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    
    echo json_encode([
        'success' => true,
        'message' => 'Feedback enregistré avec succès'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'enregistrement'
    ]);
}
?>