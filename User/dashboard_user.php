<?php
// user/dashboard_user.php - User Frontend Complete (FIXED)
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is role 'user'
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['role'] !== 'user') {
    header('Location: ../Auth/login.php');
    exit;
}

$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;
$user_email = $_SESSION['email'] ?? '';

// Initialize cart in session if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = '';
$message_type = '';
$active_section = $_GET['section'] ?? 'products';

// ==========================================
// HANDLE ADD TO CART (POST only)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($product_id > 0 && $quantity > 0) {
        try {
            require_once '../config/database.php';
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, name, price, stock, image FROM products WHERE id = :id AND status = 'active'");
            $stmt->execute(['id' => $product_id]);
            $product = $stmt->fetch();
            
            if ($product) {
                if ($quantity > $product['stock']) {
                    $message = '❌ Stok tidak mencukupi. Tersedia: ' . $product['stock'];
                    $message_type = 'error';
                } else {
                    // Check if product already in cart
                    $found = false;
                    foreach ($_SESSION['cart'] as &$item) {
                        if ($item['product_id'] == $product_id) {
                            $new_qty = $item['quantity'] + $quantity;
                            if ($new_qty > $product['stock']) {
                                $message = '❌ Stok tidak mencukupi untuk quantity tersebut';
                                $message_type = 'error';
                            } else {
                                $item['quantity'] = $new_qty;
                                $message = '✅ Quantity updated: ' . $product['name'];
                                $message_type = 'success';
                            }
                            $found = true;
                            break;
                        }
                    }
                    if (!$found && empty($message)) {
                        $_SESSION['cart'][] = [
                            'product_id' => $product['id'],
                            'name' => $product['name'],
                            'price' => $product['price'],
                            'quantity' => $quantity,
                            'image' => $product['image'],
                            'stock' => $product['stock']
                        ];
                        $message = '✅ ' . $product['name'] . ' ditambahkan ke keranjang';
                        $message_type = 'success';
                    }
                }
            } else {
                $message = '❌ Produk tidak ditemukan';
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ==========================================
// HANDLE UPDATE CART QUANTITY (POST only)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($product_id > 0) {
        foreach ($_SESSION['cart'] as $key => &$item) {
            if ($item['product_id'] == $product_id) {
                if ($quantity <= 0) {
                    unset($_SESSION['cart'][$key]);
                    $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
                    $message = '✅ Produk dihapus dari keranjang';
                    $message_type = 'success';
                } else {
                    try {
                        require_once '../config/database.php';
                        $pdo = getDBConnection();
                        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = :id");
                        $stmt->execute(['id' => $product_id]);
                        $stock = $stmt->fetchColumn();
                        
                        if ($quantity > $stock) {
                            $message = '❌ Stok hanya tersedia: ' . $stock;
                            $message_type = 'error';
                        } else {
                            $item['quantity'] = $quantity;
                            $item['stock'] = $stock;
                            $message = '✅ Keranjang diupdate';
                            $message_type = 'success';
                        }
                    } catch (PDOException $e) {
                        $message = '❌ Error: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                }
                break;
            }
        }
    }
}

// ==========================================
// HANDLE REMOVE FROM CART (GET only - separate from POST)
// ==========================================
if (isset($_GET['remove_cart']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $product_id = intval($_GET['remove_cart']);
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['product_id'] == $product_id) {
            unset($_SESSION['cart'][$key]);
            $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
            $message = '✅ Produk dihapus dari keranjang';
            $message_type = 'success';
            break;
        }
    }
}

// ==========================================
// HANDLE CLEAR CART (GET only)
// ==========================================
if (isset($_GET['clear_cart']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['cart'] = [];
    $message = '✅ Keranjang dikosongkan';
    $message_type = 'success';
}

// ==========================================
// HANDLE CHECKOUT (POST only)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (empty($_SESSION['cart'])) {
        $message = '❌ Keranjang kosong';
        $message_type = 'error';
    } else {
        try {
            require_once '../config/database.php';
            $pdo = getDBConnection();
            $pdo->beginTransaction();
            
            // Generate unique order number
            $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Calculate total amount
            $total_amount = 0;
            foreach ($_SESSION['cart'] as $item) {
                $total_amount += $item['price'] * $item['quantity'];
            }
            
            // Get shipping address from user or form
            $shipping_address = trim($_POST['shipping_address'] ?? '');
            if (empty($shipping_address)) {
                $stmt = $pdo->prepare("SELECT address FROM users WHERE id = :id");
                $stmt->execute(['id' => $user_id]);
                $user_addr = $stmt->fetchColumn();
                $shipping_address = $user_addr ?? 'Alamat belum diisi';
            }
            
            // Insert transaction
            $stmt = $pdo->prepare("
                INSERT INTO transactions (
                    user_id, order_number, total_amount, payment_method,
                    payment_status, order_status, shipping_address, notes
                ) VALUES (
                    :user_id, :order_number, :total, :payment,
                    'pending', 'pending', :address, :notes
                )
            ");
            $stmt->execute([
                'user_id' => $user_id,
                'order_number' => $order_number,
                'total' => $total_amount,
                'payment' => $_POST['payment_method'] ?? 'transfer',
                'address' => $shipping_address,
                'notes' => trim($_POST['notes'] ?? '')
            ]);
            
            $transaction_id = $pdo->lastInsertId();
            
            // Insert transaction details & update stock
            foreach ($_SESSION['cart'] as $item) {
                $subtotal = $item['price'] * $item['quantity'];
                $stmt = $pdo->prepare("
                    INSERT INTO transaction_details (transaction_id, product_id, quantity, price, subtotal)
                    VALUES (:trans_id, :prod_id, :qty, :price, :subtotal)
                ");
                $stmt->execute([
                    'trans_id' => $transaction_id,
                    'prod_id' => $item['product_id'],
                    'qty' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $subtotal
                ]);
                
                // Update product stock
                $stmt = $pdo->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id");
                $stmt->execute(['qty' => $item['quantity'], 'id' => $item['product_id']]);
            }
            
            $pdo->commit();
            
            // Clear cart after successful checkout
            $_SESSION['cart'] = [];
            
            $message = '✅ Pesanan berhasil dibuat!<br><strong>No. Order: ' . $order_number . '</strong><br>Silakan lakukan pembayaran sesuai metode yang dipilih.';
            $message_type = 'success';
            $active_section = 'history';
            
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = '❌ Error checkout: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ==========================================
// HANDLE CANCEL ORDER (GET only)
// ==========================================
if (isset($_GET['cancel_order']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $order_id = intval($_GET['cancel_order']);
    try {
        require_once '../config/database.php';
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT order_status FROM transactions WHERE id = :id AND user_id = :uid");
        $stmt->execute(['id' => $order_id, 'uid' => $user_id]);
        $order = $stmt->fetch();
        
        if ($order && $order['order_status'] === 'pending') {
            $stmt = $pdo->prepare("UPDATE transactions SET order_status = 'cancelled', payment_status = 'cancelled', updated_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $order_id]);
            $message = '✅ Pesanan berhasil dibatalkan';
            $message_type = 'success';
        } else {
            $message = '❌ Pesanan tidak dapat dibatalkan (sudah diproses)';
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        $message = '❌ Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// ==========================================
// FETCH PRODUCTS FOR DISPLAY
// ==========================================
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

$query = "SELECT p.*, c.name as category_name FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.status = 'active'";
$params = [];

if (!empty($search)) {
    // ✅ FIX: Gunakan parameter berbeda untuk setiap LIKE
    $query .= " AND (p.name LIKE :search1 OR p.description LIKE :search2 OR c.name LIKE :search3)";
    $params['search1'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
    $params['search3'] = '%' . $search . '%';
}
if (!empty($category_filter)) {
    // ✅ FIX: Gunakan parameter berbeda untuk setiap =
    $query .= " AND (p.category = :cat1 OR c.name = :cat2)";
    $params['cat1'] = $category_filter;
    $params['cat2'] = $category_filter;
}

// Sorting
switch ($sort) {
    case 'price_low': $query .= " ORDER BY p.price ASC"; break;
    case 'price_high': $query .= " ORDER BY p.price DESC"; break;
    case 'name': $query .= " ORDER BY p.name ASC"; break;
    default: $query .= " ORDER BY p.created_at DESC";
}

try {
    require_once '../config/database.php';
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params); // ✅ Sekarang parameter match dengan query
    $products = $stmt->fetchAll();
    
    // Get categories for filter dropdown
    $stmt = $pdo->query("SELECT DISTINCT name FROM categories WHERE status = 'active' ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get transaction history for this user
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
    $products = [];
    $categories = [];
    $transactions = [];
    $message = '⚠️ Database error: ' . $e->getMessage();
    $message_type = 'error';
}

// Calculate cart total and item count
$cart_total = 0;
$cart_item_count = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_total += $item['price'] * $item['quantity'];
    $cart_item_count += $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard User - E-Commerce Ilham</title>
    <link rel="stylesheet" href="../css/shared.css" />
    
</head>
<body data-role="user">
<div class="main-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <span class="admin-panel-title">Selamat Datang</span>
            <span class="dashboard-title">User Panel</span>
        </div>
        <div class="menu-divider">
            <span class="menu-label">Menu</span>
        </div>
        <nav class="sidebar-nav">
            <a href="?section=products" class="<?php echo $active_section === 'products' ? 'active' : ''; ?>">
                <span class="nav-icon">🛍️</span> Produk
            </a>
            <a href="?section=cart" class="<?php echo $active_section === 'cart' ? 'active' : ''; ?>">
                <span class="nav-icon">🛒</span> Keranjang
                <?php if ($cart_item_count > 0): ?><span class="cart-badge"><?php echo $cart_item_count; ?></span><?php endif; ?>
            </a>
            <a href="?section=history" class="<?php echo $active_section === 'history' ? 'active' : ''; ?>">
                <span class="nav-icon">📋</span> Riwayat Transaksi
            </a>
        </nav>
        <a href="../Auth/logout.php" class="logout-button">
            <span class="logout-icon">←</span>
            <span class="logout-text">Keluar</span>
        </a>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar"><h1>Dashboard User</h1></div>
        <div class="content-area">
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
                <?php if ($message_type === 'success' && strpos($message, 'No. Order') !== false): ?>
                    <br><br>
                    <a href="?section=history" class="btn btn-primary btn-sm">Lihat Riwayat</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="welcome-section">
                <h2 class="welcome-text">Halo, <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span> 👋</h2>
            </div>
            
            <div class="section-tabs">
                <button class="section-tab <?php echo $active_section === 'products' ? 'active' : ''; ?>" onclick="showSection('products')">🛍️ Produk</button>
                <button class="section-tab <?php echo $active_section === 'cart' ? 'active' : ''; ?>" onclick="showSection('cart')">🛒 Keranjang (<?php echo count($_SESSION['cart']); ?>)</button>
                <button class="section-tab <?php echo $active_section === 'history' ? 'active' : ''; ?>" onclick="showSection('history')">📋 Riwayat</button>
            </div>
            
            <!-- ================= PRODUCTS SECTION ================= -->
            <div id="products" class="section-content <?php echo $active_section === 'products' ? 'active' : ''; ?>">
                <div class="filter-section">
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="section" value="products">
                        <div class="filter-group">
                            <label>Cari Produk</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nama produk...">
                        </div>
                        <div class="filter-group">
                            <label>Kategori</label>
                            <select name="category">
                                <option value="">Semua</option>
                                <?php foreach($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Urutkan</label>
                            <select name="sort">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                                <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Harga: Rendah ke Tinggi</option>
                                <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Harga: Tinggi ke Rendah</option>
                                <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nama: A-Z</option>
                            </select>
                        </div>
                        <div class="filter-group" style="min-width:auto; display:flex; gap:10px;">
                            <button type="submit" class="btn btn-primary">Cari</button>
                            <a href="?section=products" class="btn btn-warning">Reset</a>
                        </div>
                    </form>
                </div>
                
                <div class="products-grid">
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $p): ?>
                        <div class="product-card">
<div class="product-image">
    <?php 
    $image_path = '../uploads/products/' . ($p['image'] ?? '');
    $image_exists = !empty($p['image']) && file_exists($image_path);
    ?>
    <?php if ($image_exists): ?>
        <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>">
    <?php else: ?>
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:48px;">📦</div>
    <?php endif; ?>
</div>
                            <div class="product-info">
                                <div class="product-name"><?php echo htmlspecialchars($p['name']); ?></div>
                                <div class="product-category"><?php echo htmlspecialchars($p['category_name'] ?? $p['category'] ?? 'Umum'); ?></div>
                                <div class="product-price">Rp <?php echo number_format($p['price'], 0, ',', '.'); ?></div>
                                <div class="product-stock <?php echo $p['stock'] <= 5 ? 'low' : ''; ?>">
                                    Stok: <?php echo number_format($p['stock']); ?>
                                    <?php if ($p['stock'] <= 5): ?>⚠️ Menipis<?php endif; ?>
                                </div>
                                <form method="POST" class="product-actions">
                                    <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                    <input type="number" name="quantity" value="1" min="1" max="<?php echo $p['stock']; ?>">
                                    <button type="submit" name="add_to_cart" class="btn btn-primary btn-sm" <?php echo $p['stock'] <= 0 ? 'disabled' : ''; ?>>
                                        <?php echo $p['stock'] > 0 ? '+' : 'Habis'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="grid-column: 1/-1;">
                            <div class="empty-state-icon">🔍</div>
                            <p>Tidak ada produk ditemukan</p>
                            <a href="?section=products" class="btn btn-primary">Reset Filter</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ================= CART SECTION ================= -->
            <div id="cart" class="section-content <?php echo $active_section === 'cart' ? 'active' : ''; ?>">
                <div class="cart-section">
                    <div class="cart-header">
                        <h3 style="font-family:'Inter',sans-serif;font-weight:900;font-size:24px;">🛒 Keranjang Belanja</h3>
                        <?php if (!empty($_SESSION['cart'])): ?>
                        <a href="?clear_cart=1" class="btn btn-danger btn-sm" onclick="return confirm('Kosongkan semua item dari keranjang?')">🗑️ Kosongkan</a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($_SESSION['cart'])): ?>
                        <?php foreach ($_SESSION['cart'] as $item): ?>
                        <div class="cart-item">
<div class="cart-item-image">
    <?php 
    $cart_image_path = '../uploads/products/' . ($item['image'] ?? '');
    $cart_image_exists = !empty($item['image']) && file_exists($cart_image_path);
    ?>
    <?php if ($cart_image_exists): ?>
        <img src="<?php echo htmlspecialchars($cart_image_path); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
    <?php else: ?>
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:32px;">📦</div>
    <?php endif; ?>
</div>
                            <div class="cart-item-info">
                                <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="cart-item-price">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?> / pcs</div>
                                <form method="POST" class="cart-item-qty">
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="0" max="<?php echo $item['stock'] ?? 999; ?>" onchange="this.form.submit()">
                                    <button type="submit" name="update_cart" class="btn btn-xs btn-warning">Update</button>
                                    <a href="?remove_cart=<?php echo $item['product_id']; ?>" class="btn btn-xs btn-danger" onclick="return confirm('Hapus item ini?')">✕</a>
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
                            <form method="POST">
                                <div class="form-group">
                                    <label>Alamat Pengiriman *</label>
                                    <textarea name="shipping_address" rows="3" required placeholder="Masukkan alamat lengkap untuk pengiriman"><?php echo htmlspecialchars($_POST['shipping_address'] ?? ''); ?></textarea>
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
                                <button type="submit" name="checkout" class="btn btn-success" style="width:100%;font-size:16px;padding:15px;">
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
                            <a href="?section=products" class="btn btn-primary">🛍️ Mulai Belanja</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ================= HISTORY SECTION ================= -->
            <div id="history" class="section-content <?php echo $active_section === 'history' ? 'active' : ''; ?>">
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
                                    <a href="?cancel_order=<?php echo $t['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('Batalkan pesanan ini?')">❌ Batal</a>
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
                        <a href="?section=products" class="btn btn-primary">🛍️ Belanja Sekarang</a>
                    </div>
                <?php endif; ?>
            </div>
            
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
        <div class="modal-body" id="orderModalBody">
            <!-- Content loaded via JS -->
        </div>
    </div>
</div>

<script>
// Section Navigation
function showSection(sectionId) {
    const url = new URL(window.location);
    url.searchParams.set('section', sectionId);
    window.history.pushState({}, '', url);
    
    document.querySelectorAll('.section-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.section-tab').forEach(el => el.classList.remove('active'));
    
    document.getElementById(sectionId).classList.add('active');
    
    document.querySelectorAll('.section-tab').forEach(tab => {
        if (tab.getAttribute('onclick').includes(sectionId)) {
            tab.classList.add('active');
        }
    });
    
    return false;
}

// Modal Functions
function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
}
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

// View Order Detail
function viewOrder(order) {
    const statusLabels = {
        'pending': 'Menunggu Pembayaran',
        'processing': 'Sedang Diproses',
        'shipped': 'Sedang Dikirim',
        'completed': 'Selesai',
        'cancelled': 'Dibatalkan'
    };
    const paymentLabels = {
        'transfer': 'Transfer Bank',
        'cod': 'COD (Bayar di Tempat)',
        'e-wallet': 'E-Wallet'
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
    openModal('orderModal');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alert = document.querySelector('.alert');
    if (alert) {
        setTimeout(() => { 
            alert.style.transition = 'opacity 0.3s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    }
    
    // Initialize section from URL
    const urlParams = new URLSearchParams(window.location.search);
    const section = urlParams.get('section') || 'products';
    showSection(section);
});
</script>
</body>
</html>