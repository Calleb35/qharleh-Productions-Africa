<?php
/**
 * Administrator Login
 * Qharleh Productions Africa
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin.php');
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
$message_type = 'danger';

// If database is not configured or setup is incomplete, redirect to setup
if (!$conn) {
    header('Location: setup.php');
    exit;
} else {
    // Check if any users exist. If not, redirect to setup.php to create the first admin.
    try {
        $user_check = $conn->query("SELECT COUNT(*) as count FROM users")->fetch();
        if ($user_check['count'] == 0) {
            header('Location: setup.php');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: setup.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $message = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Password is correct, start session
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $user['username'];
            header('Location: admin.php');
            exit;
        } else {
            $message = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Qharleh Productions Africa</title>
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
            background-image: radial-gradient(circle at 10% 20%, rgba(212, 175, 55, 0.05) 0%, transparent 40%), 
                              radial-gradient(circle at 90% 80%, rgba(212, 175, 55, 0.05) 0%, transparent 40%);
        }
        .login-card {
            background-color: var(--bg-card);
            border: 1px solid rgba(212, 175, 55, 0.15);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            padding: 3rem 2.5rem;
            max-width: 450px;
            width: 90%;
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, transparent, var(--primary-gold), transparent);
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
        .login-title {
            text-align: center;
            font-size: 1.1rem;
            color: var(--text-muted);
            margin-bottom: 2.5rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .btn-gold {
            background-color: var(--primary-gold);
            color: #000;
            font-weight: 600;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            letter-spacing: 1px;
            text-transform: uppercase;
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
            font-size: 0.95rem;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s ease;
            font-size: 0.9rem;
        }
        .back-link:hover {
            color: var(--primary-gold);
        }
    </style>
</head>
<body>

<div class="login-card">
    <h1 class="logo-text">Qharleh<span>Productions</span></h1>
    <h2 class="login-title">Admin Portal</h2>

    <?php if (!empty($message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="username" class="form-label text-muted">Username</label>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-muted"><i class="fa-solid fa-user"></i></span>
                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
            </div>
        </div>
        <div class="mb-4">
            <label for="password" class="form-label text-muted">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-muted"><i class="fa-solid fa-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
            </div>
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-gold">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Log In
            </button>
        </div>
    </form>

    <a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left me-2"></i>Back to Website</a>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
