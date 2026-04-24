<?php

declare(strict_types=1);

require_once __DIR__ . '/layout.php';

$parent = requireAuth();
$pdo = db();
$error = '';

$studentsStmt = $pdo->prepare('SELECT id, name, series FROM students WHERE parent_id = :parent_id ORDER BY series, name');
$studentsStmt->execute(['parent_id' => $parent['id']]);
$students = $studentsStmt->fetchAll();

if (isPost()) {
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $dueDate = trim($_POST['due_date'] ?? '');
    $description = trim($_POST['description'] ?? 'Mensalidade');

    $validStudent = false;
    foreach ($students as $student) {
        if ((int) $student['id'] === $studentId) {
            $validStudent = true;
            break;
        }
    }

    if (!$validStudent) {
        $error = 'Aluno inválido.';
    } elseif ($amount <= 0) {
        $error = 'Informe um valor válido.';
    } elseif ($dueDate === '') {
        $error = 'Informe a data de vencimento.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO boletos (parent_id, student_id, amount, due_date, description)
             VALUES (:parent_id, :student_id, :amount, :due_date, :description)'
        );
        $stmt->execute([
            'parent_id' => $parent['id'],
            'student_id' => $studentId,
            'amount' => $amount,
            'due_date' => $dueDate,
            'description' => $description,
        ]);

        header('Location: index.php');
        exit;
    }
}

renderHeader('Gerar Boleto');
?>
<section class="card" style="max-width:640px; margin:0 auto;">
    <h1>Gerar Boleto</h1>
    <p class="small">Crie boletos por aluno e série.</p>

    <?php if (!$students): ?>
        <div class="message error">Cadastre um aluno antes de gerar boleto.</div>
        <a href="student_create.php"><button type="button">Cadastrar Aluno</button></a>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="message error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <label>Aluno</label>
            <select name="student_id" required>
                <option value="">Selecione</option>
                <?php foreach ($students as $student): ?>
                    <option value="<?= e((string) $student['id']) ?>">
                        <?= e($student['name']) ?> - <?= e($student['series']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Valor (R$)</label>
            <input type="number" name="amount" step="0.01" min="0.01" required>

            <label>Vencimento</label>
            <input type="date" name="due_date" required>

            <label>Descrição</label>
            <input type="text" name="description" value="Mensalidade">

            <button type="submit">Gerar Boleto</button>
        </form>
    <?php endif; ?>
</section>
<?php
renderFooter();
