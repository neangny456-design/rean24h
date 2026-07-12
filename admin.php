<?php
require_once __DIR__ . '/database.php';
requireAdminSession();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_category') {
        $id = (int) ($_POST['category_id'] ?? 0);
        $name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['category_description'] ?? '');
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $name), '-'));
        if ($name === '') {
            $message = 'Name is required.';
        } elseif ($id > 0) {
            $stmt = $db->prepare('UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?');
            $stmt->execute([$name, $slug, $description, $id]);
            $message = 'Category updated.';
        } else {
            $stmt = $db->prepare('INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)');
            $stmt->execute([$name, $slug, $description]);
            $message = 'Category created.';
        }
    } elseif ($action === 'delete_category') {
        $id = (int) ($_POST['category_id'] ?? 0);
        if ($id > 0) {
            $db->prepare('DELETE FROM records WHERE category_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            $message = 'Category deleted.';
        }
    } elseif ($action === 'save_record') {
        $id = (int) ($_POST['record_id'] ?? 0);
        $title = trim($_POST['record_title'] ?? '');
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $body = trim($_POST['record_body'] ?? '');
        $status = trim($_POST['record_status'] ?? 'draft');
        if ($title === '') {
            $message = 'Title is required.';
        } elseif ($id > 0) {
            $stmt = $db->prepare('UPDATE records SET title = ?, category_id = ?, body = ?, status = ? WHERE id = ?');
            $stmt->execute([$title, $categoryId > 0 ? $categoryId : null, $body, $status, $id]);
            $message = 'Record updated.';
        } else {
            $stmt = $db->prepare('INSERT INTO records (title, category_id, body, status) VALUES (?, ?, ?, ?)');
            $stmt->execute([$title, $categoryId > 0 ? $categoryId : null, $body, $status]);
            $message = 'Record created.';
        }
    } elseif ($action === 'save_qcm') {
        $recordId = (int) ($_POST['record_id'] ?? 0);
        $grade = trim($_POST['qcm_grade'] ?? '');
        $paper = trim($_POST['qcm_paper'] ?? '');
        $question = trim($_POST['qcm_question'] ?? '');
        $categoryId = (int) ($_POST['qcm_category'] ?? 0);
        $status = trim($_POST['qcm_status'] ?? 'draft');
        $options = [
            'A' => trim($_POST['qcm_option_a'] ?? ''),
            'B' => trim($_POST['qcm_option_b'] ?? ''),
            'C' => trim($_POST['qcm_option_c'] ?? ''),
            'D' => trim($_POST['qcm_option_d'] ?? '')
        ];
        $correct = trim($_POST['qcm_correct'] ?? 'A');
        if ($grade === '' || $paper === '' || $question === '' || $options['A'] === '' || $options['B'] === '' || $options['C'] === '' || $options['D'] === '') {
            $message = 'Please fill the grade, paper, question, and all four options.';
        } else {
            $payload = [
                'grade' => $grade,
                'paper' => $paper,
                'question' => $question,
                'options' => [
                    'A' => $options['A'],
                    'B' => $options['B'],
                    'C' => $options['C'],
                    'D' => $options['D'],
                ],
                'correct' => $correct,
            ];
            if ($recordId > 0) {
                $stmt = $db->prepare('UPDATE records SET title = ?, category_id = ?, body = ?, status = ? WHERE id = ?');
                $stmt->execute([$paper . ' — ' . $grade, $categoryId > 0 ? $categoryId : null, json_encode($payload, JSON_UNESCAPED_UNICODE), $status, $recordId]);
                $message = 'QCM test updated successfully.';
            } else {
                $stmt = $db->prepare('INSERT INTO records (title, category_id, body, status) VALUES (?, ?, ?, ?)');
                $stmt->execute([$paper . ' — ' . $grade, $categoryId > 0 ? $categoryId : null, json_encode($payload, JSON_UNESCAPED_UNICODE), $status]);
                $message = 'QCM test saved successfully.';
            }
        }
    } elseif ($action === 'delete_record') {
        $id = (int) ($_POST['record_id'] ?? 0);
        if ($id > 0) {
            $db->prepare('DELETE FROM records WHERE id = ?')->execute([$id]);
            $message = 'Record deleted.';
        }
    }
}

$editingCategory = null;
if (!empty($_GET['edit_category'])) {
    $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([(int) $_GET['edit_category']]);
    $editingCategory = $stmt->fetch();
}

$editingRecord = null;
$editingPayload = null;
if (!empty($_GET['edit_record'])) {
    $stmt = $db->prepare('SELECT * FROM records WHERE id = ?');
    $stmt->execute([(int) $_GET['edit_record']]);
    $editingRecord = $stmt->fetch();
    if ($editingRecord) {
        $decoded = json_decode($editingRecord['body'] ?? '', true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $editingPayload = $decoded;
        }
    }
}

