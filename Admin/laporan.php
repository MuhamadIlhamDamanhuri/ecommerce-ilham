<?php
// laporan.php - All-in-One Reports (Stok, Penjualan, Transaksi)
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if admin or petugas is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || !in_array($_SESSION['role'], ['admin', 'petugas'])) {
    header('Location: Auth/login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
require_once '../config/database.php';

// Get report type from URL
$report_type = $_GET['type'] ?? 'transaksi'; // transaksi, penjualan, stok
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$report_data = [];
$summary = [];

try {
    $pdo = getDBConnection();
    
    // ==========================================
    // LAPORAN TRANSAKSI
    // ==========================================
    if ($report_type === 'transaksi') {
        $query = "
            SELECT t.*, u.username, u.full_name, u.email
            FROM transactions t
            LEFT JOIN users u ON t.user_id = u.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($search)) {
            $query .= " AND (t.order_number LIKE :search OR u.full_name LIKE :search OR u.username LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }
        if (!empty($status_filter)) {
            $query .= " AND t.order_status = :status";
            $params['status'] = $status_filter;
        }
        if (!empty($date_from)) {
            $query .= " AND t.created_at >= :date_from";
            $params['date_from'] = $date_from . ' 00:00:00';
        }
        if (!empty($date_to)) {
            $query .= " AND t.created_at <= :date_to";
            $params['date_to'] = $date_to . ' 23:59:59';
        }
        $query .= " ORDER BY t.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();
        
        // Summary
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_order
            FROM transactions
            WHERE created_at BETWEEN :from AND :to
        ");
        $stmt->execute(['from' => $date_from . ' 00:00:00', 'to' => $date_to . ' 23:59:59']);
        $summary = $stmt->fetch();
        
    // ==========================================
    // LAPORAN PENJUALAN
    // ==========================================
    } elseif ($report_type === 'penjualan') {
        $query = "
            SELECT 
                DATE(t.created_at) as sale_date,
                COUNT(t.id) as orders,
                SUM(t.total_amount) as revenue,
                SUM(td.quantity) as items_sold
            FROM transactions t
            LEFT JOIN transaction_details td ON t.id = td.transaction_id
            WHERE t.payment_status = 'paid'
        ";
        $params = [];
        
        if (!empty($date_from)) {
            $query .= " AND t.created_at >= :date_from";
            $params['date_from'] = $date_from . ' 00:00:00';
        }
        if (!empty($date_to)) {
            $query .= " AND t.created_at <= :date_to";
            $params['date_to'] = $date_to . ' 23:59:59';
        }
        $query .= " GROUP BY DATE(t.created_at) ORDER BY sale_date DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();
        
        // Summary
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_order
            FROM transactions
            WHERE payment_status = 'paid'
            AND created_at BETWEEN :from AND :to
        ");
        $stmt->execute(['from' => $date_from . ' 00:00:00', 'to' => $date_to . ' 23:59:59']);
        $summary = $stmt->fetch();
        
    // ==========================================
    // LAPORAN STOK
    // ==========================================
    } elseif ($report_type === 'stok') {
        $query = "
            SELECT * FROM products WHERE 1=1
        ";
        $params = [];
        
        if (!empty($search)) {
            $query .= " AND (name LIKE :search OR category LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }
        if (!empty($status_filter)) {
            $query .= " AND status = :status";
            $params['status'] = $status_filter;
        }
        $query .= " ORDER BY stock ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();
        
        // Summary
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_products,
                SUM(stock) as total_stock,
                AVG(stock) as avg_stock,
                SUM(CASE WHEN stock <= 5 THEN 1 ELSE 0 END) as low_stock
            FROM products
            WHERE status = 'active'
        ");
        $summary = $stmt->fetch();
    }
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Laporan - Admin</title>
    <link rel="stylesheet" href="../css/shared.css" />
    
</head>
<body data-role="admin">
<div class="main-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <span class="admin-panel-title">Admin panel</span>
            <span class="dashboard-title">Dashboard</span>
        </div>
        <div class="menu-divider">
            <span class="menu-label">Menu</span>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard_admin.php"><span class="nav-icon">📊</span> Dashboard</a>
            <a href="kelola_user.php"><span class="nav-icon">👥</span> Kelola data user</a>
            <a href="kelola_petugas.php"><span class="nav-icon">👨‍💼</span> Kelola data petugas</a>
            <a href="kelola_produk.php"><span class="nav-icon">📦</span> Kelola data produk</a>
                        <a href="kelola_kategori.php" class="<?php echo ($current_page == 'kelola_produk.php') ? 'active' : ''; ?>">
                <span class="nav-icon">🎨</span>
                Kelola kategori
            </a>
            <a href="kelola_transaksi.php"><span class="nav-icon">💳</span> Kelola data transaksi</a>
            <a href="laporan.php" class="<?php echo $current_page == 'laporan.php' ? 'active' : ''; ?>"><span class="nav-icon">📈</span> Laporan</a>
            <a href="restore_backup.php"><span class="nav-icon">💾</span> Restore & Backup</a>
        </nav>
        <a href="../Auth/logout.php" class="logout-button">
            <span class="logout-icon">←</span>
            <span class="logout-text">Keluar</span>
        </a>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar"><h1>Laporan</h1></div>
        <div class="content-area">
            <h2 class="page-title">Laporan</h2>
            
            <!-- Report Type Tabs -->
            <div class="report-tabs">
                <a href="?type=transaksi&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="report-tab <?php echo $report_type === 'transaksi' ? 'active' : ''; ?>">📋 Laporan Transaksi</a>
                <a href="?type=penjualan&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="report-tab <?php echo $report_type === 'penjualan' ? 'active' : ''; ?>">💰 Laporan Penjualan</a>
                <a href="?type=stok&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="report-tab <?php echo $report_type === 'stok' ? 'active' : ''; ?>">📦 Laporan Stok</a>
            </div>
            
            <!-- Report Controls -->
            <div class="report-controls">
                <form method="GET" style="display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end;flex:1;">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">
                    
                    <?php if ($report_type !== 'stok'): ?>
                    <div class="form-group">
                        <label>Dari Tanggal</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="form-group">
                        <label>Sampai Tanggal</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Cari</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari...">
                    </div>
                    
                    <?php if ($report_type === 'transaksi'): ?>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Semua</option>
                            <option value="berhasil" <?php echo $status_filter === 'berhasil' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <?php elseif ($report_type === 'stok'): ?>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Semua</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">Tampilkan</button>
                    <a href="?type=<?php echo $report_type; ?>" class="btn btn-warning">Reset</a>
                </form>
                
                <button class="export-btn" onclick="alert('Fitur export CSV akan dikembangkan')">📥 Export CSV</button>
            </div>
            
            <!-- Summary Stats -->
            <?php if (!empty($summary)): ?>
            <div class="stats-grid">
                <?php if ($report_type === 'transaksi' || $report_type === 'penjualan'): ?>
                <div class="stat-card">
                    <h4>Total Order</h4>
                    <div class="value"><?php echo number_format($summary['total_orders'] ?? 0); ?></div>
                </div>
                <div class="stat-card success">
                    <h4>Total Pendapatan</h4>
                    <div class="value">Rp <?php echo number_format($summary['total_revenue'] ?? 0, 0, ',', '.'); ?></div>
                </div>
                <div class="stat-card">
                    <h4>Rata-rata Order</h4>
                    <div class="value">Rp <?php echo number_format($summary['avg_order'] ?? 0, 0, ',', '.'); ?></div>
                </div>
                <?php elseif ($report_type === 'stok'): ?>
                <div class="stat-card">
                    <h4>Total Produk</h4>
                    <div class="value"><?php echo number_format($summary['total_products'] ?? 0); ?></div>
                </div>
                <div class="stat-card success">
                    <h4>Total Stok</h4>
                    <div class="value"><?php echo number_format($summary['total_stock'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <h4>Rata-rata Stok</h4>
                    <div class="value"><?php echo number_format($summary['avg_stock'] ?? 0, 0); ?></div>
                </div>
                <div class="stat-card warning">
                    <h4>Stok Menipis</h4>
                    <div class="value"><?php echo number_format($summary['low_stock'] ?? 0); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Report Table -->
            <div class="table-container">
                <table>
                    <?php if ($report_type === 'transaksi'): ?>
                    <thead>
                        <tr>
                            <th>No. Order</th>
                            <th>Pelanggan</th>
                            <th>Total</th>
                            <th>Metode</th>
                            <th>Bayar</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($report_data)): ?>
                            <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['order_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['full_name'] ?? $row['username']); ?></td>
                                <td>Rp <?php echo number_format($row['total_amount'], 0, ',', '.'); ?></td>
                                <td><?php echo ucfirst($row['payment_method']); ?></td>
                                <td><span class="badge badge-<?php echo $row['payment_status']; ?>"><?php echo ucfirst($row['payment_status']); ?></span></td>
                                <td><span class="badge badge-<?php echo $row['order_status']; ?>"><?php echo ucfirst($row['order_status']); ?></span></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center;padding:30px;">Tidak ada data transaksi untuk periode ini</td></tr>
                        <?php endif; ?>
                    </tbody>
                    
                    <?php elseif ($report_type === 'penjualan'): ?>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jumlah Order</th>
                            <th>Item Terjual</th>
                            <th>Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($report_data)): ?>
                            <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($row['sale_date'])); ?></td>
                                <td><?php echo number_format($row['orders']); ?></td>
                                <td><?php echo number_format($row['items_sold']); ?></td>
                                <td>Rp <?php echo number_format($row['revenue'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center;padding:30px;">Tidak ada data penjualan untuk periode ini</td></tr>
                        <?php endif; ?>
                    </tbody>
                    
                    <?php elseif ($report_type === 'stok'): ?>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Status Stok</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($report_data)): ?>
                            <?php foreach ($report_data as $row): ?>
                            <?php
                            $stock_status = 'high';
                            $stock_label = 'Aman';
                            if ($row['stock'] <= 5) { $stock_status = 'low'; $stock_label = 'Menipis'; }
                            elseif ($row['stock'] <= 20) { $stock_status = 'medium'; $stock_label = 'Cukup'; }
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td>Rp <?php echo number_format($row['price'], 0, ',', '.'); ?></td>
                                <td><?php echo number_format($row['stock']); ?></td>
                                <td><span class="badge badge-<?php echo $stock_status; ?>"><?php echo $stock_label; ?></span></td>
                                <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center;padding:30px;">Tidak ada data produk</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <?php endif; ?>
                </table>
            </div>
            
        </div>
    </div>
</div>
</body>
</html>