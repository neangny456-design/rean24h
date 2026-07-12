<?php
require_once __DIR__ . '/database.php';
requireUserSession();

$message = '';
$role = $_SESSION['role'] ?? 'user';
$categories = $db->query('SELECT * FROM categories ORDER BY id DESC')->fetchAll();
$records = $db->query('SELECT r.id, r.title, r.body, r.status, r.created_at, c.name AS category_name FROM records r LEFT JOIN categories c ON c.id = r.category_id ORDER BY r.id DESC')->fetchAll();
$publishedRecords = array_values(array_filter($records, function ($record) {
    return ($record['status'] ?? 'draft') === 'published';
}));
$visibleTests = $publishedRecords;

$editingRecord = null;
$editingPayload = null;
if ($role === 'admin' && !empty($_GET['edit_record'])) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_qcm') {
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
            $records = $db->query('SELECT r.id, r.title, r.body, r.status, r.created_at, c.name AS category_name FROM records r LEFT JOIN categories c ON c.id = r.category_id ORDER BY r.id DESC')->fetchAll();
            $publishedRecords = array_values(array_filter($records, function ($record) {
                return ($record['status'] ?? 'draft') === 'published';
            }));
            $recentRecords = array_slice($publishedRecords, 0, 6);
        }
    } elseif ($action === 'delete_record') {
        $recordId = (int) ($_POST['record_id'] ?? 0);
        if ($recordId > 0) {
            $db->prepare('DELETE FROM records WHERE id = ?')->execute([$recordId]);
            $message = 'Test removed successfully.';
            $records = $db->query('SELECT r.id, r.title, r.body, r.status, r.created_at, c.name AS category_name FROM records r LEFT JOIN categories c ON c.id = r.category_id ORDER BY r.id DESC')->fetchAll();
            $publishedRecords = array_values(array_filter($records, function ($record) {
                return ($record['status'] ?? 'draft') === 'published';
            }));
            $recentRecords = array_slice($publishedRecords, 0, 6);
        }
    }
}

