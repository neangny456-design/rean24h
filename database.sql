-- English24h Database Setup SQL
-- Import this in PHPMyAdmin or mysql CLI

CREATE DATABASE IF NOT EXISTS `english24h` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `english24h`;

-- 1. QUESTIONS TABLE
DROP TABLE IF EXISTS `questions`;
CREATE TABLE `questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tense` varchar(100) NOT NULL,
  `question` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_idx` int(1) NOT NULL COMMENT '0=A, 1=B, 2=C, 3=D',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tense` (`tense`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. SCORES TABLE
DROP TABLE IF EXISTS `scores`;
CREATE TABLE `scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_name` varchar(255) NOT NULL,
  `tense` varchar(100) NOT NULL,
  `score_fraction` varchar(10) NOT NULL,
  `score_percent` int(3) NOT NULL,
  `time_taken` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ADMINS TABLE
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. SEED ADMIN USER (password: english24hadmin)
-- Generated using bcrypt hash for 'english24hadmin'
INSERT INTO `admins` (`username`, `password_hash`) VALUES
('admin', '$2y$10$PCLnQZ7/6ke/g9y4opRtzew3hxxeZiD4RObCzHTK4vtwa1pPHSGES')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- 5. SEED DEFAULT QUESTIONS (36 Questions, 3 per Tense)
INSERT INTO `questions` (`tense`, `question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_idx`) VALUES
-- PRESENT TENSES
('Simple Present', 'She ___ to school by bus every morning.', 'go', 'goes', 'going', 'went', 1),
('Simple Present', '___ you play soccer on weekends?', 'Do', 'Does', 'Are', 'Have', 0),
('Simple Present', 'Water ___ at 100 degrees Celsius under normal pressure.', 'boils', 'boil', 'boiling', 'boiled', 0),

('Present Continuous', 'Look! The boys ___ football in the school yard.', 'is playing', 'play', 'are playing', 'played', 2),
('Present Continuous', 'I am ___ for my English exam right now.', 'studying', 'study', 'studies', 'studied', 0),
('Present Continuous', 'Why ___ she crying at the moment?', 'am', 'is', 'are', 'does', 1),

('Present Perfect', 'I ___ in London for three years and I like it.', 'have lived', 'has lived', 'lived', 'am living', 0),
('Present Perfect', 'She has already ___ three best-selling books.', 'written', 'write', 'wrote', 'writing', 0),
('Present Perfect', 'Have you ever ___ to France?', 'been', 'gone', 'go', 'went', 0),

('Present Perfect Continuous', 'They ___ here since early morning.', 'have been working', 'has been working', 'worked', 'are working', 0),
('Present Perfect Continuous', 'It ___ for two hours; the ground is completely wet.', 'has been raining', 'have been raining', 'is raining', 'rained', 0),
('Present Perfect Continuous', 'She is exhausted because she ___ around the park.', 'has been running', 'have been running', 'is running', 'ran', 0),

-- PAST TENSES
('Simple Past', 'We ___ a great movie at the cinema yesterday.', 'watch', 'watching', 'watched', 'have watched', 2),
('Simple Past', 'Where ___ you go for your last summer vacation?', 'did', 'do', 'were', 'have', 0),
('Simple Past', 'She ___ a new smartphone last week.', 'bought', 'buy', 'buys', 'buying', 0),

('Past Continuous', 'While I ___ dinner, the electricity went out.', 'were cooking', 'was cooking', 'cooked', 'am cooking', 1),
('Past Continuous', 'What ___ you doing at 9 PM yesterday evening?', 'were', 'was', 'did', 'are', 0),
('Past Continuous', 'They ___ when the burglar broke into the house.', 'were sleeping', 'was sleeping', 'slept', 'are sleeping', 0),

('Past Perfect', 'By the time he arrived, the train ___ already.', 'left', 'has left', 'had left', 'was leaving', 2),
('Past Perfect', 'I went to sleep right after I ___ my homework.', 'had finished', 'finished', 'have finished', 'was finishing', 0),
('Past Perfect', 'She realized she ___ her keys at the office.', 'forgot', 'had forgotten', 'has forgotten', 'forgets', 1),

('Past Perfect Continuous', 'He was out of breath because he ___ for forty minutes.', 'had been running', 'has been running', 'was running', 'ran', 0),
('Past Perfect Continuous', 'They ___ for two hours before the bus finally came.', 'have been waiting', 'were waiting', 'had been waiting', 'waited', 2),
('Past Perfect Continuous', 'She ___ English for five years before moving to New York.', 'had been studying', 'was studying', 'studied', 'has been studying', 0),

-- FUTURE TENSES
('Simple Future', 'I think it ___ rain tomorrow, so take an umbrella.', 'will', 'is going to', 'rains', 'rained', 0),
('Simple Future', 'What ___ you do if you pass the final exams?', 'will', 'are', 'do', 'would', 0),
('Simple Future', 'We ___ them at the train station next Monday.', 'meet', 'will meet', 'meeting', 'met', 1),

('Future Continuous', 'At this exact time tomorrow, I ___ to Tokyo.', 'will fly', 'will be flying', 'am flying', 'fly', 1),
('Future Continuous', 'Please don\'t call at 9 PM; I ___ the football match.', 'will watch', 'will be watching', 'watch', 'am watching', 1),
('Future Continuous', 'They ___ on the project all day tomorrow.', 'will be working', 'will work', 'work', 'are working', 0),

('Future Perfect', 'By next June, I ___ from high school.', 'will graduate', 'graduate', 'will have graduated', 'have graduated', 2),
('Future Perfect', 'She ___ the report by tomorrow afternoon.', 'will have finished', 'will finish', 'has finished', 'finishes', 0),
('Future Perfect', 'By midnight, the children ___ asleep.', 'will have fallen', 'will fall', 'will be falling', 'have fallen', 0),

('Future Perfect Continuous', 'By next month, she ___ here for exactly ten years.', 'will be working', 'will have been working', 'has been working', 'will work', 1),
('Future Perfect Continuous', 'By midnight, the band ___ for four hours straight.', 'will have been playing', 'will be playing', 'have been playing', 'play', 0),
('Future Perfect Continuous', 'In October, he ___ in this apartment for three years.', 'will have been living', 'will be living', 'is living', 'will live', 0);
