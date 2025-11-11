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

// Vérifier l'expiration de la session admin (30 minutes)
if (isset($_SESSION['admin_time']) && (time() - $_SESSION['admin_time'] > ADMIN_SESSION_TIMEOUT)) {
    unset($_SESSION['is_admin']);
    unset($_SESSION['admin_time']);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit;
}

// Récupérer le filtre de session
$sessionFilter = isset($_POST['session']) ? sanitizeInput($_POST['session']) : 'all';

// Validation stricte si pas "all"
if ($sessionFilter !== 'all' && !validateSessionId($sessionFilter)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Format de session invalide']);
    exit;
}

if ($sessionFilter === 'all') {
    // Réinitialiser toutes les statistiques
    file_put_contents(FEEDBACK_FILE, '');
} else {
    // Réinitialiser uniquement pour une session spécifique
    $data = file_exists(FEEDBACK_FILE) ? file(FEEDBACK_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $newData = [];
    
    foreach ($data as $line) {
        $parts = str_getcsv($line);
        if (count($parts) >= 4) {
            $session = trim($parts[3], '"');
            // Garder les lignes des autres sessions
            if ($session !== $sessionFilter) {
                $newData[] = $line;
            }
        }
    }
    
    file_put_contents(FEEDBACK_FILE, implode(PHP_EOL, $newData) . (count($newData) > 0 ? PHP_EOL : ''));
}

// Enregistrer la date de réinitialisation
$resetDate = date('Y-m-d H:i:s');
file_put_contents(RESET_DATE_FILE, $resetDate);

echo json_encode([
    'success' => true,
    'message' => 'Statistiques réinitialisées',
    'resetDate' => $resetDate
]);
?>