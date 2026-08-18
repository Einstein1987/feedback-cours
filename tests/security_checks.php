<?php
declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once dirname(__DIR__) . '/config.php';

function assertTrue($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "ÉCHEC : {$message}\n");
        exit(1);
    }
}

assertTrue(validateSessionId('default_2026'), 'Une session par défaut valide doit être acceptée.');
assertTrue(validateSessionId('session_abcdef123456'), 'Une session générée valide doit être acceptée.');
assertTrue(!validateSessionId('../data'), 'Un chemin ne doit jamais être accepté comme session.');
assertTrue(validateAdminCode('1234'), 'Un ancien code à quatre chiffres doit rester utilisable.');
assertTrue(validateNewAdminCode('123456'), 'Un nouveau code à six chiffres doit être accepté.');
assertTrue(!validateNewAdminCode('1234'), 'Un nouveau code à quatre chiffres doit être refusé.');
assertTrue(validateSessionName('Cours 2026/2027'), 'Un nom de session usuel doit être accepté.');
assertTrue(!validateSessionName('<script>'), 'Une balise HTML doit être refusée.');

$testJson = DATA_DIR . '/test_sessions.json';
initializeDataFile($testJson, '[]');
mutateJsonData($testJson, function (&$data) {
    $data[] = ['id' => 'session_test'];
});
assertTrue(count(readJsonData($testJson)) === 1, 'La mutation JSON verrouillée doit être persistée.');
@unlink($testJson);
@unlink($testJson . '.lock');

$initialFeedback = file_get_contents(FEEDBACK_FILE);
assertTrue(appendFeedbackRow(['2026-08-18 09:00:00', 'liked', 3, 'session_test']), 'Un feedback doit pouvoir être ajouté.');
$removed = filterFeedbackRows(function ($row) {
    return ($row[3] ?? '') !== 'session_test';
});
assertTrue($removed >= 1, 'Le feedback de test doit pouvoir être supprimé.');
atomicWriteFile(FEEDBACK_FILE, $initialFeedback === false ? '' : $initialFeedback);

$_SESSION['is_admin'] = true;
$_SESSION['admin_time'] = time() - ADMIN_SESSION_TIMEOUT - 1;
assertTrue(!isAdminSession(false), 'Une session administrateur expirée doit être refusée.');

echo "Contrôles de sécurité réussis.\n";
