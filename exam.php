<?php
session_start();
require_once __DIR__ . '/database.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$pdo = getConnection();

// Handle starting a new exam attempt (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exam_id'])) {
    $examId = (int)$_POST['exam_id'];
    
    // Fetch exam details to verify
    $stmt = $pdo->prepare('SELECT id, total_score, duration_minutes FROM exams WHERE id = ? AND is_published = 1 LIMIT 1');
    $stmt->execute([$examId]);
    $exam = $stmt->fetch();
    
    if (!$exam) {
        $_SESSION['flash'] = 'វិញ្ញាសានេះមិនមាន ឬមិនទាន់បានបោះពុម្ពផ្សាយឡើយ។';
        header('Location: dashboard.php');
        exit;
    }
    
    // Create new attempt record
    $insertStmt = $pdo->prepare('
        INSERT INTO exam_results (user_id, exam_id, score, total_score, started_at, status) 
        VALUES (?, ?, 0, ?, CURRENT_TIMESTAMP, "started")
    ');
    $insertStmt->execute([$userId, $examId, $exam['total_score']]);
    $resultId = (int)$pdo->lastInsertId();
    
    header("Location: exam.php?result_id={$resultId}");
    exit;
}

// Handle GET request to view/resume exam
$resultId = (int)($_GET['result_id'] ?? 0);
if ($resultId <= 0) {
    header('Location: dashboard.php');
    exit;
}