$editGrade = $editingPayload['grade'] ?? '';
$editPaper = $editingPayload['paper'] ?? $editingRecord['title'] ?? '';
$editQuestion = $editingPayload['question'] ?? '';
$editOptions = $editingPayload['options'] ?? ['A' => '', 'B' => '', 'C' => '', 'D' => ''];
$editCorrect = $editingPayload['correct'] ?? 'A';
?>
<!doctype html>
<html lang="km">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard – Maths24h</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Moul&family=Kantumruy+Pro:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/katex.min.css" />
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
      .btn-primary { background: var(--ink); border-color: var(--ink); border-radius: 999px; }
      .btn-outline-primary { color: var(--ink); border-color: var(--paper-line); border-radius: 999px; }
      .form-control, .form-select, textarea { border: 1.5px solid var(--paper-line); border-radius: 12px; background: #fff; }
      .form-control:focus, .form-select:focus, textarea:focus { border-color: var(--ink); box-shadow: 0 0 0 3px rgba(29,43,83,.12); }
      .paper-tabs .btn.active { background: var(--ink); color: #fff; }
      .preview-box { border: 1px dashed var(--paper-line); border-radius: 12px; padding: 0.75rem; background: rgba(255,255,255,.85); min-height: 54px; }
      a { color: inherit; text-decoration: none; }
    </style>
  </head>
  <body>
    <div class="container py-4 py-lg-5">
      <div class="panel p-4 p-lg-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start align-items-lg-center gap-3 mb-4">
          <div>
            <span class="pill">📊 Dashboard</span>
            <h2 class="brand h4 mb-1 mt-2">ស្វាគមន៍ <?= htmlspecialchars($_SESSION['fullname'] ?? 'អ្នកប្រើ', ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="text-muted mb-0">អ្នកអាចមើល categories, tests និងធ្វើការចូលទៅកាន់ផ្ទាំងគ្រប់គ្រងនៅទីនេះ</p>
          </div>
          <div class="d-flex gap-2">
            <a href="logout.php" class="btn btn-outline-dark">ចាកចេញ</a>
          </div>
        </div>
        <?php if ($message !== ''): ?>
          <div class="alert alert-info rounded-3"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <div class="stat-card p-3">
              <div class="small text-white-50">Categories</div>
              <div class="fs-3 fw-bold"><?= count($categories) ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stat-card p-3">
              <div class="small text-white-50">Tests</div>
              <div class="fs-3 fw-bold"><?= count($publishedRecords) ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stat-card p-3">
              <div class="small text-white-50">Role</div>
              <div class="fs-3 fw-bold text-capitalize"><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-lg-7">
            <div class="section-card">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">📚 Categories</h4>
              </div>
              <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?>
                  <div class="list-item">
                    <div class="fw-bold"><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($category['description'] ?: 'No description', ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="text-muted">No categories yet.</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-lg-5">
            <div class="section-card">
              <h4 class="mb-3">📝 All published tests</h4>
              <?php if (!empty($publishedRecords)): ?>
                <?php foreach ($publishedRecords as $record): ?>
                  <div class="list-item d-flex justify-content-between align-items-start gap-3">
                    <div>
                      <div class="fw-bold"><?= htmlspecialchars($record['title'], ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="small text-muted"><?= htmlspecialchars($record['category_name'] ?? 'Uncategorized', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($record['status'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="d-flex gap-2">
                      <a href="view_test.php?id=<?= (int) $record['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                      <?php if ($role === 'admin'): ?>
                        <a href="test.php?edit_record=<?= (int) $record['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="action" value="delete_record" />
                          <input type="hidden" name="record_id" value="<?= (int) $record['id'] ?>" />
                          <button class="btn btn-sm btn-outline-danger">Remove</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="text-muted">No tests available yet.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <?php if ($role === 'admin'): ?>
          <div class="section-card mt-4">
            <div class="d-flex flex-wrap gap-2 paper-tabs mb-3">
              <button type="button" class="btn btn-outline-primary active" data-paper="វិញ្ញាសាទី១">វិញ្ញាសាទី១</button>
              <button type="button" class="btn btn-outline-primary" data-paper="វិញ្ញាសាទី២">វិញ្ញាសាទី២</button>
              <button type="button" class="btn btn-outline-primary" id="addPaperBtn">បង្ហែមវិញ្ញាសា</button>
            </div>
            <form method="post" class="row g-3" id="qcmForm">
              <input type="hidden" name="action" value="save_qcm" />
              <input type="hidden" name="record_id" value="<?= (int) ($editingRecord['id'] ?? 0) ?>" />
              <div class="col-md-6">
                <label class="form-label">ជ្រើសរើសកម្រិតថ្នាក់</label>
                <select name="qcm_grade" class="form-select" required>
                  <option value="">-- ជ្រើសរើស --</option>
                  <option value="ថ្នាក់ទី៩" <?= ($editGrade === 'ថ្នាក់ទី៩') ? 'selected' : '' ?>>ថ្នាក់ទី៩</option>
                  <option value="ថ្នាក់ទី១២" <?= ($editGrade === 'ថ្នាក់ទី១២') ? 'selected' : '' ?>>ថ្នាក់ទី១២</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">វិញ្ញាសា</label>
                <input type="text" name="qcm_paper" class="form-control" value="<?= htmlspecialchars($editPaper ?: 'វិញ្ញាសាទី១', ENT_QUOTES, 'UTF-8') ?>" required />
              </div>
              <div class="col-12">
                <label class="form-label">លំហាត់ទី១</label>
                <textarea name="qcm_question" class="form-control" rows="2" placeholder="Example: $2x+4$" required><?= htmlspecialchars($editQuestion, ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="preview-box mt-2" id="questionPreview"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">A. x=-2</label>
                <textarea name="qcm_option_a" class="form-control" rows="2" placeholder="Example: $x=-2$" required><?= htmlspecialchars($editOptions['A'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="preview-box mt-2" data-preview-for="qcm_option_a"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">B. 2</label>
                <textarea name="qcm_option_b" class="form-control" rows="2" placeholder="Example: $2$" required><?= htmlspecialchars($editOptions['B'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="preview-box mt-2" data-preview-for="qcm_option_b"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">C. 3</label>
                <textarea name="qcm_option_c" class="form-control" rows="2" placeholder="Example: $3$" required><?= htmlspecialchars($editOptions['C'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="preview-box mt-2" data-preview-for="qcm_option_c"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">D. 4</label>
                <textarea name="qcm_option_d" class="form-control" rows="2" placeholder="Example: $4$" required><?= htmlspecialchars($editOptions['D'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="preview-box mt-2" data-preview-for="qcm_option_d"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Correct answer</label>
                <select name="qcm_correct" class="form-select">
                  <option value="A" <?= ($editCorrect === 'A') ? 'selected' : '' ?>>A</option>
                  <option value="B" <?= ($editCorrect === 'B') ? 'selected' : '' ?>>B</option>
                  <option value="C" <?= ($editCorrect === 'C') ? 'selected' : '' ?>>C</option>
                  <option value="D" <?= ($editCorrect === 'D') ? 'selected' : '' ?>>D</option>
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
                  <a href="test.php" class="btn btn-outline-secondary">Cancel</a>
                <?php endif; ?>
              </div>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/katex.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/contrib/auto-render.min.js"></script>
    <script>
      const renderMathPreview = (source, target) => {
        const value = source.value || '';
        target.innerHTML = value;
        try {
          renderMathInElement(target, { throwOnError: false });
        } catch (e) {}
      };

      const questionInput = document.querySelector('textarea[name="qcm_question"]');
      const questionPreview = document.getElementById('questionPreview');
      if (questionInput && questionPreview) {
        questionInput.addEventListener('input', () => renderMathPreview(questionInput, questionPreview));
        renderMathPreview(questionInput, questionPreview);
      }

      document.querySelectorAll('[data-preview-for]').forEach((previewBlock) => {
        const name = previewBlock.getAttribute('data-preview-for');
        const input = document.querySelector(`textarea[name="${name}"]`);
        if (input) {
          input.addEventListener('input', () => renderMathPreview(input, previewBlock));
          renderMathPreview(input, previewBlock);
        }
      });

      const paperTabs = document.querySelector('.paper-tabs');
      const paperInput = document.querySelector('input[name="qcm_paper"]');
      const addPaperBtn = document.getElementById('addPaperBtn');
      const activatePaper = (button) => {
        paperTabs.querySelectorAll('button:not(#addPaperBtn)').forEach((item) => item.classList.remove('active'));
        button.classList.add('active');
        if (paperInput) paperInput.value = button.textContent.trim();
      };

      paperTabs.querySelectorAll('button:not(#addPaperBtn)').forEach((button) => {
        button.addEventListener('click', () => activatePaper(button));
      });

      if (addPaperBtn) {
        addPaperBtn.addEventListener('click', (event) => {
          event.preventDefault();
          const count = paperTabs.querySelectorAll('button:not(#addPaperBtn)').length + 1;
          const newButton = document.createElement('button');
          newButton.type = 'button';
          newButton.className = 'btn btn-outline-primary';
          newButton.textContent = `វិញ្ញាសាទី${count}`;
          newButton.addEventListener('click', () => activatePaper(newButton));
          paperTabs.insertBefore(newButton, addPaperBtn);
          activatePaper(newButton);
        });
      }
    </script>
  </body>
</html>
