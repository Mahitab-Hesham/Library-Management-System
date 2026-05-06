<?php
/**
 * Library Operations (Books + Borrowing)
 * ----------------------------------------------
 * Unified file with corrected table names matching lms_schema.sql
 */

require_once 'config.php';
require_once 'user_staff.php';

if (!function_exists('respond')) {
    function respond($payload)
    {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ========================
// Books Block
// ========================
function getAvailableBooks(): array
{
    $pdo = getDBConnection();
    $sql = "SELECT book_id, book_name, author_name, release_year,
            available_copies, book_description, book_language, total_pages
            FROM book WHERE available_copies > 0 ORDER BY book_name";
    return $pdo->query($sql)->fetchAll();
}

function getAllBooks(): array
{
    $pdo = getDBConnection();
    $sql = "SELECT book_id, book_name, author_name, release_year,
            available_copies, book_description, book_language, total_pages
            FROM book ORDER BY book_name";
    return $pdo->query($sql)->fetchAll();
}

function getBookById(int $bookId): array|false
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM book WHERE book_id = :book_id");
    $stmt->execute([':book_id' => $bookId]);
    return $stmt->fetch();
}

function getBookCover(int $bookId): ?string
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT book_cover FROM book WHERE book_id = :book_id");
    $stmt->execute([':book_id' => $bookId]);
    $book = $stmt->fetch();
    return $book && $book['book_cover'] ? $book['book_cover'] : null;
}

function addBook(array $data): array
{
    // Check permission: only staff can add books
    if (!requireStaff()) {
        return ['success' => false, 'message' => 'غير مصرح: فقط الموظفون يمكنهم إضافة كتب'];
    }

    $pdo = getDBConnection();
    $sql = "INSERT INTO book (book_name, author_name, release_year, available_copies, 
            book_description, book_language, total_pages, book_cover) 
            VALUES (:book_name, :author_name, :release_year, :available_copies, 
            :book_description, :book_language, :total_pages, :book_cover)";

    try {
        $stmt = $pdo->prepare($sql);
        
        // Check if author exists, if not add them
        if (!empty($data['author_name'])) {
            $checkAuthor = $pdo->prepare("SELECT author_name FROM author WHERE author_name = :author_name");
            $checkAuthor->execute([':author_name' => $data['author_name']]);
            if (!$checkAuthor->fetch()) {
                $addAuthor = $pdo->prepare("INSERT INTO author (author_name) VALUES (:author_name)");
                $addAuthor->execute([':author_name' => $data['author_name']]);
            }
        }

        $stmt->execute([
            ':book_name' => $data['book_name'],
            ':author_name' => $data['author_name'] ?? null,
            ':release_year' => $data['publishing_year'] ?? null,
            ':available_copies' => $data['available_copies'] ?? 1,
            ':book_description' => $data['book_description'] ?? null,
            ':book_language' => $data['book_language'] ?? 'English',
            ':total_pages' => $data['total_pages'] ?? null,
            ':book_cover' => $data['book_cover'] ?? null
        ]);

        return ['success' => true, 'message' => 'Book added successfully', 'book_id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to add book: ' . $e->getMessage()];
    }
}


function deleteBook(int $bookId): array
{
    // Check permission: only staff can delete books
    if (!requireStaff()) {
        return ['success' => false, 'message' => 'غير مصرح: فقط الموظفون يمكنهم حذف كتب'];
    }

    $pdo = getDBConnection();
    try {
        // Check if book has active borrowings
        $checkSql = "SELECT COUNT(*) FROM borrowing_process WHERE book_id = :book_id AND status = 'Borrowed'";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':book_id' => $bookId]);
        if ($checkStmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Cannot delete book with active borrowings'];
        }

        $sql = "DELETE FROM book WHERE book_id = :book_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':book_id' => $bookId]);

        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Book deleted successfully'];
        }
        return ['success' => false, 'message' => 'Book not found'];
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            return ['success' => false, 'message' => 'Cannot delete book because it has borrowing history.'];
        }
        return ['success' => false, 'message' => 'Failed to delete book: ' . $e->getMessage()];
    }
}

// ========================
// Borrowing Block
// ========================
define('LOAN_PERIOD_DAYS', 14);

function isBookAvailable(int $bookId): array
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT available_copies FROM book WHERE book_id = :book_id");
    $stmt->execute([':book_id' => $bookId]);
    $book = $stmt->fetch();

    if (!$book) {
        return ['success' => false, 'message' => 'Book not found'];
    }

    if ((int)$book['available_copies'] <= 0) {
        return ['success' => false, 'message' => 'Book is not available (no copies left)'];
    }

    return ['success' => true, 'copies' => (int)$book['available_copies']];
}

