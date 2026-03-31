<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

admin_logout();
flash('success', 'Sessao encerrada com seguranca.');
redirect('/admin/login');
