<?php

declare(strict_types=1);

const APP_NAME = 'App Attus';

// Banco principal (phpMyAdmin / MySQL)
const DB_DRIVER = 'mysql'; // mysql | sqlite
const MYSQL_HOST = '127.0.0.1';
const MYSQL_PORT = 3306;
const MYSQL_DB_NAME = 'app_attus';
const MYSQL_USER = 'root';
const MYSQL_PASSWORD = '';
const MYSQL_CHARSET = 'utf8mb4';

// Fallback opcional SQLite
const DB_PATH = __DIR__ . DIRECTORY_SEPARATOR . 'database.sqlite';

const ADMIN_NAME = 'Administrador Escola';
const ADMIN_EMAIL = 'admin@escola.local';
const ADMIN_PASSWORD = 'admin123';
