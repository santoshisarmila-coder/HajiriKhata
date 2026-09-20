-- College Attendance Management System Database Schema & Seed Data
-- Compatible with MySQL 5.7+ and MySQL 8.0+ / MariaDB

CREATE DATABASE IF NOT EXISTS `attendance_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `attendance_db`;

-- Drop existing tables in reverse dependency order if resetting
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `subject_teacher`;
DROP TABLE IF EXISTS `subject_student`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `semesters`;
DROP TABLE IF EXISTS `departments`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Departments Table
CREATE TABLE `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Semesters Table
CREATE TABLE `semesters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Users Table (Admin, Teacher, Student)
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'teacher', 'student') NOT NULL,
    `department_id` INT NULL,
    `roll_no` VARCHAR(50) NULL UNIQUE,
    `phone` VARCHAR(20) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Subjects Table
CREATE TABLE `subjects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `subject_code` VARCHAR(20) NOT NULL UNIQUE,
    `subject_name` VARCHAR(100) NOT NULL,
    `department_id` INT NOT NULL,
    `semester_id` INT NOT NULL,
    `credit_hours` INT DEFAULT 3,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Subject - Student Pivot (Enrollment)
CREATE TABLE `subject_student` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `subject_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `enrolled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_subject_student` (`subject_id`, `student_id`),
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Subject - Teacher Pivot (Assignment)
CREATE TABLE `subject_teacher` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `subject_id` INT NOT NULL,
    `teacher_id` INT NOT NULL,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_subject_teacher` (`subject_id`, `teacher_id`),
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Attendance Records Table
CREATE TABLE `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `teacher_id` INT NOT NULL,
    `attendance_date` DATE NOT NULL,
    `time_slot` VARCHAR(50) NOT NULL DEFAULT '09:00 AM - 10:00 AM',
    `status` ENUM('Present', 'Absent', 'Late') NOT NULL DEFAULT 'Present',
    `remarks` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_date_subject` (`attendance_date`, `subject_id`),
    INDEX `idx_student_subject` (`student_id`, `subject_id`),
    INDEX `idx_teacher_date` (`teacher_id`, `attendance_date`),
    UNIQUE KEY `unique_attendance_entry` (`student_id`, `subject_id`, `attendance_date`, `time_slot`),
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================================
-- SEED DATA
-- ==========================================================

-- Insert Departments
INSERT INTO `departments` (`id`, `name`, `code`) VALUES
(1, 'Computer Science & Engineering', 'CSE'),
(2, 'Information Technology', 'IT'),
(3, 'Electronics & Communication', 'ECE');

-- Insert Semesters
INSERT INTO `semesters` (`id`, `name`, `code`) VALUES
(1, 'First Semester', 'SEM-1'),
(2, 'Second Semester', 'SEM-2'),
(3, 'Third Semester', 'SEM-3'),
(4, 'Fourth Semester', 'SEM-4');

-- Passwords:
-- admin123:   $2y$12$Zw6tAGqvfQVr.yugrkFXqO5cR0m71JbCXTIAbTJzpKLKWjBDuBJDy
-- teacher123: $2y$12$I1cJ.t5T1825DrJ7HRvEA.ffbrG0YZ.zdMLUA1Nv/knw84n0ZffY.
-- student123: $2y$12$P/X.fNE9Bs8VudRR5vUsJON7KOorVJ2JDiOYOb/wQczpD5k7C1zPy

-- Insert Users (1 Admin, 2 Teachers, 5 Students)
INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `department_id`, `roll_no`, `phone`) VALUES
-- Admin (ID: 1)
(1, 'admin', 'admin@college.edu', '$2y$12$Zw6tAGqvfQVr.yugrkFXqO5cR0m71JbCXTIAbTJzpKLKWjBDuBJDy', 'System Administrator', 'admin', 1, NULL, '+1-555-0100'),

