<?php

declare(strict_types=1);

require_once __DIR__ . '/layout.php';

$parent = requireAuth();
$pdo = db();
$admin = isAdmin($parent);
$parents = [];

if ($admin) {
    $parentsStmt = $pdo->query(
        'SELECT p.id, p.name, p.email, p.cpf,
                (SELECT COUNT(*) FROM students s WHERE s.parent_id = p.id) AS students_total
         FROM parents p
         WHERE p.is_admin = 0
         ORDER BY p.name'
    );
    $parents = $parentsStmt->fetchAll();
}

$studentsQuery = 'SELECT s.id, s.name, s.series, p.name AS parent_name, p.cpf AS parent_cpf
                  FROM students s
                  JOIN parents p ON p.id = s.parent_id';
$studentsParams = [];
if (!$admin) {
    $studentsQuery .= ' WHERE s.parent_id = :parent_id';
    $studentsParams['parent_id'] = $parent['id'];
}
$studentsQuery .= ' ORDER BY s.series, s.name';
$studentsStmt = $pdo->prepare($studentsQuery);
$studentsStmt->execute($studentsParams);
$students = $studentsStmt->fetchAll();

$boletosQuery = 'SELECT b.id, b.amount, b.due_date, b.status, b.description, s.name AS student_name, s.series, p.name AS parent_name, p.cpf AS parent_cpf
                 FROM boletos b
                 JOIN students s ON s.id = b.student_id
                 JOIN parents p ON p.id = b.parent_id';
$boletosParams = [];
if (!$admin) {
    $boletosQuery .= ' WHERE b.parent_id = :parent_id';
    $boletosParams['parent_id'] = $parent['id'];
}
$boletosQuery .= ' ORDER BY b.due_date ASC';
$boletosStmt = $pdo->prepare($boletosQuery);
$boletosStmt->execute($boletosParams);
$boletos = $boletosStmt->fetchAll();

$seriesCount = [];
foreach ($students as $student) {
    $series = $student['series'];
    if (!isset($seriesCount[$series])) {
        $seriesCount[$series] = 0;
    }
    $seriesCount[$series]++;
}

renderHeader('Dashboard');
?>
<div class="grid">
    <section class="card">
        <h2>Alunos por Serie</h2>
        <p class="small">Resumo de turmas cadastradas.</p>
        <?php if (!$seriesCount): ?>
            <p>Nenhum aluno cadastrado.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Serie</th>
                    <th>Total de alunos</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($seriesCount as $series => $count): ?>
                    <tr>
                        <td><?= e((string) $series) ?></td>
                        <td><?= e((string) $count) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Painel</h2>
        <?php if ($admin): ?>
            <p class="small">Modo administrador ativo: visao geral da escola.</p>
            <p><strong>Total de responsaveis:</strong> <?= e((string) count($parents)) ?></p>
            <p><strong>Total de alunos:</strong> <?= e((string) count($students)) ?></p>
            <p><strong>Total de boletos:</strong> <?= e((string) count($boletos)) ?></p>
            <a href="report_boletos.php"><button type="button" class="btn-secondary">Ver Relatorio IA</button></a>
            <a href="chat_cobranca.php"><button type="button" class="btn-secondary">Chat Cobranca IA</button></a>
        <?php else: ?>
            <p class="small">Cadastre alunos e gere boletos por serie.</p>
            <a href="student_create.php"><button type="button">Cadastrar Aluno</button></a>
            <a href="boleto_create.php"><button type="button" class="btn-secondary">Gerar Boleto</button></a>
            <a href="report_boletos.php"><button type="button" class="btn-secondary">Relatorio IA</button></a>
            <a href="chat_cobranca.php"><button type="button" class="btn-secondary">Chat Cobranca IA</button></a>
        <?php endif; ?>
    </section>
</div>

<?php if ($admin): ?>
<section class="card" style="margin-top:16px;">
    <h2>Responsaveis Cadastrados</h2>
    <?php if (!$parents): ?>
        <p>Nenhum responsavel cadastrado.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>CPF</th>
                <th>Alunos</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($parents as $parentItem): ?>
                <tr>
                    <td><?= e((string) $parentItem['name']) ?></td>
                    <td><?= e((string) $parentItem['email']) ?></td>
                    <td><?= e(formatCpf((string) $parentItem['cpf'])) ?></td>
                    <td><?= e((string) $parentItem['students_total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="card" style="margin-top:16px;">
    <h2>Alunos Cadastrados</h2>
    <?php if (!$students): ?>
        <p>Cadastre seu primeiro aluno.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Nome</th>
                <th>Serie</th>
                <?php if ($admin): ?>
                    <th>Responsavel</th>
                    <th>CPF</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $student): ?>
                <tr>
                    <td><?= e($student['name']) ?></td>
                    <td><?= e($student['series']) ?></td>
                    <?php if ($admin): ?>
                        <td><?= e((string) $student['parent_name']) ?></td>
                        <td><?= e(formatCpf((string) ($student['parent_cpf'] ?? ''))) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="card" style="margin-top:16px;">
    <h2>Boletos</h2>
    <?php if (!$boletos): ?>
        <p>Nenhum boleto gerado.</p>
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
                <th>Acao</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($boletos as $boleto): ?>
                <tr>
                    <td><?= e($boleto['student_name']) ?></td>
                    <td><?= e($boleto['series']) ?></td>
                    <?php if ($admin): ?>
                        <td><?= e((string) $boleto['parent_name']) ?></td>
                        <td><?= e(formatCpf((string) ($boleto['parent_cpf'] ?? ''))) ?></td>
                    <?php endif; ?>
                    <td><?= e((string) ($boleto['description'] ?: 'Mensalidade')) ?></td>
                    <td>R$ <?= e(number_format((float) $boleto['amount'], 2, ',', '.')) ?></td>
                    <td><?= e(date('d/m/Y', strtotime($boleto['due_date']))) ?></td>
                    <td><span class="badge <?= e($boleto['status']) ?>"><?= e(strtoupper($boleto['status'])) ?></span></td>
                    <td>
                        <?php if ($boleto['status'] !== 'pago'): ?>
                            <form method="post" action="boleto_pay.php" style="display:inline;">
                                <input type="hidden" name="boleto_id" value="<?= e((string) $boleto['id']) ?>">
                                <button type="submit" class="btn-ok">Marcar como pago</button>
                            </form>
                        <?php else: ?>
                            <span class="small">Pago</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php
renderFooter();
