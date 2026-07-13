<?php
// api.php - Backend Controller Router for English24h

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight CORS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/database.php';

// Get action from request
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Helper to read JSON POST payloads
function getPostData() {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true);
}

try {
    switch ($action) {
        
        // 1. GET RANDOM QUESTIONS FOR A SPECIFIC TENSE
        case 'get_questions':
            $tense = isset($_GET['tense']) ? $_GET['tense'] : '';
            if (empty($tense)) {
                throw new Exception("Tense category is required.");
            }
            
            // Fetch limit from settings
            $limit = 5;
            $setStmt = $pdo->prepare("SELECT key_value FROM settings WHERE key_name = 'quiz_questions_count'");
            $setStmt->execute();
            $setRow = $setStmt->fetch();
            if ($setRow && is_numeric($setRow['key_value'])) {
                $limit = (int)$setRow['key_value'];
            }
            
            $stmt = $pdo->prepare("SELECT * FROM questions WHERE tense = :tense ORDER BY RAND() LIMIT :limit");
            $stmt->bindValue(':tense', $tense, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            
            $questions = [];
            foreach ($rows as $row) {
                $questions[] = [
                    'id' => $row['id'],
                    'tense' => $row['tense'],
                    'question' => $row['question'],
                    'options' => [
                        $row['option_a'],
                        $row['option_b'],
                        $row['option_c'],
                        $row['option_d']
                    ],
                    'correctIdx' => (int)$row['correct_idx']
                ];
            }
            
            echo json_encode(['success' => true, 'questions' => $questions]);
            break;

        // 2. GET ALL QUESTIONS (FOR ADMIN PANEL)
        case 'get_all_questions':
            $stmt = $pdo->query("SELECT * FROM questions ORDER BY tense ASC, id DESC");
            $rows = $stmt->fetchAll();
            
            $questions = [];
            foreach ($rows as $row) {
                $questions[] = [
                    'id' => $row['id'],
                    'tense' => $row['tense'],
                    'question' => $row['question'],
                    'options' => [
                        $row['option_a'],
                        $row['option_b'],
                        $row['option_c'],
                        $row['option_d']
                    ],
                    'correctIdx' => (int)$row['correct_idx']
                ];
            }
            
            echo json_encode(['success' => true, 'questions' => $questions]);
            break;

        // 3. ADD OR EDIT QUESTION (ADMIN)
        case 'save_question':
            $data = getPostData();
            if (!$data) {
                throw new Exception("Invalid request body.");
            }
            
            $id = isset($data['id']) ? $data['id'] : null;
            $tense = isset($data['tense']) ? $data['tense'] : '';
            $question = isset($data['question']) ? $data['question'] : '';
            $options = isset($data['options']) ? $data['options'] : [];
            $correctIdx = isset($data['correctIdx']) ? (int)$data['correctIdx'] : 0;
            
            if (empty($tense) || empty($question) || count($options) < 4) {
                throw new Exception("Missing required question properties.");
            }
            
            if ($id && is_numeric($id)) {
                // Update Mode
                $stmt = $pdo->prepare("UPDATE questions SET tense = ?, question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_idx = ? WHERE id = ?");
                $stmt->execute([$tense, $question, $options[0], $options[1], $options[2], $options[3], $correctIdx, $id]);
                echo json_encode(['success' => true, 'message' => 'Question updated successfully.']);
            } else {
                // Insert Mode
                $stmt = $pdo->prepare("INSERT INTO questions (tense, question, option_a, option_b, option_c, option_d, correct_idx) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tense, $question, $options[0], $options[1], $options[2], $options[3], $correctIdx]);
                echo json_encode(['success' => true, 'message' => 'Question added successfully.']);
            }
            break;

        // 4. DELETE QUESTION (ADMIN)
        case 'delete_question':
            $data = getPostData();
            $id = isset($data['id']) ? $data['id'] : null;
            
            if (!$id) {
                throw new Exception("Question ID is required.");
            }
            
            $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Question deleted.']);
            break;

        // 5. SUBMIT SCORE RESULT
        case 'submit_score':
            $data = getPostData();
            if (!$data) {
                throw new Exception("Invalid request body.");
            }
            
            $studentName = isset($data['studentName']) ? $data['studentName'] : 'Anonymous';
            $tense = isset($data['tense']) ? $data['tense'] : '';
            $scoreFraction = isset($data['scoreFraction']) ? $data['scoreFraction'] : '0/5';
            $scorePercent = isset($data['scorePercent']) ? (int)$data['scorePercent'] : 0;
            $timeTaken = isset($data['timeTaken']) ? $data['timeTaken'] : '0:00';
            
            if (empty($tense)) {
                throw new Exception("Tense category is required.");
            }
            
            $stmt = $pdo->prepare("INSERT INTO scores (student_name, tense, score_fraction, score_percent, time_taken) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$studentName, $tense, $scoreFraction, $scorePercent, $timeTaken]);
            echo json_encode(['success' => true, 'message' => 'Score registered.']);
            break;

        // 6. GET ALL STUDENT LOGS (ADMIN PANEL)
        case 'get_student_logs':
            $stmt = $pdo->query("SELECT * FROM scores ORDER BY id DESC");
            $rows = $stmt->fetchAll();
            
            $logs = [];
            foreach ($rows as $row) {
                $logs[] = [
                    'studentName' => $row['student_name'],
                    'tense' => $row['tense'],
                    'scoreFraction' => $row['score_fraction'],
                    'scorePercent' => (int)$row['score_percent'],
                    'date' => date('d/m/Y H:i', strtotime($row['created_at']))
                ];
            }
            
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        // 7. GET MY PRACTICE HISTORY (STUDENT)
        case 'get_my_logs':
            $name = isset($_GET['name']) ? $_GET['name'] : '';
            if (empty($name)) {
                throw new Exception("Student name parameter is required.");
            }
            
            $stmt = $pdo->prepare("SELECT * FROM scores WHERE student_name = ? ORDER BY id DESC");
            $stmt->execute([$name]);
            $rows = $stmt->fetchAll();
            
            $logs = [];
            foreach ($rows as $row) {
                $logs[] = [
                    'studentName' => $row['student_name'],
                    'tense' => $row['tense'],
                    'scoreFraction' => $row['score_fraction'],
                    'scorePercent' => (int)$row['score_percent'],
                    'date' => date('d/m/Y H:i', strtotime($row['created_at']))
                ];
            }
            
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        // 7.5. CLEAR STUDENT INDIVIDUAL LOGS
        case 'clear_my_logs':
            $name = isset($_GET['name']) ? $_GET['name'] : '';
            if (empty($name)) {
                throw new Exception("Student name parameter is required.");
            }
            $stmt = $pdo->prepare("DELETE FROM scores WHERE student_name = ?");
            $stmt->execute([$name]);
            echo json_encode(['success' => true, 'message' => 'History cleared successfully.']);
            break;

        // 8. RESET STUDENT LOGS (ADMIN)
        case 'clear_student_logs':
            $pdo->exec("TRUNCATE TABLE scores");
            echo json_encode(['success' => true, 'message' => 'All logs cleared.']);
            break;

        // 9. ADMIN LOGIN
        case 'admin_login':
            $data = getPostData();
            if (!$data) {
                throw new Exception("Invalid request body.");
            }
            
            $username = isset($data['username']) ? trim($data['username']) : '';
            $password = isset($data['password']) ? trim($data['password']) : '';
            
            if (empty($username) || empty($password)) {
                throw new Exception("Username and password are required.");
            }
            
            $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $row = $stmt->fetch();
            
            if ($row && password_verify($password, $row['password_hash'])) {
                echo json_encode(['success' => true, 'message' => 'Logged in successfully.']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid username or password.']);
            }
            break;

        // 10. UPLOAD CERTIFICATE CUSTOM ASSETS (ADMIN)
        case 'upload_cert_assets':
            if (!is_dir('uploads')) {
                mkdir('uploads', 0755, true);
            }
            
            $uploaded = [];
            
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                move_uploaded_file($_FILES['logo']['tmp_name'], 'uploads/logo.png');
                $uploaded[] = 'logo';
            }
            if (isset($_FILES['border']) && $_FILES['border']['error'] === UPLOAD_ERR_OK) {
                move_uploaded_file($_FILES['border']['tmp_name'], 'uploads/border.png');
                $uploaded[] = 'border';
            }
            if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
                move_uploaded_file($_FILES['signature']['tmp_name'], 'uploads/signature.png');
                $uploaded[] = 'signature';
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Assets uploaded successfully.', 
                'uploaded' => $uploaded
            ]);
            break;
            
        // 11. RESET CERTIFICATE ASSETS (ADMIN)
        case 'reset_cert_assets':
            $target = isset($_GET['target']) ? $_GET['target'] : 'all';
            $files = ['logo' => 'uploads/logo.png', 'border' => 'uploads/border.png', 'signature' => 'uploads/signature.png'];
            
            $removed = [];
            foreach ($files as $name => $path) {
                if (($target === 'all' || $target === $name) && file_exists($path)) {
                    unlink($path);
                    $removed[] = $name;
                }
            }
            echo json_encode(['success' => true, 'message' => 'Assets reset.', 'removed' => $removed]);
            break;

        // 12. GET CERTIFICATE CUSTOM ASSETS
        case 'get_cert_assets':
            echo json_encode([
                'success' => true,
                'logo' => file_exists('uploads/logo.png') ? 'uploads/logo.png?v=' . filemtime('uploads/logo.png') : null,
                'border' => file_exists('uploads/border.png') ? 'uploads/border.png?v=' . filemtime('uploads/border.png') : null,
                'signature' => file_exists('uploads/signature.png') ? 'uploads/signature.png?v=' . filemtime('uploads/signature.png') : null
            ]);
            break;

        // 13. GET DISTINCT ACTIVE TENSES WITH QUESTIONS
        case 'get_tenses':
            $stmt = $pdo->query("SELECT DISTINCT tense FROM questions ORDER BY tense ASC");
            $tenses = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success' => true, 'tenses' => $tenses]);
            break;

        // 14. SEND GENERATED CERTIFICATE DIRECTLY TO TELEGRAM CHAT (FOR MOBILE DOWNLOAD BYPASS)
        case 'send_cert_telegram':
            $data = getPostData();
            if (!$data) throw new Exception("Invalid request payload.");
            
            $chat_id = isset($data['chat_id']) ? trim($data['chat_id']) : '';
            $file_type = isset($data['file_type']) ? trim($data['file_type']) : 'pdf';
            $base64_data = isset($data['base64_data']) ? trim($data['base64_data']) : '';
            $student_name = isset($data['student_name']) ? trim($data['student_name']) : 'Student';
            $tense = isset($data['tense']) ? trim($data['tense']) : 'Grammar';

            if (empty($chat_id) || empty($base64_data)) {
                throw new Exception("Chat ID and certificate content are required.");
            }

            // Extract base64 payload
            if (preg_match('/^data:([^;]+);base64,(.*)$/', $base64_data, $matches)) {
                $payload = base64_decode($matches[2]);
            } else {
                $payload = base64_decode($base64_data);
            }

            if (!$payload) {
                throw new Exception("Failed to decode certificate files.");
            }

            $safe_name = preg_replace('/[^a-zA-Z0-9]/', '_', $student_name);
            $safe_tense = preg_replace('/[^a-zA-Z0-9]/', '_', $tense);
            
            if (!is_dir('temp')) {
                mkdir('temp', 0755, true);
            }
            
            $filename = "temp/Certificate_{$safe_name}_{$safe_tense}." . ($file_type === 'pdf' ? 'pdf' : 'png');
            file_put_contents($filename, $payload);

            // Send to Telegram Bot API
            $token = '8993389047:AAG8FpaYAZMHMF3hOV2BpLQKM_0venimdBI';
            $method = $file_type === 'pdf' ? 'sendDocument' : 'sendPhoto';
            $file_field = $file_type === 'pdf' ? 'document' : 'photo';
            
            $post_fields = [
                'chat_id' => $chat_id,
                $file_field => new CURLFile(realpath($filename)),
                'caption' => "🎓 Here is your official Certificate of Recognition for mastering {$tense}! Outstanding work! 🚀"
            ];
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.telegram.org/bot{$token}/{$method}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post_fields,
                CURLOPT_TIMEOUT => 20
            ]);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            // Delete temp file
            if (file_exists($filename)) {
                unlink($filename);
            }
            
            echo $response;
            break;

        // 15. GET GLOBAL APP SETTINGS
        case 'get_settings':
            $stmt = $pdo->query("SELECT * FROM settings");
            $rows = $stmt->fetchAll();
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['key_name']] = $row['key_value'];
            }
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;

        // 16. SAVE GLOBAL APP SETTINGS (ADMIN)
        case 'save_settings':
            $data = getPostData();
            if (!$data) throw new Exception("Invalid request settings payload.");
            
            $stmt = $pdo->prepare("INSERT INTO settings (key_name, key_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE key_value = ?");
            foreach ($data as $key => $val) {
                $stmt->execute([$key, $val, $val]);
            }
            echo json_encode(['success' => true, 'message' => 'Settings saved successfully.']);
            break;

        default:
            throw new Exception("Unknown action: " . $action);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
