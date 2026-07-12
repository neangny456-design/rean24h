<?php
session_start();
require_once __DIR__ . '/database.php';

$pdo = getConnection();

// Fetch statistics for landing page
try {
    $totalExams = $pdo->query("SELECT COUNT(*) FROM exams WHERE is_published = 1")->fetchColumn();
    $totalQuestions = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {
    $totalExams = 0;
    $totalQuestions = 0;
    $totalStudents = 0;
}

$isLoggedIn = isset($_SESSION['user_id']);
$isAdminLoggedIn = isset($_SESSION['admin_id']);

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
  <title>Maths KH | វិញ្ញាសាសាកល្បងគណិតវិទ្យា ឌីប្លូម និង បាក់ឌុប</title>
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
        <a href="index.php" style="color: var(--primary);">ទំព័រដើម</a>
        
        <?php if ($isLoggedIn): ?>
          <a href="dashboard.php">ផ្ទាំងសិក្សា</a>
          <span class="user-info">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
          </span>
          <a href="logout.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">ចាកចេញ</a>
        <?php elseif ($isAdminLoggedIn): ?>
          <a href="admin/index.php" class="btn btn-accent" style="padding: 0.5rem 1rem; font-size: 0.9rem;">គ្រប់គ្រងព័ត៌មាន</a>
          <a href="logout.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">ចាកចេញ</a>
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

  <main>
    <!-- Hero Section -->
    <section class="hero">
      <div class="container">
        <span class="hero-tag">ត្រៀមខ្លួនសម្រាប់ប្រលងជាតិមធ្យមសិក្សា</span>
        <h1>រៀន និងវាស់ស្ទង់សមត្ថភាព<br><span>គណិតវិទ្យា Maths KH</span></h1>
        <p>គេហទំព័រអនុវត្តវិញ្ញាសាគណិតវិទ្យាសម្រាប់សិស្សថ្នាក់ទី៩ (ឌីប្លូម) និងថ្នាក់ទី១២ (បាក់ឌុប) ជាមួយការកែស្វ័យប្រវត្តិ ម៉ោងរាប់ថយក្រោយ និងការពន្យល់លម្អិត។</p>
        <div class="hero-actions">
          <?php if ($isLoggedIn): ?>
            <a href="dashboard.php" class="btn btn-primary">ទៅកាន់ផ្ទាំងសិក្សារបស់អ្នក</a>
          <?php else: ?>
            <a href="register.php" class="btn btn-primary">ចាប់ផ្តើមឥឡូវនេះ</a>
            <a href="login.php" class="btn btn-secondary">ចូលគណនី</a>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- Statistics Section -->
    <section style="background: white; border-top: 1px solid var(--card-border); border-bottom: 1px solid var(--card-border); padding: 2.5rem 0;">
      <div class="container" style="display: flex; justify-content: space-around; flex-wrap: wrap; gap: 2rem; text-align: center;">
        <div>
          <div style="font-size: 2.5rem; font-weight: 800; color: var(--primary);"><?php echo $totalExams; ?>+</div>
          <div class="kh-font" style="color: var(--text-muted); font-weight: 600;">វិញ្ញាសាសរុប</div>
        </div>
        <div>
          <div style="font-size: 2.5rem; font-weight: 800; color: var(--secondary);"><?php echo $totalQuestions; ?>+</div>
          <div class="kh-font" style="color: var(--text-muted); font-weight: 600;">សំណួរអនុវត្ត</div>
        </div>
        <div>
          <div style="font-size: 2.5rem; font-weight: 800; color: var(--accent);"><?php echo $totalStudents; ?>+</div>
          <div class="kh-font" style="color: var(--text-muted); font-weight: 600;">សិស្សចុះឈ្មោះ</div>
        </div>
      </div>
    </section>

    <!-- Grade Selection Cards -->
    <section class="section" id="grades">
      <div class="container">
        <h2 class="section-title">ជ្រើសរើសកម្រិតសិក្សា</h2>
        <div class="grid-cards">
          <!-- Grade 9 Card -->
          <div class="card grade-card grade-9">
            <span class="grade-badge">ថ្នាក់ទី ៩</span>
            <h2>ឌីប្លូម (Diploma)</h2>
            <p>ប្រមូលផ្តុំវិញ្ញាសាគណិតវិទ្យាថ្នាក់ទី៩ ត្រៀមប្រលងឌីប្លូមសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ។ សំណួរស្របតាមកម្មវិធីសិក្សារបស់ក្រសួងអប់រំ។</p>
            <a href="<?php echo $isLoggedIn ? 'dashboard.php?grade=Grade+9' : 'register.php?grade=Grade+9'; ?>" class="btn btn-primary" style="width: 100%;">អនុវត្តវិញ្ញាសាទី៩</a>
          </div>

          <!-- Grade 12 Card -->
          <div class="card grade-card grade-12">
            <span class="grade-badge">ថ្នាក់ទី ១២</span>
            <h2>បាក់ឌុប (Bac II)</h2>
            <p>វិញ្ញាសាគណិតវិទ្យាត្រៀមប្រលងបាក់ឌុបសញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិ។ រួមមានលីមីត ដេរីវេ អាំងតេក្រាល សមីការឌីផេរ៉ង់ស្យែល និងប្រូបាប។</p>
            <a href="<?php echo $isLoggedIn ? 'dashboard.php?grade=Grade+12' : 'register.php?grade=Grade+12'; ?>" class="btn btn-primary" style="width: 100%;">អនុវត្តវិញ្ញាសាទី១២</a>
          </div>
        </div>
      </div>
    </section>

    <!-- Key Features Section -->
    <section class="section" style="background: rgba(248, 250, 252, 0.6);">
      <div class="container">
        <h2 class="section-title">ហេតុអ្វីត្រូវរៀនជាមួយ Maths KH?</h2>
        <div class="grid-cards" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
          <div class="card">
            <div class="card-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
              <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3>ការវាស់ស្ទង់ពេលវេលា</h3>
            <p>មានម៉ោងកំណត់ច្បាស់លាស់សម្រាប់វិញ្ញាសានីមួយៗ ដែលជួយសិស្សឱ្យចេះគ្រប់គ្រងពេលវេលាប្រលងបានល្អដូចការប្រលងពិតប្រាកដ។</p>
          </div>
          <div class="card">
            <div class="card-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--accent);">
              <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3>លទ្ធផលស្វ័យប្រវត្តិ</h3>
            <p>ទទួលបានពិន្ទុ និងការបង្ហាញលទ្ធផលភ្លាមៗក្រោយដាក់ស្នើចម្លើយ រួមទាំងការបង្ហាញចម្លើយត្រូវនិងខុសច្បាស់លាស់។</p>
          </div>
          <div class="card">
            <div class="card-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
              <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 01-2 2h0a2 2 0 01-2-2v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            </div>
            <h3>ការពន្យល់លម្អិត</h3>
            <p>សំណួរនីមួយៗរួមបញ្ចូលទាំងការដោះស្រាយលម្អិត និងគន្លឹះដោះស្រាយ ជួយឱ្យសិស្សយល់កាន់តែច្បាស់ពីមេរៀននីមួយៗ។</p>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="app-footer">
    <div class="container">
      <p>&copy; 2026 Maths KH. រៀបចំឡើងសម្រាប់សិស្សថ្នាក់ទី៩ និងថ្នាក់ទី១២។</p>
    </div>
  </footer>
</body>
</html>
