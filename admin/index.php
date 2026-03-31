<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    if (!admin_has_users()) {
        redirect('/admin/setup');
    }

    require_admin();
    $supportsManualInvite = manual_supports_schema();

    $metrics = [
        'familias' => (int) db_value('SELECT COUNT(*) FROM familias'),
        'padrinhos' => $supportsManualInvite ? (int) db_value('SELECT COUNT(*) FROM convites_padrinhos') : 0,
        'convidados' => (int) db_value('SELECT COUNT(*) FROM convidados WHERE deleted_at IS NULL'),
        'confirmados' => (int) db_value("SELECT COUNT(*) FROM convidados WHERE deleted_at IS NULL AND status = 'confirmado'"),
        'pendentes' => (int) db_value("SELECT COUNT(*) FROM convidados WHERE deleted_at IS NULL AND status = 'pendente'"),
    ];

    $latestResponses = db_all(
        "SELECT
            c.id,
            c.nome,
            c.sobrenome,
            c.status,
            c.responded_at,
            f.nome_grupo
         FROM convidados c
         INNER JOIN familias f ON f.id = c.familia_id
         WHERE c.deleted_at IS NULL
           AND c.responded_at IS NOT NULL
         ORDER BY c.responded_at DESC
         LIMIT 10"
    );
} catch (Throwable $exception) {
    admin_render_runtime_error('Dashboard', $exception->getMessage());
}

admin_render_header('Dashboard');
admin_render_flashes();
?>
<section class="admin-page-header">
  <div>
    <p class="eyebrow">Painel RSVP</p>
    <h2>Visao geral do casamento</h2>
    <p>Aqui voce acompanha o andamento das respostas e pula direto para as areas mais usadas no dia a dia.</p>
  </div>
  <div class="admin-page-actions">
    <a class="admin-button" href="<?= e(url('/admin/familias')) ?>">Nova familia</a>
    <a class="admin-button-secondary" href="<?= e(url('/admin/padrinhos')) ?>">Novo convite padrinhos</a>
    <a class="admin-button-secondary" href="<?= e(url('/admin/convidados')) ?>">Novo convidado</a>
  </div>
</section>

<section class="admin-panel">
  <p class="eyebrow">Visao geral</p>
  <h3>Painel do RSVP</h3>
  <div class="admin-metrics">
    <article class="metric-card">
      <span>Familias</span>
      <strong><?= e($metrics['familias']) ?></strong>
    </article>
    <article class="metric-card">
      <span>Convidados ativos</span>
      <strong><?= e($metrics['convidados']) ?></strong>
    </article>
    <article class="metric-card">
      <span>Convites padrinhos</span>
      <strong><?= e($metrics['padrinhos']) ?></strong>
    </article>
    <article class="metric-card">
      <span>Confirmados</span>
      <strong><?= e($metrics['confirmados']) ?></strong>
    </article>
    <article class="metric-card">
      <span>Pendentes</span>
      <strong><?= e($metrics['pendentes']) ?></strong>
    </article>
  </div>
</section>

<section class="admin-grid">
  <article class="admin-panel admin-panel--side" style="grid-column: span 4;">
    <p class="eyebrow">Atalhos</p>
    <h2>Proximos passos</h2>
    <div class="admin-actions">
      <a class="admin-button" href="<?= e(url('/admin/familias')) ?>">Gerenciar familias</a>
      <a class="admin-button-secondary" href="<?= e(url('/admin/padrinhos')) ?>">Gerenciar padrinhos</a>
      <a class="admin-button-secondary" href="<?= e(url('/admin/convidados')) ?>">Gerenciar convidados</a>
      <a class="admin-button-secondary" href="<?= e(url('/admin/relatorios')) ?>">Ver relatorios</a>
    </div>
    <div class="admin-note">
      As familias usam `/c/...` para RSVP e os padrinhos usam `/p/...` com agrupamento proprio.
    </div>
  </article>

  <article class="admin-panel" style="grid-column: span 8;">
    <p class="eyebrow">Ultimas respostas</p>
    <h2>Confirmacoes recentes</h2>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Convidado</th>
            <th>Familia</th>
            <th>Status</th>
            <th>Atualizado</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($latestResponses === []): ?>
            <tr>
              <td colspan="4">Nenhuma resposta registrada ainda.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($latestResponses as $guest): ?>
              <tr>
                <td><?= e(trim($guest['nome'] . ' ' . ($guest['sobrenome'] ?? ''))) ?></td>
                <td><?= e($guest['nome_grupo']) ?></td>
                <td><span class="status-pill is-<?= e($guest['status']) ?>"><?= e($guest['status']) ?></span></td>
                <td><?= e(format_datetime($guest['responded_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </article>
</section>
<?php
admin_render_footer();
