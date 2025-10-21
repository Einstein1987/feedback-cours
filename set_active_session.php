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

// Récupérer l'ID de la session à activer
if (!isset($_POST['session_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de session manquant']);
    exit;
}

$sessionId = sanitizeInput($_POST['session_id']);

// Charger les sessions existantes
$sessions = json_decode(file_get_contents(SESSIONS_FILE), true);

// Trouver la session et mettre à jour le statut actif
$found = false;
foreach ($sessions as &$session) {
    if ($session['id'] === $sessionId) {
        $session['is_active'] = true;
        $found = true;
    } else {
        $session['is_active'] = false;
    }
}

if (!$found) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Session non trouvée']);
    exit;
}

// Sauvegarder
if (file_put_contents(SESSIONS_FILE, json_encode($sessions, JSON_PRETTY_PRINT))) {
    echo json_encode([
        'success' => true,
        'message' => 'Session active mise à jour'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde']);
}
?>