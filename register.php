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
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $username === '' || $password === '' || $confirmPassword === '') {
        $error = 'សូមបំពេញព័ត៌មានចាំបាច់ទាំងអស់។';
    } elseif ($password !== $confirmPassword) {
        $error = 'លេខសម្ងាត់ និងការបញ្ជាក់លេខសម្ងាត់មិនត្រូវគ្នាទេ។';
    } elseif (strlen($password) < 6) {
        $error = 'លេខសម្ងាត់ត្រូវមានយ៉ាងហោចណាស់ ៦ តួអក្សរ។';
    } else {
        $pdo = getConnection();
        
        // Check if username exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'ឈ្មោះអ្នកប្រើប្រាស់នេះមានគណនីរួចហើយ។';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO users (full_name, username, password_hash) VALUES (?, ?, ?)');
            $insert->execute([$fullName, $username, $passwordHash]);

            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            $_SESSION['user_name'] = $fullName;
            $_SESSION['flash'] = 'គណនីត្រូវបានបង្កើតដោយជោគជ័យ! សូមស្វាគមន៍មកកាន់ប្រព័ន្ធ។';
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Maths KH | ចុះឈ្មោះ</title>
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
  <header class="app-header">
    <div class="container navbar">
      <a class="brand" href="index.php"><span>Maths KH</span></a>
      <div class="nav-links">
        <a href="index.php">ទំព័រដើម</a>
        <a href="login.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">ចូលគណនី</a>
      </div>
    </div>
  </header>

  <main class="auth-wrapper">
    <div class="auth-card" style="padding: 2.5rem 3rem;">
      <div class="auth-header" style="margin-bottom: 1.5rem;">
        <h1>ចុះឈ្មោះគណនី</h1>
        <p>បំពេញព័ត៌មានខាងក្រោមដើម្បីចុះឈ្មោះគណនី</p>
      </div>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
          <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          <span><?php echo htmlspecialchars($error); ?></span>
        </div>
      <?php endif; ?>

      <form action="register.php" method="post">
        <div class="form-group">
          <label for="full_name">ឈ្មោះពេញ</label>
          <input type="text" id="full_name" name="full_name" class="form-control" placeholder="ឧ. សុខ វាសនា" value="<?php echo htmlspecialchars($fullName ?? ''); ?>" required />
        </div>

        <div class="form-group">
          <label for="username">ឈ្មោះអ្នកប្រើប្រាស់ (ឡាតាំង)</label>
          <input type="text" id="username" name="username" class="form-control" placeholder="ឧ. veasna12" value="<?php echo htmlspecialchars($username ?? ''); ?>" required />
        </div>

        <div class="form-group">
          <label for="password">លេខសម្ងាត់</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="យ៉ាងហោចណាស់ ៦ ខ្ទង់" required />
        </div>

        <div class="form-group">
          <label for="confirm_password">បញ្ជាក់លេខសម្ងាត់</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="បញ្ចូលលេខសម្ងាត់ឡើងវិញ" required />
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">ចុះឈ្មោះ</button>
      </form>

      <div style="text-align: center; margin-top: 1.5rem;">
        <span class="kh-font" style="color: var(--text-muted);">មានគណនីរួចហើយ? </span><a href="login.php">ចូលគណនី</a>
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
