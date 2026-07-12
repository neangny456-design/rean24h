<?php
session_start();
require_once __DIR__ . '/database.php';

$examId = (int)($_GET['exam_id'] ?? 0);
if ($examId <= 0) {
    header('Location: index.php');
    exit;
}

$pdo = getConnection();

// Fetch exam details
$stmt = $pdo->prepare('
    SELECT e.*, s.name AS subject_name, (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) AS question_count
    FROM exams e
    JOIN subjects s ON s.id = e.subject_id
    WHERE e.id = ? LIMIT 1
');
$stmt->execute([$examId]);
$exam = $stmt->fetch();

if (!$exam) {
    $_SESSION['flash'] = 'រកមិនឃើញវិញ្ញាសានេះទេ។';
    header('Location: index.php');
    exit;
}

// Fetch LED Text
try {
    $ledTextStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'led_text' LIMIT 1");
    $ledText = $ledTextStmt->fetchColumn();
    if (!$ledText) {
        $ledText = "សូមស្វាគមន៍មកកាន់ប្រព័ន្ធតេស្តគណិតវិទ្យា Maths KH! ត្រៀមប្រលងឌីប្លូម និងបាក់ឌុបសាកល្បង។";
    }
} catch (Exception $e) {
    $ledText = "សូមស្វាគមន៍មកកាន់ប្រព័ន្ធតេស្តគណិតវិទ្យា Maths KH! ត្រៀមប្រលងឌីប្លូម និងបាក់ឌុបសាកល្បង។";
}

// Fetch all questions and choices
$qStmt = $pdo->prepare('SELECT * FROM questions WHERE exam_id = ? ORDER BY id ASC');
$qStmt->execute([$examId]);
$questions = $qStmt->fetchAll();

$questionsData = [];
foreach ($questions as $q) {
    $cStmt = $pdo->prepare('SELECT * FROM choices WHERE question_id = ? ORDER BY id ASC');
    $cStmt->execute([(int)$q['id']]);
    $choices = $cStmt->fetchAll();
    
    $questionsData[] = [
        'question' => $q,
        'choices' => $choices
    ];
}

$isLoggedIn = isset($_SESSION['user_id']);
$isAdminLoggedIn = isset($_SESSION['admin_id']);
?>
<!DOCTYPE html>
<html lang="km">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Maths KH | មើលវិញ្ញាសាជាមុន - <?php echo htmlspecialchars($exam['title']); ?></title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <!-- KaTeX CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css" />
</head>
<body>
  <header class="app-header">
    <div class="container navbar">
      <a class="brand" href="index.php">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color: var(--primary);">
          <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
          <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
        </svg>
        <span>Maths KH</span>
      </a>
      <div class="nav-links">
        <a href="index.php">ទំព័រដើម</a>
        <?php if ($isLoggedIn): ?>
          <a href="dashboard.php">ផ្ទាំងសិក្សា</a>
        <?php elseif ($isAdminLoggedIn): ?>
          <a href="admin/index.php">ផ្ទាំងអ្នកគ្រប់គ្រង</a>
        <?php else: ?>
          <a href="login.php">ចូលគណនី</a>
          <a href="register.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">ចុះឈ្មោះ</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <div class="led-ticker-wrapper">
    <div class="led-ticker-container">
      <div class="led-ticker-text"><?php echo htmlspecialchars($ledText); ?></div>
    </div>
  </div>

  <main class="container exam-wrapper" style="padding-top: 2rem;">
    <!-- Preview Header Card -->
    <div class="result-header-card" style="padding: 2.5rem; margin-bottom: 2rem;">
      <span class="grade-badge" style="font-size: 0.85rem; padding: 0.3rem 0.8rem; margin-bottom: 1rem;"><?php echo htmlspecialchars($exam['grade_level']); ?> (<?php echo htmlspecialchars($exam['exam_type'] === 'Internal' ? 'ថ្នាក់ក្នុង' : 'ថ្នាក់ក្រៅ'); ?>)</span>
      <h1 style="margin: 0.5rem 0 1rem; font-size: 2rem; color: var(--secondary);"><?php echo htmlspecialchars($exam['title']); ?></h1>
      
      <?php if (!empty($exam['image_path'])): ?>
        <div style="margin: 1rem 0 1.5rem; text-align: center;">
          <img src="<?php echo htmlspecialchars($exam['image_path']); ?>" alt="Exam Banner" style="width: 100%; max-height: 260px; object-fit: cover; border-radius: 1rem; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);" />
        </div>
      <?php endif; ?>
      <div style="display: flex; justify-content: center; gap: 1.5rem; color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <span style="display: inline-flex; align-items: center; gap: 0.3rem;">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          រយៈពេល៖ <strong><?php echo (int)$exam['duration_minutes']; ?> នាទី</strong>
        </span>
        <span style="display: inline-flex; align-items: center; gap: 0.3rem;">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          សំណួរសរុប៖ <strong><?php echo (int)$exam['question_count']; ?></strong>
        </span>
        <span style="display: inline-flex; align-items: center; gap: 0.3rem;">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
          ពិន្ទុសរុប៖ <strong><?php echo (int)$exam['total_score']; ?></strong>
        </span>
      </div>

      <div style="display: flex; gap: 1rem; justify-content: center;">
        <?php if ($isLoggedIn): ?>
          <form action="exam.php" method="post" style="display: inline;">
            <input type="hidden" name="exam_id" value="<?php echo $examId; ?>" />
            <button type="submit" class="btn btn-primary">ចាប់ផ្តើមធ្វើតេស្តពិតប្រាកដ</button>
          </form>
          <a href="dashboard.php" class="btn btn-secondary">ត្រឡប់ទៅផ្ទាំងសិក្សា</a>
        <?php else: ?>
          <a href="login.php" class="btn btn-primary">ចូលគណនីដើម្បីធ្វើតេស្ត</a>
          <a href="index.php" class="btn btn-secondary">ត្រឡប់ទៅទំព័រដើម</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Questions Preview List -->
    <h2 class="kh-font" style="margin: 3rem 0 1.5rem; font-size: 1.5rem; color: var(--secondary); border-bottom: 2px solid var(--card-border); padding-bottom: 0.5rem;">
      ពិនិត្យខ្លឹមសារសំណួរ និងចម្លើយត្រឹមត្រូវ (គន្លឹះដោះស្រាយ)
    </h2>

    <?php 
    $qIndex = 1;
    foreach ($questionsData as $data):
      $question = $data['question'];
      $choices = $data['choices'];
    ?>
      <div class="question-card" style="border-left: 5px solid var(--primary);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
          <span class="kh-font" style="font-weight: 700; color: var(--secondary); font-size: 1.1rem;">សំណួរទី <?php echo $qIndex++; ?></span>
          <span style="font-size: 0.85rem; background: #f1f5f9; color: var(--text-muted); padding: 0.2rem 0.6rem; border-radius: var(--radius-sm); font-weight: 600;">
            <?php echo (int)$question['points']; ?> ពិន្ទុ
          </span>
        </div>

        <div class="question-text" style="font-size: 1.15rem;">
          <?php echo htmlspecialchars($question['question_text']); ?>
        </div>

        <?php if (!empty($question['image_path'])): ?>
          <div style="text-align: center;">
            <img src="<?php echo htmlspecialchars($question['image_path']); ?>" alt="សំណួររូបភាព" class="question-image" />
          </div>
        <?php endif; ?>

        <!-- Choices Layout with Correct Choice Highlighted -->
        <div class="option-list">
          <?php foreach ($choices as $choice): 
            $isCorrectChoice = (int)$choice['is_correct'] === 1;
            
            $statusStyle = '';
            $badge = '';
            if ($isCorrectChoice) {
              $statusStyle = 'style="border-color: var(--accent); background: rgba(16, 185, 129, 0.06); color: #065f46; font-weight: 600;"';
              $badge = '<span style="color: var(--accent); margin-left: auto; font-weight: 700;">✓ ចម្លើយត្រឹមត្រូវ</span>';
            }
          ?>
            <div class="option-label" <?php echo $statusStyle; ?> style="cursor: default;">
              <input type="radio" disabled <?php echo $isCorrectChoice ? 'checked' : ''; ?> style="accent-color: var(--accent);" />
              <span class="choice-content"><?php echo htmlspecialchars($choice['choice_text']); ?></span>
              <?php echo $badge; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Explanation box -->
        <?php if (!empty($question['explanation'])): ?>
          <div class="explanation-box" style="margin-top: 1.5rem;">
            <div class="explanation-title">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-right: 0.2rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 01-2 2h0a2 2 0 01-2-2v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
              គន្លឹះដោះស្រាយ៖
            </div>
            <div class="explanation-content">
              <?php echo htmlspecialchars($question['explanation']); ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <div style="margin-top: 3rem; text-align: center;">
      <?php if ($isLoggedIn): ?>
        <a href="dashboard.php" class="btn btn-primary">ត្រឡប់ទៅផ្ទាំងសិក្សាសិស្ស</a>
      <?php else: ?>
        <a href="index.php" class="btn btn-primary">ត្រឡប់ទៅទំព័រដើម</a>
      <?php endif; ?>
    </div>
  </main>

  <footer class="app-footer">
    <div class="container">
      <p>&copy; 2026 Maths KH. រៀបចំឡើងសម្រាប់សិស្សថ្នាក់ទី៩ និងថ្នាក់ទី១២។</p>
    </div>
  </footer>

  <!-- KaTeX and client logic -->
  <script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js"></script>
  <script src="assets/js/main.js"></script>
</body>
</html>