function borrowBook(int $userId, int $bookId, int $staffId): array
{
    $userCheck = canUserBorrow($userId);
    if (!$userCheck['success']) {
        return $userCheck;
    }

    $bookCheck = isBookAvailable($bookId);
    if (!$bookCheck['success']) {
        return $bookCheck;
    }

    $pdo = getDBConnection();
    try {
        $pdo->beginTransaction();

        // Generate numeric process code (timestamp + random) to fit INT(11)
        $processCode = time() + rand(100, 999); 
        $processDate = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime('+' . LOAN_PERIOD_DAYS . ' days'));

        $sql = "INSERT INTO borrowing_process (process_code, user_id, book_id, staff_id, process_date, due_date, status)
                VALUES (:process_code, :user_id, :book_id, :staff_id, :process_date, :due_date, 'Borrowed')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':process_code' => $processCode,
            ':user_id' => $userId,
            ':book_id' => $bookId,
            ':staff_id' => $staffId,
            ':process_date' => $processDate,
            ':due_date' => $dueDate
        ]);

        // Decrement copies
        $updateBook = "UPDATE book SET available_copies = available_copies - 1 WHERE book_id = :book_id";
        $stmtBook = $pdo->prepare($updateBook);
        $stmtBook->execute([':book_id' => $bookId]);

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Book borrowed successfully',
            'process_code' => $processCode,
            'due_date' => $dueDate
        ];
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Borrowing failed: ' . $e->getMessage()];
    }
}

    function returnBook(int $processCode): array
    {
        $pdo = getDBConnection();
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM borrowing_process WHERE process_code = :process_code");
            $stmt->execute([':process_code' => $processCode]);
        $borrowing = $stmt->fetch();

        if (!$borrowing) {
            throw new Exception('Borrowing record not found');
        }
        if ($borrowing['status'] === 'Returned') {
            throw new Exception('Book already returned');
        }

        $returnDate = date('Y-m-d');
        $updateSql = "UPDATE borrowing_process
                      SET return_date = :return_date, status = 'Returned'
                      WHERE process_code = :process_code";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':return_date' => $returnDate,
            ':process_code' => $processCode
        ]);

        // Increment copies
        $updateBook = "UPDATE book SET available_copies = available_copies + 1 WHERE book_id = :book_id";
        $stmtBook = $pdo->prepare($updateBook);
        $stmtBook->execute([':book_id' => $borrowing['book_id']]);

        $pdo->commit();
        return [
            'success' => true,
            'message' => 'Book returned successfully'
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Return failed: ' . $e->getMessage()];
    }
}

function getUserBorrowings(int $userId): array
{
    $pdo = getDBConnection();
    $sql = "SELECT bp.process_code, b.book_name, b.author_name,
            bp.process_date, bp.due_date, bp.return_date, bp.status,
            DATEDIFF(CURDATE(), bp.due_date) AS days_overdue
            FROM borrowing_process bp
            INNER JOIN book b ON bp.book_id = b.book_id
            WHERE bp.user_id = :user_id
            ORDER BY bp.process_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}


// ========================
// Entry Point
// ========================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])
) {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Unknown action'];

    switch ($action) {
        // --- Books ---
        case 'books_get_available':
            $response = ['success' => true, 'data' => getAvailableBooks()];
            break;
        case 'books_get_all':
            $response = ['success' => true, 'data' => getAllBooks()];
            break;
        case 'books_get_one':
            $data = getBookById((int)($_POST['book_id'] ?? 0));
            $response = $data ? ['success' => true, 'data' => $data] : ['success' => false, 'message' => 'Book not found'];
            break;
        case 'books_add':
            $response = addBook($_POST);
            break;
        case 'books_delete':
            $response = deleteBook((int)($_POST['book_id'] ?? 0));
            break;

        // --- Borrowing ---
        case 'borrow_create':
            $response = borrowBook(
                (int)($_POST['user_id'] ?? 0),
                (int)($_POST['book_id'] ?? 0),
                (int)($_POST['staff_id'] ?? 0)
            );
            break;
        case 'borrow_return':
            $response = returnBook((int)($_POST['process_code'] ?? 0));
            break;
        case 'borrow_user_list':
            $response = ['success' => true, 'data' => getUserBorrowings((int)($_POST['user_id'] ?? 0))];
            break;


    }

    respond($response);
}

// Allow getting book cover via GET
if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME']) &&
    isset($_GET['cover'])
) {
    $cover = getBookCover((int)$_GET['cover']);
    if ($cover) {
        header('Content-Type: image/webp');
        echo $cover;
    } else {
        header('Content-Type: image/svg+xml');
        echo '<svg width="200" height="300" xmlns="http://www.w3.org/2000/svg"><rect width="200" height="300" fill="#ddd"/><text x="50%" y="50%" text-anchor="middle" fill="#999">No Cover</text></svg>';
    }
    exit;
}
