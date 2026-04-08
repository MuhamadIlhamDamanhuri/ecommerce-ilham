<?php
// kelola_user.php - VIEW ONLY untuk data user (bukan admin/petugas)
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Hanya admin yang bisa akses halaman ini
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: Auth/login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
require_once '../config/database.php';

// Search & Filter - READ ONLY
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT id, username, email, full_name, phone, address, status, created_at FROM users WHERE role = 'user' AND 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (username LIKE :search OR email LIKE :search OR full_name LIKE :search)";
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
    $users = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $total_users = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user' AND status = 'active'");
    $active_users = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $users = [];
    $total_users = 0;
    $active_users = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Kelola Data User - Admin</title>
    <link rel="stylesheet" href="../css/shared.css" />
    
</head>
<body data-role="admin">
<div class="main-container">
    <div class="sidebar">
        <div class="sidebar-header"><span class="admin-panel-title">Admin panel</span><span class="dashboard-title">Dashboard</span></div>
        <div class="menu-divider"><span class="menu-label">Menu</span></div>
        <nav class="sidebar-nav">
            <a href="dashboard_admin.php"><span class="nav-icon">📊</span> Dashboard</a>
            <a href="kelola_user.php" class="<?php echo $current_page == 'kelola_user.php' ? 'active' : ''; ?>"><span class="nav-icon">👥</span> Kelola data user</a>
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
        <div class="top-bar"><h1>Kelola Data User</h1></div>
        <div class="content-area">
            <div class="alert alert-info">
                ℹ️ Halaman ini hanya untuk <strong>melihat informasi akun user</strong>. 
                Untuk mengedit atau menghapus user, gunakan halaman Kelola Data Petugas atau akses database langsung.
            </div>
            
            <div class="page-header">
                <h2 class="page-title">Daftar User</h2>
            </div>
            
            <div class="stats-summary">
                <div class="stat-box"><div class="stat-number"><?php echo number_format($total_users); ?></div><div class="stat-label">Total User</div></div>
                <div class="stat-box active"><div class="stat-number"><?php echo number_format($active_users); ?></div><div class="stat-label">Aktif</div></div>
                <div class="stat-box inactive"><div class="stat-number"><?php echo number_format($total_users - $active_users); ?></div><div class="stat-label">Nonaktif</div></div>
            </div>
            
            <div class="filter-section">
                <form method="GET" action="" class="filter-form">
                    <div class="filter-group"><label for="search">Cari User</label><input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Username, Email, atau Nama..."></div>
                    <div class="filter-group"><label for="status">Status</label><select id="status" name="status"><option value="">Semua</option><option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Aktif</option><option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Nonaktif</option></select></div>
                    <div class="filter-group" style="min-width:auto;"><button type="submit" class="btn btn-primary">Filter</button><a href="kelola_user.php" class="btn btn-warning" style="margin-left:10px;">Reset</a></div>
                </form>
            </div>
            
            <div class="table-container">
                <table>
                    <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Nama Lengkap</th><th>Telepon</th><th>Alamat</th><th>Status</th><th>Bergabung</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php if(count($users) > 0): ?>
                            <?php foreach($users as $u): ?>
                            <tr>
                                <td><?php echo $u['id']; ?></td>
                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($u['phone'] ?? '-'); ?></td>
                                <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($u['address'] ?? '-'); ?></td>
                                <td><span class="badge badge-<?php echo $u['status']; ?>"><?php echo ucfirst($u['status']); ?></span></td>
                                <td><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                                <td><button class="view-btn" onclick="viewUser(<?php echo htmlspecialchars(json_encode($u)); ?>)">👁️ Detail</button></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" style="text-align:center;padding:40px;">Tidak ada user ditemukan</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3 class="modal-title">Detail User</h3><button class="modal-close" onclick="closeModal('viewModal')">&times;</button></div>
        <div class="modal-body">
            <div class="info-row"><label>ID:</label><span id="v_id"></span></div>
            <div class="info-row"><label>Username:</label><span id="v_username"></span></div>
            <div class="info-row"><label>Email:</label><span id="v_email"></span></div>
            <div class="info-row"><label>Nama Lengkap:</label><span id="v_full_name"></span></div>
            <div class="info-row"><label>Telepon:</label><span id="v_phone"></span></div>
            <div class="info-row"><label>Alamat:</label><span id="v_address"></span></div>
            <div class="info-row"><label>Status:</label><span id="v_status"></span></div>
            <div class="info-row"><label>Bergabung:</label><span id="v_created"></span></div>
        </div>
    </div>
</div>

<script>
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function viewUser(user) {
    document.getElementById('v_id').textContent = user.id;
    document.getElementById('v_username').textContent = user.username;
    document.getElementById('v_email').textContent = user.email;
    document.getElementById('v_full_name').textContent = user.full_name;
    document.getElementById('v_phone').textContent = user.phone || '-';
    document.getElementById('v_address').textContent = user.address || '-';
    document.getElementById('v_status').innerHTML = '<span class="badge badge-'+user.status+'">'+user.status.toUpperCase()+'</span>';
    document.getElementById('v_created').textContent = new Date(user.created_at).toLocaleDateString('id-ID');
    document.getElementById('viewModal').classList.add('show');
}
window.onclick = function(e) { if (e.target.classList.contains('modal')) e.target.classList.remove('show'); }
</script>
</body>
</html>