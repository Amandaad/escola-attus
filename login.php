<?php

declare(strict_types=1);

require_once __DIR__ . '/layout.php';

if (currentParent()) {
    header('Location: index.php');
    exit;
}

$error = '';
$identifier = '';

if (isPost()) {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $cpf = normalizeCpf($identifier);
    if ($cpf !== '' && strlen($cpf) === 11) {
        $stmt = db()->prepare('SELECT id, password_hash FROM parents WHERE cpf = :identifier');
        $stmt->execute(['identifier' => $cpf]);
    } else {
        $stmt = db()->prepare('SELECT id, password_hash FROM parents WHERE email = :identifier');
        $stmt->execute(['identifier' => strtolower($identifier)]);
    }

    $parent = $stmt->fetch();

    if (!$parent || !password_verify($password, $parent['password_hash'])) {
        $error = 'E-mail/CPF ou senha invalidos.';
    } else {
        $_SESSION['parent_id'] = (int) $parent['id'];
        header('Location: index.php');
        exit;
    }
}

renderHeader('Login');
?>
<section class="card" style="max-width:560px; margin:0 auto;">
    <h1>Entrar</h1>
    <p class="small">Use e-mail ou CPF do responsavel.</p>
    <?php if ($error): ?>
        <div class="message error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <label>E-mail ou CPF</label>
        <input type="text" name="identifier" value="<?= e($identifier) ?>" required>

        <label>Senha</label>
        <input type="password" name="password" required>

        <button type="submit">Entrar</button>
    </form>
</section>
<?php
renderFooter();