-- Teachers (ID: 2, 3)
(2, 'teacher1', 'teacher1@college.edu', '$2y$12$I1cJ.t5T1825DrJ7HRvEA.ffbrG0YZ.zdMLUA1Nv/knw84n0ZffY.', 'Prof. John Smith', 'teacher', 1, NULL, '+1-555-0101'),
(3, 'teacher2', 'teacher2@college.edu', '$2y$12$I1cJ.t5T1825DrJ7HRvEA.ffbrG0YZ.zdMLUA1Nv/knw84n0ZffY.', 'Dr. Sarah Johnson', 'teacher', 1, NULL, '+1-555-0102'),

-- Students (ID: 4, 5, 6, 7, 8)
(4, 'student1', 'student1@college.edu', '$2y$12$P/X.fNE9Bs8VudRR5vUsJON7KOorVJ2JDiOYOb/wQczpD5k7C1zPy', 'Alex Brown', 'student', 1, 'CS-2024-01', '+1-555-0201'),
(5, 'student2', 'student2@college.edu', '$2y$12$P/X.fNE9Bs8VudRR5vUsJON7KOorVJ2JDiOYOb/wQczpD5k7C1zPy', 'Emily Davis', 'student', 1, 'CS-2024-02', '+1-555-0202'),
(6, 'student3', 'student3@college.edu', '$2y$12$P/X.fNE9Bs8VudRR5vUsJON7KOorVJ2JDiOYOb/wQczpD5k7C1zPy', 'Michael Wilson', 'student', 1, 'CS-2024-03', '+1-555-0203'),
(7, 'student4', 'student4@college.edu', '$2y$12$P/X.fNE9Bs8VudRR5vUsJON7KOorVJ2JDiOYOb/wQczpD5k7C1zPy', 'Jessica Taylor', 'student', 1, 'CS-2024-04', '+1-555-0204'),
(8, 'student5', 'student5@college.edu', '$2y$12$P/X.fNE9Bs8VudRR5vUsJON7KOorVJ2JDiOYOb/wQczpD5k7C1zPy', 'David Anderson', 'student', 1, 'CS-2024-05', '+1-555-0205');

-- Insert Subjects
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `department_id`, `semester_id`, `credit_hours`) VALUES
(1, 'CS101', 'Web Development Technologies', 1, 1, 4),
(2, 'CS102', 'Database Management Systems', 1, 1, 3),
(3, 'CS103', 'Data Structures & Algorithms', 1, 2, 4);

-- Assign Teachers to Subjects
-- Teacher 1 -> CS101 & CS103
-- Teacher 2 -> CS102
INSERT INTO `subject_teacher` (`subject_id`, `teacher_id`) VALUES
(1, 2),
(2, 3),
(3, 2);

-- Enroll Students to Subjects (All 5 students in CS101 and CS102)
INSERT INTO `subject_student` (`subject_id`, `student_id`) VALUES
(1, 4), (1, 5), (1, 6), (1, 7), (1, 8),
(2, 4), (2, 5), (2, 6), (2, 7), (2, 8);

-- Insert Sample Attendance Records across past dates to test dashboards & charts
-- Subject 1 (CS101 by Teacher 1)
-- Date 1: 2026-08-20
INSERT INTO `attendance` (`student_id`, `subject_id`, `teacher_id`, `attendance_date`, `time_slot`, `status`, `remarks`) VALUES
(4, 1, 2, '2026-08-20', '09:00 AM - 10:00 AM', 'Present', 'On time'),
(5, 1, 2, '2026-08-20', '09:00 AM - 10:00 AM', 'Present', 'On time'),
(6, 1, 2, '2026-08-20', '09:00 AM - 10:00 AM', 'Present', 'On time'),
(7, 1, 2, '2026-08-20', '09:00 AM - 10:00 AM', 'Present', 'On time'),
(8, 1, 2, '2026-08-20', '09:00 AM - 10:00 AM', 'Absent', 'Uninformed leave');

