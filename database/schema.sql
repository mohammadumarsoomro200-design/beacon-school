CREATE DATABASE IF NOT EXISTS beacon_school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE beacon_school;

CREATE TABLE IF NOT EXISTS users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 full_name VARCHAR(150) NOT NULL,
 role ENUM('admin','teacher','accountant','parent','student') NOT NULL DEFAULT 'student',
 active TINYINT(1) NOT NULL DEFAULT 1,
 student_id INT NULL,
 teacher_id INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(role), INDEX(student_id), INDEX(teacher_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS classes (
 id INT AUTO_INCREMENT PRIMARY KEY,
 class_name VARCHAR(80) NOT NULL,
 section VARCHAR(30) NOT NULL DEFAULT 'A',
 class_teacher_id INT NULL,
 room VARCHAR(30),
 academic_year VARCHAR(20) NOT NULL DEFAULT '2026-27',
 UNIQUE KEY uq_class(class_name,section,academic_year)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS students (
 id INT AUTO_INCREMENT PRIMARY KEY,
 admission_no VARCHAR(50) NOT NULL UNIQUE,
 student_name VARCHAR(150) NOT NULL,
 father_name VARCHAR(150),
 mother_name VARCHAR(150),
 gender ENUM('Male','Female','Other') DEFAULT 'Male',
 dob DATE NULL,
 class_id INT NULL,
 phone VARCHAR(40),
 parent_phone VARCHAR(40),
 email VARCHAR(150),
 address TEXT,
 admission_date DATE NULL,
 status ENUM('active','inactive','graduated','left') DEFAULT 'active',
 photo VARCHAR(255),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(class_id) REFERENCES classes(id) ON DELETE SET NULL,
 INDEX(student_name), INDEX(status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teachers (
 id INT AUTO_INCREMENT PRIMARY KEY,
 employee_no VARCHAR(50) NOT NULL UNIQUE,
 name VARCHAR(150) NOT NULL,
 subject VARCHAR(100),
 designation VARCHAR(100),
 phone VARCHAR(40),
 email VARCHAR(150),
 joining_date DATE NULL,
 salary DECIMAL(12,2) DEFAULT 0,
 status ENUM('active','inactive') DEFAULT 'active',
 photo VARCHAR(255),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE classes ADD CONSTRAINT fk_class_teacher FOREIGN KEY(class_teacher_id) REFERENCES teachers(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS subjects (
 id INT AUTO_INCREMENT PRIMARY KEY,
 subject_name VARCHAR(100) NOT NULL UNIQUE,
 subject_code VARCHAR(30),
 max_marks DECIMAL(8,2) DEFAULT 100
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance (
 id INT AUTO_INCREMENT PRIMARY KEY,
 student_id INT NOT NULL,
 attendance_date DATE NOT NULL,
 status ENUM('present','absent','leave') NOT NULL,
 remarks VARCHAR(255),
 UNIQUE KEY uq_attendance(student_id,attendance_date),
 FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
 INDEX(attendance_date), INDEX(status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exams (
 id INT AUTO_INCREMENT PRIMARY KEY,
 exam_name VARCHAR(100) NOT NULL,
 class_id INT NULL,
 exam_date DATE NULL,
 academic_year VARCHAR(20) DEFAULT '2026-27',
 FOREIGN KEY(class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS results (
 id INT AUTO_INCREMENT PRIMARY KEY,
 exam_id INT NOT NULL,
 student_id INT NOT NULL,
 subject_id INT NOT NULL,
 marks DECIMAL(8,2) NOT NULL,
 total_marks DECIMAL(8,2) NOT NULL DEFAULT 100,
 remarks VARCHAR(255),
 UNIQUE KEY uq_result(exam_id,student_id,subject_id),
 FOREIGN KEY(exam_id) REFERENCES exams(id) ON DELETE CASCADE,
 FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
 FOREIGN KEY(subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fees (
 id INT AUTO_INCREMENT PRIMARY KEY,
 student_id INT NOT NULL,
 fee_month VARCHAR(30) NOT NULL,
 fee_year INT NOT NULL,
 amount DECIMAL(12,2) NOT NULL,
 paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 due_date DATE NULL,
 status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
 payment_date DATE NULL,
 receipt_no VARCHAR(60) UNIQUE,
 notes VARCHAR(255),
 FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
 INDEX(student_id,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admission_enquiries (
 id INT AUTO_INCREMENT PRIMARY KEY,
 student_name VARCHAR(150) NOT NULL,
 parent_name VARCHAR(150) NOT NULL,
 class_level VARCHAR(80) NOT NULL,
 phone VARCHAR(40) NOT NULL,
 email VARCHAR(150),
 message TEXT,
 status ENUM('new','contacted','admitted','rejected') DEFAULT 'new',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(status), INDEX(created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notices (
 id INT AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(200) NOT NULL,
 body TEXT NOT NULL,
 audience ENUM('all','students','parents','teachers') DEFAULT 'all',
 published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
 id INT AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(200) NOT NULL,
 event_date DATE NOT NULL,
 description TEXT,
 location VARCHAR(150),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gallery (
 id INT AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(150),
 image_path VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS homework (
 id INT AUTO_INCREMENT PRIMARY KEY,
 class_id INT NOT NULL,
 subject_id INT NOT NULL,
 teacher_id INT NULL,
 title VARCHAR(200) NOT NULL,
 description TEXT,
 due_date DATE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(class_id) REFERENCES classes(id) ON DELETE CASCADE,
 FOREIGN KEY(subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
 FOREIGN KEY(teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS timetable (
 id INT AUTO_INCREMENT PRIMARY KEY,
 class_id INT NOT NULL,
 day_name ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
 period_no INT NOT NULL,
 subject_id INT NOT NULL,
 teacher_id INT NULL,
 start_time TIME,
 end_time TIME,
 FOREIGN KEY(class_id) REFERENCES classes(id) ON DELETE CASCADE,
 FOREIGN KEY(subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
 FOREIGN KEY(teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
 id INT AUTO_INCREMENT PRIMARY KEY,
 setting_key VARCHAR(100) NOT NULL UNIQUE,
 setting_value TEXT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NULL,
 action VARCHAR(80) NOT NULL,
 module VARCHAR(80) NOT NULL,
 details TEXT,
 ip_address VARCHAR(45),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO subjects(subject_name,subject_code,max_marks) VALUES
('English','ENG',100),('Mathematics','MATH',100),('Science','SCI',100),('Urdu','URD',100),('Computer Science','CS',100),('Social Studies','SST',100),('Islamiat','ISL',100);
INSERT IGNORE INTO settings(setting_key,setting_value) VALUES
('school_name','The New Beacon School System'),('campus','Larkana Campus'),('phone','03337132010'),('email','mohammadumarsoomro200@gmail.com'),('address','Behind Arts Council, Larkana'),('motto','Join Us & Change the World'),('ceo','Ali Akbar Soomro');
