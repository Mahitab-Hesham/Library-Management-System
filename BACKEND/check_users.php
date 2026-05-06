<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // Get users count
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users");
    $usersCount = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "Total users: $usersCount\n";
    
    if ($usersCount > 0) {
        echo "\nUsers in database:\n";
        $stmt = $pdo->query("SELECT user_id, username, fullname FROM users LIMIT 5");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $user) {
            echo "  - ID: {$user['user_id']}, Username: {$user['username']}, Name: {$user['fullname']}\n";
        }
    }
    
    // Get staff count
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM staff_member");
    $staffCount = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "\nTotal staff: $staffCount\n";
    
    if ($staffCount > 0) {
        echo "\nStaff in database:\n";
        $stmt = $pdo->query("SELECT staff_id, username, staff_member_name, position FROM staff_member LIMIT 5");
        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($staff as $s) {
            echo "  - ID: {$s['staff_id']}, Username: {$s['username']}, Name: {$s['staff_member_name']}, Position: {$s['position']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
