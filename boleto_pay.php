<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$parent = requireAuth();

if (!isPost()) {
    header('Location: index.php');
    exit;
}

$boletoId = (int) ($_POST['boleto_id'] ?? 0);
if ($boletoId <= 0) {
    header('Location: index.php');
    exit;
}

$sql = 'UPDATE boletos SET status = "pago" WHERE id = :id';
$params = ['id' => $boletoId];

if (!isAdmin($parent)) {
    $sql .= ' AND parent_id = :parent_id';
    $params['parent_id'] = $parent['id'];
}

$stmt = db()->prepare($sql);
$stmt->execute($params);

header('Location: index.php');
exit;
