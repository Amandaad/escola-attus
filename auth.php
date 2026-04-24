<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

initDatabase();

function currentParent(): ?array
{
    if (empty($_SESSION['parent_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, cpf, is_admin FROM parents WHERE id = :id');
    $stmt->execute(['id' => (int) $_SESSION['parent_id']]);
    $parent = $stmt->fetch();

    return $parent ?: null;
}

function requireAuth(): array
{
    $parent = currentParent();
    if ($parent) {
        return $parent;
    }

    header('Location: login.php');
    exit;
}

function isPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function isAdmin(array $parent): bool
{
    return (int) ($parent['is_admin'] ?? 0) === 1;
}

function normalizeCpf(string $cpf): string
{
    return preg_replace('/\D+/', '', $cpf) ?? '';
}

function isValidCpf(string $cpf): bool
{
    $cpf = normalizeCpf($cpf);

    if (strlen($cpf) !== 11) {
        return false;
    }

    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += ((int) $cpf[$i]) * (($t + 1) - $i);
        }

        $digit = ((10 * $sum) % 11) % 10;
        if ($digit !== (int) $cpf[$t]) {
            return false;
        }
    }

    return true;
}

function formatCpf(string $cpf): string
{
    $cpf = normalizeCpf($cpf);
    if (strlen($cpf) !== 11) {
        return $cpf;
    }

    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}
