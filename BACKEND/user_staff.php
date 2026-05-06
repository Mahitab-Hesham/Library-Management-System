<?php
/**
 * Unified User & Staff Operations
 * --------------------------------
 * يحتوي هذا الملف على كل ما يتعلق بالمستخدمين والموظفين
 * وتم تقسيمه إلى أقسام واضحة مع ملاحظات لتسهيل التعديل.
 */

require_once 'config.php';

// Start session at the top
session_start();

// ========================
// المساعدة العامة General Helpers
// ========================
function respond($payload)
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function calculateAge(string $dateOfBirth): int
{
    $birthDate = new DateTime($dateOfBirth);
    $today = new DateTime();
    return $today->diff($birthDate)->y;
}

// ========================
// Role-Based Access Control
// ========================
/**
 * Get the current user's role from session
 * Returns: 'user', 'staff', 'manager', or null if not logged in
 */
function getSessionRole(): ?string
{
    if (isset($_SESSION['user_id'])) {
        return 'user';
    }
    if (isset($_SESSION['staff_id'])) {
        // Check if staff is a manager
        if (isset($_SESSION['is_manager']) && $_SESSION['is_manager']) {
            return 'manager';
        }
        return 'staff';
    }
    return null;
}

/**
 * Get current staff info from session (if logged in as staff)
 */
function getSessionStaffInfo(): ?array
{
    if (isset($_SESSION['staff_id'])) {
        return [
            'staff_id' => $_SESSION['staff_id'],
            'username' => $_SESSION['username'] ?? null,
            'position' => $_SESSION['member_position'] ?? null,
            'is_manager' => $_SESSION['is_manager'] ?? false
        ];
    }
    return null;
}

/**
 * Check if current session is staff (any level)
 */
function requireStaff(): bool
{
    $role = getSessionRole();
    return $role === 'staff' || $role === 'manager';
}

/**
 * Check if current session is manager
 */
function requireManager(): bool
{
    return getSessionRole() === 'manager';
}

// ========================
// قسم المستخدمين Users Block
// ========================
function validateUserAge(string $dateOfBirth): bool
{
    return calculateAge($dateOfBirth) >= 15;
}

function getMaxBorrowLimit(string $userType): int
{
    return match ($userType) {
        'Student' => 3,
        'Faculty' => 10,
        'External_user' => 5,
        default => 0
    };
}

function registerUser(array $data): array
{
    if (!validateUserAge($data['date_of_birth'])) {
        return ['success' => false, 'message' => 'Minimum age requirement is 15 years'];
    }

    $age = date_diff(date_create($data['date_of_birth']), date_create('today'))->y;

    $pdo = getDBConnection();
    $sql = "INSERT INTO users (fullname, username, email, phone_number, user_type, max_borrow_limit, 
            user_status, password, age) 
            VALUES (:fullname, :username, :email, :phone_number, :user_type, :max_borrow_limit, 
            'Active', :password, :age)";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':fullname' => $data['fullname'],
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':phone_number' => $data['phone_number'] ?? null,
            ':user_type' => $data['user_type'],
            ':max_borrow_limit' => getMaxBorrowLimit($data['user_type']),
            ':password' => hashPassword($data['password']),
            ':age' => $age
        ]);

        return ['success' => true, 'message' => 'Successful User Registration', 'user_id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        $msg = $e->getCode() == 23000 ? 'Username or email already exists' : $e->getMessage();
        return ['success' => false, 'message' => 'Registration failed: ' . $msg];
    }
}

function canUserBorrow(int $userId): array
{
    $pdo = getDBConnection();
    $sql = "SELECT u.user_status, u.max_borrow_limit,
            COUNT(bp.process_code) AS current_borrowings
            FROM users u
            LEFT JOIN borrowing_process bp ON u.user_id = bp.user_id
                AND bp.status IN ('Borrowed', 'Overdue')
            WHERE u.user_id = :user_id
            GROUP BY u.user_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }

    if ($user['user_status'] === 'Suspended') {
        return ['success' => false, 'message' => 'User is suspended'];
    }

    if ($user['current_borrowings'] >= $user['max_borrow_limit']) {
        return [
            'success' => false,
            'message' => 'User has reached borrowing limit',
            'current' => (int)$user['current_borrowings'],
            'limit' => (int)$user['max_borrow_limit']
        ];
    }

    return [
        'success' => true,
        'remaining' => (int)$user['max_borrow_limit'] - (int)$user['current_borrowings']
    ];
}

