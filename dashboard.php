<?php
session_start();
require_once __DIR__ . '/database.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$pdo = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_history') {
    $attemptId = filter_input(INPUT_POST, 'attempt_id', FILTER_VALIDATE_INT);
    $grade = $_POST['grade'] ?? '';
    $category = $_POST['category'] ?? '';

    if ($attemptId) {
        $deleteStmt = $pdo->prepare('DELETE FROM exam_results WHERE id = ? AND user_id = ?');
        $deleteStmt->execute([$attemptId, $userId]);

        $_SESSION['flash'] = $deleteStmt->rowCount()
            ? 'ប្រវត្តិការប្រលងត្រូវបានលុបជោគជ័យ។'
            : 'មិនអាចលុបប្រវត្តិការប្រលងបានទេ។';
    } else {
        $_SESSION['flash'] = 'ព័ត៌មានមិនត្រឹមត្រូវសម្រាប់ការលុប។';
    }

    $redirectUrl = 'dashboard.php';
    $query = [];
    if ($grade !== '') {
        $query[] = 'grade=' . urlencode($grade);
    }
    if ($category !== '') {
        $query[] = 'category=' . urlencode($category);
    }
    if (!empty($query)) {
        $redirectUrl .= '?' . implode('&', $query);
    }

    header('Location: ' . $redirectUrl);
    exit;
}

// Get student details
$stmt = $pdo->prepare('SELECT full_name, email, grade_level FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    // User does not exist, logout
    header('Location: logout.php');
    exit;
}

// Check selected filter grade (defaults to user's registered grade, otherwise Grade 9 if empty)
$selectedGrade = $_GET['grade'] ?? $user['grade_level'] ?? 'Grade 9';
if ($selectedGrade !== 'Grade 9' && $selectedGrade !== 'Grade 12') {
    $selectedGrade = 'Grade 9';
}

$selectedCategory = $_GET['category'] ?? 'External';
if ($selectedCategory !== 'Internal' && $selectedCategory !== 'External') {
    $selectedCategory = 'External';
}

