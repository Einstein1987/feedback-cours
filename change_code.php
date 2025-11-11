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

// Récupérer le nouveau code
if (!isset($_POST['code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Code manquant']);
    exit;
}

$newCode = $_POST['code'];

// Validation du format (4 chiffres)
if (!validateAdminCode($newCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le code doit contenir exactement 4 chiffres']);
    exit;
}

// Hasher et sauvegarder le nouveau code
$hashedCode = hashAdminCode($newCode);
file_put_contents(ADMIN_HASH_FILE, $hashedCode);

echo json_encode([
    'success' => true,
    'message' => 'Code administrateur modifié avec succès'
]);
?>