# Electo

Electo is a secure, scalable online voting and election management platform for student unions, associations, and organizations.

## Requirements

- PHP 8.0 or newer with `mysqli` enabled
- MySQL or MariaDB
- Apache with URL rewriting enabled (XAMPP is suitable for local development)

## Local Setup

1. Place the project folder in the web server document root, such as `C:\xampp\htdocs\votingsystem`.
2. Create a MySQL database named `votingsystem`.
3. Import `db/votingsystem.sql` using phpMyAdmin or the MySQL client.
4. Update the connection settings in `includes/conn.php` for your local database.
5. From the project directory, run `php db/migrate.php` once to create and upgrade the election-related tables.
6. Open `http://localhost/votingsystem/` and create an administrator account as described below.

## Create the First Administrator

The database dump intentionally does not contain a default administrator account. Generate a password hash with PHP:

```sh
php -r "echo password_hash(readline('New admin password: '), PASSWORD_DEFAULT), PHP_EOL;"
```

Use the generated hash in phpMyAdmin to create the first administrator. Replace the example username, hash, and name before running:

```sql
INSERT INTO admin (username, password, firstname, lastname, photo, created_on)
VALUES ('admin', '<paste-generated-password-hash>', 'Admin', 'User', '', CURRENT_DATE());
```

Sign in through the **Official / Admin** tab. Do not use a shared or production password in local development.

## SMS Configuration

SMS settings are stored locally in `admin/config.ini`, which is excluded from version control. The application uses simulation defaults when that file is absent. Configure provider credentials through the admin SMS settings page and never commit API keys or OTP logs.

## Repository Hygiene

Voter and candidate photos, SMS logs, local configuration, CSV exports, and legacy setup notes are excluded from Git. Keep real voter records and generated credentials out of source control, including in private repositories.
