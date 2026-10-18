<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/includes/app.php';

// Auth check
$portalAdmin = current_user();
if (!$portalAdmin || $portalAdmin['role'] !== 'admin' || $portalAdmin['account_status'] !== 'active') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
try { verify_csrf(); } catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Create Category
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Name cannot be empty']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (:name)");
            $stmt->execute(['name' => $name]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'name' => $name]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to create category']);
        }
        exit;
    }
    
    // Update Category
    if ($action === 'update') {
        $id = $_POST['id'] ?? 0;
        $name = trim($_POST['name'] ?? '');
        
        if (empty($id) || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE categories SET name = :name WHERE id = :id");
            $stmt->execute(['name' => $name, 'id' => $id]);
            echo json_encode(['success' => true, 'name' => $name]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to update category']);
        }
        exit;
    }
    
    // Delete Category
    if ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute(['id' => $id]);
            echo json_encode(['success' => true, 'message' => 'Category removed']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to delete category (Might be in use by an article)']);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
