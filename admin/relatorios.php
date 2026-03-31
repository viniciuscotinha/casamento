<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    if (!admin_has_users()) {
        redirect('/admin/setup');
    }

    require_admin();

    $filters = [
        'familia_id' => (int) ($_GET['familia_id'] ?? 0),
        'status' => (string) ($_GET['status'] ?? ''),
    ];

    if ((string) ($_GET['export'] ?? '') === 'convidados') {
        $where = ['c.deleted_at IS NULL'];
        $params = [];

        if ($filters['familia_id'] > 0) {
            $where[] = 'c.familia_id = :familia_id';
            $params['familia_id'] = $filters['familia_id'];
        }

        if (in_array($filters['status'], ['pendente', 'confirmado', 'nao_ira'], true)) {
            $where[] = 'c.status = :status';
            $params['status'] = $filters['status'];
        }

        $exportRows = db_all(
            "SELECT
                f.nome_grupo,
                c.nome,
                c.sobrenome,
                c.telefone,
                c.email,
                c.is_responsavel,
                c.status,
                c.responded_at,
                c.updated_at
             FROM convidados c
             INNER JOIN familias f ON f.id = c.familia_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY f.nome_grupo ASC, c.nome ASC, c.sobrenome ASC",
            $params
        );

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="relatorio-convidados.csv"');
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'wb');
        fputcsv($output, ['familia', 'nome', 'sobrenome', 'telefone', 'email', 'is_responsavel', 'status', 'responded_at', 'updated_at'], ';');

        foreach ($exportRows as $row) {
            fputcsv($output, [
                $row['nome_grupo'],
                $row['nome'],
                $row['sobrenome'],
                $row['telefone'],
                $row['email'],
                (int) $row['is_responsavel'] === 1 ? 'sim' : 'nao',
                $row['status'],
                $row['responded_at'],
                $row['updated_at'],
            ], ';');
        }

        fclose($output);
        exit;
    }

    $metrics = [
        'total_convidados' => (int) db_value('SELECT COUNT(*) FROM convidados WHERE deleted_at IS NULL'),
        'confirmados' => (int) db_value("SELECT COUNT(*) FROM convidados WHERE deleted_at IS NULL AND status = 'confirmado'"),
        'nao_irao' => (int) db_value("SELECT COUNT(*) FROM convidados WHERE deleted_at IS NULL AND status = 'nao_ira'"),
        'pendentes' => (int) db_value("SELECT COUNT(*) FROM convidados WHERE deleted_at IS NULL AND status = 'pendente'"),
    ];

    $families = db_all('SELECT id, nome_grupo FROM familias ORDER BY nome_grupo ASC');

    $familySummary = db_all(
        "SELECT
            f.nome_grupo,
            SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL THEN 1 ELSE 0 END) AS total_convidados,
            SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL AND c.status = 'confirmado' THEN 1 ELSE 0 END) AS confirmados,
            SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL AND c.status = 'nao_ira' THEN 1 ELSE 0 END) AS nao_irao,
            SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL AND c.status = 'pendente' THEN 1 ELSE 0 END) AS pendentes
         FROM familias f
         LEFT JOIN convidados c ON c.familia_id = f.id
         GROUP BY f.id
         ORDER BY f.nome_grupo ASC"
    );

    $representativeSummary = db_all(
        "SELECT
            f.nome_grupo,
            CONCAT(COALESCE(r.nome, ''), ' ', COALESCE(r.sobrenome, '')) AS responsavel_nome,
            SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL THEN 1 ELSE 0 END) AS total_convidados,
            SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL AND c.status = 'confirmado' THEN 1 ELSE 0 END) AS confirmados,
            SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL AND c.status = 'nao_ira' THEN 1 ELSE 0 END) AS nao_irao,
            SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL AND c.status = 'pendente' THEN 1 ELSE 0 END) AS pendentes
         FROM familias f
         LEFT JOIN convidados r
           ON r.familia_id = f.id
          AND r.is_responsavel = 1
          AND r.deleted_at IS NULL
         LEFT JOIN convidados c ON c.familia_id = f.id
         GROUP BY f.id, r.id
         ORDER BY f.nome_grupo ASC"
    );

    $latestResponses = db_all(
        "SELECT
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
         LIMIT 15"
    );
} catch (Throwable $exception) {
    admin_render_runtime_error('Relatorios', $exception->getMessage());
}

admin_render_header('Relatorios');
admin_render_flashes();
?>
<section class="admin-page-header">
  <div>
    <p class="eyebrow">Relatorios</p>
    <h2>Acompanhe o RSVP com mais clareza</h2>
    <p>Veja totais, ultimas respostas, familia por familia e exporte a planilha quando precisar compartilhar com a equipe.</p>
  </div>
  <div class="admin-page-actions">
    <?php
    $exportQuery = http_build_query(array_filter([
        'export' => 'convidados',
        'familia_id' => $filters['familia_id'] > 0 ? $filters['familia_id'] : null,
        'status' => $filters['status'] !== '' ? $filters['status'] : null,
    ]));
    ?>
    <a class="admin-button" href="<?= e(url('/admin/relatorios?' . $exportQuery)) ?>">Exportar CSV</a>
  </div>
