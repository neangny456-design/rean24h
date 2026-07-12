<?php
require_once __DIR__ . '/config.php';

function getConnection(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
        $pdo->exec("SET NAMES utf8mb4");
        return $pdo;
    } catch (PDOException $e) {
        try {
            $rootPdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
            $rootPdo->exec('CREATE DATABASE IF NOT EXISTS ' . DB_NAME . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
            $pdo->exec("SET NAMES utf8mb4");
            return $pdo;
        } catch (PDOException $fallbackException) {
            throw new RuntimeException('Database connection failed: ' . $fallbackException->getMessage());
        }
    }
}

function initializeDatabase(): void {
    $pdo = getConnection();
    $statements = [
        "CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(120) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(120) DEFAULT NULL UNIQUE,
            username VARCHAR(80) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            phone VARCHAR(40) DEFAULT NULL,
            grade_level VARCHAR(20) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            description TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS exams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject_id INT NOT NULL,
            title VARCHAR(180) NOT NULL,
            grade_level VARCHAR(20) NOT NULL,
            exam_type VARCHAR(80) NOT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            duration_minutes INT NOT NULL DEFAULT 60,
            total_score INT NOT NULL DEFAULT 100,
            is_published TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            exam_id INT NOT NULL,
            question_text TEXT NOT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            points INT NOT NULL DEFAULT 1,
            explanation TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS choices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question_id INT NOT NULL,
            choice_text VARCHAR(255) NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS exam_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            exam_id INT NOT NULL,
            score INT NOT NULL DEFAULT 0,
            total_score INT NOT NULL DEFAULT 0,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            submitted_at TIMESTAMP NULL DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'started',
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS student_answers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            exam_result_id INT NOT NULL,
            question_id INT NOT NULL,
            choice_id INT DEFAULT NULL,
            answer_text TEXT DEFAULT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            FOREIGN KEY (exam_result_id) REFERENCES exam_results(id) ON DELETE CASCADE,
            FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(80) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }

    // Migration: Make email nullable in users table
    try {
        $pdo->exec("ALTER TABLE users MODIFY email VARCHAR(120) DEFAULT NULL");
    } catch (PDOException $e) {
        // Ignore if error or column already updated
    }

    // Migration: Add exam image support if missing
    try {
        $pdo->exec("ALTER TABLE exams ADD COLUMN image_path VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) {
        // Ignore if column already exists or cannot be added
    }

    // Seed Admin
    $adminCount = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    if (!$adminCount) {
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash, full_name, email) VALUES (?, ?, ?, ?)');
        $stmt->execute(['admin', $passwordHash, 'Administrator', 'admin@mathkh.local']);
    }

    // Seed Settings
    $settingCount = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if (!$settingCount) {
        $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)')->execute(['site_title', SITE_TITLE]);
    }
    
    $ledTextCount = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'led_text'")->fetchColumn();
    if (!$ledTextCount) {
        $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)')->execute(['led_text', 'សូមស្វាគមន៍មកកាន់ប្រព័ន្ធតេស្តគណិតវិទ្យា Maths KH! ត្រៀមប្រលងឌីប្លូម និងបាក់ឌុបសាកល្បង។']);
    }

    // Seed Subjects
    $subjectCount = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
    if (!$subjectCount) {
        $pdo->prepare('INSERT INTO subjects (name, description) VALUES (?, ?)')->execute(['គណិតវិទ្យា', 'ប្រធានបទគណិតវិទ្យាសម្រាប់ថ្នាក់ទី៩ និងទី១២']);
    }

    // Seed Exams (Grade 9 & Grade 12)
    $examCount = $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
    if (!$examCount) {
        $subjectId = $pdo->query("SELECT id FROM subjects ORDER BY id LIMIT 1")->fetchColumn();
        
        // Grade 9 Exam
        $pdo->prepare('INSERT INTO exams (subject_id, title, grade_level, exam_type, duration_minutes, total_score, is_published) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
            $subjectId,
            'វិញ្ញាសាគណិតវិទ្យាថ្នាក់ទី៩ (ឌីប្លូមសាកល្បង)',
            'Grade 9',
            'Diploma',
            45,
            50,
            1
        ]);
        $exam9Id = $pdo->lastInsertId();
        
        // Q1 for Grade 9
        $pdo->prepare('INSERT INTO questions (exam_id, question_text, points, explanation) VALUES (?, ?, ?, ?)')->execute([
            $exam9Id,
            'ចូរគណនាអនុគមន៍ $f(x) = x^2 + 2x + 1$ នៅពេល $x=3$ ។',
            10,
            'ជំនួស $x=3$ ទៅក្នុងអនុគមន៍ $f(3) = 3^2 + 2(3) + 1 = 9 + 6 + 1 = 16$ ។ ដូចនេះចម្លើយត្រឹមត្រូវគឺ 16 ។'
        ]);
        $q9_1 = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO choices (question_id, choice_text, is_correct) VALUES (?, ?, ?), (?, ?, ?), (?, ?, ?), (?, ?, ?)')->execute([
            $q9_1, '10', 0,
            $q9_1, '16', 1,
            $q9_1, '12', 0,
            $q9_1, '20', 0
        ]);
        
        // Q2 for Grade 9
        $pdo->prepare('INSERT INTO questions (exam_id, question_text, points, explanation) VALUES (?, ?, ?, ?)')->execute([
            $exam9Id,
            'រកតម្លៃ $x$ នៃសមីការ $2x - 8 = 0$ ។',
            10,
            'សមីការ $2x - 8 = 0 \Rightarrow 2x = 8 \Rightarrow x = 4$ ។'
        ]);
        $q9_2 = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO choices (question_id, choice_text, is_correct) VALUES (?, ?, ?), (?, ?, ?), (?, ?, ?), (?, ?, ?)')->execute([
            $q9_2, 'x = 2', 0,
            $q9_2, 'x = 4', 1,
            $q9_2, 'x = -4', 0,
            $q9_2, 'x = 8', 0
        ]);
        
        // Q3 for Grade 9
        $pdo->prepare('INSERT INTO questions (exam_id, question_text, points, explanation) VALUES (?, ?, ?, ?)')->execute([
            $exam9Id,
            'គណនាកន្សោមមេគុណ $(a+b)^2$ ។',
            10,
            'រូបមន្តផលគុណរហ័ស $(a+b)^2 = a^2 + 2ab + b^2$ ។'
        ]);
        $q9_3 = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO choices (question_id, choice_text, is_correct) VALUES (?, ?, ?), (?, ?, ?), (?, ?, ?), (?, ?, ?)')->execute([
            $q9_3, 'a^2 + b^2', 0,
            $q9_3, 'a^2 - 2ab + b^2', 0,
            $q9_3, 'a^2 + 2ab + b^2', 1,
            $q9_3, 'a^2 + ab + b^2', 0
        ]);
        
        // Grade 12 Exam
        $pdo->prepare('INSERT INTO exams (subject_id, title, grade_level, exam_type, duration_minutes, total_score, is_published) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
            $subjectId,
            'វិញ្ញាសាគណិតវិទ្យាថ្នាក់ទី១២ (បាក់ឌុបសាកល្បង)',
            'Grade 12',
            'Baccalaureate',
            60,
            50,
            1
        ]);
        $exam12Id = $pdo->lastInsertId();
        
        // Q1 for Grade 12
        $pdo->prepare('INSERT INTO questions (exam_id, question_text, points, explanation) VALUES (?, ?, ?, ?)')->execute([
            $exam12Id,
            'គណនាលីមីត $\lim_{x \to 2} \frac{x^2 - 4}{x - 2}$ ។',
            10,
            'សម្រួលកន្សោម៖ $\frac{x^2 - 4}{x - 2} = \frac{(x-2)(x+2)}{x-2} = x + 2$ ។ នៅពេល $x \to 2$, លីមីតគឺ $2 + 2 = 4$ ។'
        ]);
        $q12_1 = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO choices (question_id, choice_text, is_correct) VALUES (?, ?, ?), (?, ?, ?), (?, ?, ?), (?, ?, ?)')->execute([
            $q12_1, '2', 0,
            $q12_1, '4', 1,
            $q12_1, '0', 0,
            $q12_1, 'infinity', 0
        ]);
        
        // Q2 for Grade 12
        $pdo->prepare('INSERT INTO questions (exam_id, question_text, points, explanation) VALUES (?, ?, ?, ?)')->execute([
            $exam12Id,
            'គណនាដេរីវេនៃអនុគមន៍ $f(x) = \ln(x)$ ត្រង់ចំណុច $x > 0$ ។',
            10,
            'រូបមន្តដេរីវេនៃអនុគមន៍លោការីតធម្មជាតិគឺ $f\'(x) = (\ln x)\' = \frac{1}{x}$ ។'
        ]);
        $q12_2 = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO choices (question_id, choice_text, is_correct) VALUES (?, ?, ?), (?, ?, ?), (?, ?, ?), (?, ?, ?)')->execute([
            $q12_2, 'e^x', 0,
            $q12_2, '1/x', 1,
            $q12_2, 'x', 0,
            $q12_2, '1', 0
        ]);
    }
}

initializeDatabase();
?>