function userLogin(string $username, string $password): array
{
    $pdo = getDBConnection();
    $sql = "SELECT user_id, username, password, user_status FROM users WHERE username = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && verifyPassword($password, $user['password'])) {
        // Store in session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_status'] = $user['user_status'];
        
        return [
            'success' => true,
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'status' => $user['user_status'],
            'role' => 'user'
        ];
    }

    return ['success' => false, 'message' => 'Invalid username or password'];
}

function getUserInfo(int $userId): array|false
{
    $pdo = getDBConnection();
    $sql = "SELECT user_id, fullname, username, email, phone_number, user_type,
            max_borrow_limit, user_status, user_age, age
            FROM users WHERE user_id = :user_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetch();
}

function staffLogin(string $username, string $password): array
{
    $pdo = getDBConnection();
    $sql = "SELECT staff_id, username, password, staff_status, position
            FROM staff_member WHERE username = :username";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':username' => $username]);
    $staff = $stmt->fetch();

    if ($staff && verifyPassword($password, $staff['password'])) {
        // Determine if this staff is a manager
        $isManager = ($staff['position'] === 'Library Manager');
        
        // Store in session
        $_SESSION['staff_id'] = $staff['staff_id'];
        $_SESSION['username'] = $staff['username'];
        $_SESSION['member_position'] = $staff['position'];
        $_SESSION['staff_status'] = $staff['staff_status'];
        $_SESSION['is_manager'] = $isManager;
        
        return [
            'success' => true,
            'staff_id' => $staff['staff_id'],
            'username' => $staff['username'],
            'position' => $staff['position'],
            'status' => $staff['staff_status'],
            'is_manager' => $isManager,
            'role' => $isManager ? 'manager' : 'staff'
        ];
    }

    return ['success' => false, 'message' => 'Invalid username or password'];
}

function getStaffInfo(int $staffId): array|false
{
    $pdo = getDBConnection();
    $sql = "SELECT staff_id, member_name, email, phone_num, position,
            hire_date, salary, staff_status, age
            FROM staff_member WHERE staff_id = :staff_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':staff_id' => $staffId]);
    return $stmt->fetch();
}

function getActiveStaff(): array
{
    $pdo = getDBConnection();
    $sql = "SELECT staff_id, member_name, email, position, hire_date, staff_status
            FROM staff_member
            WHERE staff_status = 'Active'
            ORDER BY position, member_name";

    return $pdo->query($sql)->fetchAll();
}

function registerStaff(array $data): array
{
    // Check permission: only manager can add staff
    if (!requireManager()) {
        return ['success' => false, 'message' => 'غير مصرح: فقط المدراء يمكنهم إضافة موظفين'];
    }

    if (!validateUserAge($data['date_of_birth'])) {
        return ['success' => false, 'message' => 'Minimum age requirement is 15 years'];
    }

    $age = date_diff(date_create($data['date_of_birth']), date_create('today'))->y;

    $pdo = getDBConnection();
    $sql = "INSERT INTO staff_member (member_name, email, phone_num, position, 
            hire_date, salary, username, password, age, staff_status) 
            VALUES (:member_name, :email, :phone_num, :position, 
            :hire_date, :salary, :username, :password, :age, 'Active')";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':member_name' => $data['staff_member_name'],
            ':email' => $data['email'],
            ':phone_num' => $data['phone_number'] ?? null,
            ':position' => $data['member_position'],
            ':hire_date' => $data['hire_date'],
            ':salary' => $data['salary'] ?? 0,
            ':username' => $data['username'],
            ':password' => hashPassword($data['password']),
            ':age' => $age
        ]);

        return ['success' => true, 'message' => 'Staff member added successfully', 'staff_id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        $msg = $e->getCode() == 23000 ? 'Username or email already exists' : $e->getMessage();
        return ['success' => false, 'message' => 'Registration failed: ' . $msg];
    }
}

