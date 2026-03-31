<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

clear_old_input();

try {
    if (admin_has_users()) {
        redirect('/admin/login');
    }
} catch (Throwable $exception) {
    admin_render_runtime_error('Primeiro acesso', $exception->getMessage());
}

if (is_post()) {
    try {
        verify_csrf();

        $nome = trim((string) ($_POST['nome'] ?? ''));
        $login = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

        remember_old_input([
            'nome' => $nome,
            'login' => $login,
        ]);

        if ($login === '' || strlen($login) < 3) {
            throw new RuntimeException('Informe um login com pelo menos 3 caracteres.');
        }

        if ($password === '' || strlen($password) < 10) {
            throw new RuntimeException('Use uma senha com pelo menos 10 caracteres.');
        }

        if (!hash_equals($password, $passwordConfirmation)) {
            throw new RuntimeException('A confirmacao da senha nao confere.');
        }

        db_execute(
            'INSERT INTO admin_users (nome, login, password_hash, is_active)
             VALUES (:nome, :login, :password_hash, 1)',
            [
                'nome' => $nome !== '' ? $nome : null,
                'login' => $login,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]
        );

        clear_old_input();
        flash('success', 'Admin inicial criado com sucesso. Agora faca login.');
        audit_log('system', 'bootstrap_admin', 'admin_user', (int) db()->lastInsertId());
        redirect('/admin/login');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
}

admin_render_header('Primeiro acesso');
admin_render_flashes();
?>
<section class="admin-panel admin-panel--narrow">
  <p class="eyebrow">Primeiro acesso</p>
  <h1>Criar administrador inicial</h1>
  <p>Esta tela so fica disponivel enquanto nao existir nenhum usuario administrador no banco.</p>
  <form method="post" class="admin-form-grid">
    <?= csrf_input() ?>
    <div class="admin-field">
      <label for="nome">Nome</label>
      <input id="nome" name="nome" type="text" value="<?= e(old_input('nome')) ?>" placeholder="Seu nome">
    </div>
    <div class="admin-field">
      <label for="login">Login</label>
      <input id="login" name="login" type="text" value="<?= e(old_input('login')) ?>" placeholder="admin" required>
    </div>
    <div class="admin-field">
      <label for="password">Senha</label>
      <input id="password" name="password" type="password" placeholder="Use uma senha forte" required>
    </div>
    <div class="admin-field">
      <label for="password_confirmation">Confirmar senha</label>
      <input id="password_confirmation" name="password_confirmation" type="password" required>
    </div>
    <div class="admin-actions">
      <button class="admin-button" type="submit">Criar admin</button>
    </div>
  </form>
</section>
<?php
admin_render_footer();