$countStmt = $db->query('SELECT COUNT(*) AS total FROM users');
$count = $countStmt->fetch();
$categories = $db->query('SELECT * FROM categories ORDER BY id DESC')->fetchAll();
$records = $db->query('SELECT r.id, r.title, r.body, r.status, r.created_at, c.name AS category_name FROM records r LEFT JOIN categories c ON c.id = r.category_id ORDER BY r.id DESC')->fetchAll();
?>
<!doctype html>
<html lang="km">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ផ្ទាំងគ្រប់គ្រង Admin</title>
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
      .brand { font-family: "Moul", serif; }
      .panel { border: 1px solid var(--paper-line); border-radius: 24px; background: var(--cream-card); box-shadow: 0 14px 34px rgba(29,43,83,.12); }
      .pill { display: inline-block; padding: 0.32rem 0.75rem; border-radius: 999px; background: rgba(200,155,60,.16); color: var(--gold); font-weight: 700; font-size: 0.82rem; }
      .stat-card { border-radius: 18px; background: linear-gradient(135deg, rgba(29,43,83,.96), rgba(76,86,128,.95)); color: #fff; }
      .section-card { border: 1px solid var(--paper-line); border-radius: 18px; background: #fff; padding: 1rem; }
      .list-item { border-bottom: 1px solid var(--paper-line); padding: 0.7rem 0; }
      .list-item:last-child { border-bottom: 0; }
      .btn-outline-dark { border-radius: 999px; }
      .btn-outline-primary { color: var(--ink); border-color: var(--paper-line); border-radius: 999px; }
      .btn-primary { background: var(--ink); border-color: var(--ink); border-radius: 999px; }
      .form-control, .form-select, textarea { border: 1.5px solid var(--paper-line); border-radius: 12px; background: #fff; }
      .form-control:focus, .form-select:focus, textarea:focus { border-color: var(--ink); box-shadow: 0 0 0 3px rgba(29,43,83,.12); }
      a { color: inherit; text-decoration: none; }
    </style>
  </head>
  <body>
    <div class="container py-4 py-lg-5">
      <div class="panel p-4 p-lg-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start align-items-lg-center gap-3 mb-4">
          <div>
            <span class="pill">🛠️ Admin Dashboard</span>
            <h2 class="brand h4 mb-1 mt-2">ផ្ទាំងគ្រប់គ្រង Math24h</h2>
            <p class="text-muted mb-0">សូមស្វាគមន៍ <?= htmlspecialchars($_SESSION['fullname'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?> · អ្នកអាចគ្រប់គ្រង categories, tests និង QCM បានយ៉ាងងាយស្រួល</p>
          </div>
          <div class="d-flex gap-2">
            <a href="test.php" class="btn btn-outline-primary">User Dashboard</a>
            <a href="logout.php" class="btn btn-outline-dark">ចាកចេញ</a>
          </div>
        </div>
        <?php if ($message !== ''): ?>
          <div class="alert alert-info rounded-3"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <div class="stat-card p-3">
              <div class="small text-white-50">អ្នកប្រើប្រាស់</div>
              <div class="fs-3 fw-bold"><?= (int) ($count['total'] ?? 0) ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stat-card p-3">
              <div class="small text-white-50">Categories</div>
              <div class="fs-3 fw-bold"><?= count($categories) ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stat-card p-3">
              <div class="small text-white-50">Tests</div>
              <div class="fs-3 fw-bold"><?= count($records) ?></div>
            </div>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-lg-6">
            <div class="section-card">
              <h4 class="mb-3">📚 Categories</h4>
              <form method="post" class="mb-3">
                <input type="hidden" name="action" value="save_category" />
                <input type="hidden" name="category_id" value="<?= (int) ($editingCategory['id'] ?? 0) ?>" />
                <div class="mb-2">
                  <label class="form-label">ឈ្មោះ Category</label>
                  <input name="category_name" class="form-control" value="<?= htmlspecialchars($editingCategory['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required />
                </div>
                <div class="mb-2">
                  <label class="form-label">ពិពណ៌នា</label>
                  <textarea name="category_description" class="form-control" rows="3"><?= htmlspecialchars($editingCategory['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <button class="btn btn-primary">រក្សាទុក Category</button>
                <?php if (!empty($editingCategory)): ?>
                  <a href="admin.php" class="btn btn-outline-secondary ms-2">បោះបង់</a>
                <?php endif; ?>
              </form>
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead><tr><th>#</th><th>ឈ្មោះ</th><th>Slug</th><th>សកម្មភាព</th></tr></thead>
                  <tbody>
                    <?php foreach ($categories as $category): ?>
                      <tr>
                        <td><?= (int) $category['id'] ?></td>
                        <td><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($category['slug'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                          <a href="admin.php?edit_category=<?= (int) $category['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                          <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="delete_category" />
                            <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>" />
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="section-card">
              <h4 class="mb-3">🧠 <?= !empty($editingRecord) ? 'Edit QCM test' : 'Add QCM test' ?></h4>
              <form method="post" class="row g-3">
                <input type="hidden" name="action" value="save_qcm" />
                <input type="hidden" name="record_id" value="<?= (int) ($editingRecord['id'] ?? 0) ?>" />
                <div class="col-md-6">
                  <label class="form-label">Grade</label>
                  <select name="qcm_grade" class="form-select">
                    <option value="">-- Select --</option>
                    <option value="ថ្នាក់ទី៩" <?= (!empty($editingPayload) && ($editingPayload['grade'] ?? '') === 'ថ្នាក់ទី៩') ? 'selected' : '' ?>>ថ្នាក់ទី៩</option>
                    <option value="ថ្នាក់ទី១២" <?= (!empty($editingPayload) && ($editingPayload['grade'] ?? '') === 'ថ្នាក់ទី១២') ? 'selected' : '' ?>>ថ្នាក់ទី១២</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Paper</label>
                  <input name="qcm_paper" class="form-control" value="<?= htmlspecialchars($editingPayload['paper'] ?? $editingRecord['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required />
                </div>
                <div class="col-12">
                  <label class="form-label">Question</label>
                  <textarea name="qcm_question" class="form-control" rows="2" required><?= htmlspecialchars($editingPayload['question'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Option A</label>
                  <input name="qcm_option_a" class="form-control" value="<?= htmlspecialchars($editingPayload['options']['A'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Option B</label>
                  <input name="qcm_option_b" class="form-control" value="<?= htmlspecialchars($editingPayload['options']['B'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Option C</label>
                  <input name="qcm_option_c" class="form-control" value="<?= htmlspecialchars($editingPayload['options']['C'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Option D</label>
                  <input name="qcm_option_d" class="form-control" value="<?= htmlspecialchars($editingPayload['options']['D'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Correct answer</label>
                  <select name="qcm_correct" class="form-select">
                    <option value="A" <?= (!empty($editingPayload) && ($editingPayload['correct'] ?? 'A') === 'A') ? 'selected' : '' ?>>A</option>
                    <option value="B" <?= (!empty($editingPayload) && ($editingPayload['correct'] ?? 'A') === 'B') ? 'selected' : '' ?>>B</option>
                    <option value="C" <?= (!empty($editingPayload) && ($editingPayload['correct'] ?? 'A') === 'C') ? 'selected' : '' ?>>C</option>
                    <option value="D" <?= (!empty($editingPayload) && ($editingPayload['correct'] ?? 'A') === 'D') ? 'selected' : '' ?>>D</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Category</label>
                  <select name="qcm_category" class="form-select">
                    <option value="0">-- None --</option>
                    <?php foreach ($categories as $category): ?>
                      <option value="<?= (int) $category['id'] ?>" <?= (!empty($editingRecord) && (int) $editingRecord['category_id'] === (int) $category['id']) ? 'selected' : '' ?>><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Status</label>
                  <select name="qcm_status" class="form-select">
                    <option value="draft" <?= (!empty($editingRecord) && ($editingRecord['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= (!empty($editingRecord) && ($editingRecord['status'] ?? 'draft') === 'published') ? 'selected' : '' ?>>Published</option>
                  </select>
                </div>
                <div class="col-12 d-flex gap-2">
                  <button class="btn btn-primary"><?= !empty($editingRecord) ? 'Update QCM' : 'Save QCM' ?></button>
                  <?php if (!empty($editingRecord)): ?>
                    <a href="admin.php" class="btn btn-outline-secondary">Cancel</a>
                  <?php endif; ?>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="section-card mt-4">
          <h4 class="mb-3">📝 Recent tests</h4>
          <div class="table-responsive">
            <table class="table table-striped">
              <thead><tr><th>#</th><th>Title</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($records as $record): ?>
                  <tr>
                    <td><?= (int) $record['id'] ?></td>
                    <td><?= htmlspecialchars($record['title'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($record['category_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($record['status'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                      <a href="admin.php?edit_record=<?= (int) $record['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="delete_record" />
                        <input type="hidden" name="record_id" value="<?= (int) $record['id'] ?>" />
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