-- Date 2: 2026-08-22
INSERT INTO `attendance` (`student_id`, `subject_id`, `teacher_id`, `attendance_date`, `time_slot`, `status`, `remarks`) VALUES
(4, 1, 2, '2026-08-22', '09:00 AM - 10:00 AM', 'Present', 'Active participation'),
(5, 1, 2, '2026-08-22', '09:00 AM - 10:00 AM', 'Late', 'Late by 10 mins'),
(6, 1, 2, '2026-08-22', '09:00 AM - 10:00 AM', 'Present', 'On time'),
(7, 1, 2, '2026-08-22', '09:00 AM - 10:00 AM', 'Present', 'On time'),
(8, 1, 2, '2026-08-22', '09:00 AM - 10:00 AM', 'Absent', 'Medical reason');

-- Date 3: 2026-08-24
INSERT INTO `attendance` (`student_id`, `subject_id`, `teacher_id`, `attendance_date`, `time_slot`, `status`, `remarks`) VALUES
(4, 1, 2, '2026-08-24', '09:00 AM - 10:00 AM', 'Present', 'On time'),
(5, 1, 2, '2026-08-24', '09:00 AM - 10:00 AM', 'Present', 'On time'),
(6, 1, 2, '2026-08-24', '09:00 AM - 10:00 AM', 'Absent', 'Family emergency'),
(7, 1, 2, '2026-08-24', '09:00 AM - 10:00 AM', 'Present', 'On time'),
(8, 1, 2, '2026-08-24', '09:00 AM - 10:00 AM', 'Absent', 'Absent without notice');

-- Date 4: 2026-08-26
INSERT INTO `attendance` (`student_id`, `subject_id`, `teacher_id`, `attendance_date`, `time_slot`, `status`, `remarks`) VALUES
(4, 1, 2, '2026-08-26', '09:00 AM - 10:00 AM', 'Present', 'On time'),
(5, 1, 2, '2026-08-26', '09:00 AM - 10:00 AM', 'Present', 'On time'),
(6, 1, 2, '2026-08-26', '09:00 AM - 10:00 AM', 'Present', 'On time'),
(7, 1, 2, '2026-08-26', '09:00 AM - 10:00 AM', 'Late', 'Late by 5 mins'),
(8, 1, 2, '2026-08-26', '09:00 AM - 10:00 AM', 'Present', 'On time');

-- Subject 2 (CS102 by Teacher 2)
-- Date 1: 2026-08-21
INSERT INTO `attendance` (`student_id`, `subject_id`, `teacher_id`, `attendance_date`, `time_slot`, `status`, `remarks`) VALUES
(4, 2, 3, '2026-08-21', '11:00 AM - 12:00 PM', 'Present', 'Lab work completed'),
(5, 2, 3, '2026-08-21', '11:00 AM - 12:00 PM', 'Present', 'Lab work completed'),
(6, 2, 3, '2026-08-21', '11:00 AM - 12:00 PM', 'Present', 'Lab work completed'),
(7, 2, 3, '2026-08-21', '11:00 AM - 12:00 PM', 'Present', 'Lab work completed'),
(8, 2, 3, '2026-08-21', '11:00 AM - 12:00 PM', 'Absent', 'Absent');

-- Date 2: 2026-08-25
INSERT INTO `attendance` (`student_id`, `subject_id`, `teacher_id`, `attendance_date`, `time_slot`, `status`, `remarks`) VALUES
(4, 2, 3, '2026-08-25', '11:00 AM - 12:00 PM', 'Present', 'On time'),
(5, 2, 3, '2026-08-25', '11:00 AM - 12:00 PM', 'Present', 'On time'),
(6, 2, 3, '2026-08-25', '11:00 AM - 12:00 PM', 'Present', 'On time'),
(7, 2, 3, '2026-08-25', '11:00 AM - 12:00 PM', 'Absent', 'Sick leave'),
(8, 2, 3, '2026-08-25', '11:00 AM - 12:00 PM', 'Late', 'Late by 15 mins');
