<?php
require_once __DIR__ . '/database.php';
requireUserSession();

$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT r.*, c.name AS category_name, c.slug AS category_slug FROM records r LEFT JOIN categories c ON c.id = r.category_id WHERE r.id = ?');
$stmt->execute([$id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header('Location: index.php');
    exit;
}

// Redirect regular users if they try to access draft tests
$isAdmin = (($_SESSION['role'] ?? 'user') === 'admin');
if ($quiz['status'] === 'draft' && !$isAdmin) {
    header('Location: index.php');
    exit;
}

$quizPayload = null;
if (!empty($quiz['body'])) {
    $decodedPayload = json_decode($quiz['body'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedPayload)) {
        $quizPayload = $decodedPayload;
    }
}

$submission = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isAdmin) {
    $action = $_POST['action'] ?? '';
    if ($action === 'submit_test') {
        $submittedAnswer = trim($_POST['user_answer'] ?? '');
        $correctAnswer = $quizPayload['correct'] ?? '';
        $score = (int) ($quizPayload['score'] ?? 0);
        $awarded = ($submittedAnswer !== '' && $submittedAnswer === $correctAnswer) ? $score : 0;
        $submission = [
            'selected' => $submittedAnswer,
            'correct' => $correctAnswer,
            'score' => $score,
            'awarded' => $awarded,
        ];
        $_SESSION['quiz_results'][$quiz['id']] = $submission;
    }
}

