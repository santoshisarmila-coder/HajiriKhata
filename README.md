# HajiriKhata - College Attendance Management System

A full-stack, responsive, and secure **College Attendance Management System** built with modern PHP (PDO, OOP/Prepared Statements), MySQL, Bootstrap 5, HTML5, and JavaScript.

---

## 🌟 Key Features by Role

### 1. 🛡️ Administrator Module
- **Dashboard Analytics**: Real-time KPI metric cards (Total Students, Teachers, Subjects, Classes Conducted, Overall Attendance Percentage), attendance distribution charts (Chart.js), and attendance shortage warning alerts.
- **Academic Setup (CRUD)**:
  - **Departments**: Add, edit, and delete academic departments.
  - **Semesters**: Configure semesters (SEM-1, SEM-2, etc.).
  - **Subjects / Courses**: Define course codes, names, credit hours, department, and semester links.
- **User Management (CRUD)**:
  - **Teachers / Faculty**: Create and manage instructor profiles.
  - **Students**: Register students with Roll Numbers, Emails, and Department assignments.
- **Enrollment & Assignment**:
  - **Student Enrollment**: Bulk and individual enrollment matrix to assign students to specific subjects.
  - **Teacher Assignment**: Assign faculty instructors to specific subjects.
- **System-Wide Reports & Export**:
  - Filter attendance by Subject, Department, Student, Status, and Date Range.
  - One-click **CSV Export** with UTF-8 BOM Excel compatibility.
  - Print-ready and PDF-friendly attendance summary sheets.

---

### 2. 👨‍🏫 Teacher / Faculty Module
- **Instructor Dashboard**: Overview of assigned subjects, total enrolled students, and recent class sessions.
- **Interactive Attendance Sheet (`take_attendance.php`)**:
  - Dynamic subject selector, date picker, and time slot dropdown.
  - **Quick Action Bar**: "Mark All Present", "Mark All Late", "Mark All Absent" buttons.
  - Status toggle buttons (Present, Late, Absent) per student with custom remarks.
  - Automatic updates via database transaction (`ON DUPLICATE KEY UPDATE`).
- **24-Hour Edit Restriction**:
  - Teachers can edit marked attendance within **24 hours** of creation.
  - Sessions older than 24 hours are locked automatically to preserve record integrity.
- **Subject-Level Analytics (`view_attendance.php`)**:
  - Cumulative attendance percentage per student with shortage warnings (< 75%).
  - Export subject records directly to CSV.

---

### 3. 🎓 Student Module
- **Student Dashboard (`dashboard.php`)**:
  - Overall aggregate attendance percentage.
  - Per-subject breakdown (Total Classes Conducted, Attended, Absent, Percentage).
  - **Threshold Shortage Alert**: Prominent warning banner if attendance in any subject or overall falls below 75%.
- **Date-by-Date Attendance History (`history.php`)**:
  - Complete historical log with timestamps, instructor names, status badges, and remarks.
  - Filter by subject, date range, or status (Present / Absent / Late).

---

## 🗄️ Database Setup & Installation

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL/MariaDB + PHP 7.4+ or 8.x)

### Quick Setup Steps
1. Place this project folder in your XAMPP `htdocs` directory:
   ```
   C:\xampp\htdocs\Attendence System\
   ```
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open phpMyAdmin (`http://localhost/phpmyadmin/`) or MySQL CLI.
4. Import the provided [`schema.sql`](file:///C:/xampp/htdocs/Attendence%20System/schema.sql) file. It will automatically create the `attendance_db` database and seed sample data.
5. Open your web browser and navigate to:
   ```
   http://localhost/Attendence%20System/
   ```

---

## 🔑 Default Seed Test Accounts

All accounts are pre-seeded in `schema.sql` with hashed passwords via `password_hash()`:

| Role | Email / Username | Password | Notes |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@college.edu` / `admin` | `admin123` | Full system access |
| **Teacher 1** | `teacher1@college.edu` / `teacher1` | `teacher123` | Assigned to CS101, CS103 |
| **Teacher 2** | `teacher2@college.edu` / `teacher2` | `teacher123` | Assigned to CS102 |
| **Student 1** | `student1@college.edu` / `student1` | `student123` | Roll: CS-2024-01 (>80% Att.) |
| **Student 5** | `student5@college.edu` / `student5` | `student123` | Roll: CS-2024-05 (<75% Warning) |

> 💡 *On the login page, you can also use the one-click demo buttons to automatically populate any of these test accounts.*

---

## 🔒 Security Implementations
- **SQL Injection Defense**: Prepared Statements with bound parameters via PDO throughout the entire codebase.
- **XSS Protection**: HTML output encoding with `htmlspecialchars()` wrapper `e()`.
- **CSRF Defense**: Session-bound cryptographic CSRF token generation and validation on all state-altering forms.
- **Session Security**: Session regeneration on authentication (`session_regenerate_id(true)`) and `HttpOnly` cookie settings.
- **Password Security**: Modern BCRYPT hashing using `password_hash()` and `password_verify()`.
- **Role-Based Authorization**: Middleware functions (`require_role()`, `require_login()`) securing every module.

---

## 📁 Directory Structure
```
Attendence System/
├── config/
│   └── db.php                  # PDO connection and system configuration
├── includes/
│   ├── auth.php                # Authentication guards, role checkers, CSRF tokens
│   ├── header.php              # Bootstrap 5 layout header & responsive navigation
│   ├── footer.php              # Footer layout, Chart.js, scripts
│   └── helpers.php             # Flash messages, percentage calculations, sanitization
├── admin/
│   ├── dashboard.php           # Admin KPI analytics & attendance donut chart
│   ├── departments.php         # Department CRUD
│   ├── semesters.php           # Semester CRUD
│   ├── subjects.php            # Subject CRUD
│   ├── teachers.php            # Teacher CRUD
│   ├── students.php            # Student CRUD
│   ├── assign_students.php     # Student enrollment management
│   ├── assign_teachers.php     # Teacher course assignments
│   ├── reports.php             # Filterable attendance reports
│   └── export.php              # CSV exporter
├── teacher/
│   ├── dashboard.php           # Teacher summary & quick actions
│   ├── take_attendance.php     # Interactive roll call sheet with quick toggles
│   ├── edit_attendance.php     # Past attendance editor (24-hour window)
│   └── view_attendance.php     # Subject attendance breakdown & student rates
├── student/
│   ├── dashboard.php           # Student attendance overview & threshold warnings
│   └── history.php             # Date-by-date attendance history
├── assets/
│   ├── css/
│   │   └── style.css           # Custom UI styling and status colors
│   └── js/
│       └── main.js             # Live search, "Mark All" toggles, alert dismissals
├── index.php                   # Entrypoint & smart role redirector
├── login.php                   # Unified login page with demo autofill
├── logout.php                  # Secure session logout
├── schema.sql                  # Database schema & sample seed data
└── README.md                   # Documentation & setup guide
```
>>>>>>> d8383c6 (Commit)
