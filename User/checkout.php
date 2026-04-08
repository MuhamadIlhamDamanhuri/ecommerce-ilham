<?php
// user/checkout.php - Checkout processing
require_once '../config/database.php';
require_once '../helpers/cart.php';

$user_id = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$message = '';
$message_type = '';

// Process checkout only via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['cart'])) {
    $payment_method = $_POST['payment_method'] ?? 'transfer';
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($shipping_address)) {
        $message = '❌ Alamat pengiriman wajib diisi';
        $message_type = 'error';
    } else {
        $result = processCheckout($user_id, $payment_method, $shipping_address, $notes);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
        
        if ($result['success'] && $result['order_number']) {
            $message .= '<br><strong>No. Order: ' . $result['order_number'] . '</strong>';
        }
    }
} else {
    // If accessed directly without POST, redirect to cart
    header('Location: ?page=cart');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Checkout - E-Commerce Ilham</title>
    <link rel="stylesheet" href="../css/shared.css" />
    
</head>
<body data-role="user">
<div class="main-container">
    <div class="sidebar">
        <div class="sidebar-header"><span class="admin-panel-title">Selamat Datang</span><span class="dashboard-title">User Panel</span></div>
        <div class="menu-divider"><span class="menu-label">Menu</span></div>
        <nav class="sidebar-nav">
            <a href="?page=products"><span class="nav-icon">🛍️</span> Produk</a>
            <a href="?page=cart"><span class="nav-icon">🛒</span> Keranjang</a>
            <a href="?page=history"><span class="nav-icon">📋</span> Riwayat Transaksi</a>
        </nav>
        <a href="../Auth/logout.php" class="logout-button"><span class="logout-icon">←</span><span class="logout-text">Keluar</span></a>
    </div>
    
    <div class="main-content">
        <div class="top-bar"><h1>Checkout</h1></div>
        <div class="content-area">
            <div class="checkout-result">
                <div class="checkout-icon <?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                    <?php echo $message_type === 'success' ? '✅' : '❌'; ?>
                </div>
                <h3 class="checkout-title"><?php echo $message_type === 'success' ? 'Pesanan Berhasil!' : 'Checkout Gagal'; ?></h3>
                <div class="checkout-message"><?php echo $message; ?></div>
                <?php if ($message_type === 'success'): ?>
                    <a href="?page=history" class="btn btn-primary">📋 Lihat Riwayat</a>
                    <a href="?page=products" class="btn btn-success" style="margin-left:10px;">🛍️ Lanjut Belanja</a>
                <?php else: ?>
                    <a href="javascript:history.back()" class="btn btn-primary">⬅️ Kembali</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>