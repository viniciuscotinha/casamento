<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    if (!admin_has_users()) {
        redirect('/admin/setup');
    }

    require_admin();
    $supportsManualInvite = manual_supports_schema();

    if (is_post()) {
        verify_csrf();

        $action = (string) ($_POST['action'] ?? '');

        if (in_array($action, ['create', 'update'], true)) {
            $guestId = (int) ($_POST['id'] ?? 0);
            $familyId = (int) ($_POST['familia_id'] ?? 0);
            $responsavelId = (int) ($_POST['responsavel_id'] ?? 0);
            $isResponsavel = isset($_POST['is_responsavel']) ? 1 : 0;
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $sobrenome = trim((string) ($_POST['sobrenome'] ?? ''));
            $telefone = trim((string) ($_POST['telefone'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $manualInviteId = $supportsManualInvite ? (int) ($_POST['manual_invite_id'] ?? 0) : 0;
            $manualRoleTitle = $supportsManualInvite ? trim((string) ($_POST['manual_role_title'] ?? '')) : '';
            $manualRoleText = $supportsManualInvite ? trim((string) ($_POST['manual_role_text'] ?? '')) : '';
            $manualSortOrder = $supportsManualInvite ? max(1, (int) ($_POST['manual_sort_order'] ?? 1)) : 1;
            $status = (string) ($_POST['status'] ?? 'pendente');
            $observacoes = trim((string) ($_POST['observacoes'] ?? ''));

            if ($familyId <= 0 || $nome === '') {
                throw new RuntimeException('Escolha a familia e informe o nome do convidado.');
            }

            if (!in_array($status, ['pendente', 'confirmado', 'nao_ira'], true)) {
                throw new RuntimeException('Status do convidado invalido.');
            }

            $familyRecord = db_one('SELECT id FROM familias WHERE id = :id LIMIT 1', ['id' => $familyId]);

            if ($familyRecord === null) {
                throw new RuntimeException('A familia informada nao existe.');
            }

            $manualInviteRecord = null;

            if ($supportsManualInvite && $manualInviteId > 0) {
                $manualInviteRecord = db_one(
                    'SELECT id, nome_grupo, is_active
                     FROM convites_padrinhos
                     WHERE id = :id
                     LIMIT 1',
                    ['id' => $manualInviteId]
                );

                if ($manualInviteRecord === null) {
                    throw new RuntimeException('Escolha um convite de padrinhos valido.');
                }
            }

            if ($action === 'update' && $guestId <= 0) {
                throw new RuntimeException('Convidado invalido para atualizacao.');
            }

            $currentGuest = $action === 'update'
                ? db_one('SELECT id, familia_id, is_responsavel FROM convidados WHERE id = :id AND deleted_at IS NULL LIMIT 1', ['id' => $guestId])
                : null;

            if ($action === 'update' && $currentGuest === null) {
                throw new RuntimeException('Convidado nao encontrado para atualizacao.');
            }

            if ($manualInviteId > 0 && $manualRoleTitle === '') {
                throw new RuntimeException('Preencha o titulo do card individual para este convite de padrinhos.');
            }

            if ($manualInviteId > 0 && $manualRoleText === '' && $manualRoleTitle !== '') {
                $manualRoleText = manual_default_role_text_for_title($manualRoleTitle, $nome);
            }

            if ($manualInviteId > 0) {
                $manualGuestCount = (int) db_value(
                    'SELECT COUNT(*)
                     FROM convidados
                     WHERE manual_invite_id = :manual_invite_id
                       AND deleted_at IS NULL
                       AND id <> :id',
                    [
                        'manual_invite_id' => $manualInviteId,
                        'id' => $guestId,
                    ]
                );

                if ($manualGuestCount >= 2) {
                    throw new RuntimeException('Cada convite de padrinhos pode ter no maximo duas pessoas no mesmo link.');
                }
            }

            if ($isResponsavel === 1) {
                $existingRepresentative = db_one(
                    'SELECT id
                     FROM convidados
                     WHERE familia_id = :familia_id
                       AND is_responsavel = 1
                       AND deleted_at IS NULL
                       AND id <> :id
                     LIMIT 1',
                    [
                        'familia_id' => $familyId,
                        'id' => $guestId,
                    ]
                );

                if ($existingRepresentative !== null) {
                    throw new RuntimeException('Esta familia ja possui um responsavel principal cadastrado.');
                }

                $responsavelId = 0;
            }

            if ($responsavelId > 0) {
                $validRepresentative = db_one(
                    'SELECT id
                     FROM convidados
                     WHERE id = :id
                       AND familia_id = :familia_id
                       AND is_responsavel = 1
                       AND deleted_at IS NULL
                     LIMIT 1',
                    [
                        'id' => $responsavelId,
                        'familia_id' => $familyId,
                    ]
                );

                if ($validRepresentative === null) {
                    throw new RuntimeException('Escolha um responsavel valido da mesma familia.');
                }
            }

            if (
                $action === 'update'
                && $currentGuest !== null
                && (int) $currentGuest['is_responsavel'] === 1
                && (int) $currentGuest['familia_id'] !== $familyId
            ) {
                $dependentCount = (int) db_value(
                    'SELECT COUNT(*) FROM convidados WHERE responsavel_id = :responsavel_id AND deleted_at IS NULL',
                    ['responsavel_id' => $guestId]
                );

                if ($dependentCount > 0) {
                    throw new RuntimeException('Remova ou reatribua os convidados vinculados antes de trocar a familia deste responsavel.');
                }
            }

            db_transaction(function () use (
                $action,
                $guestId,
                $familyId,
                $responsavelId,
                $isResponsavel,
                $nome,
                $sobrenome,
                $telefone,
                $email,
                $manualInviteId,
                $supportsManualInvite,
                $manualRoleTitle,
                $manualRoleText,
                $manualSortOrder,
                $status,
                $observacoes
            ): void {
                $payload = [
                    'familia_id' => $familyId,
                    'manual_invite_id' => $manualInviteId > 0 ? $manualInviteId : null,
                    'responsavel_id' => $responsavelId > 0 ? $responsavelId : null,
                    'nome' => $nome,
                    'sobrenome' => $sobrenome !== '' ? $sobrenome : null,
                    'telefone' => $telefone !== '' ? $telefone : null,
                    'email' => $email !== '' ? $email : null,
                    'is_responsavel' => $isResponsavel,
                    'status' => $status,
                    'responded_at' => $status === 'pendente' ? null : date('Y-m-d H:i:s'),
                    'updated_by_origin' => 'admin',
                    'observacoes' => $observacoes !== '' ? $observacoes : null,
                ];

                if ($supportsManualInvite) {
                    $payload['manual_role_title'] = $manualInviteId > 0 && $manualRoleTitle !== '' ? $manualRoleTitle : null;
                    $payload['manual_role_text'] = $manualInviteId > 0 && $manualRoleText !== '' ? $manualRoleText : null;
                    $payload['manual_sort_order'] = $manualInviteId > 0 ? $manualSortOrder : 1;
                }

                if ($action === 'create') {
                    if ($supportsManualInvite) {
                        db_execute(
                            'INSERT INTO convidados (
                                familia_id, manual_invite_id, responsavel_id, nome, sobrenome, telefone, email,
                                manual_role_title, manual_role_text, manual_sort_order,
                                is_responsavel, status, responded_at, updated_by_origin, observacoes
                             ) VALUES (
                                :familia_id, :manual_invite_id, :responsavel_id, :nome, :sobrenome, :telefone, :email,
                                :manual_role_title, :manual_role_text, :manual_sort_order,
                                :is_responsavel, :status, :responded_at, :updated_by_origin, :observacoes
                             )',
                            $payload
                        );
                    } else {
                        db_execute(
                            'INSERT INTO convidados (
                                familia_id, responsavel_id, nome, sobrenome, telefone, email,
                                is_responsavel, status, responded_at, updated_by_origin, observacoes
                             ) VALUES (
                                :familia_id, :responsavel_id, :nome, :sobrenome, :telefone, :email,
                                :is_responsavel, :status, :responded_at, :updated_by_origin, :observacoes
                             )',
                            $payload
                        );
                    }

                    return;
                }

                $payload['id'] = $guestId;

                if ($supportsManualInvite) {
                    db_execute(
                        'UPDATE convidados
                         SET familia_id = :familia_id,
                             manual_invite_id = :manual_invite_id,
                             responsavel_id = :responsavel_id,
                             nome = :nome,
                             sobrenome = :sobrenome,
                             telefone = :telefone,
                             email = :email,
                             manual_role_title = :manual_role_title,
                             manual_role_text = :manual_role_text,
                             manual_sort_order = :manual_sort_order,
                             is_responsavel = :is_responsavel,
                             status = :status,
                             responded_at = :responded_at,
                             updated_by_origin = :updated_by_origin,
                             observacoes = :observacoes
                         WHERE id = :id',
                        $payload
                    );
                } else {
                    db_execute(
                        'UPDATE convidados
                         SET familia_id = :familia_id,
                             responsavel_id = :responsavel_id,
                             nome = :nome,
                             sobrenome = :sobrenome,
                             telefone = :telefone,
                             email = :email,
                             is_responsavel = :is_responsavel,
                             status = :status,
                             responded_at = :responded_at,
                             updated_by_origin = :updated_by_origin,
                             observacoes = :observacoes
                         WHERE id = :id',
                        $payload
                    );
                }

                if ($isResponsavel === 0) {
                    db_execute(
                        'UPDATE convidados
                         SET responsavel_id = NULL
                         WHERE responsavel_id = :responsavel_id',
                        ['responsavel_id' => $guestId]
                    );
                }
            });

            $entityId = $action === 'create' ? (int) db()->lastInsertId() : $guestId;
            audit_log('admin', $action, 'convidado', $entityId, $familyId, $entityId);
            flash('success', $action === 'create' ? 'Convidado cadastrado com sucesso.' : 'Convidado atualizado com sucesso.');
            redirect('/admin/convidados');
        }

        if ($action === 'delete') {
            $guestId = (int) ($_POST['id'] ?? 0);

            if ($guestId <= 0) {
                throw new RuntimeException('Convidado invalido.');
            }

            db_transaction(function () use ($guestId): void {
                db_execute(
                    'UPDATE convidados
                     SET responsavel_id = NULL
                     WHERE responsavel_id = :responsavel_id',
                    ['responsavel_id' => $guestId]
                );

                db_execute(
                    'UPDATE convidados
                     SET deleted_at = NOW()
                     WHERE id = :id',
                    ['id' => $guestId]
                );
            });

            audit_log('admin', 'delete', 'convidado', $guestId, null, $guestId);
            flash('success', 'Convidado removido da lista ativa.');
            redirect('/admin/convidados');
        }
    }

    $families = db_all('SELECT id, nome_grupo FROM familias ORDER BY nome_grupo ASC');
    $manualInvites = $supportsManualInvite
        ? db_all('SELECT id, nome_grupo, public_slug, is_active FROM convites_padrinhos ORDER BY nome_grupo ASC, id ASC')
        : [];

    $representatives = db_all(
        "SELECT
            c.id,
            c.familia_id,
            c.nome,
            c.sobrenome,
            f.nome_grupo
         FROM convidados c
         INNER JOIN familias f ON f.id = c.familia_id
         WHERE c.deleted_at IS NULL
           AND c.is_responsavel = 1
         ORDER BY f.nome_grupo ASC, c.nome ASC"
    );

    $filters = [
        'familia_id' => (int) ($_GET['familia_id'] ?? 0),
        'status' => (string) ($_GET['status'] ?? ''),
        'q' => trim((string) ($_GET['q'] ?? '')),
    ];

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

    if ($filters['q'] !== '') {
        $where[] = $supportsManualInvite
            ? '(c.nome LIKE :q OR c.sobrenome LIKE :q OR f.nome_grupo LIKE :q OR mi.nome_grupo LIKE :q)'
            : '(c.nome LIKE :q OR c.sobrenome LIKE :q OR f.nome_grupo LIKE :q)';
        $params['q'] = '%' . $filters['q'] . '%';
    }

    $manualInviteSelect = $supportsManualInvite
        ? "mi.nome_grupo AS manual_invite_nome,
            mi.public_slug AS manual_invite_slug,"
        : "NULL AS manual_invite_nome,
            NULL AS manual_invite_slug,";
    $manualInviteJoin = $supportsManualInvite
        ? 'LEFT JOIN convites_padrinhos mi ON mi.id = c.manual_invite_id'
        : '';

    $guests = db_all(
        "SELECT
            c.*,
            f.nome_grupo,
            {$manualInviteSelect}
            CONCAT(COALESCE(r.nome, ''), ' ', COALESCE(r.sobrenome, '')) AS responsavel_nome
         FROM convidados c
         INNER JOIN familias f ON f.id = c.familia_id
         {$manualInviteJoin}
         LEFT JOIN convidados r ON r.id = c.responsavel_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY f.nome_grupo ASC, c.is_responsavel DESC, c.nome ASC, c.sobrenome ASC",
        $params
    );

    $editingId = (int) ($_GET['edit'] ?? 0);
    $editingGuest = $editingId > 0
        ? db_one('SELECT * FROM convidados WHERE id = :id AND deleted_at IS NULL LIMIT 1', ['id' => $editingId])
        : null;

    $guestMetrics = [
        'total' => count($guests),
        'responsaveis' => 0,
        'confirmados' => 0,
        'pendentes' => 0,
    ];

    foreach ($guests as $guestRow) {
        if ((int) ($guestRow['is_responsavel'] ?? 0) === 1) {
            $guestMetrics['responsaveis']++;
        }

        if (($guestRow['status'] ?? 'pendente') === 'confirmado') {
            $guestMetrics['confirmados']++;
        }

        if (($guestRow['status'] ?? 'pendente') === 'pendente') {
            $guestMetrics['pendentes']++;
        }
    }

    $manualRoleTitleValue = $supportsManualInvite
        ? trim((string) ($editingGuest['manual_role_title'] ?? ''))
        : '';
    $manualRoleTextValue = $supportsManualInvite
        ? trim((string) ($editingGuest['manual_role_text'] ?? ''))
        : '';

    if ($supportsManualInvite && $manualRoleTextValue === '' && $manualRoleTitleValue !== '') {
        $manualRoleTextValue = manual_default_role_text_for_title(
            $manualRoleTitleValue,
            trim((string) ($editingGuest['nome'] ?? ''))
        );
    }
} catch (Throwable $exception) {
    admin_render_runtime_error('Convidados', $exception->getMessage());
}

