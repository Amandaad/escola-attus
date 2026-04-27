# App Attus - App Escolar

Aplicativo simples em PHP para:

- cadastro e login de pais/responsaveis;
- cadastro com CPF e login por e-mail ou CPF;
- perfil de administrador com visao completa da escola;
- cadastro de alunos por serie;
- geracao e controle de boletos por aluno/serie.

## Requisitos

- PHP 8.0+;
- MySQL/MariaDB (XAMPP);
- phpMyAdmin.

## Configurar phpMyAdmin

1. Abra `http://localhost/phpmyadmin`.
2. Crie o banco `app_attus` (collation `utf8mb4_unicode_ci`) ou importe o arquivo `schema_mysql.sql`.
3. Confira as credenciais em `config.php`:
   - host: `127.0.0.1`
   - porta: `3306`
   - banco: `app_attus`
   - usuario: `root`
   - senha: `` (vazia no XAMPP padrao)

## Como rodar

1. Copie a pasta do projeto para `htdocs` no XAMPP.
2. Inicie `Apache` e `MySQL` no painel do XAMPP.
3. Importe o arquivo `schema_mysql.sql` no banco `app_attus` pelo phpMyAdmin.
4. Confira os dados de conexao em `config.php`.
5. Acesse `http://localhost/escola-attus/login.php`.

## Checklist rapido

- Banco `app_attus` criado e tabelas importadas;
- `Apache` e `MySQL` em execucao;
- `config.php` apontando para `127.0.0.1:3306`;
- Login carregando em `http://localhost/escola-attus/login.php`.

## Relatorio com IA (opcional)

- Acesse `Relatorio IA` no menu apos login.
- Sem configuracao extra, o sistema usa recomendacoes inteligentes locais.
- Para ativar analise com OpenAI, defina a variavel de ambiente `OPENAI_API_KEY`.

## Acesso admin da escola

Usuario admin criado automaticamente na inicializacao:

- e-mail: `admin@escola.local`
- senha: `***`

Esse perfil tem visao de todos os responsaveis, alunos e boletos.

## Estrutura principal

- `register.php`: cadastro de pais.
- `login.php`: autenticacao por e-mail ou CPF.
- `index.php`: painel com alunos por serie e boletos.
- `student_create.php`: cadastro de alunos.
- `boleto_create.php`: criacao de boletos.
- `boleto_pay.php`: marcacao de boleto pago.
