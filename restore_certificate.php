<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Function to send JSON response and exit
function sendResponse($success, $message, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get phone number and action from POST data
$phoneNumber = $_POST['phoneNumber'] ?? '';
$action = $_POST['action'] ?? 'restore';

if (empty($phoneNumber)) {
    sendResponse(false, 'Phone number is required', [], 400);
}

// Path to certificates JSON file
$certificatesFile = './data/certificates.json';

try {
    // Read existing certificates
    if (!file_exists($certificatesFile)) {
        sendResponse(false, 'Certificates file not found', [], 404);
    }

    $certificatesData = file_get_contents($certificatesFile);
    if ($certificatesData === false) {
        sendResponse(false, 'Failed to read certificates file', [], 500);
    }

    $certificates = json_decode($certificatesData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, 'Invalid certificates data format', [], 500);
    }

    if (!$certificates || !is_array($certificates)) {
        sendResponse(false, 'Empty or invalid certificates data', [], 500);
    }

    // Check if certificate exists
    if (!isset($certificates[$phoneNumber])) {
        sendResponse(false, 'Certificate not found for this phone number', [], 404);
    }

    // Check if certificate is actually deleted
    if (!isset($certificates[$phoneNumber]['deleted']) || !$certificates[$phoneNumber]['deleted']) {
        sendResponse(false, 'Certificate is not in deleted state', [], 400);
    }

    // Reset certificate data for restore
    $certificates[$phoneNumber]['deleted'] = false;
    $certificates[$phoneNumber]['downloadCount'] = 0;

    $jsonData = json_encode($certificates, JSON_PRETTY_PRINT);
    if ($jsonData === false) {
        sendResponse(false, 'Failed to encode certificates data', [], 500);
    }

    if (file_put_contents($certificatesFile, $jsonData) === false) {
        sendResponse(false, 'Failed to save certificates file', [], 500);
    }

    // Log the restore action
    $logEntry = date('Y-m-d H:i:s') . " - Certificate restored: {$phoneNumber}\n";
    @file_put_contents('./logs/certificate_restore.log', $logEntry, FILE_APPEND | LOCK_EX);

    // Send success response
    sendResponse(true, 'Certificate restored successfully', [
        'phoneNumber' => $phoneNumber,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Certificate restore error: " . $e->getMessage());
    sendResponse(false, 'Server error occurred: ' . $e->getMessage(), [], 500);
}
?>