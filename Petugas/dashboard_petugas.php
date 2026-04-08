<?php
// Petugas/dashboard_petugas.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if petugas is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['role'] !== 'petugas') {
    header('Location: ../Auth/login.php');
    exit;
}

$petugas_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Petugas';
$petugas_id = $_SESSION['user_id'] ?? 0;
$current_page = basename($_SERVER['PHP_SELF']);

// Get stats from database
require_once '../config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get actual counts
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $total_users = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $total_products = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM transactions WHERE order_status = 'pending'");
    $pending_orders = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT SUM(total_amount) as total FROM transactions WHERE payment_status = 'paid'");
    $total_revenue = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {
    $total_users = 0;
    $total_products = 0;
    $pending_orders = 0;
    $total_revenue = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Petugas - E-Commerce Ilham</title>
    <link rel="stylesheet" href="../css/shared.css" />
    
</head>
<body data-role="petugas">
<div class="main-container">
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <span class="admin-panel-title">Panel Petugas</span>
            <span class="dashboard-title">Dashboard</span>
        </div>
        <div class="menu-divider">
            <span class="menu-label">Menu</span>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard_petugas.php" class="<?php echo ($current_page == 'dashboard_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">📊</span>
                Dashboard
            </a>
            <a href="kelola_produk_petugas.php" class="<?php echo ($current_page == 'kelola_produk_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">📦</span>
                Kelola data produk
            </a>
                        <a href="kelola_kategori_petugas.php" class="<?php echo ($current_page == 'kelola_produk.php') ? 'active' : ''; ?>">
                <span class="nav-icon">🎨</span>
                Kelola kategori
            </a>
            <a href="kelola_transaksi_petugas.php" class="<?php echo ($current_page == 'kelola_transaksi_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">💳</span>
                Kelola data transaksi
            </a>
            <a href="laporan_petugas.php" class="<?php echo ($current_page == 'laporan_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">📈</span>
                Laporan
            </a>
            <a href="restore_backup_petugas.php" class="<?php echo ($current_page == 'restore_backup_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">💾</span>
                Restore & Backup
            </a>
        </nav>
        <a href="../Auth/logout.php" class="logout-button">
            <span class="logout-icon">←</span>
            <span class="logout-text">Keluar</span>
        </a>
    </div>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1>Dashboard</h1>
        </div>
        
        <!-- Dashboard Content -->
        <div class="dashboard-main">
            <div class="welcome-section">
                <h2 class="welcome-text">
                    Welcome,<br>
                    <span class="admin-name"><?php echo htmlspecialchars($petugas_name); ?></span>
                </h2>
            </div>
            
            <!-- Quick Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-content">
                        <div class="stat-label">Total Users</div>
                        <div class="stat-value" id="total-users"><?php echo number_format($total_users); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-content">
                        <div class="stat-label">Total Products</div>
                        <div class="stat-value" id="total-products"><?php echo number_format($total_products); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💳</div>
                    <div class="stat-content">
                        <div class="stat-label">Pending Orders</div>
                        <div class="stat-value" id="pending-orders"><?php echo number_format($pending_orders); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-content">
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-value" id="total-revenue">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity Section -->
            <div class="recent-activity">
                <h3>Recent Activity</h3>
                <div class="activity-list" id="activity-container">
                    <div class="activity-item">
                        <span class="activity-icon">🛒</span>
                        <span class="activity-desc">New order #ORD-2026-001 placed</span>
                        <span class="activity-time">2 minutes ago</span>
                    </div>
                    <div class="activity-item">
                        <span class="activity-icon">👤</span>
                        <span class="activity-desc">New user registered: John Doe</span>
                        <span class="activity-time">15 minutes ago</span>
                    </div>
                    <div class="activity-item">
                        <span class="activity-icon">📦</span>
                        <span class="activity-desc">Product "Laptop Gaming" added to inventory</span>
                        <span class="activity-time">1 hour ago</span>
                    </div>
                    <div class="activity-item">
                        <span class="activity-icon">✅</span>
                        <span class="activity-desc">Order #ORD-2026-002 marked as shipped</span>
                        <span class="activity-time">2 hours ago</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Simulate loading dashboard data with animation
document.addEventListener('DOMContentLoaded', function() {
    const statValues = document.querySelectorAll('.stat-value');
    statValues.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(10px)';
        el.style.transition = 'opacity 0.3s, transform 0.3s';
    });
    
    setTimeout(function() {
        statValues.forEach((el, index) => {
            setTimeout(() => {
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }, 300);
});
</script>
</body>
</html>