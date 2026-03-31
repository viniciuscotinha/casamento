<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

clear_old_input();

try {
    if (!admin_has_users()) {
        redirect('/admin/setup');
    }

    if (is_admin_logged_in()) {
        redirect('/admin');
    }
} catch (Throwable $exception) {
    admin_render_runtime_error('Login administrativo', $exception->getMessage());
}

if (is_post()) {
    try {
        verify_csrf();

        $login = trim((string) ($_POST['login'] ?? ''));
        remember_old_input(['login' => $login]);

        $result = attempt_admin_login($login, (string) ($_POST['password'] ?? ''));

        if (!$result['ok']) {
            throw new RuntimeException((string) $result['message']);
        }

        clear_old_input();
        flash('success', 'Login realizado com sucesso.');
        redirect('/admin');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
}

admin_render_header('Login');
admin_render_flashes();
?>
<section class="admin-panel admin-panel--narrow">
  <p class="eyebrow">Area protegida</p>
  <h1>Entrar no painel</h1>
  <p>Use o login do administrador para gerenciar familias, convidados e relatorios.</p>
  <form method="post" class="admin-form-grid">
    <?= csrf_input() ?>
    <div class="admin-field">
      <label for="login">Login</label>
      <input id="login" name="login" type="text" value="<?= e(old_input('login')) ?>" required>
    </div>
    <div class="admin-field">
      <label for="password">Senha</label>
      <input id="password" name="password" type="password" required>
    </div>
    <div class="admin-actions">
      <button class="admin-button" type="submit">Entrar</button>
    </div>
  </form>
</section>
<?php
admin_render_footer();
