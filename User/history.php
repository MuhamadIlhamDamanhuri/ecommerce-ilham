<?php
// user/history.php - Transaction history page
require_once '../config/database.php';
require_once '../helpers/cart.php';

$user_id = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$message = '';
$message_type = '';

// Handle cancel order
if (isset($_GET['cancel_order'])) {
    $order_id = intval($_GET['cancel_order']);
    $result = cancelOrder($order_id, $user_id);
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'error';
}

// Fetch transaction history
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT t.*, 
            (SELECT GROUP_CONCAT(CONCAT(p.name, ' x', td.quantity) SEPARATOR ', ') 
             FROM transaction_details td 
             JOIN products p ON td.product_id = p.id 
             WHERE td.transaction_id = t.id) as items_summary,
            (SELECT COUNT(*) FROM transaction_details WHERE transaction_id = t.id) as item_count
        FROM transactions t 
        WHERE t.user_id = :user_id 
        ORDER BY t.created_at DESC
    ");
    $stmt->execute(['user_id' => $user_id]);
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    $transactions = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Riwayat Transaksi - E-Commerce Ilham</title>
    <link rel="stylesheet" href="../css/shared.css" />
    
</head>
<body data-role="user">
<div class="main-container">
    <div class="sidebar">
        <div class="sidebar-header"><span class="admin-panel-title">Selamat Datang</span><span class="dashboard-title">User Panel</span></div>
        <div class="menu-divider"><span class="menu-label">Menu</span></div>
        <nav class="sidebar-nav">
            <a href="?page=products"><span class="nav-icon">🛍️</span> Produk</a>
            <a href="?page=cart"><span class="nav-icon">🛒</span> Keranjang<?php $cnt = getCartItemCount(); if($cnt>0): ?><span class="cart-badge"><?php echo $cnt; ?></span><?php endif; ?></a>
            <a href="?page=history" class="active"><span class="nav-icon">📋</span> Riwayat Transaksi</a>
        </nav>
        <a href="../Auth/logout.php" class="logout-button"><span class="logout-icon">←</span><span class="logout-text">Keluar</span></a>
    </div>
    
    <div class="main-content">
        <div class="top-bar"><h1>Riwayat Transaksi</h1></div>
        <div class="content-area">
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <div class="welcome-section">
                <h2 class="welcome-text">Halo, <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span> 📋</h2>
            </div>
            
            <div class="section-tabs">
                <a href="?page=products" class="section-tab">🛍️ Produk</a>
                <a href="?page=cart" class="section-tab">🛒 Keranjang</a>
                <a href="?page=history" class="section-tab active">📋 Riwayat</a>
            </div>
            
            <h3 style="margin-bottom:20px;font-family:'Inter',sans-serif;font-weight:900;font-size:24px;">📋 Riwayat Transaksi</h3>
            
            <?php if (!empty($transactions)): ?>
                <div style="overflow-x:auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>No. Order</th>
                            <th>Tanggal</th>
                            <th>Item</th>
                            <th>Total</th>
                            <th>Pembayaran</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><span class="order-number"><?php echo htmlspecialchars($t['order_number']); ?></span></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                            <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:13px;">
                                <?php echo htmlspecialchars($t['items_summary'] ?? '-'); ?>
                                <br><small style="color:#666;"><?php echo $t['item_count']; ?> item</small>
                            </td>
                            <td style="font-weight:700;color:var(--primary-color);">
                                Rp <?php echo number_format($t['total_amount'], 0, ',', '.'); ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $t['payment_status']; ?>">
                                    <?php echo ucfirst($t['payment_status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $t['order_status']; ?>">
                                    <?php 
                                    $status_labels = [
                                        'pending' => 'Menunggu',
                                        'processing' => 'Diproses',
                                        'shipped' => 'Dikirim',
                                        'completed' => 'Selesai',
                                        'cancelled' => 'Dibatalkan'
                                    ];
                                    echo $status_labels[$t['order_status']] ?? ucfirst($t['order_status']);
                                    ?>
                                </span>
                            </td>
                            <td class="order-actions">
                                <button class="btn btn-primary btn-xs" onclick="viewOrder(<?php echo htmlspecialchars(json_encode($t)); ?>)">👁️ Detail</button>
                                <?php if ($t['order_status'] === 'shipped'): ?>
                                <a href="?page=history&cancel_order=<?php echo $t['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('Batalkan pesanan ini?')">❌ Batal</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <p>Belum ada riwayat transaksi</p>
                    <a href="?page=products" class="btn btn-primary">🛍️ Belanja Sekarang</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Order Detail Modal -->
<div id="orderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-title">Detail Pesanan</span>
            <button class="modal-close" onclick="closeModal('orderModal')">&times;</button>
        </div>
        <div class="modal-body" id="orderModalBody"></div>
    </div>
</div>

<script>
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}
function viewOrder(order) {
    const statusLabels = {
        'pending': 'Menunggu Pembayaran',
        'processing': 'Sedang Diproses',
        'shipped': 'Sedang Dikirim',
        'completed': 'Selesai',
        'cancelled': 'Dibatalkan'
    };
    const paymentLabels = {
        'cod': 'COD (Bayar di Tempat)',
        'e-wallet': 'DANA-GOPAY (089697158042)'
    };
    const html = `
        <div class="order-detail-grid">
            <div class="order-detail-row"><span>No. Order</span><strong>${order.order_number}</strong></div>
            <div class="order-detail-row"><span>Tanggal</span><span>${new Date(order.created_at).toLocaleString('id-ID')}</span></div>
            <div class="order-detail-row"><span>Status Order</span><span class="badge badge-${order.order_status}">${statusLabels[order.order_status] || order.order_status}</span></div>
            <div class="order-detail-row"><span>Pembayaran</span><span class="badge badge-${order.payment_status}">${paymentLabels[order.payment_method] || order.payment_method}</span></div>
            <div class="order-detail-row"><span>Alamat</span><span>${order.shipping_address || '-'}</span></div>
            ${order.notes ? `<div class="order-detail-row"><span>Catatan</span><span>${order.notes}</span></div>` : ''}
            <div style="border-top:2px solid #333;margin:15px 0;padding-top:15px;">
                <strong>Item Pesanan:</strong>
                <div class="order-items-list">
                    ${order.items_summary ? order.items_summary.split(', ').map(item => `<div class="order-item"><span>${item}</span></div>`).join('') : '<em>-</em>'}
                </div>
            </div>
            <div class="order-detail-row" style="font-size:18px;"><span>Total</span><strong style="color:var(--primary-color);">Rp ${parseInt(order.total_amount).toLocaleString('id-ID')}</strong></div>
        </div>
    `;
    document.getElementById('orderModalBody').innerHTML = html;
    document.getElementById('orderModal').classList.add('show');
}
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}
</script>
</body>
</html>