admin_render_header('Convidados');
admin_render_flashes();
?>

<section class="admin-two-col">
  <article class="admin-panel admin-panel--sticky">
    <p class="eyebrow"><?= $editingGuest !== null ? 'Editar convidado' : 'Novo convidado' ?></p>
    <h1><?= $editingGuest !== null ? 'Atualizar convidado' : 'Cadastrar convidado' ?></h1>
    <form method="post" class="admin-form-grid">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="<?= $editingGuest !== null ? 'update' : 'create' ?>">
      <?php if ($editingGuest !== null): ?>
        <input type="hidden" name="id" value="<?= e((int) $editingGuest['id']) ?>">
      <?php endif; ?>

      <div class="admin-form-grid admin-form-grid--two">
        <div class="admin-field">
          <label for="familia_id">Familia</label>
          <select id="familia_id" name="familia_id" required>
            <option value="">Selecione</option>
            <?php foreach ($families as $family): ?>
              <option
                value="<?= e((int) $family['id']) ?>"
                <?= (int) ($editingGuest['familia_id'] ?? 0) === (int) $family['id'] ? 'selected' : '' ?>
              >
                <?= e($family['nome_grupo']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="admin-field">
          <label for="status">Status</label>
          <select id="status" name="status" required>
            <?php foreach (['pendente', 'confirmado', 'nao_ira'] as $statusOption): ?>
              <option value="<?= e($statusOption) ?>" <?= ($editingGuest['status'] ?? 'pendente') === $statusOption ? 'selected' : '' ?>>
                <?= e($statusOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="admin-form-grid admin-form-grid--two">
        <div class="admin-field">
          <label for="nome">Nome</label>
          <input id="nome" name="nome" type="text" required value="<?= e($editingGuest['nome'] ?? '') ?>">
        </div>
        <div class="admin-field">
          <label for="sobrenome">Sobrenome</label>
          <input id="sobrenome" name="sobrenome" type="text" value="<?= e($editingGuest['sobrenome'] ?? '') ?>">
        </div>
      </div>

      <div class="admin-form-grid admin-form-grid--two">
        <div class="admin-field">
          <label for="telefone">Telefone</label>
          <input id="telefone" name="telefone" type="text" value="<?= e($editingGuest['telefone'] ?? '') ?>">
        </div>
        <div class="admin-field">
          <label for="email">E-mail</label>
          <input id="email" name="email" type="email" value="<?= e($editingGuest['email'] ?? '') ?>">
        </div>
      </div>

      <?php if ($supportsManualInvite): ?>
        <input type="hidden" name="manual_invite_id" value="<?= e((int) ($editingGuest['manual_invite_id'] ?? 0)) ?>">
        <input type="hidden" name="manual_role_title" value="<?= e($manualRoleTitleValue) ?>">
        <input type="hidden" name="manual_role_text" value="<?= e($manualRoleTextValue) ?>">
        <input type="hidden" name="manual_sort_order" value="<?= e((int) ($editingGuest['manual_sort_order'] ?? 1)) ?>">
      <?php endif; ?>

      <div class="admin-check-row">
        <input id="is_responsavel" name="is_responsavel" type="checkbox" value="1" <?= (int) ($editingGuest['is_responsavel'] ?? 0) === 1 ? 'checked' : '' ?>>
        <label class="admin-check" for="is_responsavel">Este convidado e o responsavel da familia</label>
      </div>

      <div class="admin-field">
        <label for="responsavel_id">Responsavel vinculado</label>
        <select id="responsavel_id" name="responsavel_id">
          <option value="">Sem responsavel definido</option>
          <?php foreach ($representatives as $representative): ?>
            <option
              value="<?= e((int) $representative['id']) ?>"
              <?= (int) ($editingGuest['responsavel_id'] ?? 0) === (int) $representative['id'] ? 'selected' : '' ?>
            >
              <?= e(trim($representative['nome_grupo'] . ' - ' . normalized_full_name($representative))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="admin-field">
        <label for="observacoes">Observacoes</label>
        <textarea id="observacoes" name="observacoes" rows="3"><?= e($editingGuest['observacoes'] ?? '') ?></textarea>
      </div>

      <div class="admin-actions">
        <button class="admin-button" type="submit"><?= $editingGuest !== null ? 'Salvar alteracoes' : 'Cadastrar convidado' ?></button>
        <?php if ($editingGuest !== null): ?>
          <a class="admin-button-secondary" href="<?= e(url('/admin/convidados')) ?>">Cancelar edicao</a>
        <?php endif; ?>
      </div>
    </form>
  </article>

  <section class="admin-panel">
    <p class="eyebrow">Lista</p>
    <h2>Convidados ativos</h2>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Convidado</th>
            <th>Familia</th>
            <th>Responsavel</th>
            <th>Contato</th>
            <th>Status</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($guests === []): ?>
            <tr>
              <td colspan="7">Nenhum convidado encontrado.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($guests as $guest): ?>
              <tr>
                <td>
                  <strong><?= e(normalized_full_name($guest)) ?></strong><br>
                  <small>
                    <?= (int) $guest['is_responsavel'] === 1 ? 'Responsavel da familia' : 'Membro vinculado' ?>
                  </small>
                </td>
                <td><?= e($guest['nome_grupo']) ?></td>
                <td><?= e(trim((string) $guest['responsavel_nome']) !== '' ? $guest['responsavel_nome'] : '-') ?></td>
                <td>
                  <?= e($guest['telefone'] ?? '-') ?><br>
                  <small><?= e($guest['email'] ?? '') ?></small>
                </td>
                <td><span class="status-pill is-<?= e($guest['status']) ?>"><?= e($guest['status']) ?></span></td>
                <td>
                  <div class="inline-form">
                    <a class="admin-button-secondary" href="<?= e(url('/admin/convidados?edit=' . (int) $guest['id'])) ?>">Editar</a>
                    <form method="post" class="inline-form">
                      <?= csrf_input() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= e((int) $guest['id']) ?>">
                      <button class="admin-button-danger" type="submit">Excluir</button>
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