</section>

<section class="admin-panel">
  <p class="eyebrow">Resumo</p>
  <h3>Relatorios do RSVP</h3>
  <div class="admin-metrics">
    <article class="metric-card">
      <span>Total</span>
      <strong><?= e($metrics['total_convidados']) ?></strong>
    </article>
    <article class="metric-card">
      <span>Confirmados</span>
      <strong><?= e($metrics['confirmados']) ?></strong>
    </article>
    <article class="metric-card">
      <span>Nao irao</span>
      <strong><?= e($metrics['nao_irao']) ?></strong>
    </article>
    <article class="metric-card">
      <span>Pendentes</span>
      <strong><?= e($metrics['pendentes']) ?></strong>
    </article>
  </div>
</section>

<section class="admin-two-col">
  <article class="admin-panel">
    <p class="eyebrow">Filtros</p>
    <h2>Filtrar relatorio</h2>
    <form method="get" class="admin-form-grid">
      <div class="admin-form-grid admin-form-grid--two">
        <div class="admin-field">
          <label for="familia_id">Familia</label>
          <select id="familia_id" name="familia_id">
            <option value="">Todas</option>
            <?php foreach ($families as $family): ?>
              <option value="<?= e((int) $family['id']) ?>" <?= $filters['familia_id'] === (int) $family['id'] ? 'selected' : '' ?>>
                <?= e($family['nome_grupo']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="admin-field">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="">Todos</option>
            <?php foreach (['pendente', 'confirmado', 'nao_ira'] as $statusOption): ?>
              <option value="<?= e($statusOption) ?>" <?= $filters['status'] === $statusOption ? 'selected' : '' ?>>
                <?= e($statusOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="admin-actions">
        <button class="admin-button-secondary" type="submit">Aplicar filtros</button>
        <a class="admin-button-secondary" href="<?= e(url('/admin/relatorios')) ?>">Limpar</a>
      </div>
    </form>
  </article>

  <article class="admin-panel">
    <p class="eyebrow">Exportacao</p>
    <h2>Baixar CSV</h2>
    <p>Exporte a lista atualizada para compartilhar com buffet, cerimonial ou equipe de recepcao.</p>
    <div class="admin-actions">
      <a class="admin-button" href="<?= e(url('/admin/relatorios?' . $exportQuery)) ?>">Exportar convidados</a>
    </div>
  </article>

  <article class="admin-panel">
    <p class="eyebrow">Ultimas respostas</p>
    <h2>Atualizacoes recentes</h2>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Convidado</th>
            <th>Familia</th>
            <th>Status</th>
            <th>Respondido em</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($latestResponses === []): ?>
            <tr>
              <td colspan="4">Nenhuma confirmacao registrada ainda.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($latestResponses as $row): ?>
              <tr>
                <td><?= e(trim($row['nome'] . ' ' . ($row['sobrenome'] ?? ''))) ?></td>
                <td><?= e($row['nome_grupo']) ?></td>
                <td><span class="status-pill is-<?= e($row['status']) ?>"><?= e($row['status']) ?></span></td>
                <td><?= e(format_datetime($row['responded_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </article>
</section>

<section class="admin-panel">
  <p class="eyebrow">Por responsavel</p>
  <h2>Resumo por representante</h2>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Familia</th>
          <th>Responsavel</th>
          <th>Total</th>
          <th>Confirmados</th>
          <th>Nao irao</th>
          <th>Pendentes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($representativeSummary as $row): ?>
          <tr>
            <td><?= e($row['nome_grupo']) ?></td>
            <td><?= e(trim((string) $row['responsavel_nome']) !== '' ? $row['responsavel_nome'] : 'Sem responsavel') ?></td>
            <td><?= e((int) $row['total_convidados']) ?></td>
            <td><?= e((int) $row['confirmados']) ?></td>
            <td><?= e((int) $row['nao_irao']) ?></td>
            <td><?= e((int) $row['pendentes']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="admin-panel">
  <p class="eyebrow">Por familia</p>
  <h2>Resumo por grupo</h2>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Familia</th>
          <th>Total</th>
          <th>Confirmados</th>
          <th>Nao irao</th>
          <th>Pendentes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($familySummary as $family): ?>
          <tr>
            <td><?= e($family['nome_grupo']) ?></td>
            <td><?= e((int) $family['total_convidados']) ?></td>
            <td><?= e((int) $family['confirmados']) ?></td>
            <td><?= e((int) $family['nao_irao']) ?></td>
            <td><?= e((int) $family['pendentes']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php
admin_render_footer();