if (!empty($_SESSION['quiz_results'][$quiz['id']] ?? null)) {
    $submission = $_SESSION['quiz_results'][$quiz['id']];
}
?>
<!doctype html>
<html lang="km">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?> — Maths24h</title>
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
      .quiz-main-title {
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

      /* ===== Quiz Display Paper ===== */
      .quiz-paper {
        background: var(--cream-card);
        border: 1.5px solid var(--paper-line);
        border-radius: 24px;
        padding: 2.5rem;
        position: relative;
        box-shadow: 0 14px 40px rgba(29, 43, 83, 0.1);
        min-height: 500px;
      }
      .quiz-paper::before {
        content: "";
        position: absolute;
        top: 0;
        left: 45px;
        width: 2px;
        height: 100%;
        background: rgba(178, 58, 47, 0.18);
      }
      .quiz-header-meta {
        border-bottom: 1.5px dashed var(--paper-line);
        padding-bottom: 1.2rem;
        margin-bottom: 2rem;
        padding-left: 1.5rem;
      }
      .quiz-body-content {
        font-size: 1.05rem;
        line-height: 1.8;
        color: var(--ink);
        padding-left: 1.5rem;
      }
      .question-block {
        border-left: 3px solid var(--gold);
        padding-left: 0.95rem;
        margin-bottom: 1.2rem;
      }
      .option-card {
        border: 1px solid var(--paper-line);
        border-radius: 12px;
        padding: 0.8rem 0.9rem;
        background: rgba(255,255,255,0.7);
      }
      .option-card.correct {
        border-color: var(--gold);
        background: rgba(200,155,60,0.12);
      }
      .math-render {
        display: inline-block;
        width: 100%;
      }
      .math-render .katex { font-size: 1.05em; }
      .result-badge {
        display: inline-block;
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
        font-weight: 700;
        background: rgba(29,43,83,.08);
        color: var(--ink);
      }
      .option-letter {
        font-weight: 700;
        color: var(--seal);
        margin-right: 0.4rem;
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
      @media print {
        body {
          background: #fff !important;
          color: #000 !important;
        }
        .topbar, .sidebar, .btn, .breadcrumb-custom, footer {
          display: none !important;
        }
        .main {
          padding: 0 !important;
        }
        .quiz-paper {
          box-shadow: none !important;
          border: none !important;
          padding: 0 !important;
        }
        .quiz-paper::before {
          display: none !important;
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
        <a href="category.php?slug=grade-9" class="side-link <?= $quiz['category_slug'] === 'grade-9' ? 'active' : '' ?>"
          ><span class="ic">📖</span><span class="side-label">គណិតវិទ្យាទី៩ (ឌីប្លូម)</span></a
        >
        <a href="category.php?slug=grade-12" class="side-link <?= $quiz['category_slug'] === 'grade-12' ? 'active' : '' ?>"
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
          <a href="index.php">ទំព័រដើម</a> &gt; 
          <a href="category.php?slug=<?= htmlspecialchars($quiz['category_slug'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($quiz['category_name'], ENT_QUOTES, 'UTF-8') ?></a> &gt; 
          <span><?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
          <a href="category.php?slug=<?= htmlspecialchars($quiz['category_slug'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-ink rounded-pill px-3">
            ← ត្រឡប់ក្រោយ
          </a>
          <button class="btn btn-ink rounded-pill px-4" onclick="window.print()">
            🖨️ បោះពុម្ពវិញ្ញាសា
          </button>
        </div>

        <div class="quiz-paper">
          <div class="quiz-header-meta">
            <div class="d-flex flex-wrap gap-2 mb-2">
              <span class="badge-status d-inline-block <?= $quiz['status'] === 'draft' ? 'badge-draft' : 'badge-published' ?>">
                <?= $quiz['status'] === 'draft' ? 'Draft' : 'Published' ?>
              </span>
              <?php if (!empty($quizPayload['score'])): ?>
                <span class="badge-status badge-published">📘 ពិន្ទុ: <?= (int) $quizPayload['score'] ?></span>
              <?php endif; ?>
              <?php if (!empty($quizPayload['time_limit_minutes'])): ?>
                <span class="badge-status badge-draft" id="quizCountdown">⏱️ <?= (int) $quizPayload['time_limit_minutes'] ?> នាទី</span>
              <?php endif; ?>
            </div>
            <h1 class="quiz-main-title h3 text-ink mb-1"><?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-muted mb-0">កម្រិតសិក្សា៖ <?= htmlspecialchars($quiz['category_name'], ENT_QUOTES, 'UTF-8') ?></p>
          </div>

          <?php if ($quizPayload): ?>
            <form method="post" class="quiz-body-content" id="quizForm">
              <input type="hidden" name="action" value="submit_test" />
              <input type="hidden" name="id" value="<?= (int) $quiz['id'] ?>" />
              <div class="question-block">
                <div class="fw-bold mb-2">សំណួរ</div>
                <div class="math-render" id="questionMathPreview">
                  <?= htmlspecialchars($quizPayload['question'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </div>
              </div>
              <div class="row g-3">
                <?php foreach ($quizPayload['options'] ?? [] as $letter => $option): ?>
                  <div class="col-md-6">
                    <label class="option-card d-block">
                      <input type="radio" class="me-2" name="user_answer" value="<?= htmlspecialchars($letter, ENT_QUOTES, 'UTF-8') ?>" <?= ($submission['selected'] ?? '') === $letter ? 'checked' : '' ?> <?= $submission ? 'disabled' : '' ?> />
                      <span class="option-letter"><?= htmlspecialchars($letter, ENT_QUOTES, 'UTF-8') ?>.</span>
                      <span class="math-render" data-math-source="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"></span>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
              <?php if (!$submission): ?>
                <button class="btn btn-ink rounded-pill px-4 mt-4">ដាក់ចម្លើយ</button>
              <?php endif; ?>
            </form>
            <?php if ($submission): ?>
              <?php
                $percent = !empty($quizPayload['score']) ? round((($submission['awarded'] ?? 0) / (int) $quizPayload['score']) * 100) : 0;
                $gradeLetter = 'F';
                if ($percent >= 90) {
                  $gradeLetter = 'A';
                } elseif ($percent >= 80) {
                  $gradeLetter = 'B';
                } elseif ($percent >= 70) {
                  $gradeLetter = 'C';
                } elseif ($percent >= 60) {
                  $gradeLetter = 'D';
                } elseif ($percent >= 50) {
                  $gradeLetter = 'E';
                }
              ?>
              <div class="alert alert-info rounded-3 mt-4">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                  <span class="result-badge">Grade: <?= htmlspecialchars($gradeLetter, ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="result-badge">ពិន្ទុ: <?= (int) ($submission['awarded'] ?? 0) ?>/<?= (int) ($quizPayload['score'] ?? 0) ?></span>
                </div>
                <div><strong>លទ្ធផល:</strong> <?= ($submission['awarded'] ?? 0) > 0 ? 'ត្រឹមត្រូវ' : 'មិនត្រឹមត្រូវ' ?></div>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="quiz-body-content">
              <?= nl2br(htmlspecialchars($quiz['body'], ENT_QUOTES, 'UTF-8')) ?>
            </div>
          <?php endif; ?>
        </div>
      </main>
    </div>

    <footer>© 2026 រក្សាសិទ្ធិដោយ <b>Maths24h</b> · រៀបរៀងដោយ៖ នាង នី</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/katex.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/contrib/auto-render.min.js"></script>
    <script>
      const sidebar = document.getElementById("sidebar");
      const collapseBtn = document.getElementById("sideCollapse");
      const mobileToggle = document.getElementById("mobileToggle");

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

      const countdownEl = document.getElementById("quizCountdown");
      const quizForm = document.getElementById("quizForm");
      const totalSeconds = <?= (int) (($quizPayload['time_limit_minutes'] ?? 0) * 60) ?>;
      if (countdownEl && totalSeconds > 0 && quizForm && !<?= $submission ? 'true' : 'false' ?>) {
        const deadline = Date.now() + totalSeconds * 1000;
        const tick = () => {
          const remaining = Math.max(0, Math.round((deadline - Date.now()) / 1000));
          const mins = Math.floor(remaining / 60);
          const secs = remaining % 60;
          countdownEl.textContent = `⏱️ ${mins}:${secs.toString().padStart(2, '0')} `;
          if (remaining <= 0) {
            quizForm.requestSubmit();
          }
        };
        tick();
        setInterval(tick, 1000);
      }

      document.querySelectorAll('.math-render').forEach((node) => {
        const raw = node.getAttribute('data-math-source') || node.textContent || '';
        if (!raw.trim()) {
          node.innerHTML = '';
          return;
        }
        node.innerHTML = raw;
      });
      if (window.renderMathInElement) {
        renderMathInElement(document.body, {
          delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '$', right: '$', display: false }
          ]
        });
      }
    </script>
  </body>
</html>
