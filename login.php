<?php
require_once __DIR__ . '/database.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(strtolower($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role'];

        header('Location: test.php');
        exit;
    }

    $message = 'ឈ្មោះអ្នកប្រើ ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវ។';
}
?>
<!doctype html>
<html lang="km">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ចូលប្រើប្រាស់ – Maths24h</title>
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
        width: min(480px, 100%);
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
      .hint { color: var(--ink-soft); font-size: 0.95rem; }
      a { color: var(--seal); font-weight: 600; text-decoration: none; }
      a:hover { text-decoration: underline; }
    </style>
  </head>
  <body>
    <div class="shell">
      <div class="card">
        <div class="mb-3"><span class="pill">🔐 ចូលប្រើប្រាស់</span></div>
        <h2 class="brand h4 mb-2">ចូលប្រើប្រាស់គណនី</h2>
        <p class="hint mb-4">សម្រាប់គ្រប់គ្រងទាំងស្រុង សូមប្រើឈ្មោះ admin: <strong>rean24hadmin</strong> និងពាក្យសម្ងាត់: <strong>Rean24h@2026</strong></p>
        <?php if ($message !== ''): ?>
          <div class="alert alert-danger rounded-3"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post">
          <div class="mb-3">
            <label class="form-label">ឈ្មោះអ្នកប្រើ (username)</label>
            <input name="username" class="form-control" required autocomplete="username" />
          </div>
          <div class="mb-3">
            <label class="form-label">ពាក្យសម្ងាត់</label>
            <div class="password-wrap">
              <input type="password" name="password" class="form-control" required autocomplete="current-password" />
              <button type="button" class="toggle-password">👁️</button>
            </div>
          </div>
          <button class="btn btn-primary w-100">ចូលប្រើប្រាស់</button>
        </form>
        <div class="mt-4 text-center d-flex flex-column gap-2">
          <div>
            <span class="text-muted">មិនទាន់មានគណនីមែនទេ?</span>
            <a href="register.php" class="ms-1">បង្កើតគណនី</a>
          </div>
          <hr class="my-2" style="border-color: var(--paper-line);" />
          <div>
            <a href="index.php">← ត្រឡប់ទៅទំព័រដើម</a>
          </div>
        </div>
      </div>
    </div>
    <script>
      document.querySelector('.toggle-password')?.addEventListener('click', () => {
        const input = document.querySelector('input[name="password"]');
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        document.querySelector('.toggle-password').textContent = show ? '🙈' : '👁️';
      });
    </script>
  </body>
</html>
