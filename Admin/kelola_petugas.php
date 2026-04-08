<?php
// kelola_petugas.php - CRUD untuk data petugas
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Hanya admin yang bisa akses
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: Auth/login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
require_once '../config/database.php';

$error = '';
$success = '';

// Handle Add Petugas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_petugas'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Username, email, password, dan nama lengkap wajib diisi';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
            $stmt->execute(['username' => $username, 'email' => $email]);
            if ($stmt->fetch()) {
                $error = 'Username atau email sudah terdaftar';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, full_name, phone, role, status)
                    VALUES (:username, :email, :password, :full_name, :phone, 'petugas', :status)
                ");
                $stmt->execute([
                    'username' => $username, 'email' => $email, 'password' => $hashed,
                    'full_name' => $full_name, 'phone' => $phone, 'status' => $status
                ]);
                $success = 'Petugas berhasil ditambahkan';
            }
        } catch (PDOException $e) { $error = 'Error: ' . $e->getMessage(); }
    }
}

// Handle Update Petugas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_petugas'])) {
    $id = intval($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($email) || empty($full_name)) {
        $error = 'Username, email, dan nama lengkap wajib diisi';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id");
            $stmt->execute(['username' => $username, 'email' => $email, 'id' => $id]);
            if ($stmt->fetch()) {
                $error = 'Username atau email sudah digunakan';
            } else {
                if (!empty($password)) {
                    if (strlen($password) < 6) { $error = 'Password minimal 6 karakter'; }
                    else {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET username=:username, email=:email, full_name=:full_name, phone=:phone, status=:status, password=:password, updated_at=NOW() WHERE id=:id");
                        $stmt->execute(['username'=>$username,'email'=>$email,'full_name'=>$full_name,'phone'=>$phone,'status'=>$status,'password'=>$hashed,'id'=>$id]);
                        $success = 'Petugas berhasil diupdate';
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username=:username, email=:email, full_name=:full_name, phone=:phone, status=:status, updated_at=NOW() WHERE id=:id");
                    $stmt->execute(['username'=>$username,'email'=>$email,'full_name'=>$full_name,'phone'=>$phone,'status'=>$status,'id'=>$id]);
                    $success = 'Petugas berhasil diupdate';
                }
            }
        } catch (PDOException $e) { $error = 'Error: ' . $e->getMessage(); }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        if ($user && $user['role'] === 'admin') {
            $error = 'Tidak dapat menghapus admin';
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'petugas'");
            $stmt->execute(['id' => $id]);
            $success = 'Petugas berhasil dihapus';
        }
    } catch (PDOException $e) { $error = 'Error: ' . $e->getMessage(); }
}

// Search & Filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$query = "SELECT * FROM users WHERE role = 'petugas' AND 1=1";
$params = [];
if (!empty($search)) { $query .= " AND (username LIKE :search OR email LIKE :search OR full_name LIKE :search)"; $params['search'] = '%'.$search.'%'; }
if (!empty($status_filter)) { $query .= " AND status = :status"; $params['status'] = $status_filter; }
$query .= " ORDER BY created_at DESC";

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $petugas = $stmt->fetchAll();
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'petugas'");
    $total = $stmt->fetch()['total'];
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'petugas' AND status = 'active'");
    $active = $stmt->fetch()['total'];
} catch (PDOException $e) { $error = 'Error: '.$e->getMessage(); $petugas = []; $total = 0; $active = 0; }

