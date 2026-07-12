<?php
session_start();
require_once __DIR__ . '/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$resultId = (int)($_GET['result_id'] ?? 0);
if ($resultId <= 0) {
    header('Location: dashboard.php');
    exit;
}

$pdo = getConnection();
$stmt = $pdo->prepare('SELECT r.*, e.title AS exam_title, e.grade_level, u.full_name FROM exam_results r JOIN exams e ON e.id = r.exam_id JOIN users u ON u.id = r.user_id WHERE r.id = ? AND r.user_id = ? AND r.status = "submitted" LIMIT 1');
$stmt->execute([$resultId, $_SESSION['user_id']]);
$result = $stmt->fetch();

if (!$result) {
    $_SESSION['flash'] = 'សញ្ញាបត្រមិនអាចបង្ហាញបានទេ។';
    header('Location: dashboard.php');
    exit;
}

$settingsStmt = $pdo->prepare('SELECT setting_key, setting_value FROM settings WHERE setting_key IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$settingsStmt->execute([
    'certificate_title',
    'certificate_subtitle',
    'certificate_awarded_line',
    'certificate_name_label',
    'certificate_body',
    'certificate_grade_note',
    'certificate_footer',
    'certificate_signature_text',
    'certificate_logo',
    'certificate_signature',
    'certificate_border'
]);
$settings = array_column($settingsStmt->fetchAll(), 'setting_value', 'setting_key');

$settings = array_merge([
    'certificate_title' => 'សញ្ញាបត្រសរសើរ',
    'certificate_subtitle' => 'ឧទ្ទេសប័ណ្ណ',
    'certificate_awarded_line' => 'ផ្តល់ឱ្យ',
    'certificate_name_label' => 'ឈ្មោះៈ',
    'certificate_body' => "សញ្ញាបត្រនេះត្រូវបានផ្តល់ឱ្យ {name} សម្រាប់ភាពយុត្តិធម៌ក្នុងការប្រឡងរួចរាល់តាមប្រធានបទ {exam}។\nនេះទទួលបានពិន្ទុ {score}/{total_score} ( {percent}% ) និងថ្នាក់ {grade}។",
    'certificate_grade_note' => 'ថ្នាក់អក្សរ {letter_grade}',
    'certificate_footer' => 'Maths KH',
    'certificate_signature_text' => 'អ្នកស៊ីជម្រៅ',
    'certificate_logo' => '',
    'certificate_signature' => '',
    'certificate_border' => ''
], $settings);

$percent = $result['total_score'] > 0 ? round(($result['score'] / $result['total_score']) * 100) : 0;
$letterGrade = $percent >= 90 ? 'A' : ($percent >= 70 ? 'B' : 'C');

$data = [
    '{name}' => htmlspecialchars($result['full_name']),
    '{exam}' => htmlspecialchars($result['exam_title']),
    '{grade}' => htmlspecialchars($result['grade_level']),
    '{score}' => (int)$result['score'],
    '{total_score}' => (int)$result['total_score'],
    '{percent}' => $percent,
    '{letter_grade}' => htmlspecialchars($letterGrade),
    '{date}' => date('d-m-Y', strtotime($result['submitted_at'] ?? $result['started_at']))
];

$bodyText = str_replace("\n", '<br>', htmlspecialchars(strtr($settings['certificate_body'], $data)));

