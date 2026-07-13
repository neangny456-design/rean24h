<?php
// database.php - Secure PDO Database Connection & Auto-installer

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'english24h';

try {
    // Try to connect directly to the database
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // If connection failed because database doesn't exist, let's run the auto-installer
    if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
        try {
            // Connect to MySQL server without DB name
            $dsn_no_db = "mysql:host=$db_host;charset=utf8mb4";
            $pdo_init = new PDO($dsn_no_db, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Read database.sql file
            $sql_file = __DIR__ . '/database.sql';
            if (file_exists($sql_file)) {
                $sql = file_get_contents($sql_file);
                
                // Execute SQL file queries (creates DB, tables, seeds default questions)
                $pdo_init->exec($sql);
                
                // Re-connect to the newly created database
                $pdo = new PDO($dsn, $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } else {
                throw new Exception("Initialization database.sql file not found.");
            }
        } catch (Exception $init_e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => "Database auto-installation failed: " . $init_e->getMessage()
            ]);
            exit;
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => "Database connection failed: " . $e->getMessage()
        ]);
        exit;
    }
}

// Auto-create settings table if not exists, and seed default values
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key_name VARCHAR(50) PRIMARY KEY,
        key_value VARCHAR(255)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    $pdo->exec("INSERT IGNORE INTO settings (key_name, key_value) VALUES 
        ('quiz_timer', '30'), 
        ('quiz_questions_count', '5');");
} catch (Exception $settings_e) {
    // Log error silently
}
?>
