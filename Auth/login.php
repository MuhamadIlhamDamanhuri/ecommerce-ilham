<?php
// Auth/login.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if already logged in
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ../Admin/dashboard_admin.php');
    } elseif ($_SESSION['role'] === 'petugas') {
        header('Location: ../Petugas/dashboard_petugas.php');
    } else {
        header('Location: ../User/dashboard_user.php');
    }
    exit;
}

require_once '../config/database.php';

$error = '';
$success = '';
$mode = $_GET['mode'] ?? 'login'; // 'login' or 'register'

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, username, email, password, role, status, full_name FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    $error = 'Akun Anda tidak aktif. Hubungi administrator.';
                } else {
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    
                    session_regenerate_id(true);
                    
                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header('Location: ../Admin/dashboard_admin.php');
                    } elseif ($user['role'] === 'petugas') {
                        header('Location: ../Petugas/dashboard_petugas.php');
                    } else {
                        header('Location: ../User/dashboard_user.php');
                    }
                    exit;
                }
            } else {
                $error = 'Email atau password salah';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Register
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Username, email, password, dan nama lengkap wajib diisi';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } elseif ($password !== $confirm_password) {
        $error = 'Konfirmasi password tidak sama';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Check if email or username exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
            $stmt->execute(['username' => $username, 'email' => $email]);
            
            if ($stmt->fetch()) {
                $error = 'Username atau email sudah terdaftar';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, full_name, phone, address, role, status)
                    VALUES (:username, :email, :password, :full_name, :phone, :address, :role, :status)
                ");
                
                $stmt->execute([
                    'username' => $username,
                    'email' => $email,
                    'password' => $hashed_password,
                    'full_name' => $full_name,
                    'phone' => $phone,
                    'address' => $address,
                    'role' => 'user',  // Default role for registration
                    'status' => 'active'
                ]);
                
                $success = 'Registrasi berhasil! Silakan login.';
                $mode = 'login';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login & Register - E-Commerce Ilham</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            color: #0f172a;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: fixed;
            top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle at 30% 50%, rgba(59,130,246,0.08) 0%, transparent 50%),
                        radial-gradient(circle at 70% 80%, rgba(6,182,212,0.06) 0%, transparent 50%);
            animation: bgFloat 20s ease-in-out infinite;
            z-index: 0;
        }
        @keyframes bgFloat { 0%,100%{transform:translate(0,0)} 50%{transform:translate(-2%,-2%)} }
        .login-container { display:flex; justify-content:center; align-items:center; min-height:100vh; padding:20px; position:relative; z-index:1; }
        .login-box {
            background: rgba(255,255,255,0.97); backdrop-filter: blur(20px);
            border: 1px solid rgba(226,232,240,0.6); border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            padding: 44px 40px; width: 100%; max-width: 440px; text-align: center;
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
        .login-header { font-size:28px; font-weight:800; line-height:1.3; color:#0f172a; margin-bottom:24px; letter-spacing:-0.02em; }
        .mode-toggle { display:flex; gap:0; margin-bottom:28px; background:#f1f5f9; border-radius:10px; padding:4px; }
        .mode-btn { padding:10px 24px; border:none; background:transparent; color:#64748b; font-family:'Inter',sans-serif; font-size:14px; font-weight:600; border-radius:8px; cursor:pointer; transition:all 0.25s ease; flex:1; }
        .mode-btn.active { background:#3b82f6; color:white; box-shadow:0 2px 8px rgba(59,130,246,0.3); }
        .mode-btn:hover:not(.active) { color:#0f172a; }
        .form-container { text-align: left; }
        .form-group { margin-bottom:16px; text-align:left; }
        .form-group label { display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px; }
        .form-group input, .form-group select, .form-group textarea {
            width:100%; padding:11px 14px; font-family:'Inter',sans-serif; font-size:14px;
            border:1.5px solid #e2e8f0; border-radius:10px; background:#ffffff; color:#0f172a; transition:all 0.2s ease;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,0.15); }
        .form-group textarea { resize:vertical; min-height:70px; }
        .login-btn {
            background:linear-gradient(135deg,#3b82f6 0%,#2563eb 100%); color:#fff; border:none;
            width:100%; padding:13px; font-family:'Inter',sans-serif; font-size:15px; font-weight:700;
            border-radius:10px; cursor:pointer; margin-top:8px; transition:all 0.25s ease;
            box-shadow:0 4px 12px rgba(59,130,246,0.3);
        }
        .login-btn:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(59,130,246,0.4); }
        .login-btn:active { transform:translateY(0); }
        .error-message { background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:12px 14px; border-radius:10px; margin-bottom:18px; font-size:13px; text-align:left; font-weight:500; }
        .success-message { background:#f0fdf4; border:1px solid #86efac; color:#15803d; padding:12px 14px; border-radius:10px; margin-bottom:18px; font-size:13px; text-align:left; font-weight:500; }
        .credentials-info { background:#f0f9ff; border:1px solid #7dd3fc; color:#0369a1; padding:14px; border-radius:10px; margin:20px 0; font-size:12px; text-align:left; line-height:1.7; }
        .switch-link { text-align:center; margin-top:20px; font-size:13px; color:#64748b; }
        .switch-link a { color:#3b82f6; text-decoration:none; font-weight:600; }
        .switch-link a:hover { text-decoration:underline; }
        .form-section { display:none; }
        .form-section.active { display:block; animation:fadeIn 0.3s ease; }
        @keyframes fadeIn { from{opacity:0} to{opacity:1} }
        @media (max-width:480px) { .login-box{padding:32px 24px;margin:12px;border-radius:16px} .login-header{font-size:24px} }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-box">
        <div class="login-header">E-Commerce Ilham</div>
        
        <div class="mode-toggle">
            <button type="button" class="mode-btn <?php echo $mode === 'login' ? 'active' : ''; ?>" onclick="switchMode('login')">Login</button>
            <button type="button" class="mode-btn <?php echo $mode === 'register' ? 'active' : ''; ?>" onclick="switchMode('register')">Register</button>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <!-- Login Form
        <div id="loginForm" class="form-container form-section <?php echo $mode === 'login' ? 'active' : ''; ?>">
            <?php if ($mode === 'login'): ?>
            <div class="credentials-info">
                <strong>🔑 Default Credentials:</strong><br><br>
                <strong>🔐 Admin:</strong><br>
                Email: admin@ecommerce.com | Pass: password<br><br>
                <strong>👨‍💼 Petugas:</strong><br>
                Email: petugas@ecommerce.com | Pass: password<br><br>
                <strong>👤 User:</strong><br>
                Email: user@ecommerce.com | Pass: password
            </div>
            <?php endif; ?> -->
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="login_email">Email</label>
                    <input type="email" id="login_email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="login_password">Password</label>
                    <input type="password" id="login_password" name="password" required>
                </div>
                
                <button type="submit" class="login-btn">MASUK</button>
            </form>
            
            <div class="switch-link">
                Belum punya akun? <a href="#" onclick="switchMode('register'); return false;">Daftar sekarang</a>
            </div>
        </div>
        
        <!-- Register Form -->
        <div id="registerForm" class="form-container form-section <?php echo $mode === 'register' ? 'active' : ''; ?>">
            <form method="POST" action="">
                <input type="hidden" name="action" value="register">
                
                <div class="form-group">
                    <label for="reg_username">Username *</label>
                    <input type="text" id="reg_username" name="username" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="reg_email">Email *</label>
                    <input type="email" id="reg_email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="reg_password">Password *</label>
                    <input type="password" id="reg_password" name="password" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="reg_confirm_password">Konfirmasi Password *</label>
                    <input type="password" id="reg_confirm_password" name="confirm_password" required>
                </div>
                
                <div class="form-group">
                    <label for="reg_full_name">Nama Lengkap *</label>
                    <input type="text" id="reg_full_name" name="full_name" required 
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="reg_phone">No. Telepon</label>
                    <input type="text" id="reg_phone" name="phone" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="reg_address">Alamat</label>
                    <textarea id="reg_address" name="address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="login-btn">DAFTAR</button>
            </form>
            
            <div class="switch-link">
                Sudah punya akun? <a href="#" onclick="switchMode('login'); return false;">Login disini</a>
            </div>
        </div>
    </div>
</div>

<script>
function switchMode(mode) {
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('mode', mode);
    window.history.pushState({}, '', url);
    
    // Toggle forms
    document.getElementById('loginForm').classList.toggle('active', mode === 'login');
    document.getElementById('registerForm').classList.toggle('active', mode === 'register');
    
    // Toggle buttons
    const buttons = document.querySelectorAll('.mode-btn');
    buttons.forEach(btn => {
        btn.classList.toggle('active', btn.textContent.toLowerCase() === mode);
    });
    
    // Clear messages when switching
    const errorEl = document.querySelector('.error-message');
    const successEl = document.querySelector('.success-message');
    if (errorEl) errorEl.style.display = 'none';
    if (successEl) successEl.style.display = 'none';
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const mode = urlParams.get('mode') || 'login';
    if (mode === 'register') {
        switchMode('register');
    }
});
</script>
</body>
</html>