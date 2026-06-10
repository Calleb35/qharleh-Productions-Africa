<?php
/**
 * Setup Script
 * Qharleh Productions Africa
 * 
 * This script initializes the database tables and creates the first admin account.
 * For security reasons, delete this file after completing the setup.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

$db = Database::getInstance();
$error = $db->getError();

$message = '';
$message_type = 'danger';
$setup_stage = 'connect'; // 'connect', 'create_database', 'create_tables', 'create_admin', 'completed'

// Handle Form Submissions BEFORE determining stage, so changes apply immediately
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_database'])) {
        if ($db->attemptCreateDatabase()) {
            $message = "Database '" . DB_NAME . "' created successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to create database automatically: " . htmlspecialchars($db->getError());
            $message_type = "danger";
        }
    } elseif (isset($_POST['run_migrations'])) {
        $conn = $db->getConnection();
        if ($conn) {
            $schema_file = __DIR__ . '/schema.sql';
            if (file_exists($schema_file)) {
                $schema_sql = file_get_contents($schema_file);
                try {
                    $conn->exec($schema_sql);
                    $message = "Database tables initialized successfully!";
                    $message_type = "success";
                } catch (PDOException $e) {
                    $message = "Failed to run SQL migrations: " . $e->getMessage();
                    $message_type = "danger";
                }
            } else {
                $message = "Migration error: schema.sql file not found!";
                $message_type = "danger";
            }
        } else {
            $message = "Database connection failed. Cannot run migrations.";
            $message_type = "danger";
        }
    } elseif (isset($_POST['create_admin_account'])) {
        $conn = $db->getConnection();
        if ($conn) {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($username) || empty($password)) {
                $message = "All fields are required.";
                $message_type = "danger";
            } elseif ($password !== $confirm_password) {
                $message = "Passwords do not match.";
                $message_type = "danger";
            } elseif (strlen($password) < 6) {
                $message = "Password must be at least 6 characters long.";
                $message_type = "danger";
            } else {
                try {
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $conn->prepare("INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)");
                    $stmt->execute([
                        ':username' => $username,
                        ':password_hash' => $password_hash
                    ]);
                    
                    // Automatically create standard categories to pre-populate the portfolio
                    $conn->exec("INSERT IGNORE INTO categories (name, slug) VALUES 
                        ('Street Photography', 'street-photography'),
                        ('Studio Portraits', 'studio-portraits'),
                        ('Cinematic Video', 'cinematic-video')
                    ");

                    // Get categories to link generated images
                    $cat_stmt = $conn->query("SELECT id, slug FROM categories");
                    $cats = $cat_stmt->fetchAll();
                    
                    foreach ($cats as $cat) {
                        if ($cat['slug'] === 'street-photography') {
                            $conn->exec("INSERT IGNORE INTO images (category_id, image_path, title, description) VALUES 
                                ({$cat['id']}, 'assets/images/street_photography.png', 'Haile Selassie Street Hustle', 'Daily street photography shot capturing the vibrant atmosphere opposite Easy Coach.')
                            ");
                        } elseif ($cat['slug'] === 'studio-portraits') {
                            $conn->exec("INSERT IGNORE INTO images (category_id, image_path, title, description) VALUES 
                                ({$cat['id']}, 'assets/images/portrait_photography.png', 'Golden Mood Portrait', 'High-end studio portrait showcasing professional off-camera flash lighting and styling.')
                            ");
                        } elseif ($cat['slug'] === 'cinematic-video') {
                            $conn->exec("INSERT IGNORE INTO images (category_id, image_path, title, description) VALUES 
                                ({$cat['id']}, 'assets/images/videography_action.png', 'Behind the Lens in Nairobi', 'Documentary cinematography session capturing local stories on a handheld cinema rig.')
                            ");
                        }
                    }

                    $message = "Administrator created successfully! Default showcase categories and images added.";
                    $message_type = "success";
                    
                    // Log the user in
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $username;
                } catch (PDOException $e) {
                    $message = "Failed to create administrator account: " . $e->getMessage();
                    $message_type = "danger";
                }
            }
        } else {
            $message = "Database connection failed. Cannot create admin account.";
            $message_type = "danger";
        }
    }
}

// Determine current setup stage dynamically
if ($db->getHostConnection()) {
    if (!$db->doesDbExist()) {
        $setup_stage = 'create_database';
    } else {
        $conn = $db->getConnection();
        if ($conn) {
            $tables_exist = false;
            try {
                $check_tables = $conn->query("SHOW TABLES LIKE 'users'");
                if ($check_tables && $check_tables->rowCount() > 0) {
                    $tables_exist = true;
                }
            } catch (PDOException $e) {
                // Tables don't exist
            }

            if (!$tables_exist) {
                $setup_stage = 'create_tables';
            } else {
                // Check if admin user exists
                try {
                    $user_check = $conn->query("SELECT COUNT(*) as count FROM users")->fetch();
                    if ($user_check && $user_check['count'] > 0) {
                        $setup_stage = 'completed';
                    } else {
                        $setup_stage = 'create_admin';
                    }
                } catch (PDOException $e) {
                    $setup_stage = 'create_tables';
                }
            }
        } else {
            $setup_stage = 'connect';
            $error = $db->getError();
        }
    }
} else {
    $setup_stage = 'connect';
    $error = $db->getError();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Qharleh Productions Africa</title>
    <!-- Google Fonts: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-dark: #0C0C0E;
            --bg-card: #16161A;
            --primary-gold: #D4AF37;
            --text-light: #EAEAEA;
            --text-muted: #9E9E9E;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-light);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        .setup-card {
            background-color: var(--bg-card);
            border: 1px solid rgba(212, 175, 55, 0.15);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            padding: 2.5rem;
            max-width: 550px;
            width: 90%;
        }
        .logo-text {
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--text-light);
            text-align: center;
            margin-bottom: 0.5rem;
        }
        .logo-text span {
            color: var(--primary-gold);
        }
        .setup-title {
            text-align: center;
            font-size: 1.25rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-gold {
            background-color: var(--primary-gold);
            color: #000;
            font-weight: 600;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-gold:hover {
            background-color: #f1c40f;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
        }
        .form-control {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-light);
            border-radius: 8px;
            padding: 0.75rem 1rem;
        }
        .form-control:focus {
            background-color: rgba(255, 255, 255, 0.08);
            border-color: var(--primary-gold);
            color: var(--text-light);
            box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
        }
        .alert {
            border-radius: 8px;
            border: none;
        }
        .config-details {
            background: rgba(0, 0, 0, 0.2);
            padding: 1rem;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            border-left: 3px solid var(--primary-gold);
        }
    </style>
</head>
<body>

<div class="setup-card">
    <h1 class="logo-text">Qharleh<span>Productions</span></h1>
    <h2 class="setup-title"><i class="fa-solid fa-gears me-2"></i>Database Installation</h2>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- STAGE: DATABASE CONNECTION ERROR -->
    <?php if ($setup_stage === 'connect'): ?>
        <div class="text-center py-3">
            <i class="fa-solid fa-triangle-exclamation text-danger fa-3x mb-3"></i>
            <h4 class="text-danger">Database Connection Failed</h4>
            <p class="text-muted mt-2">
                We could not connect to your MySQL database. Please verify the credentials defined inside <code class="text-light">config.php</code>.
            </p>
        </div>
        <div class="config-details">
            DB_HOST: <?php echo htmlspecialchars(DB_HOST); ?><br>
            DB_USER: <?php echo htmlspecialchars(DB_USER); ?><br>
            DB_NAME: <?php echo htmlspecialchars(DB_NAME); ?><br>
            Error Code: <span class="text-danger"><?php echo htmlspecialchars($error); ?></span>
        </div>
        <div class="alert alert-info">
            <i class="fa-solid fa-circle-info me-2"></i>
            <strong>InfinityFree Hosting Tip:</strong> On InfinityFree, you must first log in to your Control Panel, click <strong>MySQL Databases</strong>, create a database, and use the exact DB Host (e.g. <code>sql123.epizy.com</code>) and Username provided by InfinityFree.
        </div>
        <div class="d-grid gap-2">
            <a href="setup.php" class="btn btn-outline-light"><i class="fa-solid fa-rotate me-2"></i>Retry Connection</a>
        </div>

    <!-- STAGE: CREATE DATABASE -->
    <?php elseif ($setup_stage === 'create_database'): ?>
        <div class="text-center py-3">
            <i class="fa-solid fa-folder-plus text-warning fa-3x mb-3"></i>
            <h4>Database Missing</h4>
            <p class="text-muted mt-2">
                We successfully authenticated with the MySQL server, but the database <strong><?php echo htmlspecialchars(DB_NAME); ?></strong> does not exist.
            </p>
        </div>
        <form method="POST">
            <div class="d-grid gap-2">
                <button type="submit" name="create_database" class="btn btn-gold">
                    <i class="fa-solid fa-hammer me-2"></i>Create Database Dynamically
                </button>
            </div>
        </form>
        <div class="alert alert-info mt-4 mb-0">
            <i class="fa-solid fa-circle-info me-2"></i>
            <strong>Shared Hosting note (e.g. InfinityFree):</strong> Dynamic database creation is usually blocked on free hosting platforms. If this attempt fails, please log in to your Control Panel, click <strong>MySQL Databases</strong>, create the database manually, and make sure <code>DB_NAME</code> in <code>config.php</code> matches.
        </div>

    <!-- STAGE: CREATE TABLES -->
    <?php elseif ($setup_stage === 'create_tables'): ?>
        <div class="text-center py-3">
            <i class="fa-solid fa-database text-warning fa-3x mb-3"></i>
            <h4>Initialize Database Tables</h4>
            <p class="text-muted mt-2">
                Connected successfully to database <strong><?php echo htmlspecialchars(DB_NAME); ?></strong>. We now need to create the required tables (users, categories, and images) using <code>schema.sql</code>.
            </p>
        </div>
        <form method="POST">
            <div class="d-grid gap-2">
                <button type="submit" name="run_migrations" class="btn btn-gold">
                    <i class="fa-solid fa-play me-2"></i>Run Database Migrations
                </button>
            </div>
        </form>

    <!-- STAGE: CREATE ADMIN ACCOUNT -->
    <?php elseif ($setup_stage === 'create_admin'): ?>
        <div class="py-2">
            <h4 class="text-center mb-3">Create Administrator Account</h4>
            <p class="text-muted text-center mb-4">
                Set up your credentials to manage image categories and uploads.
            </p>
            <form method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label text-muted">Admin Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fa-solid fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="e.g. admin" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label text-muted">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="At least 6 characters" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="confirm_password" class="form-label text-muted">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
                    </div>
                </div>
                <div class="d-grid">
                    <button type="submit" name="create_admin_account" class="btn btn-gold">
                        <i class="fa-solid fa-user-plus me-2"></i>Complete Setup
                    </button>
                </div>
            </form>
        </div>

    <!-- STAGE: SETUP COMPLETED -->
    <?php elseif ($setup_stage === 'completed'): ?>
        <div class="text-center py-4">
            <i class="fa-solid fa-circle-check text-success fa-4x mb-3"></i>
            <h3 class="text-success">Installation Complete!</h3>
            <p class="text-muted mt-3">
                Your database is initialized and the administrator account is created. Default categories (Street, Portraits, Videos) and template showcase images have been added.
            </p>
            <div class="alert alert-warning my-4">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <strong>CRITICAL SECURITY ACTION:</strong><br>
                For safety, you must now delete the <code class="text-dark">setup.php</code> file from your server.
            </div>
            <div class="d-grid gap-2">
                <a href="admin.php" class="btn btn-gold"><i class="fa-solid fa-gauge me-2"></i>Go to Admin Dashboard</a>
                <a href="index.php" class="btn btn-outline-light"><i class="fa-solid fa-globe me-2"></i>View Live Website</a>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
