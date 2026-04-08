<?php
// Admin/kelola_kategori.php - CRUD Kategori
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: ../Auth/login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
require_once '../config/database.php';

$error = '';
$success = '';

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if (empty($name)) {
        $error = 'Nama kategori wajib diisi';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = :name");
            $stmt->execute(['name' => $name]);
            if ($stmt->fetch()) {
                $error = 'Kategori sudah ada';
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description, status) VALUES (:name, :description, :status)");
                $stmt->execute(['name' => $name, 'description' => $description, 'status' => $status]);
                $success = '✅ Kategori berhasil ditambahkan';
            }
        } catch (PDOException $e) {
            $error = '❌ Error: ' . $e->getMessage();
        }
    }
}

// Handle Update Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $id = intval($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if (empty($name)) {
        $error = 'Nama kategori wajib diisi';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = :name AND id != :id");
            $stmt->execute(['name' => $name, 'id' => $id]);
            if ($stmt->fetch()) {
                $error = 'Nama kategori sudah digunakan';
            } else {
                $stmt = $pdo->prepare("UPDATE categories SET name = :name, description = :description, status = :status, updated_at = NOW() WHERE id = :id");
                $stmt->execute(['name' => $name, 'description' => $description, 'status' => $status, 'id' => $id]);
                $success = '✅ Kategori berhasil diupdate';
            }
        } catch (PDOException $e) {
            $error = '❌ Error: ' . $e->getMessage();
        }
    }
}

// Handle Delete Category
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $pdo = getDBConnection();
        // Check if category has products
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = :id");
        $stmt->execute(['id' => $id]);
        if ($stmt->fetch()['count'] > 0) {
            $error = '❌ Tidak dapat menghapus kategori yang masih memiliki produk';
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $success = '✅ Kategori berhasil dihapus';
        }
    } catch (PDOException $e) {
        $error = '❌ Error: ' . $e->getMessage();
    }
}

// Search & Filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT * FROM categories WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE :search OR description LIKE :search)";
    $params['search'] = '%' . $search . '%';
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
    $categories = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories");
    $total = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories WHERE status = 'active'");
    $active = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = '❌ Database error: ' . $e->getMessage();
    $categories = [];
    $total = 0;
    $active = 0;
}

$edit_category = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $edit_category = $stmt->fetch();
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
    <title>Kelola Kategori - Admin</title>
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
            <a href="kelola_transaksi.php"><span class="nav-icon">💳</span> Kelola data transaksi</a>
            <a href="laporan.php"><span class="nav-icon">📈</span> Laporan</a>
            <a href="restore_backup.php"><span class="nav-icon">💾</span> Restore & Backup</a>
        </nav>
        <a href="../Auth/logout.php" class="logout-button"><span class="logout-icon">←</span><span class="logout-text">Keluar</span></a>
    </div>
    
    <div class="main-content">
        <div class="top-bar"><h1>Kelola Kategori</h1></div>
        <div class="content-area">
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            
            <div class="page-header">
                <h2 class="page-title">Kelola Kategori</h2>
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Tambah Kategori</button>
            </div>
            
            <div class="stats-summary">
                <div class="stat-box total"><div class="stat-number"><?php echo number_format($total); ?></div><div class="stat-label">Total Kategori</div></div>
                <div class="stat-box active"><div class="stat-number"><?php echo number_format($active); ?></div><div class="stat-label">Aktif</div></div>
            </div>
            
            <div class="filter-section">
                <form method="GET" action="" class="filter-form">
                    <div class="filter-group"><label for="search">Cari Kategori</label><input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nama atau deskripsi..."></div>
                    <div class="filter-group"><label for="status">Status</label><select id="status" name="status"><option value="">Semua</option><option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Aktif</option><option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Nonaktif</option></select></div>
                    <div class="filter-group" style="min-width:auto;"><button type="submit" class="btn btn-primary">Filter</button><a href="kelola_kategori.php" class="btn btn-warning" style="margin-left:10px;">Reset</a></div>
                </form>
            </div>
            
            <div class="table-container">
                <table>
                    <thead><tr><th>ID</th><th>Nama Kategori</th><th>Deskripsi</th><th>Jml Produk</th><th>Status</th><th>Dibuat</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php if(count($categories) > 0): ?>
                            <?php foreach($categories as $c): ?>
                            <?php
                            // Count products in this category
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = :id");
                            $stmt->execute(['id' => $c['id']]);
                            $product_count = $stmt->fetch()['count'];
                            ?>
                            <tr>
                                <td><?php echo $c['id']; ?></td>
                                <td><?php echo htmlspecialchars($c['name']); ?></td>
                                <td><?php echo htmlspecialchars($c['description'] ?? '-'); ?></td>
                                <td><?php echo number_format($product_count); ?></td>
                                <td><span class="badge badge-<?php echo $c['status']; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                                <td><?php echo date('d/m/Y', strtotime($c['created_at'])); ?></td>
                                <td class="action-buttons">
                                    <button class="btn btn-warning btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($c)); ?>)">Edit</button>
                                    <a href="?delete=<?php echo $c['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus kategori ini?')">Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center;padding:40px;">Tidak ada kategori ditemukan</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3 class="modal-title">Tambah Kategori Baru</h3><button class="modal-close" onclick="closeModal('addModal')">&times;</button></div>
        <div class="modal-body">
            <form method="POST">
                <div class="form-group"><label>Nama Kategori *</label><input type="text" name="name" required></div>
                <div class="form-group"><label>Deskripsi</label><textarea name="description"></textarea></div>
                <div class="form-group"><label>Status</label><select name="status"><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select></div>
                <button type="submit" name="add_category" class="btn btn-success">Tambah Kategori</button>
                <button type="button" class="btn btn-danger" onclick="closeModal('addModal')" style="margin-left:10px;">Batal</button>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3 class="modal-title">Edit Kategori</h3><button class="modal-close" onclick="closeModal('editModal')">&times;</button></div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="category_id" id="edit_id">
                <div class="form-group"><label>Nama Kategori *</label><input type="text" name="name" id="edit_name" required></div>
                <div class="form-group"><label>Deskripsi</label><textarea name="description" id="edit_description"></textarea></div>
                <div class="form-group"><label>Status</label><select name="status" id="edit_status"><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select></div>
                <button type="submit" name="update_category" class="btn btn-success">Update Kategori</button>
                <button type="button" class="btn btn-danger" onclick="closeModal('editModal')" style="margin-left:10px;">Batal</button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function openEditModal(cat) {
    document.getElementById('edit_id').value = cat.id;
    document.getElementById('edit_name').value = cat.name;
    document.getElementById('edit_description').value = cat.description || '';
    document.getElementById('edit_status').value = cat.status;
    openModal('editModal');
}
window.onclick = function(e) { if (e.target.classList.contains('modal')) e.target.classList.remove('show'); }
</script>
</body>
</html>