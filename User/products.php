<?php
// user/products.php - Product listing page
require_once '../config/database.php';
require_once '../helpers/cart.php';

$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$message = '';
$message_type = '';

// Handle Add to Cart POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($product_id > 0 && $quantity > 0) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, name, price, stock, image FROM products WHERE id = :id AND status = 'active'");
            $stmt->execute(['id' => $product_id]);
            $product = $stmt->fetch();
            
            if ($product) {
                $result = addToCart($product_id, $quantity, $product);
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
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

// Fetch products with filters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

$query = "SELECT p.*, c.name as category_name FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.status = 'active'";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.name LIKE :search OR p.description LIKE :search OR c.name LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if (!empty($category_filter)) {
    $query .= " AND (p.category = :cat OR c.name = :cat)";
    $params['cat'] = $category_filter;
}

switch ($sort) {
    case 'price_low': $query .= " ORDER BY p.price ASC"; break;
    case 'price_high': $query .= " ORDER BY p.price DESC"; break;
    case 'name': $query .= " ORDER BY p.name ASC"; break;
    default: $query .= " ORDER BY p.created_at DESC";
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT DISTINCT name FROM categories WHERE status = 'active' ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $products = [];
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Produk - E-Commerce Ilham</title>
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
            <a href="?page=products" class="active"><span class="nav-icon">🛍️</span> Produk</a>
            <a href="?page=cart"><span class="nav-icon">🛒</span> Keranjang<?php $cnt = getCartItemCount(); if($cnt>0): ?><span class="cart-badge"><?php echo $cnt; ?></span><?php endif; ?></a>
            <a href="?page=history"><span class="nav-icon">📋</span> Riwayat Transaksi</a>
        </nav>
        <a href="../Auth/logout.php" class="logout-button"><span class="logout-icon">←</span><span class="logout-text">Keluar</span></a>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar"><h1>Produk</h1></div>
        <div class="content-area">
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <div class="welcome-section">
                <h2 class="welcome-text">Halo, <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span> 👋</h2>
            </div>
            
            <div class="section-tabs">
                <a href="?page=products" class="section-tab active">🛍️ Produk</a>
                <a href="?page=cart" class="section-tab">🛒 Keranjang</a>
                <a href="?page=history" class="section-tab">📋 Riwayat</a>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <input type="hidden" name="page" value="products">
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
                        <a href="?page=products" class="btn btn-warning">Reset</a>
                    </div>
                </form>
            </div>
            
            <!-- Products Grid -->
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
                        <a href="?page=products" class="btn btn-primary">Reset Filter</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>