// Fetch exams matching selected grade and category (In/Out)
if ($selectedCategory === 'Internal') {
    $examStmt = $pdo->prepare('
        SELECT e.*, s.name AS subject_name, (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) AS question_count
        FROM exams e 
        JOIN subjects s ON s.id = e.subject_id 
        WHERE e.grade_level = ? AND e.is_published = 1 AND e.exam_type IN ("Internal", "ថ្នាក់ក្នុង")
        ORDER BY e.id DESC
    ');
} else {
    $examStmt = $pdo->prepare('
        SELECT e.*, s.name AS subject_name, (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) AS question_count
        FROM exams e 
        JOIN subjects s ON s.id = e.subject_id 
        WHERE e.grade_level = ? AND e.is_published = 1 AND e.exam_type NOT IN ("Internal", "ថ្នាក់ក្នុង")
        ORDER BY e.id DESC
    ');
}
$examStmt->execute([$selectedGrade]);
$exams = $examStmt->fetchAll();

// Fetch past exam attempts for this user
$historyStmt = $pdo->prepare('
    SELECT r.*, e.title AS exam_title, e.grade_level AS exam_grade
    FROM exam_results r
    JOIN exams e ON e.id = r.exam_id
    WHERE r.user_id = ?
    ORDER BY r.id DESC
');
$historyStmt->execute([$userId]);
$attempts = $historyStmt->fetchAll();

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
  <title>Maths KH | ផ្ទាំងសិក្សាសិស្ស</title>
  <link rel="stylesheet" href="assets/css/style.css" />
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
        <a href="dashboard.php" style="color: var(--primary);">ផ្ទាំងសិក្សា</a>
        <span class="user-info">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['grade_level'] ?: 'គ្មានថ្នាក់'); ?>)
        </span>
        <a href="logout.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">ចាកចេញ</a>
      </div>
    </div>
  </header>

  <div class="led-ticker-wrapper">
    <div class="led-ticker-container">
      <div class="led-ticker-text"><?php echo htmlspecialchars($ledText); ?></div>
    </div>
  </div>

  <main class="container dashboard-wrapper">
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash'])): ?>
      <div class="alert alert-success" style="margin-bottom: 2rem;">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span><?php echo htmlspecialchars($_SESSION['flash']); ?></span>
      </div>
      <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="dashboard-header">
      <div>
        <h1>ផ្ទាំងសិក្សាសិស្ស</h1>
        <p style="color: var(--text-muted);">ជ្រើសរើសវិញ្ញាសាអនុវត្ត ដើម្បីវាស់ស្ទង់សមត្ថភាពគណិតវិទ្យា</p>
      </div>
      
      <!-- Grade Filter Switcher -->
      <div style="display: flex; gap: 0.5rem; background: rgba(0,0,0,0.04); padding: 0.3rem; border-radius: var(--radius-md);">
        <a href="dashboard.php?grade=Grade+9&category=<?php echo urlencode($selectedCategory); ?>" class="btn <?php echo $selectedGrade === 'Grade 9' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 0.5rem 1.2rem; border-radius: calc(var(--radius-md) - 4px); font-size: 0.95rem; box-shadow: none;">ថ្នាក់ទី ៩ (ឌីប្លូម)</a>
        <a href="dashboard.php?grade=Grade+12&category=<?php echo urlencode($selectedCategory); ?>" class="btn <?php echo $selectedGrade === 'Grade 12' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 0.5rem 1.2rem; border-radius: calc(var(--radius-md) - 4px); font-size: 0.95rem; box-shadow: none;">ថ្នាក់ទី ១២ (បាក់ឌុប)</a>
      </div>
    </div>

    <div class="dashboard-grid">
      <!-- Left Column: Available Exams -->
      <div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
          <h2 class="kh-font" style="margin: 0; font-size: 1.5rem; color: var(--secondary);">
            វិញ្ញាសាដែលមានសម្រាប់ <?php echo $selectedGrade === 'Grade 9' ? 'ថ្នាក់ទី ៩' : 'ថ្នាក់ទី ១២'; ?>
          </h2>
          
          <!-- Category Selector -->
          <div style="display: flex; gap: 0.3rem; background: rgba(0,0,0,0.03); padding: 0.2rem; border-radius: 9999px;">
            <a href="dashboard.php?grade=<?php echo urlencode($selectedGrade); ?>&category=External" 
               class="btn <?php echo $selectedCategory === 'External' ? 'btn-primary' : 'btn-secondary'; ?>" 
               style="padding: 0.35rem 1rem; font-size: 0.8rem; border-radius: 9999px; box-shadow: none; font-weight: 700;">
              វិញ្ញាសាថ្នាក់ក្រៅ
            </a>
            <a href="dashboard.php?grade=<?php echo urlencode($selectedGrade); ?>&category=Internal" 
               class="btn <?php echo $selectedCategory === 'Internal' ? 'btn-primary' : 'btn-secondary'; ?>" 
               style="padding: 0.35rem 1rem; font-size: 0.8rem; border-radius: 9999px; box-shadow: none; font-weight: 700;">
              វិញ្ញាសាថ្នាក់ក្នុង
            </a>
          </div>
        </div>

        <?php if (empty($exams)): ?>
          <div class="card" style="text-align: center; padding: 4rem 2rem;">
            <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted); margin-bottom: 1rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <p class="kh-font" style="font-size: 1.1rem; color: var(--text-muted); margin: 0;">មិនទាន់មានវិញ្ញាសាប្រភេទនេះសម្រាប់កម្រិតថ្នាក់នេះនៅឡើយទេ។</p>
          </div>
        <?php else: ?>
          <div style="display: grid; gap: 1.5rem;">
            <?php foreach ($exams as $exam): ?>
              <div class="card" style="padding: 2rem; display: flex; justify-content: space-between; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
                <div>
                  <span class="grade-badge" style="background: rgba(79, 70, 229, 0.1); color: var(--primary); font-size: 0.8rem; padding: 0.2rem 0.6rem; margin-bottom: 0.6rem;">
                    <?php 
                      if ($exam['exam_type'] === 'Internal') {
                        echo 'វិញ្ញាសាសាលា (ថ្នាក់ក្នុង)';
                      } elseif ($exam['exam_type'] === 'External') {
                        echo 'វិញ្ញាសាជាតិ (ថ្នាក់ក្រៅ)';
                      } else {
                        echo htmlspecialchars($exam['exam_type']); 
                      }
                    ?>
                  </span>
                  <h3 style="margin-top: 0.4rem; font-size: 1.25rem;"><?php echo htmlspecialchars($exam['title']); ?></h3>
                  <div style="display: flex; gap: 1rem; color: var(--text-muted); font-size: 0.9rem; margin-top: 0.5rem; flex-wrap: wrap;">
                    <span style="display: inline-flex; align-items: center; gap: 0.3rem;">
                      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                      <?php echo (int)$exam['duration_minutes']; ?> នាទី
                    </span>
                    <span style="display: inline-flex; align-items: center; gap: 0.3rem;">
                      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                      <?php echo (int)$exam['question_count']; ?> សំណួរ
                    </span>
                    <span style="display: inline-flex; align-items: center; gap: 0.3rem;">
                      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                      ពិន្ទុសរុប៖ <?php echo (int)$exam['total_score']; ?>
                    </span>
                  </div>
                </div>
                
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                  <?php if ((int)$exam['question_count'] > 0): ?>
                    <form action="exam.php" method="post" style="margin: 0;">
                      <input type="hidden" name="exam_id" value="<?php echo (int)$exam['id']; ?>" />
                      <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.2rem; font-size: 0.9rem;">ចាប់ផ្តើមតេស្ត</button>
                    </form>
                  <?php else: ?>
                    <button class="btn btn-secondary" disabled style="cursor: not-allowed; color: var(--text-muted) !important; padding: 0.5rem 1.2rem; font-size: 0.9rem;">គ្មានសំណួរ</button>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Right Column: Exam Attempts History -->
      <div>
        <h2 class="kh-font" style="margin-bottom: 1.5rem; font-size: 1.5rem; color: var(--secondary);">ប្រវត្តិតេស្តកន្លងមក</h2>
        
        <?php if (empty($attempts)): ?>
          <div class="card" style="text-align: center; padding: 2rem;">
            <p class="kh-font" style="color: var(--text-muted); margin: 0; font-size: 0.95rem;">អ្នកមិនទាន់បានធ្វើតេស្តណាមួយនៅឡើយទេ។</p>
          </div>
        <?php else: ?>
          <div>
            <?php foreach ($attempts as $attempt): 
              $percent = $attempt['total_score'] > 0 ? round(($attempt['score'] / $attempt['total_score']) * 100) : 0;
              $badgeClass = 'low';
              if ($percent >= 80) {
                $badgeClass = 'high';
              } elseif ($percent >= 50) {
                $badgeClass = 'mid';
              }
            ?>
              <div class="history-card">
                <div style="flex: 1;">
                  <span class="grade-badge" style="font-size: 0.75rem; padding: 0.1rem 0.4rem;"><?php echo htmlspecialchars($attempt['exam_grade']); ?></span>
                  <h4 style="margin: 0.3rem 0; font-size: 1rem; font-weight: 700; color: var(--secondary);"><?php echo htmlspecialchars($attempt['exam_title']); ?></h4>
                  <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
                    <?php echo date('d-m-Y h:i A', strtotime($attempt['submitted_at'] ?? $attempt['started_at'])); ?>
                  </div>
                </div>
                
                <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 0.4rem;">
                  <span class="score-badge <?php echo $badgeClass; ?>" style="font-size: 0.9rem; padding: 0.25rem 0.75rem;">
                    <?php echo (int)$attempt['score']; ?>/<?php echo (int)$attempt['total_score']; ?> (<?php echo $percent; ?>%)
                  </span>
                  <div style="display: flex; gap: 0.5rem; justify-content: flex-end; flex-wrap: wrap;">
                    <?php if ($attempt['status'] === 'submitted'): ?>
                      <a href="result.php?result_id=<?php echo (int)$attempt['id']; ?>" class="btn btn-secondary" style="padding: 0.3rem 0.7rem; font-size: 0.8rem; border-radius: var(--radius-sm);">មើលលម្អិត</a>
                    <?php else: ?>
                      <a href="exam.php?result_id=<?php echo (int)$attempt['id']; ?>" class="btn btn-accent" style="padding: 0.3rem 0.7rem; font-size: 0.8rem; border-radius: var(--radius-sm);">បន្តធ្វើតេស្ត</a>
                    <?php endif; ?>

                    <form method="post" action="dashboard.php" style="margin: 0;">
                      <input type="hidden" name="action" value="delete_history" />
                      <input type="hidden" name="attempt_id" value="<?php echo (int)$attempt['id']; ?>" />
                      <input type="hidden" name="grade" value="<?php echo htmlspecialchars($selectedGrade); ?>" />
                      <input type="hidden" name="category" value="<?php echo htmlspecialchars($selectedCategory); ?>" />
                      <button type="submit" class="btn btn-danger" style="padding: 0.3rem 0.7rem; font-size: 0.8rem; border-radius: var(--radius-sm);" onclick="return confirm('តើអ្នកចង់លុបទិន្នន័យតេស្តនេះមែនទេ?');">លុប</button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <footer class="app-footer">
    <div class="container">
      <p>&copy; 2026 Maths KH. រៀបចំឡើងសម្រាប់សិស្សថ្នាក់ទី៩ និងថ្នាក់ទី១២។</p>
    </div>
  </footer>
</body>
</html>
