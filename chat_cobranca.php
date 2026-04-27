<?php

declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/ai_service.php';

$parent = requireAuth();
$pdo = db();
$admin = isAdmin($parent);
$todayExpr = DB_DRIVER === 'mysql' ? 'CURDATE()' : "date('now')";

$rows = [];
$selectedParentId = '';
$selectedTone = 'amigavel';
$generatedMessage = '';
$generationInfo = '';
$error = '';

if ($admin) {
    $stmt = $pdo->query(
        "SELECT p.id AS parent_id, p.name AS parent_name, p.cpf AS parent_cpf,
                SUM(CASE WHEN b.status <> 'pago' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN b.status <> 'pago' THEN b.amount ELSE 0 END) AS pending_amount,
                SUM(CASE WHEN b.status <> 'pago' AND b.due_date < " . $todayExpr . " THEN 1 ELSE 0 END) AS overdue_count,
                MIN(CASE WHEN b.status <> 'pago' THEN b.due_date ELSE NULL END) AS nearest_due_date
         FROM parents p
         LEFT JOIN boletos b ON b.parent_id = p.id
         WHERE p.is_admin = 0
         GROUP BY p.id, p.name, p.cpf
         HAVING pending_count > 0
         ORDER BY overdue_count DESC, pending_amount DESC, p.name"
    );
    $rows = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare(
        "SELECT p.id AS parent_id, p.name AS parent_name, p.cpf AS parent_cpf,
                SUM(CASE WHEN b.status <> 'pago' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN b.status <> 'pago' THEN b.amount ELSE 0 END) AS pending_amount,
                SUM(CASE WHEN b.status <> 'pago' AND b.due_date < " . $todayExpr . " THEN 1 ELSE 0 END) AS overdue_count,
                MIN(CASE WHEN b.status <> 'pago' THEN b.due_date ELSE NULL END) AS nearest_due_date
         FROM parents p
         LEFT JOIN boletos b ON b.parent_id = p.id
         WHERE p.id = :parent_id
         GROUP BY p.id, p.name, p.cpf"
    );
    $stmt->execute(['parent_id' => $parent['id']]);
    $row = $stmt->fetch();
    if ($row && (int) ($row['pending_count'] ?? 0) > 0) {
        $rows[] = $row;
    }
}

if (isPost() && ($_POST['action'] ?? '') === 'gerar_mensagem') {
    $selectedParentId = trim((string) ($_POST['parent_id'] ?? ''));
    $selectedTone = trim((string) ($_POST['tone'] ?? 'amigavel'));

    $selectedRow = null;
    foreach ($rows as $item) {
        if ((string) ($item['parent_id'] ?? '') === $selectedParentId) {
            $selectedRow = $item;
            break;
        }
    }

    if ($selectedRow === null) {
        $error = 'Selecione um responsavel valido para gerar a mensagem.';
    } else {
        $summary = [
            'pending_count' => (int) ($selectedRow['pending_count'] ?? 0),
            'pending_amount' => (float) ($selectedRow['pending_amount'] ?? 0),
            'overdue_count' => (int) ($selectedRow['overdue_count'] ?? 0),
            'nearest_due_date' => (string) ($selectedRow['nearest_due_date'] ?? ''),
        ];

        $generatedMessage = requestOpenAiCollectionMessage((string) $selectedRow['parent_name'], $summary, $selectedTone) ?? '';
        if ($generatedMessage !== '') {
            $generationInfo = 'Mensagem gerada com IA.';
        } else {
            $generatedMessage = buildFallbackCollectionMessage((string) $selectedRow['parent_name'], $summary, $selectedTone);
            $generationInfo = 'Mensagem gerada pelo modo inteligente local.';
        }
    }
}

renderHeader('Chat de Cobranca Inteligente');
?>
<section class="card">
    <h1>Chat de Cobranca Inteligente</h1>
    <p class="small">Gere mensagens prontas por responsavel com tom personalizado.</p>

    <?php if ($error !== ''): ?>
        <div class="message error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!$rows): ?>
        <p>Nao ha boletos pendentes para gerar mensagens agora.</p>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="action" value="gerar_mensagem">

            <label>Responsavel</label>
            <select name="parent_id" required>
                <option value="">Selecione...</option>
                <?php foreach ($rows as $item): ?>
                    <?php $parentId = (string) ($item['parent_id'] ?? ''); ?>
                    <option value="<?= e($parentId) ?>" <?= $selectedParentId === $parentId ? 'selected' : '' ?>>
                        <?= e((string) $item['parent_name']) ?> | Pendentes: <?= e((string) $item['pending_count']) ?> | Total: <?= e(currencyBr((float) $item['pending_amount'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Tom da mensagem</label>
            <select name="tone" required>
                <option value="amigavel" <?= $selectedTone === 'amigavel' ? 'selected' : '' ?>>Amigavel</option>
                <option value="neutro" <?= $selectedTone === 'neutro' ? 'selected' : '' ?>>Neutro</option>
                <option value="firme" <?= $selectedTone === 'firme' ? 'selected' : '' ?>>Firme</option>
            </select>

            <button type="submit">Gerar mensagem</button>
        </form>
    <?php endif; ?>

    <?php if ($generatedMessage !== ''): ?>
        <hr style="margin:16px 0; border:0; border-top:1px solid #dce3f0;">
        <p class="small"><?= e($generationInfo) ?></p>
        <label>Mensagem pronta para envio</label>
        <textarea rows="10" readonly><?= e($generatedMessage) ?></textarea>
    <?php endif; ?>
</section>
<?php
renderFooter();
