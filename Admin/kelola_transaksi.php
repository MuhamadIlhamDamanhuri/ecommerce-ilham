<?php
// kelola_transaksi.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || !in_array($_SESSION['role'], ['admin', 'petugas'])) {
    header('Location: Auth/login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
require_once '../config/database.php';

$error = '';
$success = '';

// Handle Update Transaction Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id = intval($_POST['transaction_id'] ?? 0);
    $order_status = $_POST['order_status'] ?? 'pending';
    $payment_status = $_POST['payment_status'] ?? 'pending';
    $notes = trim($_POST['notes'] ?? '');
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE transactions SET order_status = :order_status, payment_status = :payment_status, notes = :notes, updated_at = NOW() WHERE id = :id");
        $stmt->execute(['order_status' => $order_status, 'payment_status' => $payment_status, 'notes' => $notes, 'id' => $id]);
        $success = 'Status transaksi berhasil diupdate';
    } catch (PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Handle Delete Transaction
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $pdo = getDBConnection();
        // Delete details first (cascade should handle this, but just in case)
        $stmt = $pdo->prepare("DELETE FROM transaction_details WHERE transaction_id = :id");
        $stmt->execute(['id' => $id]);
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $success = 'Transaksi berhasil dihapus';
    } catch (PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Search & Filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['order_status'] ?? '';
$payment_filter = $_GET['payment_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$query = "SELECT t.*, u.username, u.full_name, u.email FROM transactions t 
          LEFT JOIN users u ON t.user_id = u.id WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (t.order_number LIKE :search OR u.username LIKE :search OR u.full_name LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if (!empty($status_filter)) {
    $query .= " AND t.order_status = :order_status";
    $params['order_status'] = $status_filter;
}
if (!empty($payment_filter)) {
    $query .= " AND t.payment_status = :payment_status";
    $params['payment_status'] = $payment_filter;
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

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    // Stats
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM transactions");
    $total_trans = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT SUM(total_amount) as total FROM transactions WHERE payment_status = 'paid'");
    $total_revenue = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM transactions WHERE order_status = 'pending'");
    $pending_count = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $transactions = [];
    $total_trans = 0;
    $total_revenue = 0;
    $pending_count = 0;
}

// Get transaction details for modal
$transaction_details = null;
if (isset($_GET['view'])) {
    $id = intval($_GET['view']);
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT t.*, u.username, u.full_name, u.email, u.phone, u.address as user_address 
            FROM transactions t 
            LEFT JOIN users u ON t.user_id = u.id 
            WHERE t.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $transaction_details = $stmt->fetch();
        
        // Get items
        $stmt = $pdo->prepare("
            SELECT td.*, p.name as product_name, p.image 
            FROM transaction_details td 
            LEFT JOIN products p ON td.product_id = p.id 
            WHERE td.transaction_id = :id
        ");
        $stmt->execute(['id' => $id]);
        $items = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Kelola Data Transaksi - Admin</title>
    <link rel="stylesheet" href="../css/shared.css" />
    
</head>
<body data-role="admin">
<div class="main-container">
    <div class="sidebar">
        <div class="sidebar-header"><span class="admin-panel-title">Admin panel</span><span class="dashboard-title">Dashboard</span></div>
        <div class="menu-divider"><span class="menu-label">Menu</span></div>
        <nav class="sidebar-nav">
            <a href="dashboard_admin.php"><span class="nav-icon">📊</span> Dashboard</a>
            <a href="kelola_user.php"><span class="nav-icon">👥</span> Kelola data user</a>
            <a href="kelola_petugas.php"><span class="nav-icon">👨‍💼</span> Kelola data petugas</a>
            <a href="kelola_produk.php"><span class="nav-icon">📦</span> Kelola data produk</a>
                        <a href="kelola_kategori.php" class="<?php echo ($current_page == 'kelola_produk.php') ? 'active' : ''; ?>">
                <span class="nav-icon">🎨</span>
                Kelola kategori
            </a>
            <a href="kelola_transaksi.php" class="<?php echo $current_page == 'kelola_transaksi.php' ? 'active' : ''; ?>"><span class="nav-icon">💳</span> Kelola data transaksi</a>
            <a href="laporan.php"><span class="nav-icon">📈</span> Laporan</a>
            <a href="restore_backup.php"><span class="nav-icon">💾</span> Restore & Backup</a>
        </nav>
        <a href="../Auth/logout.php" class="logout-button"><span class="logout-icon">←</span><span class="logout-text">Keluar</span></a>
    </div>
    
    <div class="main-content">
        <div class="top-bar"><h1>Kelola Data Transaksi</h1></div>
        <div class="content-area">
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            
            <div class="page-header">
                <h2 class="page-title">Kelola Data Transaksi</h2>
            </div>
            
            <div class="stats-summary">
                <div class="stat-box"><div class="stat-number"><?php echo number_format($total_trans); ?></div><div class="stat-label">Total Transaksi</div></div>
                <div class="stat-box revenue"><div class="stat-number">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></div><div class="stat-label">Total Pendapatan</div></div>
                <div class="stat-box pending"><div class="stat-number"><?php echo number_format($pending_count); ?></div><div class="stat-label">Menunggu Proses</div></div>
            </div>
            
            <div class="filter-section">
                <form method="GET" action="" class="filter-form">
                    <div class="filter-group"><label for="search">Cari</label><input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="No. Order / User..."></div>
                    <div class="filter-group"><label for="order_status">Status Order</label><select id="order_status" name="order_status"><option value="">Semua</option><option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option><option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option><option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option><option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option></select></div>
                    <div class="filter-group"><label for="payment_status">Status Pembayaran</label><select id="payment_status" name="payment_status"><option value="">Semua</option><option value="pending" <?php echo $payment_filter === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="paid" <?php echo $payment_filter === 'paid' ? 'selected' : ''; ?>>Paid</option><option value="cancelled" <?php echo $payment_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option></select></div>
                    <div class="filter-group"><label for="date_from">Dari Tanggal</label><input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"></div>
                    <div class="filter-group"><label for="date_to">Sampai Tanggal</label><input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"></div>
                    <div class="filter-group" style="min-width:auto;"><button type="submit" class="btn btn-primary">Filter</button><a href="kelola_transaksi.php" class="btn btn-warning" style="margin-left:10px;">Reset</a></div>
                </form>
            </div>
            
            <div class="table-container">
                <table>
                    <thead><tr><th>No. Order</th><th>Pelanggan</th><th>Total</th><th>Metode</th><th>Bayar</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php if(count($transactions) > 0): ?>
                            <?php foreach($transactions as $t): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($t['order_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($t['full_name'] ?? $t['username']); ?></td>
                                <td class="price">Rp <?php echo number_format($t['total_amount'], 0, ',', '.'); ?></td>
                                <td><?php echo ucfirst($t['payment_method']); ?></td>
                                <td><span class="badge badge-<?php echo $t['payment_status']; ?>"><?php echo ucfirst($t['payment_status']); ?></span></td>
                                <td><span class="badge badge-<?php echo $t['order_status']; ?>"><?php echo ucfirst($t['order_status']); ?></span></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                                <td class="action-buttons">
                                    <button class="view-btn" onclick="viewTransaction(<?php echo $t['id']; ?>)">👁️ Lihat</button>
                                    <?php if($_SESSION['role'] === 'admin'): ?>
                                    <a href="?delete=<?php echo $t['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus transaksi ini?')">🗑️</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center;padding:40px;">Tidak ada transaksi ditemukan</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Transaction Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3 class="modal-title">Detail Transaksi</h3><button class="modal-close" onclick="closeModal('viewModal')">&times;</button></div>
        <div class="modal-body" id="viewModalBody">
            <!-- Content loaded via JS -->
        </div>
    </div>
</div>

<script>
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function viewTransaction(id) {
    fetch('?view=' + id)
    .then(r => r.text())
    .then(html => {
        // Extract modal body content from response
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const modalBody = doc.querySelector('#viewModalBody');
        if (modalBody) {
            document.getElementById('viewModalBody').innerHTML = modalBody.innerHTML;
            document.getElementById('viewModal').classList.add('show');
        }
    });
}
window.onclick = function(e) { if (e.target.classList.contains('modal')) e.target.classList.remove('show'); }
</script>

<?php if ($transaction_details): ?>
<!-- Hidden template for modal content -->
<div id="viewModalBody" style="display:none;">
    <div class="order-info">
        <p><strong>No. Order:</strong> <?php echo htmlspecialchars($transaction_details['order_number']); ?></p>
        <p><strong>Tanggal:</strong> <?php echo date('d/m/Y H:i', strtotime($transaction_details['created_at'])); ?></p>
        <p><strong>Pelanggan:</strong> <?php echo htmlspecialchars($transaction_details['full_name']); ?> (<?php echo htmlspecialchars($transaction_details['username']); ?>)</p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($transaction_details['email']); ?></p>
        <p><strong>Phone:</strong> <?php echo htmlspecialchars($transaction_details['phone'] ?? '-'); ?></p>
        <p><strong>Alamat:</strong> <?php echo htmlspecialchars($transaction_details['shipping_address']); ?></p>
        <p><strong>Metode Bayar:</strong> <?php echo ucfirst($transaction_details['payment_method']); ?></p>
        <p><strong>Total:</strong> <span class="price">Rp <?php echo number_format($transaction_details['total_amount'], 0, ',', '.'); ?></span></p>
    </div>
    
    <h4 style="margin:20px 0 10px;font-family:'Inter',sans-serif;font-weight:700;">Item Pesanan:</h4>
    <table class="items-table">
        <thead><tr><th>Produk</th><th>Harga</th><th>Qty</th><th>Subtotal</th></tr></thead>
        <tbody>
            <?php foreach($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td>Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td>Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if($_SESSION['role'] === 'admin'): ?>
    <form method="POST" style="margin-top:20px;border-top:1px solid #ddd;padding-top:20px;">
        <input type="hidden" name="transaction_id" value="<?php echo $transaction_details['id']; ?>">
        <div class="form-group">
            <label>Status Order</label>
            <select name="order_status">
                <option value="pending" <?php echo $transaction_details['order_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="processing" <?php echo $transaction_details['order_status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                <option value="shipped" <?php echo $transaction_details['order_status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                <option value="completed" <?php echo $transaction_details['order_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo $transaction_details['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
        <div class="form-group">
            <label>Status Pembayaran</label>
            <select name="payment_status">
                <option value="berhasil" <?php echo $transaction_details['payment_status'] === 'berhasil' ? 'selected' : ''; ?>>Pending</option>
                <option value="paid" <?php echo $transaction_details['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="cancelled" <?php echo $transaction_details['payment_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
        <div class="form-group">
            <label>Catatan</label>
            <textarea name="notes"><?php echo htmlspecialchars($transaction_details['notes'] ?? ''); ?></textarea>
        </div>
        <button type="submit" name="update_status" class="btn btn-success">Update Status</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>
</body>
</html>