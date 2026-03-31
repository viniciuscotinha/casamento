<?php
declare(strict_types=1);

function admin_current_path(): string
{
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/admin');
    $path = (string) parse_url($requestUri, PHP_URL_PATH);

    return $path !== '' ? rtrim($path, '/') : '/admin';
}

function admin_nav_link(string $path, string $label): void
{
    $currentPath = admin_current_path();
    $targetPath = rtrim($path, '/');
    $isActive = $currentPath === $targetPath;

    if ($targetPath === '/admin' && str_starts_with($currentPath, '/admin') && $currentPath !== '/admin/logout') {
        $isActive = in_array($currentPath, ['/admin', '/admin/login', '/admin/setup'], true);
    }
    ?>
    <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a>
    <?php
}

function admin_render_header(string $title): void
{
    send_noindex_headers();
    $admin = current_admin();
    $pageTitle = $title . ' | ' . app_config('app_name', 'RSVP Casamento');
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title><?= e($pageTitle) ?></title>
  <link rel="icon" type="image/png" href="<?= e(url('/favicon.png')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=Manrope:wght@400;500;600;700&family=Oswald:wght@400;500;600&display=swap"
    rel="stylesheet"
  >
  <link rel="stylesheet" href="<?= e(url('/assets/css/styles.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('/assets/css/admin.css')) ?>">
</head>
<body class="site-body admin-body<?= $admin === null ? ' admin-body--auth' : '' ?>">
  <?php if ($admin !== null): ?>
    <div class="admin-app">
      <aside class="admin-sidebar">
        <div class="admin-sidebar-card admin-sidebar-card--user">
          <span class="admin-user-kicker">Conectado como</span>
          <strong><?= e(trim((string) ($admin['nome'] ?? '')) !== '' ? $admin['nome'] : $admin['login']) ?></strong>
          <span><?= e($admin['login']) ?></span>
        </div>
        <nav class="admin-nav" aria-label="Menu administrativo">
          <?php admin_nav_link('/admin', 'Dashboard'); ?>
          <?php admin_nav_link('/admin/familias', 'Familias'); ?>
          <?php admin_nav_link('/admin/padrinhos', 'Padrinhos'); ?>
          <?php admin_nav_link('/admin/convidados', 'Convidados'); ?>
          <?php admin_nav_link('/admin/relatorios', 'Relatorios'); ?>
          <?php admin_nav_link('/admin/logout', 'Sair'); ?>
        </nav>

      </aside>

      <div class="admin-content">
        <header class="admin-toolbar">
          <div>
            <p class="admin-toolbar-kicker">Area administrativa</p>
            <h1 class="admin-toolbar-title"><?= e($title) ?></h1>
          </div>
        </header>
        <main class="admin-main">
  <?php else: ?>
    <div class="admin-auth-shell">
      <div class="admin-auth-hero">
        <p class="eyebrow">Painel RSVP</p>
        <h1><?= e(app_config('app_name', 'RSVP Casamento')) ?></h1>
        <p>Gerencie familias, padrinhos, convidados e respostas em um painel mais direto e seguro.</p>
      </div>
      <main class="admin-main">
  <?php endif; ?>
<?php
}

function admin_render_footer(): void
{
    ?>
      </main>
    </div>
  <?php if (current_admin() !== null): ?>
      </div>
  <?php endif; ?>
  <script src="<?= e(url('/assets/js/admin.js')) ?>"></script>
</body>
</html>
<?php
}

function admin_render_flashes(): void
{
    foreach (consume_flashes() as $flashMessage) {
        $type = (string) ($flashMessage['type'] ?? 'info');
        $message = (string) ($flashMessage['message'] ?? '');
        ?>
        <div class="flash flash-<?= e($type) ?>"><?= e($message) ?></div>
        <?php
    }
}

function admin_render_runtime_error(string $title, string $message): never
{
    admin_render_header($title);
    ?>
    <section class="admin-panel admin-panel--narrow">
      <p class="eyebrow">Configuracao</p>
      <h1><?= e($title) ?></h1>
      <p><?= e($message) ?></p>
      <p>Revise `config/env.php`, importe `sql/schema.sql` no banco e tente novamente.</p>
    </section>
    <?php
    admin_render_footer();
    exit;
}
