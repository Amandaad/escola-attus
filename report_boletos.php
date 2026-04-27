<?php

declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/ai_service.php';

$parent = requireAuth();
$pdo = db();
$admin = isAdmin($parent);

$query = 'SELECT b.id, b.amount, b.due_date, b.status, b.description,
                 s.name AS student_name, s.series, p.name AS parent_name, p.cpf AS parent_cpf
          FROM boletos b
          JOIN students s ON s.id = b.student_id
          JOIN parents p ON p.id = b.parent_id';
$params = [];

if (!$admin) {
    $query .= ' WHERE b.parent_id = :parent_id';
    $params['parent_id'] = $parent['id'];
}

$query .= ' ORDER BY b.due_date ASC';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$boletos = $stmt->fetchAll();

$metrics = buildBoletoMetrics($boletos);
$recommendations = buildFallbackRecommendations($metrics, $admin);

$insightText = null;
$insightError = '';
if (isPost() && ($_POST['action'] ?? '') === 'gerar_insight_ia') {
    $insightText = requestOpenAiInsights($metrics, $admin);
    if ($insightText === null) {
        $insightError = 'Nao foi possivel gerar o insight com IA agora. Configure OPENAI_API_KEY para habilitar esse recurso.';
    }
}

renderHeader('Relatorio Inteligente de Boletos');
?>
<section class="card">
    <h1>Relatorio Inteligente de Boletos</h1>
    <p class="small">Resumo automatico com foco em inadimplencia e cobranca preventiva.</p>

    <div class="grid">
        <div class="card">
            <h3>Visao Geral</h3>
            <p><strong>Total de boletos:</strong> <?= e((string) $metrics['total']) ?></p>
            <p><strong>Pagos:</strong> <?= e((string) $metrics['paid']) ?></p>
            <p><strong>Pendentes:</strong> <?= e((string) $metrics['pending']) ?></p>
            <p><strong>Vencidos:</strong> <?= e((string) $metrics['overdue']) ?></p>
            <p><strong>Vencem em 7 dias:</strong> <?= e((string) $metrics['due_soon']) ?></p>
            <p><strong>Valor pendente:</strong> R$ <?= e(number_format((float) $metrics['pending_amount'], 2, ',', '.')) ?></p>
        </div>

        <div class="card">
            <h3>Recomendacoes Inteligentes</h3>
            <ul>
                <?php foreach ($recommendations as $recommendation): ?>
                    <li><?= e($recommendation) ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="chat_cobranca.php"><button type="button" class="btn-secondary">Abrir Chat Cobranca IA</button></a>
            <form method="post" style="margin-top:12px;">
                <input type="hidden" name="action" value="gerar_insight_ia">
                <button type="submit">Gerar insight com IA</button>
            </form>
            <?php if (getOpenAiApiKey() === ''): ?>
                <p class="small" style="margin-top:10px;">Defina a variavel de ambiente <code>OPENAI_API_KEY</code> para ativar a analise avancada.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($insightError !== ''): ?>
    <section class="card" style="margin-top:16px;">
        <div class="message error"><?= e($insightError) ?></div>
    </section>
<?php endif; ?>

<?php if ($insightText !== null): ?>
    <section class="card" style="margin-top:16px;">
        <h2>Insight da IA</h2>
        <div><?= nl2br(e($insightText)) ?></div>
    </section>
<?php endif; ?>

<section class="card" style="margin-top:16px;">
    <h2>Series com maior risco</h2>
    <?php if (!$metrics['series_risk']): ?>
        <p>Nenhuma serie com risco identificado no momento.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Serie</th>
                <th>Pendentes</th>
                <th>Vencidos</th>
                <th>Valor pendente</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($metrics['series_risk'] as $risk): ?>
                <tr>
                    <td><?= e((string) $risk['series']) ?></td>
                    <td><?= e((string) $risk['pending']) ?></td>
                    <td><?= e((string) $risk['overdue']) ?></td>
                    <td>R$ <?= e(number_format((float) $risk['pending_amount'], 2, ',', '.')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="card" style="margin-top:16px;">
    <h2>Boletos analisados</h2>
    <?php if (!$boletos): ?>
        <p>Nenhum boleto encontrado para analise.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Aluno</th>
                <th>Serie</th>
                <?php if ($admin): ?>
                    <th>Responsavel</th>
                    <th>CPF</th>
                <?php endif; ?>
                <th>Descricao</th>
                <th>Valor</th>
                <th>Vencimento</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($boletos as $boleto): ?>
                <tr>
                    <td><?= e((string) $boleto['student_name']) ?></td>
                    <td><?= e((string) $boleto['series']) ?></td>
                    <?php if ($admin): ?>
                        <td><?= e((string) $boleto['parent_name']) ?></td>
                        <td><?= e(formatCpf((string) ($boleto['parent_cpf'] ?? ''))) ?></td>
                    <?php endif; ?>
                    <td><?= e((string) ($boleto['description'] ?: 'Mensalidade')) ?></td>
                    <td>R$ <?= e(number_format((float) $boleto['amount'], 2, ',', '.')) ?></td>
                    <td><?= e(date('d/m/Y', strtotime((string) $boleto['due_date']))) ?></td>
                    <td><span class="badge <?= e((string) $boleto['status']) ?>"><?= e(strtoupper((string) $boleto['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php
renderFooter();
