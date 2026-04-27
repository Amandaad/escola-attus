<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

function renderHeader(string $title): void
{
    $parent = currentParent();
    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> - <?= e(APP_NAME) ?></title>
        <style>
            :root {
                --bg: #f6f8ff;
                --card: #ffffff;
                --text: #223;
                --muted: #65708a;
                --accent: #1f6feb;
                --ok: #1a7f37;
                --warn: #9a6700;
                --danger: #cf222e;
                --border: #dce3f0;
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: "Segoe UI", Arial, sans-serif;
                color: var(--text);
                background: linear-gradient(140deg, #eaf0ff, #f9fbff 60%);
            }
            .container { max-width: 980px; margin: 0 auto; padding: 16px; }
            .nav {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 16px;
                padding: 12px 16px;
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 10px;
            }
            .brand { font-weight: 700; }
            .nav-links a { margin-left: 10px; color: var(--accent); text-decoration: none; }
            .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
            .card {
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 10px;
                padding: 16px;
            }
            h1, h2, h3 { margin-top: 0; }
            form { display: grid; gap: 10px; }
            input, select, textarea, button {
                width: 100%;
                padding: 10px;
                border: 1px solid #c9d3e5;
                border-radius: 8px;
                font-size: 14px;
            }
            button {
                background: var(--accent);
                color: #fff;
                border: 0;
                font-weight: 600;
                cursor: pointer;
            }
            .btn-secondary { background: #64748b; }
            .btn-ok { background: var(--ok); }
            .message {
                padding: 10px 12px;
                border-radius: 8px;
                margin-bottom: 10px;
                border: 1px solid;
            }
            .message.error { color: var(--danger); border-color: #f2b8bd; background: #fff5f5; }
            .message.success { color: var(--ok); border-color: #a6ddb9; background: #f2fff6; }
            table { width: 100%; border-collapse: collapse; font-size: 14px; }
            th, td { border-bottom: 1px solid var(--border); padding: 8px; text-align: left; }
            .badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 700;
            }
            .badge.pendente { background: #fff8c5; color: var(--warn); }
            .badge.pago { background: #dcffe4; color: var(--ok); }
            .small { color: var(--muted); font-size: 13px; }
        </style>
    </head>
    <body>
    <div class="container">
        <div class="nav">
            <div class="brand"><?= e(APP_NAME) ?></div>
            <div class="nav-links">
                <?php if ($parent): ?>
                    <?php if (isAdmin($parent)): ?>
                        <span class="small">Perfil: Administrador</span>
                    <?php else: ?>
                        <span class="small">Responsavel: <?= e($parent['name']) ?> (CPF: <?= e(formatCpf((string) ($parent['cpf'] ?? ''))) ?>)</span>
                    <?php endif; ?>
                    <a href="index.php">Dashboard</a>
                    <a href="report_boletos.php">Relatorio IA</a>
                    <a href="logout.php">Sair</a>
                <?php else: ?>
                    <a href="login.php">Entrar</a>
                    <a href="register.php">Cadastrar</a>
                <?php endif; ?>
            </div>
        </div>
<?php
}

function renderFooter(): void
{
    ?>
    </div>
    </body>
    </html>
<?php
}
