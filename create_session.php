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

// Récupérer le nom de la session
if (!isset($_POST['name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nom de session manquant']);
    exit;
}

$name = sanitizeInput($_POST['name']);

// Validation
if (empty($name) || strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nom de session invalide']);
    exit;
}

// Charger les sessions existantes
$sessions = json_decode(file_get_contents(SESSIONS_FILE), true);

// Générer un ID unique
$id = 'session_' . time() . '_' . substr(md5($name), 0, 8);

// Vérifier que l'ID n'existe pas déjà
foreach ($sessions as $session) {
    if ($session['id'] === $id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cette session existe déjà']);
        exit;
    }
}

// Ajouter la nouvelle session
$newSession = [
    'id' => $id,
    'name' => $name,
    'created_at' => date('Y-m-d H:i:s')
];

$sessions[] = $newSession;

// Sauvegarder
if (file_put_contents(SESSIONS_FILE, json_encode($sessions, JSON_PRETTY_PRINT))) {
    echo json_encode([
        'success' => true,
        'message' => 'Session créée avec succès',
        'session' => $newSession
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde']);
}
?>
