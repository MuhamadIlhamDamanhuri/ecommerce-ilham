<?php
// Petugas/kelola_transaksi_petugas.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔐 Cek autentikasi petugas
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'petugas') {
    header('Location: ../Auth/login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
require_once '../config/database.php';

$error = '';
$success = '';
$transaction_details = null;
$items = [];

// 🔄 Handle Update Status Transaksi (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id = intval($_POST['transaction_id'] ?? 0);
    $order_status = $_POST['order_status'] ?? 'pending';
    $payment_status = $_POST['payment_status'] ?? 'pending';
    $notes = trim($_POST['notes'] ?? '');
    
    if ($id > 0) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET order_status = :order_status, 
                    payment_status = :payment_status, 
                    notes = :notes, 
                    updated_at = NOW() 
                WHERE id = :id
            ");
            $stmt->execute([
                'order_status' => $order_status, 
                'payment_status' => $payment_status, 
                'notes' => $notes, 
                'id' => $id
            ]);
            $success = '✅ Status transaksi berhasil diupdate!';
        } catch (PDOException $e) {
            $error = '❌ Error update: ' . $e->getMessage();
            error_log($e->getMessage());
        }
    }
}

// 🔍 Handle View Detail Transaksi (GET)
if (isset($_GET['view'])) {
    $id = intval($_GET['view']);
    if ($id > 0) {
        try {
            $pdo = getDBConnection();
            
            // Ambil data transaksi + user
            $stmt = $pdo->prepare("
                SELECT t.*, 
                       u.username, u.full_name, u.email, u.phone, u.address as user_address 
                FROM transactions t 
                LEFT JOIN users u ON t.user_id = u.id 
                WHERE t.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $transaction_details = $stmt->fetch();
            
            // Ambil item produk dalam transaksi
            if ($transaction_details) {
                $stmt = $pdo->prepare("
                    SELECT td.*, p.name as product_name, p.image, p.price as product_price 
                    FROM transaction_details td 
                    LEFT JOIN products p ON td.product_id = p.id 
                    WHERE td.transaction_id = :id
                ");
                $stmt->execute(['id' => $id]);
                $items = $stmt->fetchAll();
            }
        } catch (PDOException $e) {
            $error = '❌ Error load detail: ' . $e->getMessage();
            error_log($e->getMessage());
        }
    }
}

// 🔎 Search & Filter Parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['order_status'] ?? '';
$payment_filter = $_GET['payment_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 📊 Build Query dengan Filter
$query = "SELECT t.*, u.username, u.full_name, u.email 
          FROM transactions t 
          LEFT JOIN users u ON t.user_id = u.id 
          WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM transactions t 
                LEFT JOIN users u ON t.user_id = u.id 
                WHERE 1=1";
$params = [];

if (!empty($search)) {
    $like = '%' . $search . '%';
    $query .= " AND (t.order_number LIKE :search OR u.username LIKE :search OR u.full_name LIKE :search OR u.email LIKE :search)";
    $count_query .= " AND (t.order_number LIKE :search OR u.username LIKE :search OR u.full_name LIKE :search OR u.email LIKE :search)";
    $params['search'] = $like;
}
if (!empty($status_filter)) {
    $query .= " AND t.order_status = :order_status";
    $count_query .= " AND t.order_status = :order_status";
    $params['order_status'] = $status_filter;
}
if (!empty($payment_filter)) {
    $query .= " AND t.payment_status = :payment_status";
    $count_query .= " AND t.payment_status = :payment_status";
    $params['payment_status'] = $payment_filter;
}
if (!empty($date_from)) {
    $query .= " AND DATE(t.created_at) >= :date_from";
    $count_query .= " AND DATE(t.created_at) >= :date_from";
    $params['date_from'] = $date_from;
}
if (!empty($date_to)) {
    $query .= " AND DATE(t.created_at) <= :date_to";
    $count_query .= " AND DATE(t.created_at) <= :date_to";
    $params['date_to'] = $date_to;
}

$query .= " ORDER BY t.created_at DESC LIMIT :limit OFFSET :offset";
$count_query .= " ORDER BY t.created_at DESC";

try {
    $pdo = getDBConnection();
    
    // Ambil data transaksi dengan pagination
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $val) $stmt->bindValue($key, $val);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $transactions = $stmt->fetchAll();
    
    // Hitung total untuk pagination
    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $val) $stmt->bindValue($key, $val);
    $stmt->execute();
    $total_trans = $stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total_trans / $per_page);
    
    // Statistik dashboard
    $stmt = $pdo->query("SELECT SUM(total_amount) as total FROM transactions WHERE payment_status = 'paid'");
    $total_revenue = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM transactions WHERE order_status = 'pending'");
    $pending_count = $stmt->fetch()['total'] ?? 0;
    
} catch (PDOException $e) {
    $error = '❌ Database error: ' . $e->getMessage();
    error_log($e->getMessage());
    $transactions = [];
    $total_trans = 0;
    $total_pages = 1;
    $total_revenue = 0;
    $pending_count = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Kelola Transaksi - Panel Petugas</title>
    <link rel="stylesheet" href="../css/shared.css" />
</head>
<body data-role="petugas">

<div class="app-container">
    <!-- SIDEBAR -->
        <div class="sidebar">
        <div class="sidebar-header">
            <span class="admin-panel-title">Panel Petugas</span>
            <span class="dashboard-title">Dashboard Utama</span>
        </div>
        <div class="menu-divider">
            <span class="menu-label">Menu Petugas</span>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard_petugas.php" class="<?php echo ($current_page == 'dashboard_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">📊</span> <span class="nav-text">Dashboard</span>
            </a>
            <a href="kelola_produk_petugas.php" class="<?php echo ($current_page == 'kelola_produk_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">📦</span> <span class="nav-text">Kelola Produk</span>
            </a>
            <a href="kelola_kategori_petugas.php" class="<?php echo ($current_page == 'kelola_kategori_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">🎨</span> <span class="nav-text">Kelola Kategori</span>
            </a>
            <a href="kelola_transaksi_petugas.php" class="<?php echo ($current_page == 'kelola_transaksi_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">💳</span> <span class="nav-text">Kelola Transaksi</span>
            </a>
            <a href="laporan_petugas.php" class="<?php echo ($current_page == 'laporan_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">📈</span> <span class="nav-text">Laporan</span>
            </a>
            <a href="restore_backup_petugas.php" class="<?php echo ($current_page == 'restore_backup_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">💾</span> <span class="nav-text">Backup & Restore</span>
            </a>
        </nav>
        <a href="../Auth/logout.php" class="logout-button">
            <span class="logout-icon">←</span>
            <span class="logout-text">Keluar</span>
        </a>
    </div>
    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">💳 Kelola Data Transaksi</h1>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($total_trans); ?></div>
                <div class="stat-label">Total Transaksi</div>
            </div>
            <div class="stat-card revenue">
                <div class="stat-value">Rp <?php echo number_format($total_revenue ?? 0, 0, ',', '.'); ?></div>
                <div class="stat-label">Total Pendapatan</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-value"><?php echo number_format($pending_count); ?></div>
                <div class="stat-label">Menunggu Proses</div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="filter-card">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Cari</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="No. Order / Nama / Email...">
                </div>
                <div class="filter-group">
                    <label>Status Order</label>
                    <select name="order_status">
                        <option value="">Semua</option>
                        <option value="pending" <?php echo $status_filter==='pending'?'selected':''; ?>>Pending</option>
                        <option value="verified" <?php echo $status_filter==='verified'?'selected':''; ?>>Verified</option>
                        <option value="processing" <?php echo $status_filter==='processing'?'selected':''; ?>>Processing</option>
                        <option value="shipped" <?php echo $status_filter==='shipped'?'selected':''; ?>>Shipped</option>
                        <option value="completed" <?php echo $status_filter==='completed'?'selected':''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter==='cancelled'?'selected':''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status Bayar</label>
                    <select name="payment_status">
                        <option value="">Semua</option>
                        <option value="pending" <?php echo $payment_filter==='pending'?'selected':''; ?>>Pending</option>
                        <option value="verified" <?php echo $status_filter==='verified'?'selected':''; ?>>Verified</option>
                        <option value="paid" <?php echo $payment_filter==='paid'?'selected':''; ?>>Paid</option>
                        <option value="cancelled" <?php echo $payment_filter==='cancelled'?'selected':''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Dari Tanggal</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="filter-group">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div style="display:flex;gap:8px;align-items:flex-end;">
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="kelola_transaksi_petugas.php" class="btn btn-warning">🔄 Reset</a>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No. Order</th>
                            <th>Pelanggan</th>
                            <th>Total</th>
                            <th>Metode</th>
                            <th>Bayar</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($transactions)): ?>
                            <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($t['order_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($t['full_name'] ?: $t['username']); ?></td>
                                <td class="price">Rp <?php echo number_format($t['total_amount'], 0, ',', '.'); ?></td>
                                <td><?php echo ucfirst($t['payment_method'] ?? '-'); ?></td>
                                <td><span class="badge badge-<?php echo $t['payment_status']; ?>"><?php echo ucfirst($t['payment_status']); ?></span></td>
                                <td><span class="badge badge-<?php echo $t['order_status']; ?>"><?php echo ucfirst($t['order_status']); ?></span></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                                <td>
                                    <button class="action-btn" onclick="openModal(<?php echo (int)$t['id']; ?>)">👁️ Detail</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted);">
                                🔍 Tidak ada transaksi ditemukan
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_filter($_GET + ['page' => $page-1])); ?>" class="page-link">« Prev</a>
                <?php else: ?>
                    <span class="page-link disabled">« Prev</span>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                    <a href="?<?php echo http_build_query(array_filter($_GET + ['page' => $i])); ?>" 
                       class="page-link <?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_filter($_GET + ['page' => $page+1])); ?>" class="page-link">Next »</a>
                <?php else: ?>
                    <span class="page-link disabled">Next »</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- MODAL DETAIL -->
<div id="detailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">📋 Detail Transaksi</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <?php if ($transaction_details): ?>
                <!-- Info Transaksi -->
                <div class="info-grid">
                    <div class="info-item"><label>No. Order</label><span><?php echo htmlspecialchars($transaction_details['order_number']); ?></span></div>
                    <div class="info-item"><label>Tanggal</label><span><?php echo date('d/m/Y H:i', strtotime($transaction_details['created_at'])); ?></span></div>
                    <div class="info-item"><label>Pelanggan</label><span><?php echo htmlspecialchars($transaction_details['full_name'] ?: $transaction_details['username']); ?></span></div>
                    <div class="info-item"><label>Email</label><span><?php echo htmlspecialchars($transaction_details['email'] ?? '-'); ?></span></div>
                    <div class="info-item"><label>Telepon</label><span><?php echo htmlspecialchars($transaction_details['phone'] ?? '-'); ?></span></div>
                    <div class="info-item"><label>Metode Bayar</label><span><?php echo ucfirst($transaction_details['payment_method'] ?? '-'); ?></span></div>
                    <div class="info-item"><label>Status Bayar</label><span><span class="badge badge-<?php echo $transaction_details['payment_status']; ?>"><?php echo ucfirst($transaction_details['payment_status']); ?></span></span></div>
                    <div class="info-item"><label>Status Order</label><span><span class="badge badge-<?php echo $transaction_details['order_status']; ?>"><?php echo ucfirst($transaction_details['order_status']); ?></span></span></div>
                    <div class="info-item" style="grid-column: span 2;">
                        <label>Alamat Pengiriman</label>
                        <span><?php echo htmlspecialchars($transaction_details['shipping_address'] ?? $transaction_details['user_address'] ?? '-'); ?></span>
                    </div>
                    <div class="info-item" style="grid-column: span 2;">
                        <label>Total Pembayaran</label>
                        <span class="price" style="font-size:18px;">Rp <?php echo number_format($transaction_details['total_amount'], 0, ',', '.'); ?></span>
                    </div>
                </div>

                <!-- Items -->
                <h4 style="margin:20px 0 12px;font-weight:700;">🛒 Item Pesanan</h4>
                <table class="items-table">
                    <thead><tr><th>Produk</th><th class="text-right">Harga</th><th class="text-right">Qty</th><th class="text-right">Subtotal</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td class="text-right">Rp <?php echo number_format($item['price'] ?? $item['product_price'] ?? 0, 0, ',', '.'); ?></td>
                            <td class="text-right"><?php echo (int)$item['quantity']; ?></td>
                            <td class="text-right price">Rp <?php echo number_format($item['subtotal'] ?? (($item['price'] ?? $item['product_price'] ?? 0) * $item['quantity']), 0, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Form Update Status -->
                <div class="form-section">
                    <h4 style="margin-bottom:16px;font-weight:700;">✏️ Update Status</h4>
                    <form method="POST">
                        <input type="hidden" name="transaction_id" value="<?php echo (int)$transaction_details['id']; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Status Order</label>
                                <select name="order_status" required>
                                    <option value="pending" <?php echo $transaction_details['order_status']==='pending'?'selected':''; ?>>⏳ Pending</option>
                                    <option value="verified" <?php echo $transaction_details['order_status']==='verified'?'selected':''; ?>>✔️ Verified</option>
                                    <option value="processing" <?php echo $transaction_details['order_status']==='processing'?'selected':''; ?>>🔄 Processing</option>
                                    <option value="shipped" <?php echo $transaction_details['order_status']==='shipped'?'selected':''; ?>>🚚 Shipped</option>
                                    <option value="completed" <?php echo $transaction_details['order_status']==='completed'?'selected':''; ?>>✅ Completed</option>
                                    <option value="cancelled" <?php echo $transaction_details['order_status']==='cancelled'?'selected':''; ?>>❌ Cancelled</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status Pembayaran</label>
                                <select name="payment_status" required>
                                    <option value="pending" <?php echo $transaction_details['payment_status']==='pending'?'selected':''; ?>>⏳ Pending</option>
                                    <option value="verified" <?php echo $transaction_details['order_status']==='verified'?'selected':''; ?>>✔️ Verified</option>
                                    <option value="paid" <?php echo $transaction_details['payment_status']==='paid'?'selected':''; ?>>💰 Paid</option>
                                    <option value="cancelled" <?php echo $transaction_details['payment_status']==='cancelled'?'selected':''; ?>>❌ Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Catatan Tambahan</label>
                            <textarea name="notes" placeholder="Tambahkan catatan untuk transaksi ini..."><?php echo htmlspecialchars($transaction_details['notes'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" name="update_status" class="btn btn-success">💾 Simpan Perubahan</button>
                    </form>
                </div>
            <?php else: ?>
                <p style="text-align:center;color:var(--text-muted);padding:40px;">
                    ⚠️ Detail transaksi tidak ditemukan atau ID tidak valid.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// ✅ Modal Functions - Simple & Reliable
function openModal(id) {
    // Redirect ke halaman yang sama dengan parameter view
    window.location.href = '?view=' + id;
}

function closeModal() {
    // Hapus parameter view dari URL tanpa reload
    const url = new URL(window.location);
    url.searchParams.delete('view');
    window.history.replaceState({}, '', url);
    document.getElementById('detailModal').classList.remove('show');
}

// Auto open modal if view parameter exists
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    if (params.has('view')) {
        document.getElementById('detailModal').classList.add('show');
    }
});

// Close modal on outside click
document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

</body>
</html>