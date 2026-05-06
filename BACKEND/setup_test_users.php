<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $adminExists = $stmt->fetch();
    
    if ($adminExists) {
        echo "Admin user already exists\n";
    } else {
        // Create admin user
        $hashedPassword = hashPassword('admin123');
        $stmt = $pdo->prepare("INSERT INTO users (fullname, username, email, password, user_type, user_status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Administrator', 'admin', 'admin@library.local', $hashedPassword, 'Admin', 'Active']);
        echo "Admin user created successfully\n";
    }
    
    // Check if test user exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute(['testuser']);
    $testUserExists = $stmt->fetch();
    
    if ($testUserExists) {
        echo "Test user already exists\n";
    } else {
        // Create test user
        $hashedPassword = hashPassword('test123');
        $stmt = $pdo->prepare("INSERT INTO users (fullname, username, email, password, user_type, user_status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Test User', 'testuser', 'testuser@library.local', $hashedPassword, 'Student', 'Active']);
        echo "Test user created successfully\n";
    }
    
    // Check if manager exists
    $stmt = $pdo->prepare("SELECT staff_id FROM staff_member WHERE username = ?");
    $stmt->execute(['manager']);
    $managerExists = $stmt->fetch();
    
    if ($managerExists) {
        echo "Manager user already exists\n";
    } else {
        // Create manager
        $hashedPassword = hashPassword('manager123');
        $stmt = $pdo->prepare("INSERT INTO staff_member (staff_member_name, username, email, password, position, staff_status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Library Manager', 'manager', 'manager@library.local', $hashedPassword, 'Library Manager', 'Active']);
        echo "Manager user created successfully\n";
    }
    
    // Check if staff exists
    $stmt = $pdo->prepare("SELECT staff_id FROM staff_member WHERE username = ?");
    $stmt->execute(['staff']);
    $staffExists = $stmt->fetch();
    
    if ($staffExists) {
        echo "Staff user already exists\n";
    } else {
        // Create staff
        $hashedPassword = hashPassword('staff123');
        $stmt = $pdo->prepare("INSERT INTO staff_member (staff_member_name, username, email, password, position, staff_status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Librarian', 'staff', 'staff@library.local', $hashedPassword, 'Librarian', 'Active']);
        echo "Staff user created successfully\n";
    }
    
    echo "\n✓ Test credentials setup complete\n";
    echo "\nTest Accounts:\n";
    echo "1. User: admin / admin123 (for reference only)\n";
    echo "2. User: testuser / test123\n";
    echo "3. Staff: staff / staff123\n";
    echo "4. Manager: manager / manager123\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
