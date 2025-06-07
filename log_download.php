<?php
/**
 * Certificate Download Logger
 * Logs all download activities and file deletions to download.log
 * Compatible with cPanel hosting
 */

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests for CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get the JSON data from the request body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Validate JSON data
    if (!$data) {
        throw new Exception('Invalid JSON data received');
    }
    
    // Validate required fields
    $requiredFields = ['action', 'timestamp', 'phoneNumber', 'certificateName', 'filename'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Get client IP address (works with CloudFlare, proxies, etc.)
    $clientIP = getClientIP();
    
    // Prepare log entry based on action type
    switch ($data['action']) {
        case 'download':
            // Validate download-specific fields
            if (!isset($data['downloadCount']) || !isset($data['remainingDownloads'])) {
                throw new Exception('Missing download count or remaining downloads');
            }
            
            $logEntry = sprintf(
                "[%s] DOWNLOAD - Phone: %s | Certificate: %s | File: %s | Count: %d/5 | Remaining: %d | IP: %s | UA: %s\n",
                $data['timestamp'],
                sanitizeInput($data['phoneNumber']),
                sanitizeInput($data['certificateName']),
                sanitizeInput($data['filename']),
                intval($data['downloadCount']),
                intval($data['remainingDownloads']),
                $clientIP,
                sanitizeInput(substr($data['userAgent'], 0, 100)) // Limit user agent length
            );
            break;
            
        case 'delete':
            // Validate deletion-specific fields
            if (!isset($data['reason'])) {
                throw new Exception('Missing deletion reason');
            }
            
            $logEntry = sprintf(
                "[%s] DELETE - Phone: %s | Certificate: %s | File: %s | Reason: %s | IP: %s\n",
                $data['timestamp'],
                sanitizeInput($data['phoneNumber']),
                sanitizeInput($data['certificateName']),
                sanitizeInput($data['filename']),
                sanitizeInput($data['reason']),
                $clientIP
            );
            break;
            
        default:
            throw new Exception('Invalid action type: ' . $data['action']);
    }
    
    // Define log file path (in root directory)
    $logFile = './download.log';
    
    // Create log file if it doesn't exist
    if (!file_exists($logFile)) {
        $header = "=== Certificate Download Log Started ===\n";
        $header .= "Generated: " . date('Y-m-d H:i:s T') . "\n";
        $header .= "Format: [Timestamp] ACTION - Details\n";
        $header .= "=====================================\n\n";
        
        file_put_contents($logFile, $header, LOCK_EX);
    }
    
    // Rotate log file if it's getting too large (optional)
    rotateLogIfNeeded($logFile, 10485760); // 10MB limit
    
    // Append log entry to file
    $result = file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    if ($result === false) {
        throw new Exception('Failed to write to log file - check permissions');
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Log entry recorded successfully',
        'action' => $data['action'],
        'bytes_written' => $result,
        'log_file' => $logFile
    ]);
    
} catch (Exception $e) {
    // Log error and return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Logging failed: ' . $e->getMessage()
    ]);
    
    // Log the error to PHP error log
    error_log('Certificate logging error: ' . $e->getMessage());
}

/**
 * Get the real client IP address
 * Handles CloudFlare, proxies, load balancers, etc.
 */
function getClientIP() {
    // List of headers that might contain the real IP
    $ipHeaders = [
        'HTTP_CF_CONNECTING_IP',     // CloudFlare
        'HTTP_CLIENT_IP',            // Proxy
        'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
        'HTTP_X_FORWARDED',          // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
        'HTTP_FORWARDED_FOR',        // Proxy
        'HTTP_FORWARDED',            // Proxy
        'REMOTE_ADDR'                // Standard
    ];
    
    foreach ($ipHeaders as $header) {
        if (array_key_exists($header, $_SERVER) && !empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            
            // Handle comma-separated IPs (when multiple proxies are involved)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            
            $ip = trim($ip);
            
            // Validate IP address and exclude private/reserved ranges
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    // Fallback to REMOTE_ADDR or unknown
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Sanitize input to prevent log injection
 */
function sanitizeInput($input) {
    // Remove/replace potentially harmful characters
    $input = str_replace(["\n", "\r", "\t"], ' ', $input);
    $input = strip_tags($input);
    return trim($input);
}

/**
 * Rotate log file when it gets too large
 * Keeps the system responsive and prevents huge log files
 */
function rotateLogIfNeeded($logFile, $maxSize = 10485760) { // 10MB default
    if (file_exists($logFile) && filesize($logFile) > $maxSize) {
        // Create backup filename with timestamp
        $backupFile = $logFile . '.backup.' . date('Y-m-d_H-i-s');
        
        // Move current log to backup
        if (rename($logFile, $backupFile)) {
            // Create new log file with header
            $header = "=== Certificate Download Log Started (After Rotation) ===\n";
            $header .= "Generated: " . date('Y-m-d H:i:s T') . "\n";
            $header .= "Previous log backed up to: " . basename($backupFile) . "\n";
            $header .= "========================================================\n\n";
            
            file_put_contents($logFile, $header, LOCK_EX);
            
            // Optional: Clean up old backups (keep only last 5)
            cleanupOldBackups($logFile, 5);
        }
    }
}

/**
 * Clean up old backup files
 * Keeps only the specified number of most recent backups
 */
function cleanupOldBackups($logFile, $keepCount = 5) {
    $logDir = dirname($logFile);
    $logName = basename($logFile);
    
    // Find all backup files
    $backupFiles = glob($logDir . '/' . $logName . '.backup.*');
    
    if (count($backupFiles) > $keepCount) {
        // Sort by modification time (newest first)
        usort($backupFiles, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Delete oldest backups
        $filesToDelete = array_slice($backupFiles, $keepCount);
        foreach ($filesToDelete as $file) {
            unlink($file);
        }
    }
}

/**
 * Optional: Get log file statistics
 * Useful for monitoring and debugging
 */
function getLogStats($logFile) {
    if (!file_exists($logFile)) {
        return null;
    }
    
    return [
        'file_size' => filesize($logFile),
        'file_size_mb' => round(filesize($logFile) / 1024 / 1024, 2),
        'last_modified' => date('Y-m-d H:i:s', filemtime($logFile)),
        'line_count' => count(file($logFile)),
        'permissions' => substr(sprintf('%o', fileperms($logFile)), -4)
    ];
}
?>