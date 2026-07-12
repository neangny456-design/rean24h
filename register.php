<?php
require_once __DIR__ . '/database.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim(strtolower($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullname === '' || $username === '' || $password === '' || $confirmPassword === '') {
        $message = 'សូមបំពេញព័ត៌មានទាំងអស់។';
    } elseif ($password !== $confirmPassword) {
        $message = 'ពាក្យសម្ងាតមិនត្រឹមត្រូវ។';
    } else {
        $check = $db->prepare('SELECT id FROM users WHERE username = ?');
        $check->execute([$username]);
        if ($check->fetch()) {
            $message = 'ឈ្មោះអ្នកប្រើនេះមានរួចហើយ។';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $db->prepare(
                'INSERT INTO users (fullname, username, password_hash, role) VALUES (?, ?, ?, ?)'
            );
            $insert->execute([$fullname, $username, $hash, 'user']);
            $userId = (int) $db->lastInsertId();
            $_SESSION['user_id'] = $userId;
            $_SESSION['fullname'] = $fullname;
            $_SESSION['role'] = 'user';
            header('Location: test.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="km">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>បង្កើតគណនី – Maths24h</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Moul&family=Kantumruy+Pro:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
      :root {
        --paper: #fbf7ef;
        --paper-line: #e4dcc8;
        --cream-card: #fffdf8;
        --ink: #1d2b53;
        --ink-soft: #4c5680;
        --gold: #c89b3c;
        --seal: #b23a2f;
      }
      body {
        margin: 0;
        min-height: 100vh;
        background: var(--paper);
        background-image: linear-gradient(var(--paper-line) 1px, transparent 1px), linear-gradient(90deg, var(--paper-line) 1px, transparent 1px);
        background-size: 28px 28px;
        color: var(--ink);
        font-family: "Kantumruy Pro", sans-serif;
      }
      .brand {
        font-family: "Moul", serif;
        color: var(--ink);
        letter-spacing: 0.4px;
      }
      .shell { min-height: 100vh; display: grid; place-items: center; padding: 2rem 1rem; }
      .card {
        width: min(560px, 100%);
        border: 1px solid var(--paper-line);
        border-radius: 24px;
        background: var(--cream-card);
        box-shadow: 0 14px 40px rgba(29, 43, 83, 0.14);
        padding: 2rem;
      }
      .pill {
        display: inline-block;
        padding: 0.25rem 0.7rem;
        border-radius: 999px;
        background: rgba(200, 155, 60, 0.18);
        color: var(--gold);
        font-weight: 700;
        font-size: 0.82rem;
      }
      .form-label { font-weight: 600; color: var(--ink); }
      .form-control {
        border: 1.5px solid var(--paper-line);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        background: #fff;
      }
      .form-control:focus { border-color: var(--ink); box-shadow: 0 0 0 3px rgba(29, 43, 83, 0.12); }
      .password-wrap { position: relative; }
      .password-wrap button {
        position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: 0; background: transparent; font-size: 1rem;
      }
      .btn-primary {
        background: var(--ink);
        border-color: var(--ink);
        border-radius: 999px;
        padding: 0.8rem 1rem;
        font-weight: 700;
      }
      .btn-primary:hover { background: #141f3d; border-color: #141f3d; }
      a { color: var(--seal); font-weight: 600; }
    </style>
  </head>
  <body>
    <div class="shell">
      <div class="card">
        <div class="mb-3"><span class="pill">📝 បង្កើតគណនី</span></div>
        <h2 class="brand h4 mb-2">ស្វាគមន៍មកកាន់ Maths24h</h2>
        <p class="text-muted mb-4">សូមបញ្ចូលព័ត៌មានខាងក្រោម ដើម្បីចាប់ផ្ដើមការធ្វើតេស្តដោយងាយស្រួល</p>
        <?php if ($message !== ''): ?>
          <div class="alert alert-danger rounded-3"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post">
          <div class="mb-3">
            <label class="form-label">ឈ្មោះពេញ</label>
            <input name="fullname" class="form-control" required />
          </div>
          <div class="mb-3">
            <label class="form-label">ឈ្មោះអ្នកប្រើ (username)</label>
            <input name="username" class="form-control" required />
          </div>
          <div class="mb-3">
            <label class="form-label">ពាក្យសម្ងាត់</label>
            <div class="password-wrap">
              <input type="password" name="password" class="form-control" required />
              <button type="button" class="toggle-password" data-target="password">👁️</button>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">បញ្ជាក់ពាក្យសម្ងាត់</label>
            <div class="password-wrap">
              <input type="password" name="confirm_password" class="form-control" required />
              <button type="button" class="toggle-password" data-target="confirm_password">👁️</button>
            </div>
          </div>
          <button class="btn btn-primary w-100">បង្កើតគណនី</button>
        </form>
        <div class="mt-4 text-center d-flex flex-column gap-2">
          <div>
            <span class="text-muted">មានគណនីរួចហើយ?</span>
            <a href="login.php" class="ms-1" style="text-decoration: none;">ចូលប្រើប្រាស់</a>
          </div>
          <hr class="my-2" style="border-color: var(--paper-line);" />
          <div>
            <a href="index.php" style="text-decoration: none;">← ត្រឡប់ទៅទំព័រដើម</a>
          </div>
        </div>
      </div>
    </div>
    <script>
      document.querySelectorAll('.toggle-password').forEach((btn) => {
        btn.addEventListener('click', () => {
          const targetName = btn.getAttribute('data-target');
          const input = document.querySelector(`input[name="${targetName}"]`);
          if (!input) return;
          const show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          btn.textContent = show ? '🙈' : '👁️';
        });
      });
    </script>
  </body>
</html>
