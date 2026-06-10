<?php
/**
 * Configuration File
 * Qharleh Productions Africa
 * 
 * Replace database constants below with your InfinityFree details upon deployment.
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'qharleh_productions');

// Site Meta
define('SITE_NAME', 'Qharleh Productions Africa');
define('SITE_MOTTO', 'Capture moment, tell a story');

// Directory Paths
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');
