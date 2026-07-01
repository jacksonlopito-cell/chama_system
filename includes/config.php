<?php
/**
 * Main Configuration File
 * Loaded on all pages
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/error.log');

// Timezone
date_default_timezone_set('Africa/Nairobi');

// Session configuration
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', $isHttps ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base URL (auto-detect) — always points to application root
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Compute from __DIR__ since config.php is at <root>/includes/config.php
$configDir = str_replace('\\', '/', __DIR__); // e.g., C:/xampp/htdocs/chama-system/includes
$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''); // e.g., C:/xampp/htdocs
if ($docRoot && strpos($configDir, $docRoot) === 0) {
    // Strip docRoot to get relative path, then go up one level (remove /includes)
    $relativePath = '/' . ltrim(str_replace($docRoot, '', $configDir), '/');
    $relativePath = dirname($relativePath); // Remove /includes
    $relativePath = str_replace('\\', '/', $relativePath);
    define('BASE_URL', $protocol . '://' . $host . rtrim($relativePath, '/') . '/');
} else {
    // Fallback: use SCRIPT_NAME and strip subdirectories
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $scriptDir = preg_replace('#/(accounting|reports|ajax|views|includes)(/.*)?$#', '', $scriptDir);
    define('BASE_URL', $protocol . '://' . $host . $scriptDir . '/');
}

// Paths
define('ROOT_PATH', dirname(__DIR__) . '/');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');
define('ASSETS_PATH', ROOT_PATH . 'assets/');
define('UPLOADS_PATH', ROOT_PATH . 'uploads/');

// Load constants
require_once __DIR__ . '/constants.php';

// Load database
require_once ROOT_PATH . 'config/database.php';

// Load core functions
require_once __DIR__ . '/functions.php';

// Load security
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/validation.php';

// Load mailer
require_once __DIR__ . '/mailer.php';

// Load session handler and auth
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';

// Set secure headers
setSecureHeaders();
