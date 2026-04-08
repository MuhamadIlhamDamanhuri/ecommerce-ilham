<?php
// kelola_produk.php - CRUD Lengkap (Tambah, Ubah, Kelola Produk)
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

$error = '';
$success = '';
$upload_dir = '../uploads/products/';

// Create upload directory if not exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ==========================================
// HANDLE ADD PRODUCT
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Handle image upload
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $filename = uniqid('prod_') . '.' . $ext;
            $destination = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $image = $filename;
            } else {
                $error = 'Gagal mengupload gambar';
            }
        } else {
            $error = 'Format gambar tidak didukung (jpg, jpeg, png, gif, webp)';
        }
    }
    
    if (empty($name) || $price <= 0 || empty($category)) {
        $error = 'Nama produk, harga, dan kategori wajib diisi';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("
                INSERT INTO products (name, description, price, stock, image, category, status)
                VALUES (:name, :description, :price, :stock, :image, :category, :status)
            ");
            $stmt->execute([
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'stock' => $stock,
                'image' => $image,
                'category' => $category,
                'status' => $status
            ]);
            $success = '✅ Produk berhasil ditambahkan';
        } catch (PDOException $e) {
            $error = '❌ Error: ' . $e->getMessage();
        }
    }
}

// ==========================================
// HANDLE UPDATE PRODUCT
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $id = intval($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Handle image upload (optional)
    $image_update = '';
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $filename = uniqid('prod_') . '.' . $ext;
            $destination = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                // Delete old image if exists
                $stmt = $pdo->prepare("SELECT image FROM products WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $old = $stmt->fetch();
                if ($old && $old['image'] && file_exists($upload_dir . $old['image'])) {
                    unlink($upload_dir . $old['image']);
                }
                $image_update = ", image = :image";
                $image = $filename;
            }
        }
    }
    
    if (empty($name) || $price <= 0 || empty($category)) {
        $error = 'Nama produk, harga, dan kategori wajib diisi';
    } else {
        try {
            $pdo = getDBConnection();
            $sql = "UPDATE products SET name = :name, description = :description, price = :price, stock = :stock, category = :category, status = :status $image_update WHERE id = :id";
            $params = [
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'stock' => $stock,
                'category' => $category,
                'status' => $status,
                'id' => $id
            ];
            if (!empty($image_update)) {
                $params['image'] = $image;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $success = '✅ Produk berhasil diupdate';
        } catch (PDOException $e) {
            $error = '❌ Error: ' . $e->getMessage();
        }
    }
}

// ==========================================
// HANDLE DELETE PRODUCT
// ==========================================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $pdo = getDBConnection();
        // Get image before delete
        $stmt = $pdo->prepare("SELECT image FROM products WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();
        
        // Delete image file if exists
        if ($product && $product['image'] && file_exists($upload_dir . $product['image'])) {
            unlink($upload_dir . $product['image']);
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $success = '✅ Produk berhasil dihapus';
    } catch (PDOException $e) {
        $error = '❌ Error: ' . $e->getMessage();
    }
}

// ==========================================
// HANDLE SEARCH & FILTER
// ==========================================
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT * FROM products WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE :search OR description LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if (!empty($category_filter)) {
    $query .= " AND category = :category";
    $params['category'] = $category_filter;
}
if (!empty($status_filter)) {
    $query .= " AND status = :status";
    $params['status'] = $status_filter;
}
$query .= " ORDER BY created_at DESC";

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Get stats
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $total_products = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE status = 'active'");
    $active_products = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT SUM(stock) as total FROM products WHERE status = 'active'");
    $total_stock = $stmt->fetch()['total'] ?? 0;
    
    // Get unique categories
    $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error = '❌ Database error: ' . $e->getMessage();
    $products = [];
    $total_products = 0;
    $active_products = 0;
    $total_stock = 0;
    $categories = [];
}

