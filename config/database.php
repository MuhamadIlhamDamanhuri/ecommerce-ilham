<?php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_ilham_enh');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function getDBConnection() {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        throw new PDOException('Connection failed: ' . $e->getMessage(), (int)$e->getCode());
    }
}

function checkDatabase() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT 1 FROM users LIMIT 1");
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false;
    }
}
?>