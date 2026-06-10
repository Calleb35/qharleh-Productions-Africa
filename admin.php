<?php
/**
 * Administrator Dashboard
 * Qharleh Productions Africa
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Auth Check - Redirect to login if not logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

if (!$conn) {
    header('Location: setup.php');
    exit;
}

$message = '';
$message_type = 'success';

// Ensure upload directory exists and is writeable
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// 1. HANDLE CATEGORY CREATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_category'])) {
    $cat_name = trim($_POST['category_name'] ?? '');
    if (empty($cat_name)) {
        $message = "Category name cannot be empty.";
        $message_type = "danger";
    } else {
        // Create a URL-safe slug from name
        $slug = strtolower($cat_name);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', ' ', $slug);
        $slug = preg_replace('/[\s]/', '-', $slug);
        $slug = trim($slug, '-');

        try {
            $stmt = $conn->prepare("INSERT INTO categories (name, slug) VALUES (:name, :slug)");
            $stmt->execute([':name' => $cat_name, ':slug' => $slug]);
            $message = "Category '$cat_name' created successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Failed to create category (it may already exist): " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// 2. HANDLE CATEGORY DELETION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $cat_id = intval($_POST['category_id'] ?? 0);
    if ($cat_id > 0) {
        try {
            // First select all images in this category and delete their physical files
            $stmt = $conn->prepare("SELECT image_path FROM images WHERE category_id = :cat_id");
            $stmt->execute([':cat_id' => $cat_id]);
            $cat_images = $stmt->fetchAll();
            foreach ($cat_images as $img) {
                $file_path = __DIR__ . '/' . $img['image_path'];
                if (file_exists($file_path) && !str_contains($img['image_path'], 'assets/images/')) {
                    // Do not delete template assets if they are referenced
                    unlink($file_path);
                }
            }

            // Delete category (cascades to images in DB)
            $del_stmt = $conn->prepare("DELETE FROM categories WHERE id = :id");
            $del_stmt->execute([':id' => $cat_id]);
            $message = "Category and all its associated images deleted successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Failed to delete category: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// 3. HANDLE IMAGE UPLOAD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image'])) {
    $category_id = intval($_POST['category_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if ($category_id <= 0) {
        $message = "Please select a valid category.";
        $message_type = "danger";
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $message = "Error uploading file. Error Code: " . ($_FILES['image']['error'] ?? 'No File');
        $message_type = "danger";
    } else {
        $file = $_FILES['image'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        
        // Allowed extension / mime check
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $file_type = null;
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $file_type = $finfo->file($file_tmp);
        } elseif (function_exists('mime_content_type')) {
            $file_type = mime_content_type($file_tmp);
        } else {
            // Fallback to extension check if system functions are disabled
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $mimes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp'
            ];
            $file_type = $mimes[$ext] ?? null;
        }
        
        if (!$file_type || !in_array($file_type, $allowed_types)) {
            $message = "Invalid file type. Only JPG, PNG, and WEBP images are allowed.";
            $message_type = "danger";
        } elseif ($file_size > 5 * 1024 * 1024) { // 5MB Limit
            $message = "File is too large. Maximum size is 5MB.";
            $message_type = "danger";
        } else {
            // Generate unique filename to avoid overwrites
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            if (empty($ext)) {
                $ext = ($file_type === 'image/png') ? 'png' : (($file_type === 'image/webp') ? 'webp' : 'jpg');
            }
            $safe_name = uniqid('img_', true) . '.' . $ext;
            $destination_path = UPLOAD_DIR . $safe_name;
            $db_path = UPLOAD_URL . $safe_name;
            
            if (move_uploaded_file($file_tmp, $destination_path)) {
                try {
                    $stmt = $conn->prepare("INSERT INTO images (category_id, image_path, title, description) VALUES (:category_id, :image_path, :title, :description)");
                    $stmt->execute([
                        ':category_id' => $category_id,
                        ':image_path' => $db_path,
                        ':title' => $title,
                        ':description' => $description
                    ]);
                    $message = "Image '$title' uploaded and added to portfolio successfully!";
                    $message_type = "success";
                } catch (PDOException $e) {
                    // Cleanup file if DB insert fails
                    if (file_exists($destination_path)) {
                        unlink($destination_path);
                    }
                    $message = "Database save failed: " . $e->getMessage();
                    $message_type = "danger";
                }
            } else {
                $message = "Failed to save file to uploads folder. Check folder permissions.";
                $message_type = "danger";
            }
        }
    }
}

// 4. HANDLE IMAGE DELETION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image'])) {
    $image_id = intval($_POST['image_id'] ?? 0);
    if ($image_id > 0) {
        try {
            // Fetch file path first to delete it
            $stmt = $conn->prepare("SELECT image_path FROM images WHERE id = :id");
            $stmt->execute([':id' => $image_id]);
            $img = $stmt->fetch();
            
            if ($img) {
                // Delete physical file
                $file_path = __DIR__ . '/' . $img['image_path'];
                if (file_exists($file_path) && !str_contains($img['image_path'], 'assets/images/')) {
                    unlink($file_path);
                }
                
                // Delete DB record
                $del_stmt = $conn->prepare("DELETE FROM images WHERE id = :id");
                $del_stmt->execute([':id' => $image_id]);
                $message = "Image deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Image not found.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            $message = "Failed to delete image: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// 5. HANDLE MESSAGE DELETION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $message_id = intval($_POST['message_id'] ?? 0);
    if ($message_id > 0) {
        try {
            $del_stmt = $conn->prepare("DELETE FROM messages WHERE id = :id");
            $del_stmt->execute([':id' => $message_id]);
            $message = "Inquiry message deleted successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Failed to delete message: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// FETCH CATEGORIES, IMAGES AND MESSAGES FOR RENDERING
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$images = $conn->query("
    SELECT i.*, c.name as category_name 
    FROM images i 
    JOIN categories c ON i.category_id = c.id 
    ORDER BY i.created_at DESC
")->fetchAll();
$client_messages = $conn->query("SELECT * FROM messages ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Qharleh Productions Africa</title>
    <!-- Google Fonts: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-dark: #09090b;
            --bg-card: #121216;
            --primary-gold: #C5A880;
            --gold-bright: #D4AF37;
            --text-light: #F4F4F5;
            --text-muted: #A1A1AA;
            --border-color: rgba(255, 255, 255, 0.05);
            --border-gold: rgba(197, 168, 128, 0.15);
        }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-light);
            min-height: 100vh;
        }
        .admin-nav {
            background-color: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
        }
        .dashboard-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        .dashboard-card-title {
            font-size: 1.25rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary-gold);
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .form-control, .form-select {
            background-color: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            color: var(--text-light);
            border-radius: 6px;
            padding: 0.65rem 0.85rem;
        }
        .form-control:focus, .form-select:focus {
            background-color: rgba(255, 255, 255, 0.05);
            border-color: var(--primary-gold);
            color: var(--text-light);
            box-shadow: none;
        }
        .btn-gold {
            background-color: var(--primary-gold);
            color: #000;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-gold:hover {
            background-color: var(--gold-bright);
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }
        .btn-outline-danger {
            border-color: rgba(220, 53, 69, 0.2);
            background-color: rgba(220, 53, 69, 0.02);
        }
        .btn-outline-danger:hover {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .table {
            color: var(--text-light);
            vertical-align: middle;
        }
        .table th {
            color: var(--primary-gold);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
            border-bottom-color: var(--border-color);
        }
        .table td {
            border-bottom-color: rgba(255,255,255,0.02);
        }
        .thumbnail-img {
            width: 70px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }
        .badge-category {
            background-color: rgba(197, 168, 128, 0.1);
            color: var(--primary-gold);
            border: 1px solid var(--border-gold);
            padding: 4px 8px;
            font-size: 0.75rem;
            border-radius: 4px;
        }
    </style>
</head>
<body>

    <!-- Admin Navigation Bar -->
    <nav class="navbar navbar-expand navbar-dark admin-nav py-3 mb-5">
        <div class="container">
            <a class="navbar-brand text-uppercase" href="index.php">
                Qharleh<span class="text-warning">Dashboard</span>
            </a>
            <div class="ms-auto d-flex align-items-center">
                <span class="text-muted me-3 d-none d-sm-inline">
                    Logged in as: <strong class="text-light"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm text-uppercase fw-bold px-3">
                    <i class="fa-solid fa-right-from-bracket me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Message Notification Alert -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show mb-4 border-0" role="alert">
                <i class="fa-solid <?php echo ($message_type === 'success') ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close <?php echo ($message_type === 'success') ? '' : 'btn-close-white'; ?>" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Writeable Directory warning -->
        <?php if (!is_writable(UPLOAD_DIR)): ?>
            <div class="alert alert-danger border-0 mb-4" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <strong>Security Alert:</strong> The <code class="text-white">uploads/</code> directory is not writeable. Uploads will fail. Please set folder permissions (CHMOD) of the uploads folder to <code>755</code> or <code>777</code> on your host.
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Left Side: Manage Categories & Upload Image Form -->
            <div class="col-lg-5">
                <!-- Add Category Card -->
                <div class="dashboard-card">
                    <h2 class="dashboard-card-title">
                        <span><i class="fa-solid fa-folder-plus me-2"></i>New Category</span>
                    </h2>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="category_name" class="form-label text-muted small">Category Name</label>
                            <input type="text" class="form-control" id="category_name" name="category_name" placeholder="e.g. Wedding, Street" required>
                        </div>
                        <button type="submit" name="create_category" class="btn btn-gold w-100">
                            <i class="fa-solid fa-plus me-2"></i>Add Category
                        </button>
                    </form>
                </div>

                <!-- Upload Image Card -->
                <div class="dashboard-card">
                    <h2 class="dashboard-card-title">
                        <span><i class="fa-solid fa-cloud-arrow-up me-2"></i>Upload Photo</span>
                    </h2>
                    <?php if (empty($categories)): ?>
                        <div class="alert alert-info py-3 text-center border-0 mb-0">
                            <i class="fa-solid fa-circle-info me-2"></i>
                            Please create a category first before uploading images.
                        </div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="category_id" class="form-label text-muted small">Portfolio Category</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="" disabled selected>Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="title" class="form-label text-muted small">Image Title</label>
                                <input type="text" class="form-control" id="title" name="title" placeholder="Give the photo a name..." required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label text-muted small">Image Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Context or story behind the photo..."></textarea>
                            </div>
                            <div class="mb-4">
                                <label for="image" class="form-label text-muted small">Select Photo File</label>
                                <input class="form-control" type="file" id="image" name="image" accept="image/*" required>
                                <div class="form-text text-muted small mt-1">Allowed formats: JPG, PNG, WEBP. Max size: 5MB.</div>
                            </div>
                            <button type="submit" name="upload_image" class="btn btn-gold w-100">
                                <i class="fa-solid fa-upload me-2"></i>Upload and Add
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side: Category list & Image list -->
            <div class="col-lg-7">
                <!-- Categories Table Card -->
                <div class="dashboard-card">
                    <h2 class="dashboard-card-title">
                        <span><i class="fa-solid fa-folder-open me-2"></i>Active Categories</span>
                    </h2>
                    <?php if (empty($categories)): ?>
                        <p class="text-muted mb-0">No categories created yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Slug</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($cat['name']); ?></td>
                                            <td class="text-muted"><code>/<?php echo htmlspecialchars($cat['slug']); ?></code></td>
                                            <td class="text-end">
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this category? All images in this category will be deleted permanently from both the database and folders.');" style="display:inline;">
                                                    <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                                    <button type="submit" name="delete_category" class="btn btn-sm btn-outline-danger">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Photos list table Card -->
                <div class="dashboard-card">
                    <h2 class="dashboard-card-title">
                        <span><i class="fa-solid fa-images me-2"></i>Uploaded Portfolio Photos</span>
                    </h2>
                    <?php if (empty($images)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fa-regular fa-image fa-2x mb-2"></i>
                            <p class="mb-0">No photos uploaded yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Preview</th>
                                        <th>Details</th>
                                        <th>Category</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($images as $img): ?>
                                        <tr>
                                            <td>
                                                <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="Thumbnail" class="thumbnail-img">
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($img['title']); ?></div>
                                                <small class="text-muted text-truncate d-block" style="max-width: 150px;"><?php echo htmlspecialchars($img['description']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge-category"><?php echo htmlspecialchars($img['category_name']); ?></span>
                                            </td>
                                            <td class="text-end">
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this photo from the portfolio?');" style="display:inline;">
                                                    <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                                                    <button type="submit" name="delete_image" class="btn btn-sm btn-outline-danger">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Client Booking Requests/Inquiries Panel -->
                <div class="dashboard-card">
                    <h2 class="dashboard-card-title">
                        <span><i class="fa-solid fa-envelope-open-text me-2"></i>Client Booking Inquiries</span>
                    </h2>
                    <?php if (empty($client_messages)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fa-regular fa-envelope fa-2x mb-2"></i>
                            <p class="mb-0">No customer messages received yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Sender</th>
                                        <th>Subject</th>
                                        <th>Message</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($client_messages as $msg): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($msg['name']); ?></div>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($msg['email']); ?></small>
                                                <small class="text-warning" style="font-size: 0.7rem;"><?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary text-light"><?php echo htmlspecialchars($msg['subject']); ?></span>
                                            </td>
                                            <td>
                                                <p class="mb-0 small text-light" style="max-width: 250px; white-space: normal; word-wrap: break-word; line-height: 1.4;">
                                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                                </p>
                                            </td>
                                            <td class="text-end">
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this customer inquiry?');" style="display:inline;">
                                                    <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                    <button type="submit" name="delete_message" class="btn btn-sm btn-outline-danger">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
