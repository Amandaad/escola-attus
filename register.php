<?php

declare(strict_types=1);

require_once __DIR__ . '/layout.php';

if (currentParent()) {
    header('Location: index.php');
    exit;
}

$error = '';
$name = '';
$email = '';
$cpf = '';

if (isPost()) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $cpfNormalized = normalizeCpf($cpf);

    if ($name === '' || $email === '' || $cpf === '' || $password === '') {
        $error = 'Preencha todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail valido.';
    } elseif (!isValidCpf($cpfNormalized)) {
        $error = 'Informe um CPF valido.';
    } else {
        try {
            $stmt = db()->prepare(
                'INSERT INTO parents (name, email, cpf, password_hash)
                 VALUES (:name, :email, :cpf, :password_hash)'
            );
            $stmt->execute([
                'name' => $name,
                'email' => strtolower($email),
                'cpf' => $cpfNormalized,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $_SESSION['parent_id'] = (int) db()->lastInsertId();
            header('Location: index.php');
            exit;
        } catch (PDOException $exception) {
            $error = 'E-mail ou CPF ja cadastrado.';
        }
    }
}

renderHeader('Cadastro de Pais');
?>
<section class="card" style="max-width:560px; margin:0 auto;">
    <h1>Cadastro de Responsavel</h1>
    <p class="small">Crie seu acesso para gerenciar alunos e boletos.</p>
    <?php if ($error): ?>
        <div class="message error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <label>Nome completo</label>
        <input type="text" name="name" value="<?= e($name) ?>" required>

        <label>E-mail</label>
        <input type="email" name="email" value="<?= e($email) ?>" required>

        <label>CPF</label>
        <input type="text" name="cpf" value="<?= e($cpf) ?>" placeholder="000.000.000-00" required>

        <label>Senha</label>
        <input type="password" name="password" minlength="6" required>

        <button type="submit">Cadastrar</button>
    </form>
</section>
<?php
renderFooter();
