<?php
/**
 * SmartSpend Database Configuration
 * Connects to MySQL/MariaDB using PHP Data Objects (PDO)
 */

declare(strict_types=1);

// Configuration constants
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'smartspend');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared PDO database connection instance.
 *
 * @return PDO
 * @throws PDOException
 */
function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Provide a clear, actionable developer message if DB is not initialized yet
            error_log('Database Connection Failed: ' . $e->getMessage());
            throw new Exception(
                'Unable to connect to the SmartSpend database. ' .
                'Please ensure MySQL is running in your XAMPP Control Panel and database "smartspend" has been imported from database.sql. ' .
                'Error details: ' . htmlspecialchars($e->getMessage())
            );
        }
    }

    return $pdo;
}