function deleteUser(int $userId): array
{
    if ($userId <= 0) {
        return ['success' => false, 'message' => 'رقم المستخدم غير صحيح'];
    }

    $pdo = getDBConnection();
    try {
        $pdo->beginTransaction();

        // Check if user exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'المستخدم غير موجود'];
        }

        // Delete user (CASCADE will handle related records)
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);

        $pdo->commit();
        return ['success' => true, 'message' => 'تم حذف المستخدم بنجاح'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()];
    }
}

function deleteStaff(int $staffId): array
{
    if ($staffId <= 0) {
        return ['success' => false, 'message' => 'رقم الموظف غير صحيح'];
    }

    // Check permission: only manager can delete staff
    if (!requireManager()) {
        return ['success' => false, 'message' => 'غير مصرح: فقط المدراء يمكنهم حذف الموظفين'];
    }

    $pdo = getDBConnection();
    try {
        $pdo->beginTransaction();

        // Check if staff exists
        $stmt = $pdo->prepare("SELECT staff_id FROM staff_member WHERE staff_id = :staff_id");
        $stmt->execute([':staff_id' => $staffId]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'الموظف غير موجود'];
        }

        // Delete staff (CASCADE will handle related records)
        $stmt = $pdo->prepare("DELETE FROM staff_member WHERE staff_id = :staff_id");
        $stmt->execute([':staff_id' => $staffId]);

        $pdo->commit();
        return ['success' => true, 'message' => 'تم حذف الموظف بنجاح'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()];
    }
}

// ========================
// نقطة الدخول Entry Point
// ========================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])
) {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Unknown action'];

    switch ($action) {
        // ---- Users ----
        case 'register_user':
            $response = registerUser($_POST);
            break;
        case 'user_login':
        case 'login_user':
            $response = userLogin($_POST['username'] ?? '', $_POST['password'] ?? '');
            break;
        case 'user_can_borrow':
            $response = canUserBorrow((int)($_POST['user_id'] ?? 0));
            break;
        case 'user_info':
            $info = getUserInfo((int)($_POST['user_id'] ?? 0));
            $response = $info ? ['success' => true, 'data' => $info] : ['success' => false, 'message' => 'User not found'];
            break;

        // ---- Staff ----
        case 'register_staff':
            $response = registerStaff($_POST);
            break;
        case 'staff_login':
        case 'login_staff':
            $response = staffLogin($_POST['username'] ?? '', $_POST['password'] ?? '');
            break;
        case 'logout':
            session_destroy();
            $response = ['success' => true, 'message' => 'تم تسجيل الخروج بنجاح'];
            break;
        case 'session_check':
            $role = getSessionRole();
            if ($role) {
                $response = ['success' => true, 'role' => $role];
                if ($role === 'user') {
                    $response['user_id'] = $_SESSION['user_id'] ?? null;
                    $response['username'] = $_SESSION['username'] ?? null;
                } else {
                    $response['staff_id'] = $_SESSION['staff_id'] ?? null;
                    $response['username'] = $_SESSION['username'] ?? null;
                    $response['is_manager'] = $_SESSION['is_manager'] ?? false;
                }
            } else {
                $response = ['success' => false, 'message' => 'Not logged in', 'role' => null];
            }
            break;
            $info = getStaffInfo((int)($_POST['staff_id'] ?? 0));
            $response = $info ? ['success' => true, 'data' => $info] : ['success' => false, 'message' => 'Staff not found'];
            break;
        case 'staff_list':
            $response = ['success' => true, 'data' => getActiveStaff()];
            break;
        
        case 'get_all_users':
            $pdo = getDBConnection();
            $stmt = $pdo->query("SELECT user_id, fullname, username, email, phone_number, user_type, max_borrow_limit, user_status, age FROM users ORDER BY user_id DESC");
            $users = $stmt->fetchAll();
            $response = ['success' => true, 'data' => $users];
            break;

        case 'delete_user':
            $response = deleteUser((int)($_POST['user_id'] ?? 0));
            break;

        case 'delete_staff':
            $response = deleteStaff((int)($_POST['staff_id'] ?? 0));
            break;
    }

    respond($response);
}


