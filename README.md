# Qharleh Productions Africa — Portfolio & Booking Management System

Qharleh Productions Africa is a premium, fully responsive, and dynamic portfolio and client inquiry web application tailored for professional photographers and videographers. It features a modern, dark-themed landing page showcasing portfolio categories, a secure admin panel for managing content, and a client booking portal.

---

## 🌟 Core Features

- **Dynamic Showcase Gallery**: Filters photographs and cinematic videos by category (e.g., Street Photography, Studio Portraits, Cinematic Video) with seamless CSS animations.
- **Client Booking & Inquiry Portal**: An interactive contact form that saves booking inquiries directly into the database.
- **Robust Admin Dashboard**:
  - **Portfolio Manager**: Upload new images, write titles and descriptions, and link them to categories.
  - **Category Manager**: Create, edit, and delete portfolio categories dynamically.
  - **Inquiry Inbox**: Read messages and booking requests from potential clients in real-time.
- **Interactive Database Installer**: A step-by-step visual setup wizard (`setup.php`) that runs MySQL migrations, seeds demo categories/images, and establishes the administrator account.
- **Security First**: Password encryption via bcrypt, PDO prepared statements for SQL injection prevention, and strict session-based authentication.

---

## 🛠️ Tech Stack & Libraries

- **Backend**: Core PHP (OOP & PDO database wrapper, PHP Sessions)
- **Frontend**: Custom CSS3, HTML5, Bootstrap 5 (Admin & Setup pages)
- **Database**: MySQL / MariaDB
- **Typography & Icons**: Google Fonts (Outfit), FontAwesome 6 Icons

---

## 📂 Project Structure

```text
qharleh_productions/
├── assets/
│   └── images/              # Static media assets & sample photography images
├── uploads/                 # Dynamically uploaded portfolio images (Git ignored)
├── config.php               # System configurations, database credentials, & site meta
├── database.php             # Singleton Database connection wrapper using PDO
├── schema.sql               # MySQL database tables definition
├── setup.php                # Database installation & admin initialization wizard
├── index.php                # Public portfolio landing page & contact form
├── login.php                # Admin authentication page
├── logout.php               # Session termination script
├── admin.php                # Protected Admin management panel
├── style.css                # Custom visual styling & responsive design rules
└── .gitignore               # Ignored system files and dynamic uploads
```

---

## 🚀 Quick Start & Installation

### Step 1: Set Up Locally (XAMPP / MAMP / Laragon)
1. Clone or copy the project files to your local server root (e.g., `htdocs` or `www`).
2. Make sure your MySQL and Apache services are running.

### Step 2: Configure Database Credentials
Open `config.php` and configure your database host, user, and password:
```php
// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'qharleh_productions'); // The setup wizard will attempt to create this database
```

### Step 3: Run the Visual Setup Wizard
1. Open your browser and navigate to: `http://localhost/qharleh_productions/setup.php`
2. Follow the 4-step wizard:
   - **Database Connection Check**: Ensures the credentials in `config.php` are correct.
   - **Database Creation**: Creates the database automatically if it doesn't exist.
   - **Run SQL Migrations**: Creates the `users`, `categories`, and `images` tables using `schema.sql`.
   - **Create Administrator**: Form to set up your primary admin login credentials, which also seeds the default demo images and categories.

### Step 4: Security Polish
> [!IMPORTANT]
> Once setup is complete, delete the `setup.php` file from your web server to prevent unauthorized database resets or credential overwrites.

---

## 🌐 Deploying to Shared Hosting (e.g., InfinityFree)

If you are deploying this application to a free hosting service like InfinityFree, follow these steps:

1. **Create the Database Manually**: Free hosts restrict dynamic database creation. Log in to your hosting Control Panel, open **MySQL Databases**, and create a database.
2. **Update `config.php`**: Copy the database host (e.g., `sql202.epizy.com`), username (e.g., `epiz_31201923`), database name, and password provided by the hosting dashboard into `config.php`.
3. **Upload Files**: Transfer the files to your server's public folder (usually `htdocs`) via FTP.
4. **Initialize tables**: Run the `setup.php` script from your web browser to initialize the database tables, seed the demo assets, and set up your admin username and password.
5. **Delete Installer**: Delete `setup.php` from your FTP file manager once complete.
