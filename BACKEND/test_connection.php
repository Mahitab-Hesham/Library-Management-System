<?php
/**
 * Database Connection Test Script
 * Diagnostics to check if backend can connect to the database
 */

require_once 'config.php';

// Try to connect
try {
    $pdo = getDBConnection();
    
    // Test 1: Connection successful
    echo "✓ Database connection successful\n\n";
    
    // Test 2: Check if tables exist
    $stmt = $pdo->query("SHOW TABLES FROM lms");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables in database 'lms':\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    echo "\n";
    
    // Test 3: Count records in each table
    $tableNames = ['books', 'users', 'staff_members', 'borrowing_processes', 'fines'];
    echo "Record count in each table:\n";
    foreach ($tableNames as $table) {
        try {
            $result = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
            $count = $result->fetch(PDO::FETCH_ASSOC)['cnt'];
            echo "  - $table: $count records\n";
        } catch (Exception $e) {
            echo "  - $table: ERROR - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✓ All tests passed! Backend can read the database.\n";
    
} catch (PDOException $e) {
    echo "✗ Database connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}
?>
