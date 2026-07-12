<?php
session_start();

$dbFile = __DIR__ . '/database.sqlite';

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

function ensureColumn(PDO $db, string $table, string $column, string $definition): void
{
    $stmt = $db->query("PRAGMA table_info($table)");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array($column, $columns, true)) {
        $db->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

function ensureDatabase(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS users (" .
        "id INTEGER PRIMARY KEY AUTOINCREMENT, " .
        "fullname TEXT NOT NULL, " .
        "username TEXT NOT NULL UNIQUE, " .
        "password_hash TEXT NOT NULL, " .
        "role TEXT NOT NULL DEFAULT 'user', " .
        "created_at DATETIME DEFAULT CURRENT_TIMESTAMP)"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS categories (" .
        "id INTEGER PRIMARY KEY AUTOINCREMENT, " .
        "name TEXT NOT NULL UNIQUE, " .
        "slug TEXT, " .
        "description TEXT, " .
        "created_at DATETIME DEFAULT CURRENT_TIMESTAMP)"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS records (" .
        "id INTEGER PRIMARY KEY AUTOINCREMENT, " .
        "title TEXT NOT NULL, " .
        "category_id INTEGER, " .
        "body TEXT, " .
        "status TEXT NOT NULL DEFAULT 'draft', " .
        "created_at DATETIME DEFAULT CURRENT_TIMESTAMP, " .
        "FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL)"
    );

    ensureColumn($db, 'users', 'role', "TEXT NOT NULL DEFAULT 'user'");
    ensureColumn($db, 'users', 'created_at', "DATETIME DEFAULT CURRENT_TIMESTAMP");
    ensureColumn($db, 'categories', 'slug', 'TEXT');
    ensureColumn($db, 'categories', 'description', 'TEXT');
    ensureColumn($db, 'categories', 'created_at', "DATETIME DEFAULT CURRENT_TIMESTAMP");
    ensureColumn($db, 'records', 'category_id', 'INTEGER');
    ensureColumn($db, 'records', 'body', 'TEXT');
    ensureColumn($db, 'records', 'status', "TEXT NOT NULL DEFAULT 'draft'");
    ensureColumn($db, 'records', 'created_at', "DATETIME DEFAULT CURRENT_TIMESTAMP");

    $fillMissingSlug = $db->prepare('SELECT id, name FROM categories WHERE COALESCE(slug, "") = ""');
    $fillMissingSlug->execute();
    while ($row = $fillMissingSlug->fetch()) {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $row['name']), '-'));
        $update = $db->prepare('UPDATE categories SET slug = ? WHERE id = ?');
        $update->execute([$slug, (int) $row['id']]);
    }

    $adminUsername = 'rean24hadmin';
    $adminPassword = 'Rean24h@2026';
    $adminHash = password_hash($adminPassword, PASSWORD_DEFAULT);
    $adminCheck = $db->prepare('SELECT id FROM users WHERE username = ?');
    $adminCheck->execute([$adminUsername]);
    if ($adminCheck->fetch()) {
        $adminUpdate = $db->prepare('UPDATE users SET fullname = ?, password_hash = ?, role = ? WHERE username = ?');
        $adminUpdate->execute(['Administrator', $adminHash, 'admin', $adminUsername]);
    } else {
        $adminInsert = $db->prepare('INSERT INTO users (fullname, username, password_hash, role) VALUES (?, ?, ?, ?)');
        $adminInsert->execute(['Administrator', $adminUsername, $adminHash, 'admin']);
    }

    $seed = $db->prepare('SELECT id FROM categories WHERE slug = ?');
    $seed->execute(['grade-9']);
    if (!$seed->fetch()) {
        $db->exec("INSERT INTO categories (name, slug, description) VALUES ('Grade 9', 'grade-9', 'Grade 9 materials')");
        $db->exec("INSERT INTO categories (name, slug, description) VALUES ('Grade 12', 'grade-12', 'Grade 12 materials')");
        $db->exec("INSERT INTO categories (name, slug, description) VALUES ('Old Papers', 'old-papers', 'Old exam papers')");
    }
}

ensureDatabase($db);

function requireUserSession(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: register.php');
        exit;
    }
}

function requireAdminSession(): void
{
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
        header('Location: admin_login.php');
        exit;
    }
}
