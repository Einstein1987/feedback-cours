<?php
require_once 'config.php';

header('Content-Type: application/json');

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Vérifier l'authentification admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Vérifier le token CSRF
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit;
}

// Récupérer l'ID de la session
if (!isset($_POST['session'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de session manquant']);
    exit;
}

$sessionId = sanitizeInput($_POST['session']);

// Validation stricte
if (!validateSessionId($sessionId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Format de session invalide']);
    exit;
}

// Charger les sessions existantes
$sessions = json_decode(file_get_contents(SESSIONS_FILE), true);

// Trouver et supprimer la session
$found = false;
$newSessions = [];
foreach ($sessions as $session) {
    if ($session['id'] === $sessionId) {
        $found = true;
        continue; // Ne pas ajouter cette session
    }
    $newSessions[] = $session;
}

if (!$found) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Session non trouvée']);
    exit;
}

// Ne pas permettre de supprimer si c'est la dernière session
if (count($newSessions) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Impossible de supprimer la dernière session']);
    exit;
}

// Supprimer également les données associées à cette session
$data = file_exists(FEEDBACK_FILE) ? file(FEEDBACK_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$newData = [];

foreach ($data as $line) {
    $parts = str_getcsv($line);
    if (count($parts) >= 4) {
        $session = trim($parts[3], '"');
        // Garder les lignes des autres sessions
        if ($session !== $sessionId) {
            $newData[] = $line;
        }
    }
}

// Sauvegarder
file_put_contents(FEEDBACK_FILE, implode(PHP_EOL, $newData) . (count($newData) > 0 ? PHP_EOL : ''));
file_put_contents(SESSIONS_FILE, json_encode($newSessions, JSON_PRETTY_PRINT));

echo json_encode([
    'success' => true,
    'message' => 'Session supprimée avec succès'
]);
?>