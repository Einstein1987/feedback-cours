<?php
/**
 * Fichier ping.php - Heartbeat pour maintenir la connexion active
 */

// Headers pour éviter la mise en cache
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Réponse simple pour confirmer que le serveur est accessible
echo json_encode([
    'status' => 'ok',
    'timestamp' => time(),
    'datetime' => date('Y-m-d H:i:s')
]);
?>