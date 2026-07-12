<?php
session_start();
require_once __DIR__ . '/database.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$resultId = (int)($_GET['result_id'] ?? 0);
if ($resultId <= 0) {
    header('Location: dashboard.php');
    exit;
}

$pdo = getConnection();

// Fetch the attempt result details
$stmt = $pdo->prepare('
    SELECT r.*, e.title, e.grade_level, e.exam_type, e.duration_minutes
    FROM exam_results r
    JOIN exams e ON e.id = r.exam_id
    WHERE r.id = ? AND r.user_id = ? LIMIT 1
');
$stmt->execute([$resultId, $userId]);
$result = $stmt->fetch();

if (!$result) {
    $_SESSION['flash'] = 'រកមិនឃើញលទ្ធផលនេះទេ។';
    header('Location: dashboard.php');
    exit;
}

// If attempt is not submitted yet, redirect to resume exam
if ($result['status'] !== 'submitted') {
    header("Location: exam.php?result_id={$resultId}");
    exit;
}

// Calculate duration taken
$started = strtotime($result['started_at']);
$submitted = strtotime($result['submitted_at']);
$timeTakenSeconds = $submitted - $started;
$timeTakenMin = floor($timeTakenSeconds / 60);
$timeTakenSec = $timeTakenSeconds % 60;
$timeTakenStr = ($timeTakenMin > 0 ? "{$timeTakenMin} នាទី " : "") . "{$timeTakenSec} វិនាទី";

// Calculate performance values
$score = (int)$result['score'];
$totalScore = (int)$result['total_score'];
$percent = $totalScore > 0 ? round(($score / $totalScore) * 100) : 0;

$feedback = 'ព្យាយាមបន្ថែមទៀត! កុំបោះបង់ការខិតខំប្រឹងប្រែង។';
$feedbackClass = 'low';
if ($percent >= 80) {
    $feedback = 'អស្ចារ្យណាស់! អ្នកបានយល់ដឹងច្បាស់លាស់ពីមេរៀន។';
    $feedbackClass = 'high';
} elseif ($percent >= 50) {
    $feedback = 'ល្អបង្គួរ! អ្នកអាចធ្វើបានកាន់តែល្អជាងនេះក្នុងការសាកល្បងក្រោយ។';
    $feedbackClass = 'mid';
}

// Fetch all questions, choices and student answers for review
$qStmt = $pdo->prepare('
    SELECT q.*, a.choice_id AS student_choice_id, a.is_correct AS answer_is_correct
    FROM questions q
    LEFT JOIN student_answers a ON a.question_id = q.id AND a.exam_result_id = ?
    WHERE q.exam_id = ?
    ORDER BY q.id ASC
');
$qStmt->execute([$resultId, (int)$result['exam_id']]);
$questions = $qStmt->fetchAll();

$reviewData = [];
foreach ($questions as $q) {
    $cStmt = $pdo->prepare('SELECT * FROM choices WHERE question_id = ? ORDER BY id ASC');
    $cStmt->execute([(int)$q['id']]);
    $choices = $cStmt->fetchAll();
    
    $reviewData[] = [
        'question' => $q,
        'choices' => $choices
    ];
}

try {
    $ledTextStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'led_text' LIMIT 1");
    $ledText = $ledTextStmt->fetchColumn();
    if (!$ledText) {
        $ledText = "សូមស្វាគមន៍មកកាន់ប្រព័ន្ធតេស្តគណិតវិទ្យា Maths KH! ត្រៀមប្រលងឌីប្លូម និងបាក់ឌុបសាកល្បង។";
    }
} catch (Exception $e) {
    $ledText = "សូមស្វាគមន៍មកកាន់ប្រព័ន្ធតេស្តគណិតវិទ្យា Maths KH! ត្រៀមប្រលងឌីប្លូម និងបាក់ឌុបសាកល្បង។";
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Maths KH | លទ្ធផលតេស្ត - <?php echo htmlspecialchars($result['title']); ?></title>
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
        <a href="dashboard.php">ផ្ទាំងសិក្សា</a>
        <a href="logout.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">ចាកចេញ</a>
      </div>
    </div>
  </header>

  <div class="led-ticker-wrapper">
    <div class="led-ticker-container">
      <div class="led-ticker-text"><?php echo htmlspecialchars($ledText); ?></div>
    </div>
  </div>

  <main class="container exam-wrapper" style="padding-top: 2rem;">
    <!-- Result Header Dashboard Card -->
    <div class="result-header-card" style="--percentage: <?php echo $percent; ?>;">
      <div class="circular-progress">
        <div class="circular-progress-val"><?php echo $percent; ?>%</div>
      </div>
      
      <span class="grade-badge" style="font-size: 0.9rem; padding: 0.3rem 0.8rem; margin-bottom: 1rem;"><?php echo htmlspecialchars($result['grade_level']); ?> (<?php echo htmlspecialchars($result['exam_type']); ?>)</span>
      <h1 style="margin: 0.5rem 0 1rem; font-size: 2rem; color: var(--secondary);"><?php echo htmlspecialchars($result['title']); ?></h1>
      
      <p class="kh-font" style="font-size: 1.15rem; font-weight: 600; color: var(--secondary); margin-bottom: 0.5rem;">
        លទ្ធផល៖ <span class="score-badge <?php echo $feedbackClass; ?>" style="font-size: 1.1rem; padding: 0.3rem 0.9rem;"><?php echo $score; ?> / <?php echo $totalScore; ?> ពិន្ទុ</span>
      </p>
      
      <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem;">
        ពេលវេលាដោះស្រាយ៖ <strong><?php echo $timeTakenStr; ?></strong> / កំណត់ម៉ោង៖ <strong><?php echo (int)$result['duration_minutes']; ?> នាទី</strong>
      </p>

      <div style="background: rgba(0,0,0,0.02); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--card-border); max-width: 500px; margin: 0 auto 2rem;">
        <p style="font-weight: 600; color: var(--secondary); margin: 0; font-size: 1rem;"><?php echo $feedback; ?></p>
      </div>

      <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
        <a href="dashboard.php" class="btn btn-secondary">ត្រឡប់ទៅផ្ទាំងគ្រប់គ្រង</a>
        <a href="certificate.php?result_id=<?php echo $resultId; ?>" class="btn btn-primary">សញ្ញាបត្រា</a>
        <form action="exam.php" method="post" style="display: inline;">
          <input type="hidden" name="exam_id" value="<?php echo (int)$result['exam_id']; ?>" />
          <button type="submit" class="btn btn-primary">ធ្វើតេស្តឡើងវិញ</button>
        </form>
      </div>
    </div>

    <!-- Review Section Header -->
    <h2 class="kh-font" style="margin: 3rem 0 1.5rem; font-size: 1.6rem; color: var(--secondary); border-bottom: 2px solid var(--card-border); padding-bottom: 0.5rem;">
      ពិនិត្យ និងកែតម្រូវចម្លើយឡើងវិញ
    </h2>

    <!-- Detailed Review List -->
    <?php 
    $qIndex = 1;
    foreach ($reviewData as $data):
      $question = $data['question'];
      $choices = $data['choices'];
      $isCorrect = (int)$question['answer_is_correct'] === 1;
      $studentChoiceId = $question['student_choice_id'];
    ?>
      <div class="question-card" style="border-left: 5px solid <?php echo $isCorrect ? 'var(--accent)' : 'var(--danger)'; ?>;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
          <span class="kh-font" style="font-weight: 700; color: var(--secondary); font-size: 1.1rem;">សំណួរទី <?php echo $qIndex++; ?></span>
          
          <?php if ($studentChoiceId === null): ?>
            <span class="score-badge low" style="font-size: 0.8rem; padding: 0.2rem 0.6rem; border-radius: var(--radius-sm);">មិនបានឆ្លើយ (០/<?php echo (int)$question['points']; ?> ពិន្ទុ)</span>
          <?php elseif ($isCorrect): ?>
            <span class="score-badge high" style="font-size: 0.8rem; padding: 0.2rem 0.6rem; border-radius: var(--radius-sm);">ត្រឹមត្រូវ (+<?php echo (int)$question['points']; ?> ពិន្ទុ)</span>
          <?php else: ?>
            <span class="score-badge low" style="font-size: 0.8rem; padding: 0.2rem 0.6rem; border-radius: var(--radius-sm);">មិនត្រឹមត្រូវ (០/<?php echo (int)$question['points']; ?> ពិន្ទុ)</span>
          <?php endif; ?>
        </div>

        <div class="question-text" style="font-size: 1.15rem;">
          <?php echo htmlspecialchars($question['question_text']); ?>
        </div>

        <?php if (!empty($question['image_path'])): ?>
          <div style="text-align: center;">
            <img src="<?php echo htmlspecialchars($question['image_path']); ?>" alt="សំណួររូបភាព" class="question-image" />
          </div>
        <?php endif; ?>

        <!-- Choices Highlight Review -->
        <div class="option-list">
          <?php foreach ($choices as $choice): 
            $isCorrectChoice = (int)$choice['is_correct'] === 1;
            $isStudentSelected = (int)$choice['id'] === (int)$studentChoiceId;
            
            $statusClass = '';
            $icon = '';
            if ($isCorrectChoice) {
              $statusClass = 'style="border-color: var(--accent); background: rgba(16, 185, 129, 0.06); color: #065f46; font-weight: 600;"';
              $icon = '<span style="color: var(--accent); margin-left: auto; font-weight: 700;">✓ ចម្លើយត្រឹមត្រូវ</span>';
            } elseif ($isStudentSelected && !$isCorrectChoice) {
              $statusClass = 'style="border-color: var(--danger); background: rgba(239, 68, 68, 0.06); color: #991b1b; font-weight: 600;"';
              $icon = '<span style="color: var(--danger); margin-left: auto; font-weight: 700;">✗ ចម្លើយរបស់អ្នក</span>';
            } elseif ($isStudentSelected) {
              $icon = '<span style="color: var(--accent); margin-left: auto; font-weight: 700;">✓ ចម្លើយរបស់អ្នក</span>';
            }
          ?>
            <div class="option-label" <?php echo $statusClass; ?> style="cursor: default;">
              <input type="radio" disabled <?php echo $isStudentSelected ? 'checked' : ''; ?> style="accent-color: <?php echo $isCorrectChoice ? 'var(--accent)' : 'var(--danger)'; ?>;" />
              <span class="choice-content"><?php echo htmlspecialchars($choice['choice_text']); ?></span>
              <?php echo $icon; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Explanation box -->
        <?php if (!empty($question['explanation'])): ?>
          <div class="explanation-box" style="margin-top: 1.5rem;">
            <div class="explanation-title">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-right: 0.2rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 01-2 2h0a2 2 0 01-2-2v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
              ការពន្យល់ដោះស្រាយ៖
            </div>
            <div class="explanation-content">
              <?php echo htmlspecialchars($question['explanation']); ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <div style="margin-top: 3rem; text-align: center;">
      <a href="dashboard.php" class="btn btn-primary">ត្រឡប់ទៅផ្ទាំងសិក្សាសិស្ស</a>
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
