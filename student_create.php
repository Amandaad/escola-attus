<?php

declare(strict_types=1);

require_once __DIR__ . '/layout.php';

$parent = requireAuth();
$error = '';
$name = '';
$series = '';

if (isPost()) {
    $name = trim($_POST['name'] ?? '');
    $series = trim($_POST['series'] ?? '');

    if ($name === '' || $series === '') {
        $error = 'Preencha todos os campos.';
    } else {
        $stmt = db()->prepare(
            'INSERT INTO students (parent_id, name, series)
             VALUES (:parent_id, :name, :series)'
        );
        $stmt->execute([
            'parent_id' => $parent['id'],
            'name' => $name,
            'series' => $series,
        ]);
        header('Location: index.php');
        exit;
    }
}

renderHeader('Cadastrar Aluno');
?>
<section class="card" style="max-width:600px; margin:0 auto;">
    <h1>Novo Aluno</h1>
    <p class="small">Cadastre alunos para vincular boletos por série.</p>
    <?php if ($error): ?>
        <div class="message error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <label>Nome do aluno</label>
        <input type="text" name="name" value="<?= e($name) ?>" required>

        <label>Série</label>
        <input type="text" name="series" value="<?= e($series) ?>" placeholder="Ex: 6º Ano" required>

        <button type="submit">Salvar Aluno</button>
    </form>
</section>
<?php
renderFooter();
