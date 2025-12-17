<?php
// debug_auth.php - Simple version to test
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'test' => 'File is working',
    'timestamp' => date('Y-m-d H:i:s')
]);

exit;
?>