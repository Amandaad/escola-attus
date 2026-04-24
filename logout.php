<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

session_unset();
session_destroy();

header('Location: login.php');
exit;
