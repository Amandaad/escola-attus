<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (DB_DRIVER === 'mysql') {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            MYSQL_HOST,
            MYSQL_PORT,
            MYSQL_DB_NAME,
            MYSQL_CHARSET
        );

        try {
            $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASSWORD, $options);
        } catch (PDOException $exception) {
            $bootstrapDsn = sprintf(
                'mysql:host=%s;port=%d;charset=%s',
                MYSQL_HOST,
                MYSQL_PORT,
                MYSQL_CHARSET
            );
            $bootstrapPdo = new PDO($bootstrapDsn, MYSQL_USER, MYSQL_PASSWORD, $options);
            $bootstrapPdo->exec(
                sprintf(
                    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
                    MYSQL_DB_NAME,
                    MYSQL_CHARSET,
                    MYSQL_CHARSET . '_unicode_ci'
                )
            );

            $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASSWORD, $options);
        }

        return $pdo;
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    return $pdo;
}

function initDatabase(): void
{
    $pdo = db();

    if (DB_DRIVER === 'mysql') {
        initMySqlSchema($pdo);
    } else {
        initSqliteSchema($pdo);
    }

    seedAdminUser($pdo);
}

function initMySqlSchema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS parents (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            cpf VARCHAR(11) NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            is_admin TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS students (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id INT UNSIGNED NOT NULL,
            name VARCHAR(150) NOT NULL,
            series VARCHAR(80) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_students_parent FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS boletos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            due_date DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "pendente",
            description VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_boletos_parent FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
            CONSTRAINT fk_boletos_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );
}

function initSqliteSchema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS parents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            cpf TEXT NULL,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );'
    );

    if (!columnExists($pdo, 'parents', 'cpf')) {
        $pdo->exec('ALTER TABLE parents ADD COLUMN cpf TEXT NULL;');
    }

    if (!columnExists($pdo, 'parents', 'is_admin')) {
        $pdo->exec('ALTER TABLE parents ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0;');
    }

    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_parents_cpf_unique
         ON parents(cpf)
         WHERE cpf IS NOT NULL AND cpf <> ""'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parent_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            series TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(parent_id) REFERENCES parents(id) ON DELETE CASCADE
        );'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS boletos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parent_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            amount REAL NOT NULL,
            due_date TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "pendente",
            description TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(parent_id) REFERENCES parents(id) ON DELETE CASCADE,
            FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE
        );'
    );
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    if (DB_DRIVER === 'mysql') {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = :schema_name
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            'schema_name' => MYSQL_DB_NAME,
            'table_name' => $table,
            'column_name' => $column,
        ]);
        $row = $stmt->fetch();

        return (int) ($row['total'] ?? 0) > 0;
    }

    $stmt = $pdo->query(sprintf('PRAGMA table_info(%s)', $table));
    $columns = $stmt->fetchAll();

    foreach ($columns as $tableColumn) {
        if (($tableColumn['name'] ?? '') === $column) {
            return true;
        }
    }

    return false;
}

function seedAdminUser(PDO $pdo): void
{
    $stmt = $pdo->prepare('SELECT id FROM parents WHERE email = :email');
    $stmt->execute(['email' => strtolower(ADMIN_EMAIL)]);
    $existingAdmin = $stmt->fetch();

    if ($existingAdmin) {
        $updateStmt = $pdo->prepare('UPDATE parents SET is_admin = 1 WHERE id = :id');
        $updateStmt->execute(['id' => $existingAdmin['id']]);
        return;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO parents (name, email, cpf, password_hash, is_admin)
         VALUES (:name, :email, :cpf, :password_hash, 1)'
    );
    $insertStmt->execute([
        'name' => ADMIN_NAME,
        'email' => strtolower(ADMIN_EMAIL),
        'cpf' => null,
        'password_hash' => password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT),
    ]);
}
