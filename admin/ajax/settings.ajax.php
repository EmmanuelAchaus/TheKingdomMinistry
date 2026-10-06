<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db.php';

// Auth check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'save_highlighted_scripture') {
        $verse = $_POST['highlighted_verse'] ?? '';
        $details = $_POST['highlighted_details'] ?? '';
        
        if (empty($verse) || empty($details)) {
            echo json_encode(['success' => false, 'message' => 'Verse and details cannot be empty.']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'highlighted_verse'");
            $stmt->execute([$verse]);
            
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'highlighted_details'");
            $stmt->execute([$details]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Highlighted scripture updated successfully.']);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'change_password') {
        $admin_id = $_SESSION['admin_id'];
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (empty($current) || empty($new) || empty($confirm)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }
        
        if ($new !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
            exit;
        }
        
        if (strlen($new) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
            exit;
        }
        
        try {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = :id");
            $stmt->execute(['id' => $admin_id]);
            $admin = $stmt->fetch();
            
            if (!$admin || !password_verify($current, $admin['password'])) {
                echo json_encode(['success' => false, 'message' => 'Incorrect current password.']);
                exit;
            }
            
            // Hash and update new password
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE admins SET password = :password WHERE id = :id");
            $update->execute(['password' => $hashed, 'id' => $admin_id]);
            
            echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
