<?php
session_start();
require_once __DIR__ . '/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
} elseif (isset($_SESSION['admin_id'])) {
    header('Location: admin/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'សូមបំពេញឈ្មោះអ្នកប្រើប្រាស់ និងលេខសម្ងាត់។';
    } else {
        $pdo = getConnection();
        
        // Try student login
        $stmt = $pdo->prepare('SELECT id, full_name, password_hash FROM users WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            header('Location: dashboard.php');
            exit;
        }

        // Try admin login
        $adminStmt = $pdo->prepare('SELECT id, full_name, password_hash FROM admins WHERE username = ? OR email = ? LIMIT 1');
        $adminStmt->execute([$identifier, $identifier]);
        $admin = $adminStmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            header('Location: admin/index.php');
            exit;
        }

        $error = 'ឈ្មោះអ្នកប្រើប្រាស់ ឬលេខសម្ងាត់មិនត្រឹមត្រូវទេ។';
    }
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Maths KH | ចូលប្រើប្រាស់</title>
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
  <header class="app-header">
    <div class="container navbar">
      <a class="brand" href="index.php"><span>Maths KH</span></a>
      <div class="nav-links">
        <a href="index.php">ទំព័រដើម</a>
        <a href="register.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">ចុះឈ្មោះ</a>
      </div>
    </div>
  </header>

  <main class="auth-wrapper">
    <div class="auth-card">
      <div class="auth-header">
        <h1>ចូលប្រើប្រាស់</h1>
        <p>សូមបំពេញព័ត៌មានខាងក្រោមដើម្បីចូលគណនី</p>
      </div>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
          <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          <span><?php echo htmlspecialchars($error); ?></span>
        </div>
      <?php endif; ?>

      <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-success">
          <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <span><?php echo htmlspecialchars($_SESSION['flash']); ?></span>
        </div>
        <?php unset($_SESSION['flash']); ?>
      <?php endif; ?>

      <form action="login.php" method="post">
        <div class="form-group">
          <label for="username">ឈ្មោះអ្នកប្រើប្រាស់ ឬ អ៊ីមែល</label>
          <input type="text" id="username" name="username" class="form-control" placeholder="ឧ. somnang" required />
        </div>
        
        <div class="form-group">
          <label for="password">លេខសម្ងាត់</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required />
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">ចូលគណនី</button>
      </form>

      <div style="text-align: center; margin-top: 1.5rem;">
        <span class="kh-font" style="color: var(--text-muted);">មិនទាន់មានគណនី? </span><a href="register.php">ចុះឈ្មោះឥឡូវនេះ</a>
      </div>
    </div>
  </main>

  <footer class="app-footer">
    <div class="container">
      <p>&copy; 2026 Maths KH. រៀបចំឡើងសម្រាប់សិស្សថ្នាក់ទី៩ និងថ្នាក់ទី១២។</p>
    </div>
  </footer>
</body>
</html>