$edit_user = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role = 'petugas'");
        $stmt->execute(['id' => $id]);
        $edit_user = $stmt->fetch();
    } catch (PDOException $e) { $error = 'Error: '.$e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Kelola Data Petugas - Admin</title>
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
            <a href="kelola_petugas.php" class="<?php echo $current_page == 'kelola_petugas.php' ? 'active' : ''; ?>"><span class="nav-icon">👨‍💼</span> Kelola data petugas</a>
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
        <div class="top-bar"><h1>Kelola Data Petugas</h1></div>
        <div class="content-area">
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            
            <div class="page-header">
                <h2 class="page-title">Kelola Data Petugas</h2>
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Tambah Petugas</button>
            </div>
            
            <div class="stats-summary">
                <div class="stat-box"><div class="stat-number"><?php echo number_format($total); ?></div><div class="stat-label">Total Petugas</div></div>
                <div class="stat-box active"><div class="stat-number"><?php echo number_format($active); ?></div><div class="stat-label">Aktif</div></div>
                <div class="stat-box inactive"><div class="stat-number"><?php echo number_format($total - $active); ?></div><div class="stat-label">Nonaktif</div></div>
            </div>
            
            <div class="filter-section">
                <form method="GET" action="" class="filter-form">
                    <div class="filter-group"><label for="search">Cari Petugas</label><input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Username, Email, atau Nama..."></div>
                    <div class="filter-group"><label for="status">Status</label><select id="status" name="status"><option value="">Semua</option><option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Aktif</option><option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Nonaktif</option></select></div>
                    <div class="filter-group" style="min-width:auto;"><button type="submit" class="btn btn-primary">Filter</button><a href="kelola_petugas.php" class="btn btn-warning" style="margin-left:10px;">Reset</a></div>
                </form>
            </div>
            
            <div class="table-container">
                <table>
                    <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Nama Lengkap</th><th>Telepon</th><th>Status</th><th>Bergabung</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php if(count($petugas) > 0): ?>
                            <?php foreach($petugas as $p): ?>
                            <tr>
                                <td><?php echo $p['id']; ?></td>
                                <td><?php echo htmlspecialchars($p['username']); ?></td>
                                <td><?php echo htmlspecialchars($p['email']); ?></td>
                                <td><?php echo htmlspecialchars($p['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($p['phone'] ?? '-'); ?></td>
                                <td><span class="badge badge-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                                <td><?php echo date('d/m/Y', strtotime($p['created_at'])); ?></td>
                                <td class="action-buttons">
                                    <button class="btn btn-warning btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($p)); ?>)">Edit</button>
                                    <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus petugas ini?')">Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center;padding:40px;">Tidak ada petugas ditemukan</td></tr>
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
        <div class="modal-header"><h3 class="modal-title">Tambah Petugas Baru</h3><button class="modal-close" onclick="closeModal('addModal')">&times;</button></div>
        <div class="modal-body">
            <form method="POST">
                <div class="form-group"><label>Username *</label><input type="text" name="username" required></div>
                <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Password *</label><input type="password" name="password" required minlength="6"></div>
                <div class="form-group"><label>Nama Lengkap *</label><input type="text" name="full_name" required></div>
                <div class="form-group"><label>No. Telepon</label><input type="text" name="phone"></div>
                <div class="form-group"><label>Status</label><select name="status"><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select></div>
                <button type="submit" name="add_petugas" class="btn btn-success">Tambah Petugas</button>
                <button type="button" class="btn btn-danger" onclick="closeModal('addModal')" style="margin-left:10px;">Batal</button>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3 class="modal-title">Edit Petugas</h3><button class="modal-close" onclick="closeModal('editModal')">&times;</button></div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="user_id" id="edit_id">
                <div class="form-group"><label>Username *</label><input type="text" name="username" id="edit_username" required></div>
                <div class="form-group"><label>Email *</label><input type="email" name="email" id="edit_email" required></div>
                <div class="form-group"><label>Password (kosongkan jika tidak diubah)</label><input type="password" name="password" minlength="6"></div>
                <div class="form-group"><label>Nama Lengkap *</label><input type="text" name="full_name" id="edit_full_name" required></div>
                <div class="form-group"><label>No. Telepon</label><input type="text" name="phone" id="edit_phone"></div>
                <div class="form-group"><label>Status</label><select name="status" id="edit_status"><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select></div>
                <button type="submit" name="update_petugas" class="btn btn-success">Update Petugas</button>
                <button type="button" class="btn btn-danger" onclick="closeModal('editModal')" style="margin-left:10px;">Batal</button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function openEditModal(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_status').value = user.status;
    openModal('editModal');
}
window.onclick = function(e) { if (e.target.classList.contains('modal')) e.target.classList.remove('show'); }
</script>
</body>
</html>