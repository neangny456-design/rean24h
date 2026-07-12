<?php
session_start();
require_once __DIR__ . '/database.php';

$pdo = getConnection();

$questionText = '';
$choices = [];
$explanation = '';
$points = 10;
$imagePath = '';
$examTitle = 'ការមើលសំណួរជាមុន';

$qId = (int)($_GET['id'] ?? 0);
if ($qId > 0) {
    // Load from DB
    $stmt = $pdo->prepare('SELECT q.*, e.title AS exam_title FROM questions q JOIN exams e ON e.id = q.exam_id WHERE q.id = ?');
    $stmt->execute([$qId]);
    $question = $stmt->fetch();
    if ($question) {
        $questionText = $question['question_text'];
        $explanation = $question['explanation'];
        $points = $question['points'];
        $imagePath = $question['image_path'];
        $examTitle = $question['exam_title'];
        
        $choiceStmt = $pdo->prepare('SELECT * FROM choices WHERE question_id = ? ORDER BY id ASC');
        $choiceStmt->execute([$qId]);
        $choices = $choiceStmt->fetchAll();
    }
} else {
    // Load from GET query params for real-time draft preview
    $questionText = $_GET['question'] ?? '';
    $explanation = $_GET['explanation'] ?? '';
    $points = (int)($_GET['points'] ?? 10);
    $correct = $_GET['correct'] ?? 'a';
    
    $choices = [
        ['choice_text' => $_GET['a'] ?? '', 'is_correct' => ($correct === 'a' ? 1 : 0)],
        ['choice_text' => $_GET['b'] ?? '', 'is_correct' => ($correct === 'b' ? 1 : 0)],
        ['choice_text' => $_GET['c'] ?? '', 'is_correct' => ($correct === 'c' ? 1 : 0)],
        ['choice_text' => $_GET['d'] ?? '', 'is_correct' => ($correct === 'd' ? 1 : 0)],
    ];
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Maths KH | ការមើលសំណួរជាមុន</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <!-- KaTeX CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css" />
</head>
<body style="background: #f8fafc;">
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
        <a href="admin/questions.php">ត្រឡប់ទៅការគ្រប់គ្រងសំណួរ</a>
      </div>
    </div>
  </header>

  <main class="container exam-wrapper" style="padding-top: 3rem; max-width: 800px;">
    <div class="result-header-card" style="padding: 2rem; margin-bottom: 2rem; text-align: center;">
      <span class="grade-badge" style="font-size: 0.85rem; padding: 0.3rem 0.8rem; margin-bottom: 1rem;">ការមើលសំណួរជាមុន (Draft Preview)</span>
      <h1 style="margin: 0.5rem 0; font-size: 1.8rem; color: var(--secondary);"><?php echo htmlspecialchars($examTitle); ?></h1>
      <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.5rem 0 0;">សំណួរនេះនឹងបង្ហាញជូនសិស្សដូចខាងក្រោម</p>
    </div>

    <!-- Question Card -->
    <div class="question-card" style="border-left: 5px solid var(--primary); background: white; padding: 2rem; border-radius: var(--radius-md); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
        <span class="kh-font" style="font-weight: 700; color: var(--secondary); font-size: 1.1rem;">ខ្លឹមសារសំណួរ</span>
        <span style="font-size: 0.85rem; background: #f1f5f9; color: var(--text-muted); padding: 0.2rem 0.6rem; border-radius: var(--radius-sm); font-weight: 600;">
          <?php echo (int)$points; ?> ពិន្ទុ
        </span>
      </div>

      <div class="question-text" style="font-size: 1.2rem; line-height: 1.6; margin-bottom: 1.5rem; color: var(--text);">
        <?php echo htmlspecialchars($questionText ?: 'គ្មានខ្លឹមសារសំណួរឡើយ។'); ?>
      </div>

      <?php if (!empty($imagePath)): ?>
        <div style="text-align: center; margin: 1.5rem 0;">
          <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="សំណួររូបភាព" class="question-image" style="max-width: 100%; max-height: 250px; border-radius: var(--radius-md);" />
        </div>
      <?php endif; ?>

      <!-- Choices Layout -->
      <div class="option-list" style="margin-top: 1.5rem;">
        <?php 
        $labels = ['A', 'B', 'C', 'D'];
        foreach ($choices as $index => $choice): 
          $isCorrectChoice = (int)($choice['is_correct'] ?? 0) === 1;
          $statusStyle = '';
          $badge = '';
          if ($isCorrectChoice) {
            $statusStyle = 'style="border-color: var(--accent); background: rgba(16, 185, 129, 0.06); color: #065f46; font-weight: 600;"';
            $badge = '<span style="color: var(--accent); margin-left: auto; font-weight: 700;">✓ ចម្លើយត្រឹមត្រូវ</span>';
          }
        ?>
          <div class="option-label" <?php echo $statusStyle; ?> style="cursor: default; display: flex; align-items: center; padding: 0.8rem 1rem; border: 1.5px solid var(--card-border); border-radius: var(--radius-md); margin-bottom: 0.75rem;">
            <span style="font-weight: 700; margin-right: 0.5rem; color: var(--primary);"><?php echo $labels[$index]; ?>.</span>
            <span class="choice-content" style="font-size: 1.05rem;"><?php echo htmlspecialchars($choice['choice_text'] ?: 'គ្មានចម្លើយ'); ?></span>
            <?php echo $badge; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Explanation box -->
      <?php if (!empty($explanation)): ?>
        <div class="explanation-box" style="margin-top: 2rem; padding: 1.25rem; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: var(--radius-md);">
          <div class="explanation-title" style="font-weight: 700; color: #92400e; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.3rem;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 01-2 2h0a2 2 0 01-2-2v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            គន្លឹះដោះស្រាយ៖
          </div>
          <div class="explanation-content" style="color: #78350f; font-size: 1rem; line-height: 1.5;">
            <?php echo htmlspecialchars($explanation); ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div style="margin-top: 2.5rem; text-align: center; display: flex; justify-content: center; gap: 1rem;">
      <button onclick="window.close()" class="btn btn-secondary" style="padding: 0.75rem 2rem;">បិទផ្ទាំងនេះ</button>
      <a href="admin/questions.php" class="btn btn-primary" style="padding: 0.75rem 2rem;">ត្រឡប់ទៅផ្ទាំងគ្រប់គ្រង</a>
    </div>
  </main>

  <footer class="app-footer" style="margin-top: 5rem;">
    <div class="container">
      <p>&copy; 2026 Maths KH. រៀបចំឡើងសម្រាប់សិស្សថ្នាក់ទី៩ និងថ្នាក់ទី១២។</p>
    </div>
  </footer>

  <!-- KaTeX and client logic -->
  <script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (window.renderMathInElement) {
        renderMathInElement(document.body, {
          delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '$', right: '$', display: false },
            { left: '\\(', right: '\\)', display: false },
            { left: '\\[', right: '\\]', display: true }
          ],
          throwOnError: false
        });
      }
    });
  </script>
</body>
</html>
