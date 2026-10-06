<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db.php';

// Auth check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Slug generator helper
function createSlug($str, $pdo, $id = 0) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $str)));
    if (empty($slug)) $slug = 'article';
    
    // Check for uniqueness
    $original_slug = $slug;
    $count = 1;
    while(true) {
        $stmt = $pdo->prepare("SELECT id FROM articles WHERE slug = :slug AND id != :id");
        $stmt->execute(['slug' => $slug, 'id' => $id]);
        if(!$stmt->fetch()) {
            break;
        }
        $slug = $original_slug . '-' . $count;
        $count++;
    }
    return $slug;
}

// Image upload helper
function handleImageUpload($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    // Validate type
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and WEBP are allowed.');
    }
    
    // Validate size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File size exceeds 5MB limit.');
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_') . '.' . $ext;
    
    // root dir logic
    $upload_dir = dirname(dirname(__DIR__)) . '/uploads/articles/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $target_file = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return 'uploads/articles/' . $filename;
    }
    
    throw new Exception('Failed to move uploaded file.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
        $status = $_POST['status'] ?? 'Draft';
        $excerpt = trim($_POST['excerpt'] ?? '');
        $slug = createSlug($title, $pdo);
        
        if (empty($title) || empty($body)) {
            echo json_encode(['success' => false, 'message' => 'Title and Body are required.']);
            exit;
        }
        
        try {
            $image_path = null;
            if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
                $image_path = handleImageUpload($_FILES['image']);
            }
            
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            
            if ($is_featured) {
                // Unset all other featured articles
                $pdo->query("UPDATE articles SET is_featured = 0");
            }
            
            $stmt = $pdo->prepare("INSERT INTO articles (title, slug, image, body, excerpt, category_id, status, is_featured) VALUES (:title, :slug, :image, :body, :excerpt, :category_id, :status, :is_featured)");
            $stmt->execute([
                'title' => $title,
                'slug' => $slug,
                'image' => $image_path,
                'body' => $body,
                'excerpt' => $excerpt,
                'category_id' => $category_id,
                'status' => $status,
                'is_featured' => $is_featured
            ]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'update') {
        $id = $_POST['id'] ?? 0;
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
        $status = $_POST['status'] ?? 'Draft';
        $excerpt = trim($_POST['excerpt'] ?? '');
        $existing_image = $_POST['existing_image'] ?? null;
        
        if (empty($id) || empty($title) || empty($body)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
            exit;
        }
        
        $slug = createSlug($title, $pdo, $id);
        
        try {
            $image_path = $existing_image;
            if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
                // Remove old image if needed (optional)
                if($existing_image && file_exists(dirname(dirname(__DIR__)) . '/' . $existing_image)) {
                    @unlink(dirname(dirname(__DIR__)) . '/' . $existing_image);
                }
                $image_path = handleImageUpload($_FILES['image']);
            }
            
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            
            if ($is_featured) {
                // Unset all other featured articles
                $pdo->query("UPDATE articles SET is_featured = 0");
            }
            
            $stmt = $pdo->prepare("UPDATE articles SET title=:title, slug=:slug, image=:image, body=:body, excerpt=:excerpt, category_id=:category_id, status=:status, is_featured=:is_featured WHERE id=:id");
            $stmt->execute([
                'title' => $title,
                'slug' => $slug,
                'image' => $image_path,
                'body' => $body,
                'excerpt' => $excerpt,
                'category_id' => $category_id,
                'status' => $status,
                'is_featured' => $is_featured,
                'id' => $id
            ]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        
        try {
            // Check for image
            $stmt = $pdo->prepare("SELECT image FROM articles WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $art = $stmt->fetch();
            
            if ($art && $art['image'] && file_exists(dirname(dirname(__DIR__)) . '/' . $art['image'])) {
                @unlink(dirname(dirname(__DIR__)) . '/' . $art['image']);
            }
            
            $stmt = $pdo->prepare("DELETE FROM articles WHERE id = :id");
            $stmt->execute(['id' => $id]);
            echo json_encode(['success' => true, 'message' => 'Article removed']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to delete article']);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
