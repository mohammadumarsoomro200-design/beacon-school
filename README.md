# The New Beacon School System — Complete School Management System

A PHP + MySQL school website and management system for **The New Beacon School System, Larkana Campus**.

## Included
### Public website
- Responsive home/about/academics/facilities/admissions/gallery/contact pages
- Supplied school logo and campus images
- Admission enquiry form saved to MySQL
- Portal login link

### Admin / staff management portal
- Secure session login with password hashing
- CSRF protection and prepared SQL statements
- Dashboard statistics
- Students: add, edit, search, delete
- Teachers: add, edit, delete
- Classes and sections
- Daily attendance by class/date
- Exams and marks/results entry
- Fee billing, payments, balances and receipt numbers
- Admission enquiry tracking/status
- Notices
- Homework
- Timetable
- Events
- Gallery uploads/deletes
- User accounts and roles
- Editable school settings
- Audit log table

### Student / parent portal
- Login for student/parent accounts
- Student profile/class
- Attendance percentage
- Fee outstanding balance
- Latest results
- Homework
- School notices

## Local installation (XAMPP)
1. Install XAMPP.
2. Copy this folder into `C:\xampp\htdocs\beacon-school\`.
3. Start **Apache** and **MySQL**.
4. Open phpMyAdmin: `http://localhost/phpmyadmin/`.
5. Import `database/schema.sql`.
6. Open `http://localhost/beacon-school/setup.php`.
7. Create your first admin username/password.
8. **Delete `setup.php` from the server after setup.**
9. Open `http://localhost/beacon-school/admin/login.php`.
10. Public site: `http://localhost/beacon-school/`.

## Database settings
Default local XAMPP credentials in `config/config.php` are:
- Host: 127.0.0.1
- Database: beacon_school
- User: root
- Password: blank

If your MySQL password is different, edit `config/config.php` before logging in.

## Important production steps
Before putting this online, use HTTPS, change the database credentials, keep `setup.php` deleted, configure backups, restrict upload types/size, and use a strong unique admin password.

## Domain deployment
After buying/activating `beaconschoolsystem.edu.pk`, upload the project to your PHP/MySQL hosting, import the schema, update database credentials, and point the domain DNS/nameservers to the hosting server.
