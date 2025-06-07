<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    // Validate required field
    if (empty($_POST['phoneNumber'])) {
        throw new Exception('Phone number is required');
    }

    $phoneNumber = trim($_POST['phoneNumber']);
    $jsonFile = './data/certificates.json';

    // Check if certificates.json exists
    if (!file_exists($jsonFile)) {
        throw new Exception('No certificates found');
    }

    // Load certificates data
    $certificates = json_decode(file_get_contents($jsonFile), true);
    if (!$certificates) {
        throw new Exception('Failed to read certificates data');
    }

    // Check if phone number exists
    if (!isset($certificates[$phoneNumber])) {
        throw new Exception('No certificate found for this phone number');
    }

    $certificateData = $certificates[$phoneNumber];
    
    // Check if already deleted (soft delete check)
    if (isset($certificateData['deleted']) && $certificateData['deleted'] === true) {
        throw new Exception('Certificate has already been deleted');
    }

    $filename = $certificateData['filename'];
    $filepath = $certificateData['path'];
    $displayName = $certificateData['displayName'];

    // Delete the physical file
    if (file_exists($filepath)) {
        if (!unlink($filepath)) {
            throw new Exception('Failed to delete certificate file');
        }
    }

    // Remove entry from certificates array
    unset($certificates[$phoneNumber]);

    // Save updated JSON
    if (!file_put_contents($jsonFile, json_encode($certificates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
        throw new Exception('Failed to update certificate data');
    }

    // Log the deletion
    $logEntry = date('Y-m-d H:i:s') . " - Deleted: {$filename} for {$phoneNumber} ({$displayName})\n";
    file_put_contents('./delete_log.txt', $logEntry, FILE_APPEND | LOCK_EX);

    // Return success response
    echo json_encode([
        'success' => true,
        'phoneNumber' => $phoneNumber,
        'filename' => $filename,
        'displayName' => $displayName,
        'deletedDate' => date('c'),
        'message' => 'Certificate deleted successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>