// Fetch exam result details
$stmt = $pdo->prepare('
    SELECT r.*, e.title, e.duration_minutes, e.total_score AS exam_total_score 
    FROM exam_results r
    JOIN exams e ON e.id = r.exam_id
    WHERE r.id = ? AND r.user_id = ? LIMIT 1
');
$stmt->execute([$resultId, $userId]);
$result = $stmt->fetch();

if (!$result) {
    $_SESSION['flash'] = 'រកមិនឃើញការប្រលងនេះទេ។';
    header('Location: dashboard.php');
    exit;
}

// If already submitted, redirect to result page
if ($result['status'] === 'submitted') {
    header("Location: result.php?result_id={$resultId}");
    exit;
}

// Calculate remaining time
$startedTime = strtotime($result['started_at']);
$durationSeconds = (int)$result['duration_minutes'] * 60;
$endTime = $startedTime + $durationSeconds;
$currentTime = time();
$timeLeft = $endTime - $currentTime;

// If time is up, force submit
$forceSubmit = false;
if ($timeLeft <= 0) {
    $forceSubmit = true;
}

// Process Exam Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam']) || $forceSubmit) {
    // 1. Fetch all questions for this exam
    $qStmt = $pdo->prepare('SELECT id, points FROM questions WHERE exam_id = ?');
    $qStmt->execute([(int)$result['exam_id']]);
    $questions = $qStmt->fetchAll();
    
    $totalEarnedPoints = 0;
    
    // Use transaction for consistency
    $pdo->beginTransaction();
    try {
        // Clear previous answers if any (to prevent duplicates)
        $clearStmt = $pdo->prepare('DELETE FROM student_answers WHERE exam_result_id = ?');
        $clearStmt->execute([$resultId]);
        
        foreach ($questions as $q) {
            $questionId = (int)$q['id'];
            $points = (int)$q['points'];
            
            // Get submitted choice
            $selectedChoiceId = isset($_POST["q_{$questionId}"]) ? (int)$_POST["q_{$questionId}"] : null;
            
            $isCorrect = 0;
            if ($selectedChoiceId !== null) {
                // Check if correct choice
                $cStmt = $pdo->prepare('SELECT is_correct FROM choices WHERE id = ? AND question_id = ? LIMIT 1');
                $cStmt->execute([$selectedChoiceId, $questionId]);
                $choice = $cStmt->fetch();
                if ($choice && (int)$choice['is_correct'] === 1) {
                    $isCorrect = 1;
                    $totalEarnedPoints += $points;
                }
            }
            
            // Insert student answer
            $ansStmt = $pdo->prepare('
                INSERT INTO student_answers (exam_result_id, question_id, choice_id, is_correct) 
                VALUES (?, ?, ?, ?)
            ');
            $ansStmt->execute([$resultId, $questionId, $selectedChoiceId, $isCorrect]);
        }
        
        // 2. Update exam result status
        $updateStmt = $pdo->prepare('
            UPDATE exam_results 
            SET score = ?, status = "submitted", submitted_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ');
        $updateStmt->execute([$totalEarnedPoints, $resultId]);
        
        $pdo->commit();
        
        header("Location: result.php?result_id={$resultId}");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'មានបញ្ហាបច្ចេកទេស៖ ' . $e->getMessage();
    }
}

// Fetch all questions and choices for rendering
$qStmt = $pdo->prepare('SELECT * FROM questions WHERE exam_id = ? ORDER BY id ASC');
$qStmt->execute([(int)$result['exam_id']]);
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
  <title>Maths KH | កំពុងធ្វើតេស្ត - <?php echo htmlspecialchars($result['title']); ?></title>
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
        <span class="user-info">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          <?php echo htmlspecialchars($_SESSION['user_name']); ?>
        </span>
      </div>
    </div>
  </header>

  <div class="led-ticker-wrapper">
    <div class="led-ticker-container">
      <div class="led-ticker-text"><?php echo htmlspecialchars($ledText); ?></div>
    </div>
  </div>

  <main class="container exam-wrapper">
    <!-- Sticky Timer Bar -->
    <div class="exam-sticky-bar">
      <div>
        <h2 style="font-size: 1.15rem; margin: 0;"><?php echo htmlspecialchars($result['title']); ?></h2>
        <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">សូមឆ្លើយឱ្យអស់សំណួរមុនពេលអស់ម៉ោង</p>
      </div>
      
      <div class="timer-pill" id="timer" 
           data-duration="<?php echo (int)$result['duration_minutes']; ?>" 
           data-result-id="<?php echo $resultId; ?>" 
           data-time-left="<?php echo $timeLeft; ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>--:--</span>
      </div>
    </div>

    <!-- Exam form -->
    <form id="exam-form" action="exam.php?result_id=<?php echo $resultId; ?>" method="post">
      <input type="hidden" name="submit_exam" value="1" />
      
      <?php 
      $qIndex = 1;
      foreach ($questionsData as $data): 
        $question = $data['question'];
        $choices = $data['choices'];
      ?>
        <div class="question-card" id="q-block-<?php echo $question['id']; ?>">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
            <span class="kh-font" style="font-weight: 700; color: var(--primary); font-size: 1.1rem;">សំណួរទី <?php echo $qIndex++; ?></span>
            <span style="font-size: 0.85rem; background: #f1f5f9; color: var(--text-muted); padding: 0.2rem 0.6rem; border-radius: var(--radius-sm); font-weight: 600;">
              <?php echo (int)$question['points']; ?> ពិន្ទុ
            </span>
          </div>
          
          <div class="question-text">
            <?php echo htmlspecialchars($question['question_text']); ?>
          </div>

          <?php if (!empty($question['image_path'])): ?>
            <div style="text-align: center;">
              <img src="<?php echo htmlspecialchars($question['image_path']); ?>" alt="សំណួររូបភាព" class="question-image" />
            </div>
          <?php endif; ?>

          <div class="option-list">
            <?php foreach ($choices as $choice): ?>
              <label class="option-label">
                <input type="radio" name="q_<?php echo (int)$question['id']; ?>" value="<?php echo (int)$choice['id']; ?>" />
                <span class="choice-content"><?php echo htmlspecialchars($choice['choice_text']); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <div style="margin-top: 2rem; display: flex; justify-content: space-between;">
        <a href="dashboard.php" class="btn btn-secondary" onclick="return confirm('តើអ្នកពិតជាចង់ត្រឡប់ក្រោយពីការប្រឡងឬទេ? ចម្លើយដែលបានបំពេញនឹងមិនទាន់រក្សាទុកឡើយ។')">ត្រឡប់ក្រោយ</a>
        <button type="submit" class="btn btn-primary" onclick="return confirm('តើអ្នកពិតជាចង់បញ្ចប់ និងដាក់ស្នើវិញ្ញាសានេះឬទេ?')">បញ្ចប់ការប្រលង</button>
      </div>
    </form>
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
