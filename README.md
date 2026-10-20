# Click CRM

Click CRM is a PHP and MySQL customer relationship management application.

## Requirements

- PHP 8.1 or newer with PDO MySQL, GD, mbstring, and ZIP extensions
- MySQL 5.7+ or MariaDB 10.4+
- Composer

## Setup

1. Clone this repository into your PHP web server document root.
2. Run `composer install` in the project directory.
3. Create the database by importing `install.sql` into MySQL.
4. Copy `config/config.example.php` to `config/config.php` and set the database credentials.
5. Ensure the web server can write to `uploads/` and `logs/`.
6. Open the project URL in your browser and sign in with the initial account: `admin` / `password`.
7. Change the initial admin password immediately after the first sign-in.

The local configuration, uploaded files, runtime logs, and Composer dependencies are intentionally excluded from Git.
