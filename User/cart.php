<?php
// user/cart.php - Cart management page
require_once '../config/database.php';
require_once '../helpers/cart.php';

$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$message = '';
$message_type = '';

// Handle cart actions via POST (prevents GET/POST conflict)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update quantity
    if (isset($_POST['update_cart'])) {
        $product_id = intval($_POST['product_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        $result = updateCartItem($product_id, $quantity);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
    }
    
    // Remove item
    if (isset($_POST['remove_cart'])) {
        $product_id = intval($_POST['product_id'] ?? 0);
        $result = removeFromCart($product_id);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
    }
}

// Handle clear cart via GET (safe operation)
if (isset($_GET['clear_cart'])) {
    $result = clearCart();
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'error';
}

$cart_total = getCartTotal();
$cart_item_count = getCartItemCount();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Keranjang - E-Commerce Ilham</title>
    <link rel="stylesheet" href="../css/shared.css" />
    
</head>
<body data-role="user">
<div class="main-container">
    <div class="sidebar">
        <div class="sidebar-header"><span class="admin-panel-title">Selamat Datang</span><span class="dashboard-title">User Panel</span></div>
        <div class="menu-divider"><span class="menu-label">Menu</span></div>
        <nav class="sidebar-nav">
            <a href="?page=products"><span class="nav-icon">🛍️</span> Produk</a>
            <a href="?page=cart" class="active"><span class="nav-icon">🛒</span> Keranjang<?php if($cart_item_count>0): ?><span class="cart-badge"><?php echo $cart_item_count; ?></span><?php endif; ?></a>
            <a href="?page=history"><span class="nav-icon">📋</span> Riwayat Transaksi</a>
        </nav>
        <a href="../Auth/logout.php" class="logout-button"><span class="logout-icon">←</span><span class="logout-text">Keluar</span></a>
    </div>
    
    <div class="main-content">
        <div class="top-bar"><h1>Keranjang Belanja</h1></div>
        <div class="content-area">
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <div class="welcome-section">
                <h2 class="welcome-text">Halo, <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span> 🛒</h2>
            </div>
            
            <div class="section-tabs">
                <a href="?page=products" class="section-tab">🛍️ Produk</a>
                <a href="?page=cart" class="section-tab active">🛒 Keranjang</a>
                <a href="?page=history" class="section-tab">📋 Riwayat</a>
            </div>
            
            <div class="cart-section">
                <div class="cart-header">
                    <h3 style="font-family:'Inter',sans-serif;font-weight:900;font-size:24px;">🛒 Keranjang Belanja</h3>
                    <?php if (!empty($_SESSION['cart'])): ?>
                    <a href="?page=cart&clear_cart=1" class="btn btn-danger btn-sm" onclick="return confirm('Kosongkan semua item dari keranjang?')">🗑️ Kosongkan</a>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($_SESSION['cart'])): ?>
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                    <div class="cart-item">
                        <div class="cart-item-image">
                            <?php if ($item['image'] && file_exists('../uploads/products/'.$item['image'])): ?>
                                <img src="../uploads/products/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <?php else: ?>
                                📦
                            <?php endif; ?>
                        </div>
                        <div class="cart-item-info">
                            <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="cart-item-price">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?> / pcs</div>
                            <form method="POST" class="cart-item-qty">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="0" max="<?php echo $item['stock'] ?? 999; ?>">
                                <button type="submit" name="update_cart" class="btn btn-xs btn-warning">Update</button>
                                <button type="submit" name="remove_cart" class="btn btn-xs btn-danger" formnovalidate>✕</button>
                            </form>
                        </div>
                        <div class="cart-item-total">
                            Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="cart-total-row">
                        <span class="total-label">Total Pembayaran:</span>
                        <span class="total-value">Rp <?php echo number_format($cart_total, 0, ',', '.'); ?></span>
                    </div>
                    
                    <!-- Checkout Form -->
                    <div class="checkout-form">
                        <h4 style="margin-bottom:15px;font-weight:700;">📦 Informasi Pengiriman & Pembayaran</h4>
                        <form method="POST" action="?page=checkout">
                            <div class="form-group">
                                <label>Alamat Pengiriman *</label>
                                <textarea name="shipping_address" rows="3" required placeholder="Masukkan alamat lengkap untuk pengiriman"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Metode Pembayaran *</label>
                                <select name="payment_method" required>
                                    <option value="">-- Pilih Metode --</option>
                                    <option value="transfer">🏦 Transfer Bank</option>
                                    <option value="cod">🚚 COD (Bayar di Tempat)</option>
                                    <option value="e-wallet">📱 E-Wallet (OVO/Gopay/DANA)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Catatan Pesanan (Opsional)</label>
                                <textarea name="notes" rows="2" placeholder="Contoh: Kirim setelah jam 5 sore, dll."></textarea>
                            </div>
                            <button type="submit" class="btn btn-success" style="width:100%;font-size:16px;padding:15px;">
                                🛒 Checkout - Rp <?php echo number_format($cart_total, 0, ',', '.'); ?>
                            </button>
                            <p style="text-align:center;font-size:12px;color:#666;margin-top:10px;">
                                ⚠️ Dengan checkout, Anda menyetujui syarat & ketentuan toko
                            </p>
                        </form>
                    </div>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🛒</div>
                        <p>Keranjang belanja Anda masih kosong</p>
                        <a href="?page=products" class="btn btn-primary">🛍️ Mulai Belanja</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>