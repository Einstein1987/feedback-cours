<?php
require_once 'config.php';

header('Content-Type: application/json');

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Rate limiting pour éviter les attaques par force brute
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit('admin_' . $clientIP, MAX_ADMIN_ATTEMPTS, ADMIN_RATE_LIMIT_WINDOW)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Trop de tentatives. Veuillez patienter 5 minutes.']);
    exit;
}

// Récupérer et valider le code
if (!isset($_POST['code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Code manquant']);
    exit;
}

$code = $_POST['code'];

// Validation du format (4 chiffres)
if (!validateAdminCode($code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Format de code invalide']);
    exit;
}

// Vérifier le code
if (verifyAdminCode($code)) {
    session_regenerate_id(true); // Protection session fixation
    // Créer une session admin
    $_SESSION['is_admin'] = true;
    $_SESSION['admin_time'] = time();
    
    echo json_encode([
        'success' => true,
        'message' => 'Authentification réussie'
    ]);
} else {
    // Délai supplémentaire en cas d'échec (protection contre brute force)
    usleep(500000); // 0.5 seconde
    
    echo json_encode([
        'success' => false,
        'message' => 'Code incorrect'
    ]);
}
?>
