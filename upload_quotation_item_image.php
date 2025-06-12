<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$quotation_item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);

if (!$quotation_item_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID.']);
    exit;
}

if (!isset($_FILES['item_image']) || $_FILES['item_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    exit;
}

$file = $_FILES['item_image'];
$upload_dir = 'uploads/quotation_item_images/'; // Make sure this directory exists and is writable
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_size = 5 * 1024 * 1024; // 5 MB

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF allowed.']);
    exit;
}

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit.']);
    exit;
}

// Generate unique filename
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$new_filename = uniqid('quotation_item_') . '.' . $file_extension;
$destination_path = $upload_dir . $new_filename;

if (move_uploaded_file($file['tmp_name'], $destination_path)) {
    try {
        $conn = new PDO("mysql:host=localhost;dbname=supplies", "root", "");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql_update = "UPDATE quotation_items SET image_path_override = :image_path WHERE id = :item_id";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bindParam(':image_path', $destination_path);
        $stmt_update->bindParam(':item_id', $quotation_item_id, PDO::PARAM_INT);
        $stmt_update->execute();

        echo json_encode(['success' => true, 'message' => 'Image uploaded and path saved.', 'image_path' => $destination_path]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } finally {
        $conn = null;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
}
?>