$logoPath = $settings['certificate_logo'] && file_exists(__DIR__ . '/' . $settings['certificate_logo']) ? $settings['certificate_logo'] : '';
$signaturePath = $settings['certificate_signature'] && file_exists(__DIR__ . '/' . $settings['certificate_signature']) ? $settings['certificate_signature'] : '';
$borderPath = $settings['certificate_border'] && file_exists(__DIR__ . '/' . $settings['certificate_border']) ? $settings['certificate_border'] : '';
?>
<!DOCTYPE html>
<html lang="km">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Maths KH | សញ្ញាបត្រា</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <style>
    @page {
      size: A4 landscape;
      margin: 0;
    }
    body { background: #f3f4f6; margin: 0; }
    .certificate-shell {
      width: 100%;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 2rem;
      box-sizing: border-box;
    }
    .certificate-card {
      width: min(1180px, 100%);
      aspect-ratio: 297 / 210;
      background: radial-gradient(circle at top, #fffdf5 0%, #ffffff 28%, #fdf9ef 100%);
      padding: 3.5rem 3rem 1.5rem;
      border-radius: 2rem;
      box-shadow: 0 32px 90px rgba(15, 23, 42, 0.12);
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      border: 1px solid rgba(245, 198, 109, 0.7);
    }
    .certificate-card::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image: url('<?php echo $borderPath ? $borderPath : ''; ?>');
      background-repeat: no-repeat;
      background-size: cover;
      background-position: center;
      opacity: <?php echo $borderPath ? '0.85' : '0'; ?>;
      pointer-events: none;
    }
    .certificate-inner { position: relative; z-index: 1; display: flex; flex-direction: column; justify-content: flex-start; align-items: center; height: 100%; padding: 2rem 2rem 1rem; }
    .certificate-logo { position: absolute; top: 2rem; left: 2rem; max-width: 110px; height: auto; }
    .certificate-subtitle { color: #dc2626; font-size: 2.4rem; font-weight: 900; text-align: center; letter-spacing: 0.2em; margin: 0; text-transform: uppercase; }
    .certificate-title { color: #131313; font-size: 3.3rem; text-align: center; font-weight: 900; margin: 0.75rem 0 0.4rem; letter-spacing: 0.04em; text-decoration: underline; text-decoration-thickness: 0.28rem; text-underline-offset: 0.6rem; text-decoration-color: rgba(220, 37, 42, 0.7); }
    .certificate-awarded-line { color: #1f2937; text-align: center; font-size: 1.45rem; font-weight: 700; letter-spacing: 0.12em; margin: 1.0rem 0 0.4rem; font-style: italic; }
    .certificate-name-label { color: #1f2937; text-align: center; font-size: 1.3rem; letter-spacing: 0.12em; margin: 1.3rem 0 0.25rem; }
    .certificate-name { color: #b37d00; text-align: center; font-size: 5.4rem; font-weight: 900; letter-spacing: 0.16em; margin: 0; line-height: 1; }
    .certificate-body { font-size: 1.18rem; line-height: 2.1; color: #164e80; text-align: center; max-width: 860px; margin: 2rem auto 1.5rem; white-space: pre-wrap; }
    .certificate-grade-note { color: #d97706; text-align: center; font-size: 1.4rem; font-weight: 800; margin: 1rem auto 1.5rem; }
    .certificate-details { display: block; text-align: center; color: #1d4ed8; font-size: 1.05rem; font-weight: 600; margin-bottom: 2.5rem; }
    .certificate-footer { display: flex; justify-content: center; align-items: center; gap: 1rem; flex-direction: column; margin-top: auto; }
    .certificate-footer .footer-text { font-size: 1rem; color: #475569; text-transform: uppercase; letter-spacing: 0.08em; }
    .certificate-signature { display: flex; flex-direction: column; align-items: center; gap: 0.8rem; }
    .certificate-signature img { max-width: 240px; height: auto; }
    .certificate-signature span { color: #1f2937; font-size: 0.95rem; letter-spacing: 0.06em; }
    .certificate-actions { margin-top: 1.8rem; display: flex; justify-content: center; gap: 1rem; }
    .certificate-actions button,
    .certificate-actions a { min-width: 180px; }
    .certificate-card h1,
    .certificate-card p,
    .certificate-card span,
    .certificate-card div {
      font-family: var(--font-kh), var(--font-en), sans-serif;
    }
    @media (max-width: 980px) {
      .certificate-card { padding: 2rem; }
      .certificate-subtitle { font-size: 1.9rem; }
      .certificate-title { font-size: 2.2rem; }
      .certificate-name { font-size: 3.2rem; }
      .certificate-body { font-size: 1rem; }
      .certificate-footer { flex-direction: column; align-items: stretch; }
      .certificate-signature { align-items: flex-start; }
    }
    @media print {
      body { background: white; }
      .certificate-shell { padding: 0; }
      .certificate-card { box-shadow: none; border-radius: 0; }
      .certificate-actions { display: none; }
    }
  </style>
</head>
<body>
  <div class="certificate-shell">
    <div class="certificate-card">
      <div class="certificate-inner">
        <?php if ($logoPath): ?>
          <img class="certificate-logo" src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo" />
        <?php endif; ?>
        <div class="certificate-subtitle"><?php echo htmlspecialchars($settings['certificate_subtitle']); ?></div>
        <h1 class="certificate-title"><?php echo htmlspecialchars($settings['certificate_title']); ?></h1>
        <div class="certificate-awarded-line"><?php echo htmlspecialchars($settings['certificate_awarded_line']); ?></div>
        <div class="certificate-name-label"><?php echo htmlspecialchars($settings['certificate_name_label']); ?></div>
        <div class="certificate-name"><?php echo htmlspecialchars($data['{name}']); ?></div>
        <div class="certificate-body"><?php echo $bodyText; ?></div>
        <div class="certificate-grade-note"><?php echo nl2br(htmlspecialchars(strtr($settings['certificate_grade_note'], $data))); ?></div>
        <div class="certificate-details">
          ខែវិភាគ៖ <?php echo htmlspecialchars($data['{exam}']); ?> | ថ្នាក់៖ <?php echo htmlspecialchars($data['{grade}']); ?> | ពិន្ទុ៖ <?php echo htmlspecialchars($data['{score}']); ?>/<?php echo htmlspecialchars($data['{total_score}']); ?> (<?php echo htmlspecialchars($data['{percent}']); ?>%)
        </div>
        <div class="certificate-footer">
          <?php if ($signaturePath): ?>
            <div class="certificate-signature">
              <img src="<?php echo htmlspecialchars($signaturePath); ?>" alt="Signature" />
              <span><?php echo htmlspecialchars($settings['certificate_signature_text'] ?: 'Maths KH'); ?></span>
            </div>
          <?php endif; ?>
          <div class="footer-text"><?php echo htmlspecialchars($settings['certificate_footer']); ?></div>
        </div>
      </div>
    </div>
    <div class="certificate-actions">
      <button id="btn-download-png" class="btn btn-primary" type="button">រក្សាទុកជា PNG</button>
      <a href="javascript:window.print()" class="btn btn-primary">បោះពុម្ព</a>
      <a href="dashboard.php" class="btn btn-secondary">ត្រឡប់ទៅផ្ទាំងសិក្សា</a>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
  <script>
    const downloadBtn = document.getElementById('btn-download-png');
    const certificateCard = document.querySelector('.certificate-card');

    if (downloadBtn && certificateCard) {
      downloadBtn.addEventListener('click', async () => {
        downloadBtn.disabled = true;
        downloadBtn.textContent = 'កំពុងតំណើរការ...';

        try {
          const canvas = await html2canvas(certificateCard, {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false,
          });

          canvas.toBlob((blob) => {
            if (!blob) {
              alert('មិនអាចបង្កើត PNG បានទេ។ សូមព្យាយាមម្តងទៀត។');
              downloadBtn.disabled = false;
              downloadBtn.textContent = 'រក្សាទុកជា PNG';
              return;
            }

            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'Certificate.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
            downloadBtn.textContent = 'បានរក្សាទុក';
            setTimeout(() => {
              downloadBtn.textContent = 'រក្សាទុកជា PNG';
              downloadBtn.disabled = false;
            }, 1800);
          }, 'image/png');
        } catch (err) {
          console.error(err);
          alert('មានកំហុសកើតឡើងពេលរក្សាទុក PNG។ សូមព្យាយាមម្តងទៀត។');
          downloadBtn.disabled = false;
          downloadBtn.textContent = 'រក្សាទុកជា PNG';
        }
      });
    }
  </script>
</body>
</html>
