<?php
// Petugas/restore_backup_petugas.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['role'] !== 'petugas') {
    header('Location: ../Auth/login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
require_once '../config/database.php';

$message = '';
$message_type = '';

// Handle Backup Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
    try {
        $pdo = getDBConnection();
        
        $tables = [];
        $result = $pdo->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        $output = "-- Database: ecommerce_ilham_enh\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $table) {
            $result = $pdo->query("SHOW CREATE TABLE `$table`");
            $row = $result->fetch(PDO::FETCH_NUM);
            $output .= $row[1] . ";\n\n";
            
            $result = $pdo->query("SELECT * FROM `$table`");
            if ($result->rowCount() > 0) {
                while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    $keys = array_keys($row);
                    $values = array_map(function($v) use ($pdo) {
                        if ($v === null) return 'NULL';
                        return "'" . $pdo->quote($v) . "'";
                    }, array_values($row));
                    $output .= "INSERT INTO `$table` (`" . implode('`, `', $keys) . "`) VALUES (" . implode(', ', $values) . ");\n";
                }
                $output .= "\n";
            }
        }
        
        $backup_dir = __DIR__ . '/../backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $filename = 'backup_' . date('Ymd_His') . '.sql';
        file_put_contents($backup_dir . $filename, $output);
        
        $stmt = $pdo->prepare("INSERT INTO backup_logs (backup_file, backup_type, created_by) VALUES (:file, :type, :user_id)");
        $stmt->execute(['file' => $filename, 'type' => 'full', 'user_id' => $_SESSION['user_id']]);
        
        $message = 'Backup berhasil dibuat: ' . $filename;
        $message_type = 'success';
        
    } catch (PDOException $e) {
        $message = 'Error backup: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle Restore Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Silakan pilih file SQL untuk direstore';
        $message_type = 'error';
    } else {
        try {
            $pdo = getDBConnection();
            $file_content = file_get_contents($_FILES['sql_file']['tmp_name']);
            
            $statements = array_filter(array_map('trim', explode(';', $file_content)));
            
            $pdo->beginTransaction();
            
            foreach ($statements as $stmt) {
                if (!empty($stmt) && !preg_match('/^--/', $stmt)) {
                    $pdo->exec($stmt);
                }
            }
            
            $pdo->commit();
            
            $message = 'Restore berhasil dilakukan!';
            $message_type = 'success';
            
        } catch (PDOException $e) {
            if (isset($pdo)) {
                $pdo->rollBack();
            }
            $message = 'Error restore: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get backup logs
$backup_logs = [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT bl.*, u.username as created_by_name FROM backup_logs bl LEFT JOIN users u ON bl.created_by = u.id ORDER BY bl.created_at DESC LIMIT 20");
    $stmt->execute();
    $backup_logs = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignore if error
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Restore & Backup - Petugas</title>
    <link rel="stylesheet" href="../css/shared.css" />
    
</head>
<body data-role="petugas">
<div class="main-container">
        <div class="sidebar">
        <div class="sidebar-header">
            <span class="admin-panel-title">Panel Petugas</span>
            <span class="dashboard-title">Dashboard Utama</span>
        </div>
        <div class="menu-divider">
            <span class="menu-label">Menu Petugas</span>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard_petugas.php" class="<?php echo ($current_page == 'dashboard_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">📊</span> <span class="nav-text">Dashboard</span>
            </a>
            <a href="kelola_produk_petugas.php" class="<?php echo ($current_page == 'kelola_produk_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">📦</span> <span class="nav-text">Kelola Produk</span>
            </a>
            <a href="kelola_kategori_petugas.php" class="<?php echo ($current_page == 'kelola_kategori_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">🎨</span> <span class="nav-text">Kelola Kategori</span>
            </a>
            <a href="kelola_transaksi_petugas.php" class="<?php echo ($current_page == 'kelola_transaksi_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">💳</span> <span class="nav-text">Kelola Transaksi</span>
            </a>
            <a href="laporan_petugas.php" class="<?php echo ($current_page == 'laporan_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">📈</span> <span class="nav-text">Laporan</span>
            </a>
            <a href="restore_backup_petugas.php" class="<?php echo ($current_page == 'restore_backup_petugas.php') ? 'active' : ''; ?>">
                <span class="nav-icon">💾</span> <span class="nav-text">Backup & Restore</span>
            </a>
        </nav>
        <a href="../Auth/logout.php" class="logout-button">
            <span class="logout-icon">←</span>
            <span class="logout-text">Keluar</span>
        </a>
    </div>
    <div class="main-content">
        <div class="top-bar"><h1>Restore & Backup</h1></div>
        <div class="content-area">
            <h2 class="page-title">Restore & Backup Data</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <div class="cards-grid">
                <div class="card">
                    <h3>💾 Backup Database</h3>
                    <p>Buat cadangan lengkap database ecommerce_ilham_enh. File backup akan disimpan dalam format SQL dan dapat diunduh kapan saja.</p>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="backup">
                        <button type="submit" class="btn btn-primary">Buat Backup Sekarang</button>
                    </form>
                </div>
                
                <div class="card">
                    <h3>🔄 Restore Database</h3>
                    <p>Pulihkan database dari file backup SQL. <strong>Peringatan:</strong> Operasi ini akan menimpa data yang ada!</p>
                    <div class="warning-box">⚠️ Pastikan Anda telah membuat backup sebelum melakukan restore.</div>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="restore">
                        <div class="form-group">
                            <label for="sql_file">Pilih File SQL:</label>
                            <input type="file" id="sql_file" name="sql_file" accept=".sql" required>
                        </div>
                        <button type="submit" class="btn btn-success" onclick="return confirm('Apakah Anda yakin ingin melakukan restore? Data yang ada akan ditimpa!')">Restore Database</button>
                    </form>
                </div>
            </div>
            
            <h3 style="font-family: 'Inter', var(--default-font-family); font-size: 24px; font-weight: 900; margin: 40px 0 20px; color: var(--text-dark);">Riwayat Backup</h3>
            
            <?php if (count($backup_logs) > 0): ?>
                <table class="logs-table">
                    <thead><tr><th>No</th><th>File Backup</th><th>Tipe</th><th>Dibuat Oleh</th><th>Tanggal</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach ($backup_logs as $index => $log): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($log['backup_file']); ?></td>
                            <td><?php echo ucfirst($log['backup_type']); ?></td>
                            <td><?php echo htmlspecialchars($log['created_by_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                            <td><a href="../backups/<?php echo urlencode($log['backup_file']); ?>" class="download-link" download>⬇️ Download</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="font-family: 'Inter', var(--default-font-family); font-size: 14px; color: #666;">Belum ada riwayat backup.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('form[action*="restore"]').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!confirm('⚠️ PERINGATAN!\n\nOperasi restore akan menimpa seluruh data database yang ada.\n\nApakah Anda yakin ingin melanjutkan?')) {
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>