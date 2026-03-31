<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    if (!admin_has_users()) {
        redirect('/admin/setup');
    }

    require_admin();
    $supportsPublicSlug = db_column_exists('familias', 'public_slug');
    $supportsEncryptedToken = db_column_exists('familias', 'token_encrypted');
    $publicSlugSelect = $supportsPublicSlug ? 'f.public_slug' : 'NULL AS public_slug';
    $tokenEncryptedSelect = $supportsEncryptedToken ? 'f.token_encrypted' : 'NULL AS token_encrypted';

    $slugExists = static function (string $slug, int $ignoreId = 0) use ($supportsPublicSlug): bool {
        if (!$supportsPublicSlug) {
            return false;
        }

        $params = ['public_slug' => $slug];
        $sql = 'SELECT id FROM familias WHERE public_slug = :public_slug';

        if ($ignoreId > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        return db_one($sql, $params) !== null;
    };

    $resolveCreateSlug = static function (string $requestedSlug) use ($supportsPublicSlug, $slugExists): ?string {
        if (!$supportsPublicSlug) {
            return null;
        }

        $normalizedSlug = normalize_public_slug($requestedSlug);

        if ($normalizedSlug === '') {
            do {
                $normalizedSlug = generate_public_slug(6);
            } while ($slugExists($normalizedSlug));

            return $normalizedSlug;
        }

        if (!public_slug_is_valid($normalizedSlug)) {
            throw new RuntimeException('Use um link curto com 3 a 24 caracteres, letras minusculas, numeros, "-" ou "_".');
        }

        if ($slugExists($normalizedSlug)) {
            throw new RuntimeException('Este link curto ja esta em uso. Escolha outro.');
        }

        return $normalizedSlug;
    };

    $resolveUpdateSlug = static function (string $requestedSlug, string $currentSlug, int $familyId) use ($supportsPublicSlug, $slugExists): ?string {
        if (!$supportsPublicSlug) {
            return null;
        }

        $trimmedSlug = trim($requestedSlug);

        if ($trimmedSlug === '') {
            return $currentSlug !== '' ? $currentSlug : null;
        }

        $normalizedSlug = normalize_public_slug($trimmedSlug);

        if (!public_slug_is_valid($normalizedSlug)) {
            throw new RuntimeException('Use um link curto com 3 a 24 caracteres, letras minusculas, numeros, "-" ou "_".');
        }

        if ($slugExists($normalizedSlug, $familyId)) {
            throw new RuntimeException('Este link curto ja esta em uso. Escolha outro.');
        }

        return $normalizedSlug;
    };

    if ((string) ($_GET['export'] ?? '') === 'familias_links') {
        $exportRows = db_all(
            "SELECT
                f.id,
                f.nome_grupo,
                {$publicSlugSelect},
                {$tokenEncryptedSelect},
                f.token_preview,
                f.token_ativo,
                f.observacoes,
                f.updated_at,
                SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL THEN 1 ELSE 0 END) AS total_convidados,
                SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL AND c.status = 'confirmado' THEN 1 ELSE 0 END) AS confirmados,
                SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL AND c.status = 'nao_ira' THEN 1 ELSE 0 END) AS nao_irao,
                SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL AND c.status = 'pendente' THEN 1 ELSE 0 END) AS pendentes
             FROM familias f
             LEFT JOIN convidados c ON c.familia_id = f.id
             GROUP BY f.id
             ORDER BY f.nome_grupo ASC"
        );

        audit_log('admin', 'export_links', 'familia', null, null, null, ['total_familias' => count($exportRows)]);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="familias-links.csv"');
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'wb');
        fputcsv(
            $output,
            ['id', 'familia', 'identificador_publico', 'link_rsvp', 'status_link', 'token_preview', 'token_ativo', 'total_convidados', 'confirmados', 'nao_irao', 'pendentes', 'observacoes', 'updated_at'],
            ';'
        );

        foreach ($exportRows as $row) {
            $familyIdentifier = family_public_identifier($row);
            $familyLink = family_link_from_row($row);
            $linkStatus = 'link_pronto';

            if ($familyLink === null) {
                if ($supportsPublicSlug) {
                    $linkStatus = 'definir_link_curto';
                } else {
                    $linkStatus = $supportsEncryptedToken ? 'registrar_link_atual' : 'atualizar_banco';
                }
            } elseif ((int) ($row['token_ativo'] ?? 0) !== 1) {
                $linkStatus = 'link_inativo';
            }

            fputcsv($output, [
                (int) $row['id'],
                $row['nome_grupo'],
                $familyIdentifier ?? '',
                $familyLink ?? '',
                $linkStatus,
                $row['token_preview'],
                (int) ($row['token_ativo'] ?? 0) === 1 ? 'sim' : 'nao',
                (int) ($row['total_convidados'] ?? 0),
                (int) ($row['confirmados'] ?? 0),
                (int) ($row['nao_irao'] ?? 0),
                (int) ($row['pendentes'] ?? 0),
                $row['observacoes'],
                $row['updated_at'],
            ], ';');
        }

        fclose($output);
        exit;
    }

    if (is_post()) {
        verify_csrf();

        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'create') {
            $nomeGrupo = trim((string) ($_POST['nome_grupo'] ?? ''));
            $publicSlug = $resolveCreateSlug((string) ($_POST['public_slug'] ?? ''));
            $observacoes = trim((string) ($_POST['observacoes'] ?? ''));

            if ($nomeGrupo === '') {
                throw new RuntimeException('Informe o nome da familia ou grupo.');
            }

            $token = generate_public_token(20);
            $encryptedToken = $supportsEncryptedToken ? encrypt_public_token($token) : null;
            $insertColumns = ['nome_grupo'];
            $insertValues = [':nome_grupo'];
            $params = ['nome_grupo' => $nomeGrupo];

            if ($supportsPublicSlug) {
                $insertColumns[] = 'public_slug';
                $insertValues[] = ':public_slug';
                $params['public_slug'] = $publicSlug;
            }

            $insertColumns[] = 'token_hash';
            $insertValues[] = ':token_hash';
            $params['token_hash'] = hash_public_token($token);

            if ($supportsEncryptedToken) {
                $insertColumns[] = 'token_encrypted';
                $insertValues[] = ':token_encrypted';
                $params['token_encrypted'] = $encryptedToken;
            }

            $insertColumns[] = 'token_preview';
            $insertValues[] = ':token_preview';
            $params['token_preview'] = token_preview($token);

            $insertColumns[] = 'token_ativo';
            $insertValues[] = '1';
            $insertColumns[] = 'observacoes';
            $insertValues[] = ':observacoes';
            $params['observacoes'] = $observacoes !== '' ? $observacoes : null;

            db_execute(
                sprintf('INSERT INTO familias (%s) VALUES (%s)', implode(', ', $insertColumns), implode(', ', $insertValues)),
                $params
            );

            $familyId = (int) db()->lastInsertId();
            audit_log('admin', 'create', 'familia', $familyId, $familyId, null, ['nome_grupo' => $nomeGrupo]);
            flash('success', 'Familia criada com sucesso.');
            flash('info', 'Link gerado agora: ' . family_public_url($publicSlug ?? $token));

            if ($supportsEncryptedToken && $encryptedToken === null) {
                flash('error', 'O servidor nao conseguiu preparar o botao de copiar link para esta familia.');
            }

            redirect('/admin/familias');
        }

        if ($action === 'update') {
            $familyId = (int) ($_POST['id'] ?? 0);
            $nomeGrupo = trim((string) ($_POST['nome_grupo'] ?? ''));
            $observacoes = trim((string) ($_POST['observacoes'] ?? ''));

            if ($familyId <= 0 || $nomeGrupo === '') {
                throw new RuntimeException('Dados da familia invalidos.');
            }

            $currentFamily = db_one(
                $supportsPublicSlug
                    ? 'SELECT id, public_slug FROM familias WHERE id = :id LIMIT 1'
                    : 'SELECT id FROM familias WHERE id = :id LIMIT 1',
                ['id' => $familyId]
            );

            if ($currentFamily === null) {
                throw new RuntimeException('Familia invalida.');
            }

            $currentSlug = $supportsPublicSlug ? normalize_public_slug((string) ($currentFamily['public_slug'] ?? '')) : '';
            $publicSlug = $resolveUpdateSlug((string) ($_POST['public_slug'] ?? ''), $currentSlug, $familyId);

            $updateSql = 'UPDATE familias
                 SET nome_grupo = :nome_grupo,
                     observacoes = :observacoes';
            $updateParams = [
                'nome_grupo' => $nomeGrupo,
                'observacoes' => $observacoes !== '' ? $observacoes : null,
                'id' => $familyId,
            ];

            if ($supportsPublicSlug) {
                $updateSql .= ', public_slug = :public_slug';
                $updateParams['public_slug'] = $publicSlug;
            }

            $updateSql .= ' WHERE id = :id';

            db_execute($updateSql, $updateParams);

            audit_log('admin', 'update', 'familia', $familyId, $familyId);
            flash('success', 'Familia atualizada com sucesso.');

            if ($supportsPublicSlug && $publicSlug !== null && $publicSlug !== $currentSlug) {
                flash('info', 'Novo link principal: ' . family_public_url($publicSlug));
            }

            redirect('/admin/familias');
        }

        if ($action === 'toggle_token') {
            $familyId = (int) ($_POST['id'] ?? 0);

            if ($familyId <= 0) {
                throw new RuntimeException('Familia invalida.');
            }

            db_execute(
                'UPDATE familias
                 SET token_ativo = CASE WHEN token_ativo = 1 THEN 0 ELSE 1 END
                 WHERE id = :id',
                ['id' => $familyId]
            );

            audit_log('admin', 'toggle_token', 'familia', $familyId, $familyId);
            flash('success', 'Status do link da familia atualizado.');
            redirect('/admin/familias');
        }

        if ($action === 'store_current_token') {
            $familyId = (int) ($_POST['id'] ?? 0);
            $inputToken = extract_public_token((string) ($_POST['existing_token'] ?? ''));

            if (!$supportsEncryptedToken) {
                throw new RuntimeException('Atualize o banco antes de registrar links existentes.');
            }

            if ($familyId <= 0 || $inputToken === null) {
                throw new RuntimeException('Cole o link atual completo ou apenas o token valido da familia.');
            }

            $familyRow = db_one('SELECT id, token_hash FROM familias WHERE id = :id LIMIT 1', ['id' => $familyId]);

            if ($familyRow === null) {
                throw new RuntimeException('Familia invalida.');
            }

            if (!hash_equals((string) ($familyRow['token_hash'] ?? ''), hash_public_token($inputToken))) {
                throw new RuntimeException('O link informado nao corresponde ao token atual desta familia.');
            }

            $encryptedToken = encrypt_public_token($inputToken);

            if ($encryptedToken === null) {
                throw new RuntimeException('O servidor nao conseguiu preparar este link para copia automatica.');
            }

            db_execute(
                'UPDATE familias
                 SET token_encrypted = :token_encrypted
                 WHERE id = :id',
                [
                    'token_encrypted' => $encryptedToken,
                    'id' => $familyId,
                ]
            );

            audit_log('admin', 'store_current_token', 'familia', $familyId, $familyId);
            flash('success', 'Link atual registrado. Agora esta familia pode usar o botao de copiar sem trocar o QR code.');
            redirect('/admin/familias');
        }

        if ($action === 'regenerate_token') {
            $familyId = (int) ($_POST['id'] ?? 0);

            if ($familyId <= 0) {
                throw new RuntimeException('Familia invalida.');
            }

            $token = generate_public_token(20);
            $params = [
                'token_hash' => hash_public_token($token),
                'token_preview' => token_preview($token),
                'id' => $familyId,
            ];
            $tokenUpdateSql = 'UPDATE familias
                 SET token_hash = :token_hash,
                     token_preview = :token_preview,
                     token_ativo = 1';

            if ($supportsEncryptedToken) {
                $tokenUpdateSql .= ', token_encrypted = :token_encrypted';
                $params['token_encrypted'] = encrypt_public_token($token);
            }

            $tokenUpdateSql .= ' WHERE id = :id';

            db_execute($tokenUpdateSql, $params);

            audit_log('admin', 'regenerate_token', 'familia', $familyId, $familyId);
            flash('success', 'Novo link gerado com sucesso. O link anterior foi invalidado.');
            flash('info', 'Link novo: ' . family_public_url($token));

            if ($supportsEncryptedToken && ($params['token_encrypted'] ?? null) === null) {
                flash('error', 'O servidor nao conseguiu preparar o botao de copiar link para esta familia.');
            }

            redirect('/admin/familias');
        }
    }

    $families = db_all(
        "SELECT
            f.id,
            f.nome_grupo,
            {$publicSlugSelect},
            {$tokenEncryptedSelect},
            f.token_preview,
            f.token_ativo,
            f.observacoes,
            f.updated_at,
            SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL THEN 1 ELSE 0 END) AS total_convidados,
            SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL AND c.status = 'confirmado' THEN 1 ELSE 0 END) AS confirmados,
            SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL AND c.status = 'nao_ira' THEN 1 ELSE 0 END) AS nao_irao,
            SUM(CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL AND c.status = 'pendente' THEN 1 ELSE 0 END) AS pendentes
         FROM familias f
         LEFT JOIN convidados c ON c.familia_id = f.id
         GROUP BY f.id
         ORDER BY f.nome_grupo ASC"
    );

    $editingId = (int) ($_GET['edit'] ?? 0);
    $editingFamily = $editingId > 0
        ? db_one('SELECT * FROM familias WHERE id = :id LIMIT 1', ['id' => $editingId])
        : null;

    $familyMetrics = [
        'familias' => count($families),
        'links_ativos' => 0,
        'confirmados' => 0,
        'pendentes' => 0,
    ];

    foreach ($families as $familyRow) {
        if ((int) ($familyRow['token_ativo'] ?? 0) === 1) {
            $familyMetrics['links_ativos']++;
        }

        $familyMetrics['confirmados'] += (int) ($familyRow['confirmados'] ?? 0);
        $familyMetrics['pendentes'] += (int) ($familyRow['pendentes'] ?? 0);
    }
} catch (Throwable $exception) {
    admin_render_runtime_error('Familias', $exception->getMessage());
}

