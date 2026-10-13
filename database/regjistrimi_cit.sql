-- =====================================================================
--  Regjistrimi i Studentëve — CIT (Instituti Kanadian i Teknologjisë)
--  Databaza MySQL / MariaDB
--
--  Si përdoret:
--   1) Ndizni MySQL në XAMPP Control Panel.
--   2) Hapni programin: ai e krijon databazën vetë me këtë skedar (butoni "Krijo databazën"),
--      ose importojeni me dorë në phpMyAdmin (http://localhost/phpmyadmin -> Import).
--   3) Hyni me llogarinë fillestare:  përdoruesi "admin", fjalëkalimi "admin"
--      dhe ndryshojeni fjalëkalimin menjëherë te ⚙ Admin -> Llogaria.
--
--  Fjalëkalimi ruhet i enkriptuar (bcrypt), jo si tekst.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `regjistrimi_cit` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `regjistrimi_cit`;

-- Llogaria e administratorit (një llogari)
CREATE TABLE IF NOT EXISTS `admin` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(60)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cilësimet e programit (p.sh. dosja ku ruhen studentët)
CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(80) NOT NULL,
  `v` TEXT        NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nivelet e studimit: emri në program + dosja ku ruhen Word + foto
CREATE TABLE IF NOT EXISTS `levels` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(80)  NOT NULL,              -- p.sh. "Master of Science"
  `folder`     VARCHAR(40)  NOT NULL,              -- p.sh. "Master"
  `sort_order` INT          NOT NULL DEFAULT 0,
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_levels_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Programet / degët (me profil opsional)
CREATE TABLE IF NOT EXISTS `programs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `level_id`   INT UNSIGNED NOT NULL,
  `name`       VARCHAR(150) NOT NULL,              -- p.sh. "Business Administration"
  `profile`    VARCHAR(150) NOT NULL DEFAULT '',   -- p.sh. "Digital Marketing Profile" (bosh = pa profil)
  `sort_order` INT          NOT NULL DEFAULT 0,
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_programs` (`level_id`, `name`, `profile`),
  CONSTRAINT `fk_programs_level` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Raundet e regjistrimit
CREATE TABLE IF NOT EXISTS `rounds` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(60)  NOT NULL,              -- p.sh. "Raundi 1"
  `sort_order` INT          NOT NULL DEFAULT 0,
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rounds_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fushat shtesë për studentët, që i shton administratori (p.sh. "Nr. personal", "Qyteti")
CREATE TABLE IF NOT EXISTS `custom_fields` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label`      VARCHAR(100) NOT NULL,              -- emri që shfaqet, p.sh. "Nr. personal"
  `field_key`  VARCHAR(60)  NOT NULL,              -- p.sh. "NR_PERSONAL" -> {{NR_PERSONAL}} në modelin Word
  `type`       ENUM('text','number','date','select','textarea') NOT NULL DEFAULT 'text',
  `options`    TEXT         NULL,                  -- për "select": një zgjedhje për rresht
  `required`   TINYINT(1)   NOT NULL DEFAULT 0,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_custom_fields_key` (`field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Studentët (Word + foto ruhen në dosje; këtu ruhen të dhënat dhe vendi i skedarëve)
CREATE TABLE IF NOT EXISTS `students` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name`    VARCHAR(100) NOT NULL,
  `last_name`     VARCHAR(100) NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `password`      VARCHAR(100) NOT NULL,
  `phone`         VARCHAR(40)  NOT NULL DEFAULT '',
  `notes`         VARCHAR(500) NOT NULL DEFAULT '',
  `academic_year` VARCHAR(9)   NOT NULL DEFAULT '',
  `round`         VARCHAR(60)  NOT NULL DEFAULT '',
  `program`       VARCHAR(400) NOT NULL DEFAULT '', -- p.sh. "Software Engineering (Bachelor)"
  `base`          VARCHAR(500) NOT NULL DEFAULT '', -- dosja kryesore ("" = dosja "students" e programit)
  `subfolder`     VARCHAR(255) NOT NULL DEFAULT '', -- p.sh. "2026-2027/Raundi 1/Bachelor"
  `photo`         VARCHAR(255) NOT NULL DEFAULT '', -- p.sh. "Ana Hoxha.jpg"
  `docx`          VARCHAR(255) NOT NULL DEFAULT '', -- p.sh. "Ana Hoxha.docx"
  `imported`      TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = lexuar nga letra Word në dosje
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_students_email` (`email`),
  KEY `ix_students_round` (`academic_year`, `round`),
  KEY `ix_students_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vlerat e fushave shtesë për çdo student
CREATE TABLE IF NOT EXISTS `student_values` (
  `student_id` INT UNSIGNED NOT NULL,
  `field_id`   INT UNSIGNED NOT NULL,
  `value`      TEXT         NULL,
  PRIMARY KEY (`student_id`, `field_id`),
  CONSTRAINT `fk_values_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_values_field`   FOREIGN KEY (`field_id`)   REFERENCES `custom_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Të dhënat fillestare (mund të ndryshohen te faqja ⚙ Admin)
-- ---------------------------------------------------------------------

-- Llogaria fillestare: përdoruesi "admin", fjalëkalimi "admin" (ndryshojeni pas hyrjes së parë)
INSERT IGNORE INTO `admin` (`id`, `username`, `password_hash`) VALUES
  (1, 'admin', '$2y$10$/X4MVGV3sATSLU3IU4oKSu9hASNMdEZqdk4kGyROc.33BKn5T5J5K');
INSERT IGNORE INTO `levels` (`id`, `name`, `folder`, `sort_order`) VALUES
  (1, 'Bachelor', 'Bachelor', 1),
  (2, 'Master of Science', 'Master', 2);

INSERT IGNORE INTO `rounds` (`name`, `sort_order`) VALUES
  ('Raundi 1', 1), ('Raundi 2', 2), ('Raundi 3', 3);

INSERT IGNORE INTO `programs` (`level_id`, `name`, `profile`, `sort_order`) VALUES
  (1, 'Software Engineering', '', 1),
  (1, 'Computer Engineering & IT', '', 2),
  (1, 'Artificial Intelligence and Data Science', '', 3),
  (1, 'Electronics Engineering', '', 4),
  (1, 'Robotics & Mechatronics Engineering', '', 5),
  (1, 'Telecommunication Engineering', '', 6),
  (1, 'Business Administration & IT', '', 7),
  (1, 'Business Administration', '', 8),
  (1, 'Business Administration', 'Digital Marketing Profile', 9),
  (1, 'Business Administration', 'Business Analytics Profile', 10),
  (1, 'Finance & Accounting', '', 11),
  (1, 'Finance & Accounting', 'Fintech & Investments Profile', 12),
  (1, 'Finance & Accounting', 'International Finance & Economics Profile', 13),
  (2, 'Software Engineering', '', 1),
  (2, 'Computer Engineering & IT', '', 2),
  (2, 'Computer Engineering & IT', 'Computer Engineering & Big Data Profile', 3),
  (2, 'Computer Engineering & IT', 'Network & CyberSecurity Profile', 4),
  (2, 'Computer Engineering & IT', 'Multimedia Design Profile', 5),
  (2, 'Artificial Intelligence', '', 6),
  (2, 'Business Administration', '', 7),
  (2, 'Business Information Technology', '', 8),
  (2, 'Digital Marketing', '', 9),
  (2, 'Finance & Accounting', '', 10),
  (2, 'Finance & Accounting', 'Accounting & Auditing Profile', 11),
  (2, 'Finance & Accounting', 'Finance & Banking Profile', 12);
