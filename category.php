<?php
require_once __DIR__ . '/database.php';
requireUserSession();

$slug = $_GET['slug'] ?? '';
$stmt = $db->prepare('SELECT * FROM categories WHERE slug = ?');
$stmt->execute([$slug]);
$category = $stmt->fetch();

if (!$category) {
    header('Location: index.php');
    exit;
}

$isAdmin = (($_SESSION['role'] ?? 'user') === 'admin');

// Handle Admin POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_test') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        $grade = trim($_POST['qcm_grade'] ?? '');
        $paper = trim($_POST['qcm_paper'] ?? '');
        $question = trim($_POST['qcm_question'] ?? '');
        $subQuestionLabels = $_POST['subquestion_label'] ?? [];
        $subQuestionTexts = $_POST['subquestion_text'] ?? [];
        $subQuestionParts = [];
        if ($question !== '') {
            $subQuestionParts[] = $question;
        }
        foreach ($subQuestionLabels as $index => $label) {
            $labelText = trim((string) $label);
            $text = trim((string) ($subQuestionTexts[$index] ?? ''));
            if ($text !== '') {
                $subQuestionParts[] = ($labelText !== '' ? $labelText . '. ' : '') . $text;
            }
        }
        $question = implode("\n\n", $subQuestionParts);
        $options = [
            'A' => trim($_POST['qcm_option_a'] ?? ''),
            'B' => trim($_POST['qcm_option_b'] ?? ''),
            'C' => trim($_POST['qcm_option_c'] ?? ''),
            'D' => trim($_POST['qcm_option_d'] ?? '')
        ];
        $correct = trim($_POST['qcm_correct'] ?? 'A');
        $score = (int) ($_POST['qcm_score'] ?? 0);
        $timeLimit = (int) ($_POST['qcm_time_limit'] ?? 0);

        $recordTitle = $title !== '' ? $title : ($paper !== '' ? $paper : 'វិញ្ញាសាថ្មី');
        $hasDetailedPayload = $grade !== '' || $paper !== '' || $question !== '' || !empty($subQuestionLabels) || $options['A'] !== '' || $options['B'] !== '' || $options['C'] !== '' || $options['D'] !== '';
        $payloadBody = $hasDetailedPayload
            ? json_encode([
                'grade' => $grade,
                'paper' => $paper,
                'question' => $question,
                'options' => $options,
                'correct' => $correct,
                'score' => $score > 0 ? $score : 10,
                'time_limit_minutes' => $timeLimit > 0 ? $timeLimit : 30,
            ], JSON_UNESCAPED_UNICODE)
            : $body;

        if ($recordTitle !== '') {
            $stmt = $db->prepare('INSERT INTO records (title, category_id, body, status) VALUES (?, ?, ?, ?)');
            $stmt->execute([$recordTitle, (int) $category['id'], $payloadBody, $status]);
            $redirectParams = [
                'slug' => $slug,
                'msg' => 'បន្ថែមវិញ្ញាសាបានសម្រេច!',
                'show_inline' => '1',
                'prefill_paper' => $paper,
            ];
            header('Location: category.php?' . http_build_query($redirectParams));
            exit;
        }
    } elseif ($action === 'edit_test') {
        $id = (int) ($_POST['test_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        $grade = trim($_POST['qcm_grade'] ?? '');
        $paper = trim($_POST['qcm_paper'] ?? '');
        $question = trim($_POST['qcm_question'] ?? '');
        $subQuestionLabels = $_POST['subquestion_label'] ?? [];
        $subQuestionTexts = $_POST['subquestion_text'] ?? [];
        $subQuestionParts = [];
        if ($question !== '') {
            $subQuestionParts[] = $question;
        }
        foreach ($subQuestionLabels as $index => $label) {
            $labelText = trim((string) $label);
            $text = trim((string) ($subQuestionTexts[$index] ?? ''));
            if ($text !== '') {
                $subQuestionParts[] = ($labelText !== '' ? $labelText . '. ' : '') . $text;
            }
        }
        $question = implode("\n\n", $subQuestionParts);
        $options = [
            'A' => trim($_POST['qcm_option_a'] ?? ''),
            'B' => trim($_POST['qcm_option_b'] ?? ''),
            'C' => trim($_POST['qcm_option_c'] ?? ''),
            'D' => trim($_POST['qcm_option_d'] ?? '')
        ];
        $correct = trim($_POST['qcm_correct'] ?? 'A');
        $score = (int) ($_POST['qcm_score'] ?? 0);
        $timeLimit = (int) ($_POST['qcm_time_limit'] ?? 0);

        $recordTitle = $title !== '' ? $title : ($paper !== '' ? $paper : 'វិញ្ញាសាថ្មី');
        $hasDetailedPayload = $grade !== '' || $paper !== '' || $question !== '' || !empty($subQuestionLabels) || $options['A'] !== '' || $options['B'] !== '' || $options['C'] !== '' || $options['D'] !== '';
        $payloadBody = $hasDetailedPayload
            ? json_encode([
                'grade' => $grade,
                'paper' => $paper,
                'question' => $question,
                'options' => $options,
                'correct' => $correct,
                'score' => $score > 0 ? $score : 10,
                'time_limit_minutes' => $timeLimit > 0 ? $timeLimit : 30,
            ], JSON_UNESCAPED_UNICODE)
            : $body;

        if ($id > 0 && $recordTitle !== '') {
            $stmt = $db->prepare('UPDATE records SET title = ?, body = ?, status = ? WHERE id = ?');
            $stmt->execute([$recordTitle, $payloadBody, $status, $id]);
            header("Location: category.php?slug=" . urlencode($slug) . "&msg=" . urlencode("កែប្រែវិញ្ញាសាបានសម្រេច!"));
            exit;
        }
    } elseif ($action === 'delete_test') {
        $id = (int) ($_POST['test_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare('DELETE FROM records WHERE id = ?');
            $stmt->execute([$id]);
            header("Location: category.php?slug=" . urlencode($slug) . "&msg=" . urlencode("លុបវិញ្ញាសាបានសម្រេច!"));
            exit;
        }
    }
}

// Fetch quizzes
if ($isAdmin) {
    $stmt = $db->prepare('SELECT * FROM records WHERE category_id = ? ORDER BY id DESC');
    $stmt->execute([(int) $category['id']]);
} else {
    $stmt = $db->prepare('SELECT * FROM records WHERE category_id = ? AND status = ? ORDER BY id DESC');
    $stmt->execute([(int) $category['id'], 'published']);
}
$quizzes = $stmt->fetchAll();
?>
<!doctype html>
<html lang="km">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?> — Maths24h</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link
      href="https://fonts.googleapis.com/css2?family=Moul&family=Kantumruy+Pro:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap"
      rel="stylesheet"
    />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/katex.min.css" />
    <style>
      :root {
        --paper: #fbf7ef;
        --paper-line: #e4dcc8;
        --cream-card: #fffdf8;
        --ink: #1d2b53;
        --ink-soft: #4c5680;
        --gold: #c89b3c;
        --gold-soft: #f0dfb0;
        --seal: #b23a2f;
        --jade: #3e7c59;
      }

      body {
        font-family: "Kantumruy Pro", sans-serif;
        color: var(--ink);
        background-color: var(--paper);
        background-image:
          linear-gradient(var(--paper-line) 1px, transparent 1px),
          linear-gradient(90deg, var(--paper-line) 1px, transparent 1px);
        background-size: 28px 28px;
        min-height: 100vh;
      }

      .display,
      .brand-word,
      .section-title,
      .quiz-title {
        font-family: "Moul", serif;
      }
      .mono {
        font-family: "JetBrains Mono", monospace;
      }

      a {
        text-decoration: none;
        color: inherit;
      }

      /* ===== Navbar ===== */
      .topbar {
        background: var(--cream-card);
        border-bottom: 2px solid var(--ink);
        position: sticky;
        top: 0;
        z-index: 40;
      }
      .brand-mark {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: var(--ink);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--gold-soft);
        font-family: "JetBrains Mono", monospace;
        font-weight: 700;
        font-size: 0.78rem;
        line-height: 1;
        text-align: center;
        flex-shrink: 0;
        box-shadow: 2px 2px 0 var(--gold);
      }
      .brand-word {
        font-size: 1.15rem;
        letter-spacing: 0.5px;
        color: var(--ink);
      }
      .brand-sub {
        font-size: 0.68rem;
        color: var(--ink-soft);
        letter-spacing: 0.4px;
      }

      .bell-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--paper);
        border: 1.5px solid var(--paper-line);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
      }
      .btn-ink {
        background: var(--ink);
        color: var(--paper);
        border: 1.5px solid var(--ink);
        font-weight: 600;
      }
      .btn-ink:hover {
        background: #141f3d;
        color: var(--gold-soft);
      }
      .btn-outline-ink {
        background: transparent;
        color: var(--ink);
        border: 1.5px solid var(--ink);
        font-weight: 600;
      }
      .btn-outline-ink:hover {
        background: var(--ink);
        color: var(--paper);
      }

      /* ===== Layout ===== */
      .shell {
        display: flex;
        min-height: calc(100vh - 88px);
      }

      .sidebar {
        width: 236px;
        flex-shrink: 0;
        background: var(--cream-card);
        border-right: 2px solid var(--ink);
        padding: 1.4rem 1rem;
        transition:
          width 0.22s ease,
          padding 0.22s ease;
      }
      .sidebar.collapsed {
        width: 76px;
        padding: 1.4rem 0.6rem;
      }
      .sidebar.collapsed .side-label,
      .sidebar.collapsed .side-caption {
        display: none;
      }
      .side-caption {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--ink-soft);
        margin: 0 0.6rem 1rem;
      }
      .side-link {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0.62rem 0.7rem;
        border-radius: 8px;
        color: var(--ink-soft);
        font-weight: 600;
        font-size: 0.92rem;
        margin-bottom: 0.2rem;
      }
      .side-link .ic {
        font-size: 1.05rem;
        width: 22px;
        text-align: center;
        flex-shrink: 0;
      }
      .side-link:hover {
        background: var(--paper);
        color: var(--ink);
      }
      .side-link.active {
        background: var(--ink);
        color: var(--gold-soft);
      }
      .side-toggle {
        border: 1.5px solid var(--paper-line);
        background: var(--paper);
        border-radius: 8px;
        width: 100%;
        padding: 0.4rem;
        margin-bottom: 1rem;
        color: var(--ink-soft);
        font-size: 0.85rem;
      }

      .main {
        flex: 1;
        padding: 2rem clamp(1rem, 3vw, 2.6rem);
        min-width: 0;
      }

      .breadcrumb-custom {
        font-size: 0.88rem;
        margin-bottom: 1.5rem;
        color: var(--ink-soft);
      }
      .breadcrumb-custom a {
        font-weight: 600;
      }
      .breadcrumb-custom a:hover {
        color: var(--seal);
      }

      /* ===== Quiz Card ===== */
      .quiz-card {
        background: var(--cream-card);
        border: 1.5px solid var(--paper-line);
        border-radius: 16px;
        padding: 1.5rem;
        position: relative;
        box-shadow: 0 8px 24px rgba(29, 43, 83, 0.06);
        border-left: 5px solid var(--gold);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }
      .quiz-card.draft {
        border-left-color: var(--ink-soft);
      }
      .quiz-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 30px rgba(29, 43, 83, 0.12);
      }
      .quiz-title {
        font-size: 1.05rem;
        color: var(--ink);
        margin-bottom: 0.5rem;
        overflow-wrap: anywhere;
        white-space: normal;
      }
      .quiz-snippet {
        font-size: 0.88rem;
        color: var(--ink-soft);
        margin-bottom: 1.2rem;
        min-height: 1.2rem;
        overflow: hidden;
      }
      .inline-form-shell {
        border: 1.5px dashed var(--paper-line);
        border-radius: 18px;
        padding: 1.25rem;
        background: rgba(255,255,255,0.6);
      }
      .badge-status {
        font-size: 0.72rem;
        font-weight: 700;
        padding: 0.2rem 0.6rem;
        border-radius: 999px;
        text-transform: uppercase;
      }
      .badge-published {
        background: rgba(62, 124, 89, 0.15);
        color: var(--jade);
      }
      .badge-draft {
        background: rgba(76, 86, 128, 0.15);
        color: var(--ink-soft);
      }

      /* Modals */
      .modal-content {
        background: var(--cream-card);
        border: 2px solid var(--ink);
        border-radius: 20px;
      }
      .modal-header {
        border-bottom: 1px solid var(--paper-line);
      }
      .modal-footer {
        border-top: 1px solid var(--paper-line);
      }
      .form-label {
        font-weight: 600;
      }
      .form-control, .form-select {
        border: 1.5px solid var(--paper-line);
        border-radius: 10px;
      }
      .form-control:focus, .form-select:focus {
        border-color: var(--ink);
        box-shadow: 0 0 0 3px rgba(29, 43, 83, 0.12);
      }
      .katex-preview {
        border: 1px dashed var(--paper-line);
        border-radius: 12px;
        padding: 0.75rem;
        background: rgba(255,255,255,.85);
        min-height: 54px;
        font-size: 1rem;
      }
      .katex-preview .katex { font-size: 1.05em; }
      .subquestion-block {
        border: 1px solid var(--paper-line);
        border-radius: 12px;
        padding: 0.85rem;
        background: rgba(255,255,255,.75);
      }
      .subquestion-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2rem;
        height: 2rem;
        border-radius: 999px;
        background: var(--ink);
        color: var(--paper);
        font-weight: 700;
        padding: 0 0.5rem;
      }

      @media (max-width: 820px) {
        .sidebar {
          position: fixed;
          top: 0;
          left: 0;
          height: 100vh;
          z-index: 60;
          transform: translateX(-100%);
        }
        .sidebar.mobile-open {
          transform: translateX(0);
        }
      }
    </style>
  </head>
  <body>
    <!-- Navbar -->
    <div class="topbar">
      <div
        class="container-fluid px-3 px-md-4 py-2 d-flex align-items-center gap-3"
      >
        <button
          class="btn btn-sm btn-outline-ink d-md-none"
          id="mobileToggle"
          aria-label="បើក/បិទម៉ឺនុយ"
        >
          ☰
        </button>
        <a
          href="index.php"
          class="d-flex align-items-center gap-2 text-decoration-none"
        >
          <span class="brand-mark">24h</span>
          <span class="d-none d-sm-block">
            <span class="brand-word d-block">Maths24h</span>
            <span class="brand-sub d-block">រៀនគណិតវិទ្យា២៤ម៉ោង</span>
          </span>
        </a>

        <div class="ms-auto d-flex align-items-center gap-2 flex-shrink-0">
          <span class="d-none d-md-inline-block text-ink me-2 fw-semibold" style="font-size: 0.9rem;">
            👤 <?= htmlspecialchars($_SESSION['fullname'], ENT_QUOTES, 'UTF-8') ?>
          </span>
          <a href="logout.php" class="btn btn-sm btn-ink px-3">ចាកចេញ</a>
        </div>
      </div>
    </div>

    <div class="shell">
      <!-- Sidebar -->
      <aside class="sidebar" id="sidebar">
        <button class="side-toggle" id="sideCollapse">‹‹ បង្រួម</button>
        <p class="side-caption">ម៉ឺនុយ</p>
        <a href="index.php" class="side-link"
          ><span class="ic">🏠</span><span class="side-label">ផ្ទាំងគ្រប់គ្រង</span></a
        >
        <a href="category.php?slug=grade-9" class="side-link <?= $slug === 'grade-9' ? 'active' : '' ?>"
          ><span class="ic">📖</span><span class="side-label">គណិតវិទ្យាទី៩ (ឌីប្លូម)</span></a
        >
        <a href="category.php?slug=grade-12" class="side-link <?= $slug === 'grade-12' ? 'active' : '' ?>"
          ><span class="ic">📝</span><span class="side-label">គណិតវិទ្យាទី១២ (បាក់ឌុប)</span></a
        >
        <a href="#" class="side-link"
          ><span class="ic">🏅</span><span class="side-label">ប័ណ្ណសរសើរ</span></a
        >
        <a href="#" class="side-link"
          ><span class="ic">📅</span><span class="side-label">ប្រវត្តិប្រឡង</span></a
        >
        <a href="#" class="side-link"
          ><span class="ic">⚙️</span><span class="side-label">ការកំណត់</span></a
        >
      </aside>

      <!-- Main -->
      <main class="main">
        <div class="breadcrumb-custom">
          <a href="index.php">ទំព័រដើម</a> &gt; <span><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h2 class="quiz-title mb-1"><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="text-muted mb-0"><?= htmlspecialchars($category['description'] ?? 'បណ្តុំវិញ្ញាសាត្រៀមប្រឡង', ENT_QUOTES, 'UTF-8') ?></p>
          </div>
          <?php if ($isAdmin): ?>
            <div class="d-flex gap-2 align-items-center">
              <button id="openAddTestModal" class="btn btn-ink px-4" data-bs-toggle="modal" data-bs-target="#testFormModal">
                ➕ បន្ថែមវិញ្ញាសា
              </button>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($isAdmin): ?>
          <div class="inline-form-shell mb-4" id="inlineTestFormPanel" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h4 class="quiz-title mb-0">បន្ថែមលំហាត់</h4>
              <button type="button" id="hideInlineTestForm" class="btn btn-sm btn-outline-secondary rounded-pill">បិទ</button>
            </div>
            <form method="post" class="row g-3">
              <input type="hidden" name="action" value="add_test" />
              <div class="col-12">
                <label class="form-label">ចំណងជើងវិញ្ញាសា</label>
                <input name="title" id="inline_test_title" class="form-control" placeholder="ឧទាហរណ៍៖ វិញ្ញាសាទី១" required />
              </div>
              <div class="col-md-6">
                <label class="form-label">ជ្រើសរើសកម្រិតថ្នាក់</label>
                <select name="qcm_grade" id="inline_test_grade" class="form-select" required>
                  <option value="">-- ជ្រើសរើស --</option>
                  <option value="ថ្នាក់ទី៩">ថ្នាក់ទី៩</option>
                  <option value="ថ្នាក់ទី១២">ថ្នាក់ទី១២</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">អំពីវិញ្ញាសា</label>
                <input name="qcm_paper" id="inline_test_paper" class="form-control" placeholder="ឧ. វិញ្ញាសាទី១" required />
              </div>
              <div class="col-12">
                <label class="form-label">លំហាត់</label>
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="text-muted small">បន្ថែមលំហាត់ជាបណ្តុំ</span>
                </div>
                <div id="inlineSubquestionList">
                  <div class="subquestion-block mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span class="subquestion-label">ក</span>
                      <button type="button" class="btn btn-link btn-sm p-0 text-danger remove-subquestion" style="text-decoration:none;">ដកចេញ</button>
                    </div>
                    <input type="hidden" name="subquestion_label[]" value="ក" />
                    <textarea name="subquestion_text[]" id="inline_test_question" class="form-control" rows="3" placeholder="សូមបញ្ចូលលំហាត់ក..." required></textarea>
                  </div>
                </div>
                <div class="katex-preview mt-2" id="inline_questionPreview"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">A</label>
                <textarea name="qcm_option_a" id="inline_test_option_a" class="form-control" rows="3" placeholder="ជម្រើស A" required></textarea>
                <div class="katex-preview mt-2" data-preview-for="inline_test_option_a"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">B</label>
                <textarea name="qcm_option_b" id="inline_test_option_b" class="form-control" rows="3" placeholder="ជម្រើស B" required></textarea>
                <div class="katex-preview mt-2" data-preview-for="inline_test_option_b"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">C</label>
                <textarea name="qcm_option_c" id="inline_test_option_c" class="form-control" rows="3" placeholder="ជម្រើស C" required></textarea>
                <div class="katex-preview mt-2" data-preview-for="inline_test_option_c"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">D</label>
                <textarea name="qcm_option_d" id="inline_test_option_d" class="form-control" rows="3" placeholder="ជម្រើស D" required></textarea>
                <div class="katex-preview mt-2" data-preview-for="inline_test_option_d"></div>
              </div>
              <div class="col-md-4">
                <label class="form-label">ចម្លើយត្រឹមត្រូវ</label>
                <select name="qcm_correct" id="inline_test_correct" class="form-select">
                  <option value="A">A</option>
                  <option value="B">B</option>
                  <option value="C">C</option>
                  <option value="D">D</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">ពិន្ទុ</label>
                <input type="number" name="qcm_score" id="inline_test_score" class="form-control" min="1" value="10" required />
              </div>
              <div class="col-md-4">
                <label class="form-label">ពេលវេលា (នាទី)</label>
                <input type="number" name="qcm_time_limit" id="inline_test_time_limit" class="form-control" min="1" value="30" required />
              </div>
              <div class="col-12">
                <label class="form-label">ស្ថានភាព</label>
                <select name="status" id="inline_test_status" class="form-select">
                  <option value="published">Published</option>
                  <option value="draft">Draft</option>
                </select>
              </div>
              <div class="col-12 d-flex justify-content-end gap-2">
                <button type="button" id="clearInlineTestForm" class="btn btn-outline-secondary rounded-pill px-4">សម្អាត</button>
                <button class="btn btn-ink rounded-pill px-4">រក្សាទុក</button>
              </div>
            </form>
          </div>
        <?php endif; ?>

        <?php if (!empty($_GET['msg'])): ?>
          <div class="alert alert-success rounded-3 mb-4 alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <div class="row g-4">
          <?php if (count($quizzes) === 0): ?>
            <div class="col-12 text-center py-5">
              <span style="font-size: 3rem;">📄</span>
              <p class="text-muted mt-2">មិនទាន់មានវិញ្ញាសានៅឡើយទេ។</p>
            </div>
          <?php else: ?>
            <?php foreach ($quizzes as $quiz): ?>
              <?php $isDraft = ($quiz['status'] === 'draft'); ?>
              <?php
                $quizPayload = null;
                if (!empty($quiz['body'])) {
                  $decodedQuizPayload = json_decode($quiz['body'], true);
                  if (json_last_error() === JSON_ERROR_NONE && is_array($decodedQuizPayload)) {
                    $quizPayload = $decodedQuizPayload;
                  }
                }
              ?>
              <div class="col-12 col-md-6 col-lg-4">
                <div class="quiz-card <?= $isDraft ? 'draft' : '' ?>">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge-status <?= $isDraft ? 'badge-draft' : 'badge-published' ?>">
                      <?= $isDraft ? 'Draft' : 'Published' ?>
                    </span>
                    <span style="font-size: 1.25rem;">📄</span>
                  </div>
                  <h4 class="quiz-title" title="<?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?>
                  </h4>
                  <div class="quiz-snippet">
                    <?= htmlspecialchars(($quizPayload['paper'] ?? $quiz['title'] ?? 'លំហាត់'), ENT_QUOTES, 'UTF-8') ?>
                  </div>
                  <div class="d-flex gap-2">
                    <a href="view_test.php?id=<?= (int)$quiz['id'] ?>" class="btn btn-sm btn-outline-ink flex-grow-1">
                      ចូលធ្វើតេស្ត
                    </a>
                    <?php if ($isAdmin): ?>
                      <button class="btn btn-sm btn-outline-primary btn-edit" 
                              data-id="<?= (int)$quiz['id'] ?>" 
                              data-title="<?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?>" 
                              data-status="<?= htmlspecialchars($quiz['status'], ENT_QUOTES, 'UTF-8') ?>"
                              data-grade="<?= htmlspecialchars($quizPayload['grade'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                              data-paper="<?= htmlspecialchars($quizPayload['paper'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                              data-question="<?= htmlspecialchars($quizPayload['question'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                              data-option-a="<?= htmlspecialchars($quizPayload['options']['A'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                              data-option-b="<?= htmlspecialchars($quizPayload['options']['B'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                              data-option-c="<?= htmlspecialchars($quizPayload['options']['C'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                              data-option-d="<?= htmlspecialchars($quizPayload['options']['D'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                              data-correct="<?= htmlspecialchars($quizPayload['correct'] ?? 'A', ENT_QUOTES, 'UTF-8') ?>"
                              data-score="<?= (int) ($quizPayload['score'] ?? 10) ?>"
                              data-time-limit="<?= (int) ($quizPayload['time_limit_minutes'] ?? 30) ?>"
                              data-bs-toggle="modal" data-bs-target="#testFormModal">
                        កែប្រែ
                      </button>
                      <button class="btn btn-sm btn-outline-danger btn-delete" 
                              data-id="<?= (int)$quiz['id'] ?>" 
                              data-title="<?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?>" 
                              data-bs-toggle="modal" data-bs-target="#deleteTestModal">
                        លុប
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </main>
    </div>

    <!-- Admin Test Modal -->
    <?php if ($isAdmin): ?>
      <div class="modal fade" id="testFormModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <form method="post" class="modal-content">
            <input type="hidden" name="action" id="test_form_action" value="add_test" />
            <input type="hidden" name="test_id" id="test_form_id" value="0" />
            <div class="modal-header">
              <h5 class="modal-title font-moul" id="testFormModalTitle">បន្ថែមវិញ្ញាសាថ្មី</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">ចំណងជើងវិញ្ញាសា</label>
                <input name="title" id="test_title" class="form-control" placeholder="ឧទាហរណ៍៖ វិញ្ញាសាទី១" required />
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">ជ្រើសរើសកម្រិតថ្នាក់</label>
                  <select name="qcm_grade" id="test_grade" class="form-select" required>
                    <option value="">-- ជ្រើសរើស --</option>
                    <option value="ថ្នាក់ទី៩">ថ្នាក់ទី៩</option>
                    <option value="ថ្នាក់ទី១២">ថ្នាក់ទី១២</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">អំពីវិញ្ញាសា</label>
                  <input name="qcm_paper" id="test_paper" class="form-control" placeholder="ឧ. ត្រៀបប្រឡងថ្នាក់ជាតិ" required />
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">លំហាត់</label>
                <textarea name="qcm_question" id="test_question" class="form-control" rows="4" placeholder="សូមបញ្ចូលសំណួរ ឬមាតិកាវិញ្ញាសាទីនេះ..." required></textarea>
                <div class="katex-preview mt-2" id="questionPreview"></div>
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">A</label>
                  <textarea name="qcm_option_a" id="test_option_a" class="form-control" rows="3" placeholder="ជម្រើស A" required></textarea>
                  <div class="katex-preview mt-2" data-preview-for="test_option_a"></div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">B</label>
                  <textarea name="qcm_option_b" id="test_option_b" class="form-control" rows="3" placeholder="ជម្រើស B" required></textarea>
                  <div class="katex-preview mt-2" data-preview-for="test_option_b"></div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">C</label>
                  <textarea name="qcm_option_c" id="test_option_c" class="form-control" rows="3" placeholder="ជម្រើស C" required></textarea>
                  <div class="katex-preview mt-2" data-preview-for="test_option_c"></div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">D</label>
                  <textarea name="qcm_option_d" id="test_option_d" class="form-control" rows="3" placeholder="ជម្រើស D" required></textarea>
                  <div class="katex-preview mt-2" data-preview-for="test_option_d"></div>
                </div>
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">ចម្លើយត្រឹមត្រូវ</label>
                  <select name="qcm_correct" id="test_correct" class="form-select">
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">ពិន្ទុ</label>
                  <input type="number" name="qcm_score" id="test_score" class="form-control" min="1" value="10" required />
                </div>
                <div class="col-md-3">
                  <label class="form-label">ពេលវេលា (នាទី)</label>
                  <input type="number" name="qcm_time_limit" id="test_time_limit" class="form-control" min="1" value="30" required />
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">ស្ថានភាព</label>
                <select name="status" id="test_status" class="form-select">
                  <option value="published">Published</option>
                  <option value="draft">Draft</option>
                </select>
              </div>
            </div>
            <div class="modal-footer justify-content-end">
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">បោះបង់</button>
                <button class="btn btn-ink rounded-pill px-4" id="test_form_submit">រក្សាទុក</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Admin Delete Confirmation Modal -->
      <div class="modal fade" id="deleteTestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <form method="post" class="modal-content">
            <input type="hidden" name="action" value="delete_test" />
            <input type="hidden" name="test_id" id="delete_test_id" />
            <div class="modal-header">
              <h5 class="modal-title font-moul">លុបវិញ្ញាសា</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <p>តើអ្នកពិតជាចង់លុបវិញ្ញាសា "<strong id="delete_test_title"></strong>" នេះមែនទេ? ការលុបនេះមិនអាចយកមកវិញបានឡើយ។</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">បោះបង់</button>
              <button class="btn btn-danger rounded-pill px-4">លុបវិញ្ញាសា</button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <footer>© 2026 រក្សាសិទ្ធិដោយ <b>Maths24h</b> · រៀបរៀងដោយ៖ នាង នី</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/katex.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/contrib/auto-render.min.js"></script>
    <script>
      const sidebar = document.getElementById("sidebar");
      const collapseBtn = document.getElementById("sideCollapse");
      const mobileToggle = document.getElementById("mobileToggle");
      const shouldOpenInlineForm = <?= !empty($_GET['show_inline']) ? 'true' : 'false' ?>;
      const initialPrefillPaper = <?= json_encode(trim((string) ($_GET['prefill_paper'] ?? '')), JSON_UNESCAPED_UNICODE) ?>;

      const savedState = localStorage.getItem("maths24h-sidebar");
      if (savedState === "collapsed") {
        sidebar.classList.add("collapsed");
        collapseBtn.textContent = "››";
      }

      collapseBtn.addEventListener("click", () => {
        sidebar.classList.toggle("collapsed");
        const collapsed = sidebar.classList.contains("collapsed");
        collapseBtn.textContent = collapsed ? "››" : "‹‹ បង្រួម";
        localStorage.setItem(
          "maths24h-sidebar",
          collapsed ? "collapsed" : "open",
        );
      });

      mobileToggle.addEventListener("click", () => {
        sidebar.classList.toggle("mobile-open");
      });

      <?php if ($isAdmin): ?>
      const renderMathPreview = (target, text) => {
        if (!target) return;
        const source = (text || '').trim();
        if (!source) {
          target.innerHTML = '<span class="text-muted">បញ្ចូលរូបមន្ត KaTeX</span>';
          return;
        }
        try {
          target.innerHTML = '';
          const rendered = document.createElement('div');
          rendered.className = 'math-render';
          rendered.textContent = source;
          target.appendChild(rendered);
          renderMathInElement(target, { delimiters: [ {left: '$$', right: '$$', display: true}, {left: '$', right: '$', display: false} ] });
        } catch (err) {
          target.innerHTML = '<span class="text-danger">មិនអាចបង្ហាញ KaTeX</span>';
        }
      };

      const subquestionLabels = ['ក', 'ខ', 'គ', 'ឃ', 'ង', 'ច', 'ឆ', 'ជ', 'ឈ', 'ញ', 'ដ', 'ឋ', 'ឌ', 'ណ', 'ត'];
      let subquestionCounter = 0;

      const addSubquestionBlock = (containerId, startIndex = 0) => {
        const list = document.getElementById(containerId);
        if (!list) return;
        const label = subquestionLabels[(subquestionCounter + startIndex) % subquestionLabels.length];
        subquestionCounter += 1;
        const block = document.createElement('div');
        block.className = 'subquestion-block mb-3';
        block.innerHTML = `
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="subquestion-label">${label}</span>
            <button type="button" class="btn btn-link btn-sm p-0 text-danger remove-subquestion" style="text-decoration:none;">ដកចេញ</button>
          </div>
          <input type="hidden" name="subquestion_label[]" value="${label}" />
          <textarea name="subquestion_text[]" class="form-control" rows="3" placeholder="សូមបញ្ចូលលំហាត់${label}..."></textarea>
        `;
        list.appendChild(block);
        block.querySelector('.remove-subquestion')?.addEventListener('click', () => {
          block.remove();
        });
      };

      const addInlineSubquestion = () => {
        addSubquestionBlock('inlineSubquestionList');
      };

      const initMathPreview = () => {
        const previewTargets = [
          { source: document.getElementById('test_question'), target: document.getElementById('questionPreview') },
          { source: document.getElementById('test_option_a'), target: document.querySelector('[data-preview-for="test_option_a"]') },
          { source: document.getElementById('test_option_b'), target: document.querySelector('[data-preview-for="test_option_b"]') },
          { source: document.getElementById('test_option_c'), target: document.querySelector('[data-preview-for="test_option_c"]') },
          { source: document.getElementById('test_option_d'), target: document.querySelector('[data-preview-for="test_option_d"]') },
          { source: document.getElementById('inline_test_question'), target: document.getElementById('inline_questionPreview') },
          { source: document.getElementById('inline_test_option_a'), target: document.querySelector('[data-preview-for="inline_test_option_a"]') },
          { source: document.getElementById('inline_test_option_b'), target: document.querySelector('[data-preview-for="inline_test_option_b"]') },
          { source: document.getElementById('inline_test_option_c'), target: document.querySelector('[data-preview-for="inline_test_option_c"]') },
          { source: document.getElementById('inline_test_option_d'), target: document.querySelector('[data-preview-for="inline_test_option_d"]') },
        ];
        previewTargets.forEach(({ source, target }) => {
          if (!source || !target) return;
          const update = () => renderMathPreview(target, source.value);
          source.addEventListener('input', update);
          source.addEventListener('keyup', update);
          update();
        });
      };

      const resetTestForm = () => {
        document.getElementById('test_form_action').value = 'add_test';
        document.getElementById('test_form_id').value = '0';
        document.getElementById('testFormModalTitle').textContent = 'បន្ថែមវិញ្ញាសាថ្មី';
        document.getElementById('test_form_submit').textContent = 'រក្សាទុក';
        document.getElementById('test_title').value = '';
        document.getElementById('test_grade').value = '';
        document.getElementById('test_paper').value = '';
        document.getElementById('test_question').value = '';
        document.getElementById('test_option_a').value = '';
        document.getElementById('test_option_b').value = '';
        document.getElementById('test_option_c').value = '';
        document.getElementById('test_option_d').value = '';
        document.getElementById('test_correct').value = 'A';
        document.getElementById('test_score').value = '10';
        document.getElementById('test_time_limit').value = '30';
        document.getElementById('test_status').value = 'published';
        document.getElementById('inline_test_title').value = '';
        document.getElementById('inline_test_grade').value = '';
        document.getElementById('inline_test_paper').value = '';
        document.getElementById('inline_test_question').value = '';
        document.getElementById('inline_test_option_a').value = '';
        document.getElementById('inline_test_option_b').value = '';
        document.getElementById('inline_test_option_c').value = '';
        document.getElementById('inline_test_option_d').value = '';
        document.getElementById('inline_test_correct').value = 'A';
        document.getElementById('inline_test_score').value = '10';
        document.getElementById('inline_test_time_limit').value = '30';
        document.getElementById('inline_test_status').value = 'published';
        document.getElementById('questionPreview').innerHTML = '<span class="text-muted">បញ្ចូលរូបមន្ត KaTeX</span>';
        document.getElementById('inline_questionPreview').innerHTML = '<span class="text-muted">បញ្ចូលរូបមន្ត KaTeX</span>';
        document.querySelectorAll('[data-preview-for]').forEach((preview) => {
          preview.innerHTML = '<span class="text-muted">បញ្ចូលរូបមន្ត KaTeX</span>';
        });
        document.querySelectorAll('textarea[id^="inline_test_option_"]').forEach((field) => {
          if (field.id === 'inline_test_option_a' || field.id === 'inline_test_option_b' || field.id === 'inline_test_option_c' || field.id === 'inline_test_option_d') {
            const preview = document.querySelector(`[data-preview-for="${field.id}"]`);
            if (preview) {
              preview.innerHTML = '<span class="text-muted">បញ្ចូលរូបមន្ត KaTeX</span>';
            }
          }
        });
      };

      if (shouldOpenInlineForm) {
        const panel = document.getElementById('inlineTestFormPanel');
        if (panel) {
          panel.style.display = 'block';
          if (initialPrefillPaper) {
            const paperField = document.getElementById('inline_test_paper');
            if (paperField) {
              paperField.value = initialPrefillPaper;
            }
          }
          panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }

      initMathPreview();
      document.getElementById('addInlineSubquestion')?.addEventListener('click', addInlineSubquestion);
      document.getElementById('openAddTestModal')?.addEventListener('click', resetTestForm);
      document.getElementById('showInlineTestForm')?.addEventListener('click', () => {
        const panel = document.getElementById('inlineTestFormPanel');
        if (panel) {
          panel.style.display = 'block';
          panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        resetTestForm();
      });
      document.getElementById('hideInlineTestForm')?.addEventListener('click', () => {
        const panel = document.getElementById('inlineTestFormPanel');
        if (panel) panel.style.display = 'none';
      });
      document.getElementById('clearInlineTestForm')?.addEventListener('click', () => {
        resetTestForm();
      });
      document.querySelectorAll('#inlineSubquestionList .remove-subquestion').forEach((btn) => {
        btn.addEventListener('click', () => btn.closest('.subquestion-block')?.remove());
      });

      document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', () => {
          document.getElementById('test_form_action').value = 'edit_test';
          document.getElementById('test_form_id').value = btn.getAttribute('data-id');
          document.getElementById('testFormModalTitle').textContent = 'កែប្រែវិញ្ញាសា';
          document.getElementById('test_form_submit').textContent = 'រក្សាទុកការផ្លាស់ប្ដូរ';
          document.getElementById('test_title').value = btn.getAttribute('data-title') || '';
          document.getElementById('test_grade').value = btn.getAttribute('data-grade') || '';
          document.getElementById('test_paper').value = btn.getAttribute('data-paper') || '';
          document.getElementById('test_question').value = btn.getAttribute('data-question') || '';
          document.getElementById('test_option_a').value = btn.getAttribute('data-option-a') || '';
          document.getElementById('test_option_b').value = btn.getAttribute('data-option-b') || '';
          document.getElementById('test_option_c').value = btn.getAttribute('data-option-c') || '';
          document.getElementById('test_option_d').value = btn.getAttribute('data-option-d') || '';
          document.getElementById('test_correct').value = btn.getAttribute('data-correct') || 'A';
          document.getElementById('test_score').value = btn.getAttribute('data-score') || '10';
          document.getElementById('test_time_limit').value = btn.getAttribute('data-time-limit') || '30';
          document.getElementById('test_status').value = btn.getAttribute('data-status') || 'published';
          document.querySelectorAll('textarea[id^="test_option_"]').forEach((field) => {
            const preview = document.querySelector(`[data-preview-for="${field.id}"]`);
            if (preview) {
              renderMathPreview(preview, field.value);
            }
          });
          renderMathPreview(document.getElementById('questionPreview'), document.getElementById('test_question').value);
        });
      });

      document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', () => {
          document.getElementById('delete_test_id').value = btn.getAttribute('data-id');
          document.getElementById('delete_test_title').textContent = btn.getAttribute('data-title');
        });
      });
      <?php endif; ?>
    </script>
  </body>
</html>