admin_render_header('Familias');
admin_render_flashes();
$exportLinksQuery = http_build_query(['export' => 'familias_links']);
?>

<section class="admin-two-col">
  <article class="admin-panel admin-panel--sticky">
    <p class="eyebrow"><?= $editingFamily !== null ? 'Editar familia' : 'Nova familia' ?></p>
    <h1><?= $editingFamily !== null ? 'Atualizar familia' : 'Cadastrar familia' ?></h1>
    <form method="post" class="admin-form-grid">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="<?= $editingFamily !== null ? 'update' : 'create' ?>">
      <?php if ($editingFamily !== null): ?>
        <input type="hidden" name="id" value="<?= e((int) $editingFamily['id']) ?>">
      <?php endif; ?>

      <div class="admin-field">
        <label for="nome_grupo">Nome da familia ou grupo</label>
        <input
          id="nome_grupo"
          name="nome_grupo"
          type="text"
          required
          value="<?= e($editingFamily['nome_grupo'] ?? '') ?>"
          placeholder="Ex.: Familia Tolentino"
        >
      </div>

      <?php if ($supportsPublicSlug): ?>
        <div class="admin-field">
          <label for="public_slug">Link curto</label>
          <input
            id="public_slug"
            name="public_slug"
            type="text"
            value="<?= e($editingFamily['public_slug'] ?? '') ?>"
            placeholder="Ex.: tolentino"
            autocapitalize="off"
            spellcheck="false"
          >
          <!-- <small>Use de 3 a 24 caracteres. Este identificador sera usado em `/c/...`.</small> -->
        </div>
      <?php endif; ?>

      <div class="admin-field">
        <label for="observacoes">Observacoes internas</label>
        <textarea id="observacoes" name="observacoes" rows="4" placeholder="Anotacoes do cerimonial ou dos noivos"><?= e($editingFamily['observacoes'] ?? '') ?></textarea>
      </div>

      <div class="admin-actions">
        <button class="admin-button" type="submit"><?= $editingFamily !== null ? 'Salvar alteracoes' : 'Cadastrar familia' ?></button>
        <?php if ($editingFamily !== null): ?>
          <a class="admin-button-secondary" href="<?= e(url('/admin/familias')) ?>">Cancelar edicao</a>
        <?php endif; ?>
      </div>
    </form>
  </article>

  <section class="admin-panel">
    <section class="lista-familias">
        <section>
            <p class="eyebrow">Lista</p>
            <h2>Familias cadastradas</h2>
        </section>
        <div class="admin-page-actions">
          <a class="admin-button" href="<?= e(url('/admin/familias?' . $exportLinksQuery)) ?>">Exportar</a>
        </div>
    </section>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Familia</th>
            <th>Identificador</th>
            <th>Convidados</th>
            <th>Status do link</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($families === []): ?>
            <tr>
              <td colspan="7">Nenhuma familia cadastrada ainda.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($families as $family): ?>
              <?php $familyIdentifier = family_public_identifier($family); ?>
              <?php $familyLink = family_link_from_row($family); ?>
              <tr>
                <td>
                  <strong><?= e($family['nome_grupo']) ?></strong><br>
                  <small><?= e($family['observacoes'] ?? '') ?></small>
                </td>
                <td>
                  <strong><?= e((string) ($family['public_slug'] ?? '') !== '' ? $family['public_slug'] : ($family['token_preview'] ?? '-')) ?></strong><br>
                </td>
                <td><?= e((int) $family['total_convidados']) ?></td>
                <td>
                  <span class="status-pill <?= (int) $family['token_ativo'] === 1 ? 'is-confirmado' : 'is-nao_ira' ?>">
                    <?= (int) $family['token_ativo'] === 1 ? 'ativo' : 'inativo' ?>
                  </span>
                </td>
                <td>
                  <div class="inline-form">
                    <a class="admin-button-secondary" href="<?= e(url('/admin/familias?edit=' . (int) $family['id'])) ?>">Editar</a>
                    <?php if ($familyLink !== null): ?>
                    <?php elseif ($supportsEncryptedToken): ?>
                      <form method="post" class="inline-form">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="store_current_token">
                        <input type="hidden" name="id" value="<?= e((int) $family['id']) ?>">
                        <input type="hidden" name="existing_token" value="" class="js-existing-token-input">
                        <button
                          class="admin-button-secondary js-fill-existing-token"
                          type="button"
                          data-family-name="<?= e($family['nome_grupo']) ?>"
                        >
                          Registrar link atual
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="admin-inline-note"><?= $supportsEncryptedToken ? 'Regenere para copiar' : 'Atualize o banco' ?></span>
                    <?php endif; ?>
                    <form method="post" class="inline-form">
                      <?= csrf_input() ?>
                      <input type="hidden" name="action" value="toggle_token">
                      <input type="hidden" name="id" value="<?= e((int) $family['id']) ?>">
                      <button class="admin-button-danger" type="submit">
                        <?= (int) $family['token_ativo'] === 1 ? 'Desativar' : 'Ativar' ?>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</section>

<?php
admin_render_footer();
