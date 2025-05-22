<?php
/**
 * Database Configuration File
 * 
 * This file contains database connection settings and utility functions
 * for connecting to the database.
 */
require_once 'security_config.php';
// Use environment variables or a .env file in production
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'blkfarms_connect');
define('DB_PASSWORD', 'gNGA{-xKX#v3');
define('DB_NAME', 'blkfarms_farmapp');

/**
 * Get PDO database connection
 * 
 * @return PDO Database connection object
 */
function getDbConnection() {
    static $db = null;
    
    if ($db === null) {
        try {
            $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $db = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
        } catch (PDOException $e) {
            // Log error and display user-friendly message
            error_log('Database Connection Error: ' . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    return $db;
}

/**
 * Close database connection
 * 
 * @param PDO $db Database connection to close
 * @return void
 */
function closeDbConnection(&$db) {
    $db = null;
}