// Get product for edit
$edit_product = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $edit_product = $stmt->fetch();
    } catch (PDOException $e) {
        $error = '❌ Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Kelola Data Produk - Admin</title>
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
            <a href="kelola_produk.php" class="<?php echo $current_page == 'kelola_produk.php' ? 'active' : ''; ?>"><span class="nav-icon">📦</span> Kelola data produk</a>
                        <a href="kelola_kategori.php" class="<?php echo ($current_page == 'kelola_produk.php') ? 'active' : ''; ?>">
                <span class="nav-icon">🎨</span>
                Kelola kategori
            </a>
            <a href="kelola_transaksi.php"><span class="nav-icon">💳</span> Kelola data transaksi</a>
            <a href="laporan.php"><span class="nav-icon">📈</span> Laporan</a>
            <a href="restore_backup.php"><span class="nav-icon">💾</span> Restore & Backup</a>
        </nav>
        <a href="../Auth/logout.php" class="logout-button">
            <span class="logout-icon">←</span>
            <span class="logout-text">Keluar</span>
        </a>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar"><h1>Kelola Data Produk</h1></div>
        <div class="content-area">
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            
            <div class="page-header">
                <h2 class="page-title">Kelola Data Produk</h2>
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Tambah Produk</button>
            </div>
            
            <div class="stats-summary">
                <div class="stat-box total">
                    <div class="stat-number"><?php echo number_format($total_products); ?></div>
                    <div class="stat-label">Total Produk</div>
                </div>
                <div class="stat-box active">
                    <div class="stat-number"><?php echo number_format($active_products); ?></div>
                    <div class="stat-label">Aktif</div>
                </div>
                <div class="stat-box stock">
                    <div class="stat-number"><?php echo number_format($total_stock); ?></div>
                    <div class="stat-label">Total Stok</div>
                </div>
            </div>
            
            <div class="filter-section">
                <form method="GET" action="" class="filter-form">
                    <div class="filter-group">
                        <label for="search">Cari Produk</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nama atau deskripsi...">
                    </div>
                    <div class="filter-group">
                        <label for="category">Kategori</label>
                        <select id="category" name="category">
                            <option value="">Semua</option>
                            <?php foreach($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">Semua</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>
                    <div class="filter-group" style="min-width:auto;">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="kelola_produk.php" class="btn btn-warning" style="margin-left:10px;">Reset</a>
                    </div>
                </form>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Gambar</th>
                            <th>Nama Produk</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($products) > 0): ?>
                            <?php foreach($products as $p): ?>
                            <tr>
                                <td>
                                    <?php if($p['image'] && file_exists($upload_dir.$p['image'])): ?>
                                        <img src="<?php echo $upload_dir.$p['image']; ?>" class="product-image" alt="<?php echo htmlspecialchars($p['name']); ?>">
                                    <?php else: ?>
                                        <span style="font-size:24px;">📦</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><?php echo htmlspecialchars($p['category']); ?></td>
                                <td class="price">Rp <?php echo number_format($p['price'], 0, ',', '.'); ?></td>
                                <td><?php echo number_format($p['stock']); ?></td>
                                <td><span class="badge badge-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                                <td><?php echo date('d/m/Y', strtotime($p['created_at'])); ?></td>
                                <td class="action-buttons">
                                    <button class="btn btn-warning btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($p)); ?>)">Ubah</button>
                                    <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus produk ini?')">Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center;padding:40px;">Tidak ada produk ditemukan</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Tambah Produk Baru</h3>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Nama Produk *</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="description"></textarea>
                </div>
                <div class="form-group">
                    <label>Harga (Rp) *</label>
                    <input type="number" name="price" min="0" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Stok *</label>
                    <input type="number" name="stock" min="0" required>
                </div>
                <div class="form-group">
                    <label>Kategori *</label>
                    <input type="text" name="category" list="catList" required>
                    <datalist id="catList">
                        <?php foreach($categories as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Gambar Produk</label>
                    <input type="file" name="image" accept="image/*" onchange="previewImage(this, 'addPreview')">
                    <img id="addPreview" class="image-preview">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>
                <button type="submit" name="add_product" class="btn btn-success">Tambah Produk</button>
                <button type="button" class="btn btn-danger" onclick="closeModal('addModal')" style="margin-left:10px;">Batal</button>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Ubah Produk</h3>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="product_id" id="edit_id">
                <div class="form-group">
                    <label>Nama Produk *</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="description" id="edit_description"></textarea>
                </div>
                <div class="form-group">
                    <label>Harga (Rp) *</label>
                    <input type="number" name="price" id="edit_price" min="0" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Stok *</label>
                    <input type="number" name="stock" id="edit_stock" min="0" required>
                </div>
                <div class="form-group">
                    <label>Kategori *</label>
                    <input type="text" name="category" id="edit_category" list="catList2" required>
                    <datalist id="catList2">
                        <?php foreach($categories as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Gambar Produk</label>
                    <input type="file" name="image" accept="image/*" onchange="previewImage(this, 'editPreview')">
                    <img id="editPreview" class="image-preview">
                    <div id="editCurrentImage" style="margin-top:5px;font-size:12px;color:#666;"></div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>
                <button type="submit" name="update_product" class="btn btn-success">Update Produk</button>
                <button type="button" class="btn btn-danger" onclick="closeModal('editModal')" style="margin-left:10px;">Batal</button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) { preview.src = e.target.result; preview.style.display = 'block'; }
        reader.readAsDataURL(input.files[0]);
    }
}
function openEditModal(product) {
    document.getElementById('edit_id').value = product.id;
    document.getElementById('edit_name').value = product.name;
    document.getElementById('edit_description').value = product.description || '';
    document.getElementById('edit_price').value = product.price;
    document.getElementById('edit_stock').value = product.stock;
    document.getElementById('edit_category').value = product.category || '';
    document.getElementById('edit_status').value = product.status;
    const imgPreview = document.getElementById('editPreview');
    const currentImg = document.getElementById('editCurrentImage');
    if (product.image) {
        currentImg.innerHTML = 'Gambar saat ini: <strong>' + product.image + '</strong>';
        imgPreview.src = '../uploads/products/' + product.image;
        imgPreview.style.display = 'block';
    } else {
        currentImg.innerHTML = '';
        imgPreview.style.display = 'none';
    }
    openModal('editModal');
}
window.onclick = function(e) { if (e.target.classList.contains('modal')) e.target.classList.remove('show'); }
</script>
</body>
</html>