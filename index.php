<?php
// index.php
session_start();
require_once 'config/database.php';

$search = ltrim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

try {
    $pdo = getDBConnection();
    
    // Get all categories for sidebar
    $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE status = 'active' AND category IS NOT NULL");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Build product query
    $query = "SELECT * FROM products WHERE status = 'active'";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (name LIKE :search OR description LIKE :search)";
        $params['search'] = "%$search%";
    }
    
    if (!empty($category_filter)) {
        $query .= " AND category = :category";
        $params['category'] = $category_filter;
    }

    // Add sorting
    switch($sort) {
        case 'price_low':
            $query .= " ORDER BY price ASC";
            break;
        case 'price_high':
            $query .= " ORDER BY price DESC";
            break;
        case 'newest':
        default:
            $query .= " ORDER BY created_at DESC";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

} catch (PDOException $e) {
    $products = [];
    $categories = [];
    $error = "Terjadi kesalahan: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Produk - E-Commerce Ilham</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --primary-light: rgba(59,130,246,0.08);
            --text-dark: #0f172a;
            --text-grey: #64748b;
            --bg-light: #f1f5f9;
            --white: #ffffff;
            --border: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --radius: 12px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:var(--bg-light); color:var(--text-dark); -webkit-font-smoothing:antialiased; }
        a { text-decoration:none; color:inherit; }
        ul { list-style:none; }

        /* HEADER */
        header { background:var(--white); box-shadow:0 1px 3px rgba(0,0,0,0.08); position:sticky; top:0; z-index:1000; border-bottom:1px solid var(--border); }
        .navbar { max-width:1200px; margin:0 auto; padding:14px 20px; display:flex; align-items:center; justify-content:space-between; gap:20px; }
        .logo { font-size:22px; font-weight:800; color:var(--primary); white-space:nowrap; cursor:pointer; letter-spacing:-0.02em; }
        .logo span { color:var(--text-dark); }
        .search-container { flex:1; max-width:560px; position:relative; }
        .search-container input { width:100%; padding:10px 42px 10px 16px; border:1.5px solid var(--border); border-radius:10px; font-size:14px; font-family:'Inter',sans-serif; transition:all 0.2s; background:var(--bg-light); color:var(--text-dark); }
        .search-container input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(59,130,246,0.12); background:var(--white); }
        .search-icon { position:absolute; right:14px; top:50%; transform:translateY(-50%); color:var(--text-grey); cursor:pointer; }
        .nav-actions { display:flex; align-items:center; gap:18px; }
        .nav-item { display:flex; flex-direction:column; align-items:center; font-size:11px; color:var(--text-dark); cursor:pointer; transition:color 0.2s; position:relative; font-weight:500; }
        .nav-item:hover { color:var(--primary); }
        .nav-item i { font-size:20px; margin-bottom:3px; }
        .cart-count { position:absolute; top:-6px; right:-8px; background:var(--primary); color:white; font-size:10px; font-weight:700; padding:2px 6px; border-radius:10px; border:2px solid var(--white); }

        /* HERO */
        .hero-banner { max-width:1200px; margin:20px auto; padding:0 20px; }
        .hero-banner > div, .hero-banner { background:linear-gradient(135deg,#3b82f6 0%,#6366f1 100%); border-radius:var(--radius); padding:40px; color:white; text-align:center; box-shadow:var(--shadow-md); }
        .hero-banner h1 { font-size:28px; margin-bottom:8px; font-weight:800; letter-spacing:-0.02em; }
        .hero-banner p { font-size:15px; opacity:0.9; font-weight:400; }

        /* MAIN LAYOUT */
        .container { max-width:1200px; margin:0 auto; padding:20px; display:grid; grid-template-columns:240px 1fr; gap:24px; }

        /* SIDEBAR FILTERS */
        .filter-sidebar { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px; position:sticky; top:80px; height:fit-content; box-shadow:var(--shadow-sm); }
        .filter-sidebar h3 { font-size:16px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border); font-weight:700; color:var(--text-dark); }
        .filter-group { margin-bottom:20px; }
        .filter-group h4 { font-size:13px; font-weight:600; margin-bottom:10px; color:var(--text-grey); text-transform:uppercase; letter-spacing:0.04em; }
        .filter-group ul li { margin-bottom:4px; }
        .filter-group ul li a { display:block; padding:8px 12px; border-radius:8px; font-size:13px; font-weight:500; transition:all 0.2s; color:var(--text-dark); }
        .filter-group ul li a:hover, .filter-group ul li a.active-filter { background:var(--primary-light); color:var(--primary); }
        .sort-select { width:100%; padding:9px 12px; border:1.5px solid var(--border); border-radius:8px; font-size:13px; font-family:'Inter',sans-serif; transition:border 0.2s; cursor:pointer; color:var(--text-dark); }
        .sort-select:focus { outline:none; border-color:var(--primary); }

        /* PRODUCTS */
        .product-area { }
        .product-count { font-size:14px; color:var(--text-grey); margin-bottom:16px; font-weight:500; }
        .product-count strong { color:var(--text-dark); }
        .product-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; }
        .product-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; transition:all 0.25s ease; display:flex; flex-direction:column; text-decoration:none; }
        .product-card:hover { transform:translateY(-4px); box-shadow:var(--shadow-md); border-color:var(--primary); }
        .card-image { width:100%; aspect-ratio:1/1; overflow:hidden; background:var(--bg-light); display:flex; align-items:center; justify-content:center; position:relative; }
        .card-image img { width:100%; height:100%; object-fit:cover; transition:transform 0.3s; }
        .product-card:hover .card-image img { transform:scale(1.05); }
        .no-image { font-size:48px; opacity:0.2; }
        .card-info { padding:14px; flex-grow:1; display:flex; flex-direction:column; }
        .card-name { font-size:13px; font-weight:500; margin-bottom:6px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; line-height:1.4; color:var(--text-dark); }
        .card-category { font-size:11px; color:var(--text-grey); margin-bottom:4px; font-weight:500; }
        .card-price { font-size:16px; font-weight:800; color:var(--primary); margin-top:auto; padding-top:8px; }
        .card-stock { font-size:11px; color:var(--text-grey); margin-top:4px; font-weight:500; }
        .card-stock.low { color:#ef4444; font-weight:600; }

        /* EMPTY */
        .no-products { text-align:center; padding:60px 20px; color:var(--text-grey); }
        .no-products i { font-size:56px; margin-bottom:16px; opacity:0.3; display:block; }
        .no-products p { font-size:15px; }

        /* FOOTER */
        footer { background:var(--white); border-top:1px solid var(--border); padding:32px 20px; text-align:center; color:var(--text-grey); font-size:13px; margin-top:40px; }
        footer a { color:var(--primary); font-weight:600; }

        /* RESPONSIVE */
        @media (max-width:768px) {
            .container { grid-template-columns:1fr; }
            .filter-sidebar { position:static; }
            .navbar { flex-wrap:wrap; }
            .search-container { order:3; flex-basis:100%; max-width:100%; }
            .product-grid { grid-template-columns:repeat(2,1fr); }
            .hero-banner h1 { font-size:22px; }
        }
        @media (max-width:480px) { .product-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>

    <!-- Header -->
    <header>
        <div class="navbar">
            <div class="logo" onclick="window.location.href='index.php'">Ilham<span>Store</span></div>
            
            <form class="search-container" method="GET" action="">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari barang di IlhamStore...">
                <button type="submit" style="background:none; border:none; cursor:pointer; position:absolute; right:0; top:0; height:100%; width:40px;">
                    <i class="fas fa-search search-icon"></i>
                </button>
            </form>

            <div class="nav-actions">
                <?php if(isset($_SESSION['user_logged_in'])): ?>
                    <a href="User/dashboard_user.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>Akun</span>
                    </a>
                    <a href="User/dashboard_user.php?section=cart" class="nav-item">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Keranjang</span>
                        <?php 
                            // Hitung item cart (simple count if session exists)
                            $cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
                            if($cart_count > 0) echo '<span class="cart-count">' . $cart_count . '</span>';
                        ?>
                    </a>
                <?php else: ?>
                    <a href="Auth/login.php" class="nav-item">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Masuk</span>
                    </a>
                    <a href="Auth/register.php" class="nav-item">
                        <i class="fas fa-user-plus"></i>
                        <span>Daftar</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Banner -->
    <div class="hero-banner">
        <div>
            <h1>Promo Spesial Hari Ini!</h1>
            <p>Diskon hingga 50% untuk produk elektronik dan fashion pilihan.</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        
        <!-- Sidebar Filter -->
        <aside class="filter-sidebar">
            <h3>Kategori</h3>
            <div class="filter-group">
                <ul>
                    <li>
                        <a href="?search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>" class="<?php echo empty($category_filter) ? 'active-filter' : ''; ?>">
                            Semua Kategori
                        </a>
                    </li>
                    <?php foreach($categories as $cat): ?>
                    <li>
                        <a href="?search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($cat); ?>&sort=<?php echo urlencode($sort); ?>" 
                           class="<?php echo $category_filter === $cat ? 'active-filter' : ''; ?>">
                            <?php echo htmlspecialchars($cat); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="filter-group">
                <h3>Urutkan</h3>
                <select class="sort-select" onchange="window.location.href=this.value">
                    <option value="?search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&sort=newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                    <option value="?search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&sort=price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Harga Terendah</option>
                    <option value="?search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&sort=price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Harga Tertinggi</option>
                </select>
            </div>
        </aside>

        <!-- Product Grid -->
        <main class="product-area">
            <div class="product-count">
                <?php echo $category_filter ? htmlspecialchars($category_filter) : 'Semua Produk'; ?> 
                - <strong><?php echo count($products); ?> barang ditemukan</strong>
            </div>

            <div class="product-grid">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $p): ?>
                        <a href="User/dashboard_user.php?section=products&search=<?php echo urlencode($p['name']); ?>" class="product-card">
                            <div class="card-image">
                                <?php 
                                $image_path = 'uploads/products/' . ($p['image'] ?? '');
                                if (!empty($p['image']) && file_exists($image_path)): ?>
                                    <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy">
                                <?php else: ?>
                                    <i class="fas fa-box no-image"></i>
                                <?php endif; ?>
                            </div>
                            <div class="card-info">
                                <div class="card-name" title="<?php echo htmlspecialchars($p['name']); ?>"><?php echo htmlspecialchars($p['name']); ?></div>
                                <div class="card-category"><?php echo htmlspecialchars($p['category'] ?? 'Kategori Umum'); ?></div>
                                <div class="card-price">Rp <?php echo number_format($p['price'], 0, ',', '.'); ?></div>
                                <?php if(isset($p['stock'])): ?>
                                <div class="card-stock <?php echo $p['stock'] <= 5 ? 'low' : ''; ?>">
                                    Stok: <?php echo $p['stock']; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-products" style="grid-column: 1/-1;">
                        <i class="fas fa-box-open"></i>
                        <p>Yah, barang yang kamu cari tidak ditemukan.</p>
                        <a href="?" style="color: var(--primary); font-weight: 600; margin-top: 10px; display:inline-block;">Reset Pencarian</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> <a href="index.php">IlhamStore</a>. All rights reserved.</p>
    </footer>

</body>
</html>