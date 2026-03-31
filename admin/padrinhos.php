<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    if (!admin_has_users()) {
        redirect('/admin/setup');
    }

    require_admin();

    if (!manual_supports_schema()) {
        throw new RuntimeException('Rode `sql/migrations/20260331_add_manual_invites.sql` para habilitar os convites de padrinhos independentes.');
    }

    $slugExists = static function (string $slug, int $ignoreId = 0): bool {
        $params = ['public_slug' => $slug];
        $sql = 'SELECT id FROM convites_padrinhos WHERE public_slug = :public_slug';

        if ($ignoreId > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        return db_one($sql, $params) !== null;
    };

    $resolveCreateSlug = static function (string $requestedSlug) use ($slugExists): string {
        $normalizedSlug = normalize_public_slug($requestedSlug);

        if ($normalizedSlug === '') {
            do {
                $normalizedSlug = generate_public_slug(6);
            } while ($slugExists($normalizedSlug));

            return $normalizedSlug;
        }

        if (!public_slug_is_valid($normalizedSlug)) {
            throw new RuntimeException('Use um link curto com 3 a 24 caracteres, letras minúsculas, números, "-" ou "_".');
        }

        if ($slugExists($normalizedSlug)) {
            throw new RuntimeException('Este link curto já está em uso nos padrinhos. Escolha outro.');
        }

        return $normalizedSlug;
    };

    $resolveUpdateSlug = static function (string $requestedSlug, string $currentSlug, int $inviteId) use ($slugExists): string {
        $trimmedSlug = trim($requestedSlug);

        if ($trimmedSlug === '') {
            return $currentSlug;
        }

        $normalizedSlug = normalize_public_slug($trimmedSlug);

        if (!public_slug_is_valid($normalizedSlug)) {
            throw new RuntimeException('Use um link curto com 3 a 24 caracteres, letras minúsculas, números, "-" ou "_".');
        }

        if ($slugExists($normalizedSlug, $inviteId)) {
            throw new RuntimeException('Este link curto já está em uso nos padrinhos. Escolha outro.');
        }

        return $normalizedSlug;
    };

    $findInvite = static function (int $inviteId): ?array {
        return db_one('SELECT * FROM convites_padrinhos WHERE id = :id LIMIT 1', ['id' => $inviteId]);
    };

    $inviteGuestCount = static function (int $inviteId, int $ignoreGuestId = 0): int {
        $sql = 'SELECT COUNT(*) FROM convidados WHERE manual_invite_id = :manual_invite_id AND deleted_at IS NULL';
        $params = ['manual_invite_id' => $inviteId];

        if ($ignoreGuestId > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreGuestId;
        }

        return (int) db_value($sql, $params);
    };

    if (is_post()) {
        verify_csrf();

        $action = (string) ($_POST['action'] ?? '');

        if (in_array($action, ['create', 'update'], true)) {
            $inviteId = (int) ($_POST['id'] ?? 0);
            $nomeGrupo = trim((string) ($_POST['nome_grupo'] ?? ''));
            $publicSlug = $action === 'create'
                ? $resolveCreateSlug((string) ($_POST['public_slug'] ?? ''))
                : '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $observacoes = trim((string) ($_POST['observacoes'] ?? ''));
            $sharedFields = [
                'manual_question_text' => trim((string) ($_POST['manual_question_text'] ?? '')),
                'manual_intro_title' => trim((string) ($_POST['manual_intro_title'] ?? '')),
                'manual_intro_line_1' => trim((string) ($_POST['manual_intro_line_1'] ?? '')),
                'manual_intro_line_2' => trim((string) ($_POST['manual_intro_line_2'] ?? '')),
                'manual_calendar_title' => trim((string) ($_POST['manual_calendar_title'] ?? '')),
                'manual_calendar_month_label' => trim((string) ($_POST['manual_calendar_month_label'] ?? '')),
                'manual_day_title' => trim((string) ($_POST['manual_day_title'] ?? '')),
                'manual_day_line_1' => trim((string) ($_POST['manual_day_line_1'] ?? '')),
                'manual_day_line_2' => trim((string) ($_POST['manual_day_line_2'] ?? '')),
                'manual_day_line_3' => trim((string) ($_POST['manual_day_line_3'] ?? '')),
                'manual_thanks_text' => trim((string) ($_POST['manual_thanks_text'] ?? '')),
            ];

            if ($nomeGrupo === '') {
                throw new RuntimeException('Informe um nome interno para o convite de padrinhos.');
            }

            if ($action === 'create') {
                $params = [
                    'nome_grupo' => $nomeGrupo,
                    'public_slug' => $publicSlug,
                    'is_active' => $isActive,
                    'observacoes' => $observacoes !== '' ? $observacoes : null,
                ];

                foreach ($sharedFields as $field => $value) {
                    $params[$field] = $value !== '' ? $value : null;
                }

                db_execute(
                    'INSERT INTO convites_padrinhos (
                        nome_grupo,
                        public_slug,
                        is_active,
                        manual_question_text,
                        manual_intro_title,
                        manual_intro_line_1,
                        manual_intro_line_2,
                        manual_calendar_title,
                        manual_calendar_month_label,
                        manual_day_title,
                        manual_day_line_1,
                        manual_day_line_2,
                        manual_day_line_3,
                        manual_thanks_text,
                        observacoes
                    ) VALUES (
                        :nome_grupo,
                        :public_slug,
                        :is_active,
                        :manual_question_text,
                        :manual_intro_title,
                        :manual_intro_line_1,
                        :manual_intro_line_2,
                        :manual_calendar_title,
                        :manual_calendar_month_label,
                        :manual_day_title,
                        :manual_day_line_1,
                        :manual_day_line_2,
                        :manual_day_line_3,
                        :manual_thanks_text,
                        :observacoes
                    )',
                    $params
                );

                $inviteId = (int) db()->lastInsertId();
                audit_log('admin', 'create', 'manual_invite', $inviteId, null, null, ['nome_grupo' => $nomeGrupo]);
                flash('success', 'Convite de padrinhos criado com sucesso.');
                flash('info', 'Link novo: ' . url('/p/' . rawurlencode($publicSlug)));
                redirect('/admin/padrinhos?edit=' . $inviteId);
            }

            if ($inviteId <= 0) {
                throw new RuntimeException('Convite de padrinhos inválido.');
            }

            $currentInvite = db_one(
                'SELECT id, public_slug FROM convites_padrinhos WHERE id = :id LIMIT 1',
                ['id' => $inviteId]
            );

            if ($currentInvite === null) {
                throw new RuntimeException('Convite de padrinhos não encontrado.');
            }

            $currentSlug = normalize_public_slug((string) ($currentInvite['public_slug'] ?? ''));
            $publicSlug = $resolveUpdateSlug((string) ($_POST['public_slug'] ?? ''), $currentSlug, $inviteId);

            $params = [
                'id' => $inviteId,
                'nome_grupo' => $nomeGrupo,
                'public_slug' => $publicSlug,
                'is_active' => $isActive,
                'observacoes' => $observacoes !== '' ? $observacoes : null,
            ];

            foreach ($sharedFields as $field => $value) {
                $params[$field] = $value !== '' ? $value : null;
            }

            db_execute(
                'UPDATE convites_padrinhos
                 SET nome_grupo = :nome_grupo,
                     public_slug = :public_slug,
                     is_active = :is_active,
                     manual_question_text = :manual_question_text,
                     manual_intro_title = :manual_intro_title,
                     manual_intro_line_1 = :manual_intro_line_1,
                     manual_intro_line_2 = :manual_intro_line_2,
                     manual_calendar_title = :manual_calendar_title,
                     manual_calendar_month_label = :manual_calendar_month_label,
                     manual_day_title = :manual_day_title,
                     manual_day_line_1 = :manual_day_line_1,
                     manual_day_line_2 = :manual_day_line_2,
                     manual_day_line_3 = :manual_day_line_3,
                     manual_thanks_text = :manual_thanks_text,
                     observacoes = :observacoes
                 WHERE id = :id',
                $params
            );

            audit_log('admin', 'update', 'manual_invite', $inviteId, null, null);
            flash('success', 'Convite de padrinhos atualizado com sucesso.');

            if ($publicSlug !== $currentSlug) {
                flash('info', 'Novo link: ' . url('/p/' . rawurlencode($publicSlug)));
            }

            redirect('/admin/padrinhos');
        }

        if ($action === 'delete') {
            $inviteId = (int) ($_POST['id'] ?? 0);

            if ($inviteId <= 0) {
                throw new RuntimeException('Convite de padrinhos inválido.');
            }

            $linkedGuests = (int) db_value(
                'SELECT COUNT(*)
                 FROM convidados
                 WHERE manual_invite_id = :manual_invite_id
                   AND deleted_at IS NULL',
                ['manual_invite_id' => $inviteId]
            );

            if ($linkedGuests > 0) {
                throw new RuntimeException('Desvincule os convidados deste convite antes de excluir.');
            }

            db_execute('DELETE FROM convites_padrinhos WHERE id = :id', ['id' => $inviteId]);
            audit_log('admin', 'delete', 'manual_invite', $inviteId, null, null);
            flash('success', 'Convite de padrinhos excluído.');
            redirect('/admin/padrinhos');
        }

        if ($action === 'attach_guest') {
            $inviteId = (int) ($_POST['invite_id'] ?? 0);
            $guestId = (int) ($_POST['guest_id'] ?? 0);
            $manualRoleTitle = trim((string) ($_POST['manual_role_title'] ?? ''));
            $manualRoleText = trim((string) ($_POST['manual_role_text'] ?? ''));
            $manualSortOrder = max(1, (int) ($_POST['manual_sort_order'] ?? 1));

            if ($inviteId <= 0 || $guestId <= 0) {
                throw new RuntimeException('Escolha o convite e o convidado que vão entrar neste link.');
            }

            if ($manualRoleTitle === '') {
                throw new RuntimeException('Informe o título do card para este participante.');
            }

            $invite = $findInvite($inviteId);

            if ($invite === null) {
                throw new RuntimeException('Convite de padrinhos não encontrado.');
            }

            $guest = db_one(
                'SELECT id, nome, sobrenome, manual_invite_id
                 FROM convidados
                 WHERE id = :id
                   AND deleted_at IS NULL
                 LIMIT 1',
                ['id' => $guestId]
            );

            if ($guest === null) {
                throw new RuntimeException('Convidado não encontrado para este convite.');
            }

            if ((int) ($guest['manual_invite_id'] ?? 0) !== $inviteId && $inviteGuestCount($inviteId, $guestId) >= 2) {
                throw new RuntimeException('Cada convite de padrinhos pode ter no máximo duas pessoas no mesmo link.');
            }

            if ($manualRoleText === '') {
                $manualRoleText = manual_default_role_text_for_title($manualRoleTitle, trim((string) ($guest['nome'] ?? '')));
            }

            db_execute(
                'UPDATE convidados
                 SET manual_invite_id = :manual_invite_id,
                     manual_role_title = :manual_role_title,
                     manual_role_text = :manual_role_text,
                     manual_sort_order = :manual_sort_order
                 WHERE id = :id',
                [
                    'manual_invite_id' => $inviteId,
                    'manual_role_title' => $manualRoleTitle,
                    'manual_role_text' => $manualRoleText,
                    'manual_sort_order' => $manualSortOrder,
                    'id' => $guestId,
                ]
            );

            audit_log('admin', 'update', 'manual_invite', $inviteId, null, $guestId);
            flash('success', 'Participante vinculado ao convite de padrinhos.');
            redirect('/admin/padrinhos?edit=' . $inviteId);
        }

        if ($action === 'update_guest_card') {
            $inviteId = (int) ($_POST['invite_id'] ?? 0);
            $guestId = (int) ($_POST['guest_id'] ?? 0);
            $manualRoleTitle = trim((string) ($_POST['manual_role_title'] ?? ''));
            $manualRoleText = trim((string) ($_POST['manual_role_text'] ?? ''));
            $manualSortOrder = max(1, (int) ($_POST['manual_sort_order'] ?? 1));

            if ($inviteId <= 0 || $guestId <= 0) {
                throw new RuntimeException('Participante inválido para atualização.');
            }

            if ($manualRoleTitle === '') {
                throw new RuntimeException('Informe o título do card para este participante.');
            }

            $guest = db_one(
                'SELECT id, nome, manual_invite_id
                 FROM convidados
                 WHERE id = :id
                   AND deleted_at IS NULL
                 LIMIT 1',
                ['id' => $guestId]
            );

            if ($guest === null || (int) ($guest['manual_invite_id'] ?? 0) !== $inviteId) {
                throw new RuntimeException('Este participante não pertence mais a este convite.');
            }

            if ($manualRoleText === '') {
                $manualRoleText = manual_default_role_text_for_title($manualRoleTitle, trim((string) ($guest['nome'] ?? '')));
            }

            db_execute(
                'UPDATE convidados
                 SET manual_role_title = :manual_role_title,
                     manual_role_text = :manual_role_text,
                     manual_sort_order = :manual_sort_order
                 WHERE id = :id',
                [
                    'manual_role_title' => $manualRoleTitle,
                    'manual_role_text' => $manualRoleText,
                    'manual_sort_order' => $manualSortOrder,
                    'id' => $guestId,
                ]
            );

            audit_log('admin', 'update', 'manual_invite', $inviteId, null, $guestId);
            flash('success', 'Card do participante atualizado.');
            redirect('/admin/padrinhos?edit=' . $inviteId);
        }

        if ($action === 'unlink_guest') {
            $inviteId = (int) ($_POST['invite_id'] ?? 0);
            $guestId = (int) ($_POST['guest_id'] ?? 0);

            if ($inviteId <= 0 || $guestId <= 0) {
                throw new RuntimeException('Participante inválido para desvincular.');
            }

            $guest = db_one(
                'SELECT id, manual_invite_id
                 FROM convidados
                 WHERE id = :id
                   AND deleted_at IS NULL
                 LIMIT 1',
                ['id' => $guestId]
            );

            if ($guest === null || (int) ($guest['manual_invite_id'] ?? 0) !== $inviteId) {
                throw new RuntimeException('Este participante não está vinculado a este convite.');
            }

            db_execute(
                'UPDATE convidados
                 SET manual_invite_id = NULL,
                     manual_role_title = NULL,
                     manual_role_text = NULL,
                     manual_sort_order = 1
                 WHERE id = :id',
                ['id' => $guestId]
            );

            audit_log('admin', 'update', 'manual_invite', $inviteId, null, $guestId);
            flash('success', 'Participante removido deste convite de padrinhos.');
            redirect('/admin/padrinhos?edit=' . $inviteId);
        }
    }

    $manualInvites = db_all(
        "SELECT
            mi.*,
            COUNT(c.id) AS total_convidados,
            GROUP_CONCAT(TRIM(CONCAT(c.nome, ' ', COALESCE(c.sobrenome, ''))) ORDER BY c.manual_sort_order ASC, c.id ASC SEPARATOR ' | ') AS participantes
         FROM convites_padrinhos mi
         LEFT JOIN convidados c
           ON c.manual_invite_id = mi.id
          AND c.deleted_at IS NULL
         GROUP BY mi.id
         ORDER BY mi.nome_grupo ASC, mi.id ASC"
    );

    $editingId = (int) ($_GET['edit'] ?? 0);
    $editingInvite = $editingId > 0
        ? db_one('SELECT * FROM convites_padrinhos WHERE id = :id LIMIT 1', ['id' => $editingId])
        : null;
    $linkedGuests = $editingInvite !== null
        ? db_all(
            "SELECT
                c.id,
                c.nome,
                c.sobrenome,
                c.telefone,
                c.email,
                c.manual_role_title,
                c.manual_role_text,
                c.manual_sort_order,
                f.nome_grupo AS familia_nome
             FROM convidados c
             INNER JOIN familias f ON f.id = c.familia_id
             WHERE c.manual_invite_id = :manual_invite_id
               AND c.deleted_at IS NULL
             ORDER BY c.manual_sort_order ASC, c.id ASC",
            ['manual_invite_id' => (int) $editingInvite['id']]
        )
        : [];
    $availableGuests = $editingInvite !== null
        ? db_all(
            "SELECT
                c.id,
                c.nome,
                c.sobrenome,
                f.nome_grupo AS familia_nome,
                mi.nome_grupo AS invite_nome,
                mi.public_slug AS invite_slug
             FROM convidados c
             INNER JOIN familias f ON f.id = c.familia_id
             LEFT JOIN convites_padrinhos mi ON mi.id = c.manual_invite_id
             WHERE c.deleted_at IS NULL
               AND (c.manual_invite_id IS NULL OR c.manual_invite_id <> :manual_invite_id)
             ORDER BY c.nome ASC, c.sobrenome ASC",
            ['manual_invite_id' => (int) $editingInvite['id']]
        )
        : [];
    $manualRoleOptions = manual_role_title_options();
    $inviteFieldFallbacks = [
        'manual_question_text' => 'Vocês aceitam ser nossos padrinhos?',
        'manual_intro_title' => 'Queridos amigos',
        'manual_intro_line_1' => 'Vocês são pessoas muito especiais para nós e queremos que estejam presentes nesse momento tão importante.',
        'manual_intro_line_2' => 'Seria uma honra tê-los ao nosso lado como padrinhos nesse dia especial.',
        'manual_calendar_title' => 'Save the Date',
        'manual_calendar_month_label' => 'Agosto',
        'manual_day_title' => 'Para o Grande Dia',
        'manual_day_line_1' => 'Cheguem com meia hora de antecedência no local da cerimônia. Não podemos nos atrasar.',
        'manual_day_line_2' => 'Tirem muitas fotos e nos ajudem a eternizar este momento especial.',
        'manual_day_line_3' => 'Divirtam-se muito!',
        'manual_thanks_text' => 'Obrigado por fazerem parte deste momento importante!',
    ];
    $inviteFieldValues = [];
    $inviteFieldPreviewFallbacks = [];

    foreach ($inviteFieldFallbacks as $field => $fallbackValue) {
        $storedValue = trim((string) ($editingInvite[$field] ?? ''));

        if ($storedValue !== '') {
            $inviteFieldValues[$field] = $storedValue;
            continue;
        }

        if ($editingInvite !== null && $linkedGuests !== []) {
            $resolvedValue = manual_invite_text($editingInvite, $linkedGuests, $field);
            $inviteFieldValues[$field] = $resolvedValue !== '' ? $resolvedValue : $fallbackValue;
            continue;
        }

        $inviteFieldValues[$field] = $fallbackValue;
    }

    foreach ($inviteFieldFallbacks as $field => $fallbackValue) {
        if ($editingInvite !== null && $linkedGuests !== []) {
            $fallbackInvite = $editingInvite;
            $fallbackInvite[$field] = null;
            $resolvedFallback = manual_invite_text($fallbackInvite, $linkedGuests, $field);
            $inviteFieldPreviewFallbacks[$field] = $resolvedFallback !== '' ? $resolvedFallback : $fallbackValue;
            continue;
        }

        $inviteFieldPreviewFallbacks[$field] = $fallbackValue;
    }

    $previewGuests = $linkedGuests;

    if ($previewGuests === []) {
        $previewGuests = [
            [
                'nome' => 'Nome',
                'sobrenome' => 'da Madrinha',
                'manual_role_title' => 'Madrinha',
            ],
            [
                'nome' => 'Nome',
                'sobrenome' => 'do Padrinho',
                'manual_role_title' => 'Padrinho',
            ],
        ];
    }

    $previewGuests = array_slice($previewGuests, 0, 2);
    $previewIsPlural = manual_group_is_plural($previewGuests);
    $previewCoverLines = manual_cover_lines($previewGuests);
    $previewSupportRecommendations = manual_support_recommendations($previewGuests);
    $previewRecommendationCatalog = manual_support_recommendations([]);
    $previewRoles = [];

    foreach ($previewGuests as $index => $previewGuest) {
        $previewRoleTitle = manual_guest_role_title($previewGuest, $index);
        $previewRoleText = trim((string) ($previewGuest['manual_role_text'] ?? ''));

        if ($previewRoleText === '') {
            $previewRoleText = manual_default_role_text_for_title(
                $previewRoleTitle,
                trim((string) ($previewGuest['nome'] ?? ''))
            );
        }

        $previewRoles[] = [
            'name' => normalized_full_name($previewGuest),
            'title' => $previewRoleTitle,
            'text' => $previewRoleText,
            'swatches' => manual_role_swatches($previewRoleTitle),
        ];
    }

    $previewDocumentHtml = (static function () use (
        $previewIsPlural,
        $previewCoverLines,
        $previewRoles,
        $previewSupportRecommendations,
        $inviteFieldValues,
        $inviteFieldPreviewFallbacks
    ): string {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prévia do convite</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700&family=Oswald:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('/assets/css/styles.css')) ?>">
  <style>
    html {
      scrollbar-width: none;
      -ms-overflow-style: none;
    }

    body {
      scrollbar-width: none;
      -ms-overflow-style: none;
    }

    body::-webkit-scrollbar {
      width: 0;
      height: 0;
      display: none;
    }
  </style>
</head>
<body class="manual-body">
  <main class="private-manual">
    <div class="manual-container">
      <div class="manual-stack">
        <?php manual_render_page_open('manual-page--cover', 'manual-page-content manual-page-content--wide'); ?>
          <div class="manual-cover-title<?= !$previewIsPlural ? ' manual-cover-title--single' : '' ?>" data-preview-cover-title>
            <span data-preview-cover-line="0"><?= e($previewCoverLines[0] ?? 'Convite') ?></span>
            <span class="manual-ampersand" data-preview-cover-ampersand <?= $previewIsPlural ? '' : 'hidden' ?>>&amp;</span>
            <span data-preview-cover-line="1" <?= isset($previewCoverLines[1]) ? '' : 'hidden' ?>><?= e($previewCoverLines[1] ?? '') ?></span>
          </div>
          <div class="manual-rule"></div>
          <h1
            class="manual-question"
            data-preview-question
            data-preview-fallback="<?= e($inviteFieldPreviewFallbacks['manual_question_text']) ?>"
          ><?= e($inviteFieldValues['manual_question_text']) ?></h1>
        <?php manual_render_page_close(); ?>

        <?php manual_render_page_open('', 'manual-page-content manual-page-content--wide'); ?>
          <h2 class="manual-title" data-preview-intro-title data-preview-fallback="<?= e($inviteFieldPreviewFallbacks['manual_intro_title']) ?>"><?= e($inviteFieldValues['manual_intro_title']) ?></h2>
          <p class="manual-copy manual-copy--wide" data-preview-intro-line-1 data-preview-fallback="<?= e($inviteFieldPreviewFallbacks['manual_intro_line_1']) ?>"><?= e($inviteFieldValues['manual_intro_line_1']) ?></p>
          <p class="manual-copy manual-copy--wide" data-preview-intro-line-2 data-preview-fallback="<?= e($inviteFieldPreviewFallbacks['manual_intro_line_2']) ?>"><?= e($inviteFieldValues['manual_intro_line_2']) ?></p>
        <?php manual_render_page_close(); ?>

        <?php manual_render_page_open(); ?>
          <h2 class="manual-title" data-preview-calendar-title data-preview-fallback="<?= e($inviteFieldPreviewFallbacks['manual_calendar_title']) ?>"><?= e($inviteFieldValues['manual_calendar_title']) ?></h2>
          <p class="manual-month" data-preview-calendar-month data-preview-fallback="<?= e($inviteFieldPreviewFallbacks['manual_calendar_month_label']) ?>"><?= e($inviteFieldValues['manual_calendar_month_label']) ?></p>
          <div class="manual-calendar">
            <div class="manual-calendar-grid">
              <span class="manual-weekday">Dom</span>
              <span class="manual-weekday">Seg</span>
              <span class="manual-weekday">Ter</span>
              <span class="manual-weekday">Qua</span>
              <span class="manual-weekday">Qui</span>
              <span class="manual-weekday">Sex</span>
              <span class="manual-weekday">Sab</span>
              <span class="manual-day is-empty">.</span>
              <span class="manual-day is-empty">.</span>
              <span class="manual-day is-empty">.</span>
              <span class="manual-day is-empty">.</span>
              <span class="manual-day is-empty">.</span>
              <span class="manual-day is-empty">.</span>
              <span class="manual-day">1</span>
              <span class="manual-day">2</span>
              <span class="manual-day">3</span>
              <span class="manual-day">4</span>
              <span class="manual-day">5</span>
              <span class="manual-day">6</span>
              <span class="manual-day">7</span>
              <span class="manual-day">8</span>
              <span class="manual-day is-active">9</span>
              <span class="manual-day">10</span>
              <span class="manual-day">11</span>
              <span class="manual-day">12</span>
              <span class="manual-day">13</span>
              <span class="manual-day">14</span>
              <span class="manual-day">15</span>
              <span class="manual-day">16</span>
              <span class="manual-day">17</span>
              <span class="manual-day">18</span>
              <span class="manual-day">19</span>
              <span class="manual-day">20</span>
              <span class="manual-day">21</span>
              <span class="manual-day">22</span>
              <span class="manual-day">23</span>
              <span class="manual-day">24</span>
              <span class="manual-day">25</span>
              <span class="manual-day">26</span>
              <span class="manual-day">27</span>
              <span class="manual-day">28</span>
              <span class="manual-day">29</span>
              <span class="manual-day">30</span>
              <span class="manual-day">31</span>
            </div>
          </div>
        <?php manual_render_page_close(); ?>

        <?php foreach ($previewRoles as $previewIndex => $previewRole): ?>
          <?php manual_render_page_open(); ?>
            <div class="manual-role-card" data-preview-role-index="<?= e((string) $previewIndex) ?>">
              <span hidden data-preview-role-name><?= e($previewRole['name']) ?></span>
              <h3 data-preview-role-title><?= e($previewRole['title']) ?></h3>
              <p class="manual-copy manual-copy--small" data-preview-role-text><?= e($previewRole['text']) ?></p>
              <div class="manual-swatches" data-preview-role-swatches>
                <?php foreach ($previewRole['swatches'] as $swatchClass): ?>
                  <span class="manual-swatch <?= e($swatchClass) ?>"></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php manual_render_page_close(); ?>
        <?php endforeach; ?>

        <?php manual_render_page_open('', 'manual-page-content manual-page-content--wide'); ?>
          <h2 class="manual-title"><?= e(manual_support_title()) ?></h2>
          <div class="manual-recommendations" data-preview-recommendations>
            <?php foreach ($previewSupportRecommendations as $recommendation): ?>
              <article class="manual-recommendation-card">
                <p class="manual-recommendation-kicker"><?= e($recommendation['label']) ?></p>
                <h3><?= e($recommendation['name']) ?></h3>
                <div class="manual-recommendation-links">
                  <a class="manual-recommendation-link" href="<?= e($recommendation['phone_href']) ?>"><?= e($recommendation['phone']) ?></a>
                  <a class="manual-recommendation-link" href="<?= e($recommendation['url']) ?>" target="_blank" rel="noreferrer">Instagram</a>
                </div>
                <?php if (trim((string) ($recommendation['note'] ?? '')) !== ''): ?>
                  <p class="manual-recommendation-note"><?= e($recommendation['note']) ?></p>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php manual_render_page_close(); ?>

        <?php manual_render_page_open('', 'manual-page-content manual-page-content--wide'); ?>
          <h2 class="manual-title" data-preview-day-title data-preview-fallback="<?= e($inviteFieldPreviewFallbacks['manual_day_title']) ?>"><?= e($inviteFieldValues['manual_day_title']) ?></h2>
          <p class="manual-copy manual-copy--wide" data-preview-day-line-1 data-preview-fallback="<?= e($inviteFieldPreviewFallbacks['manual_day_line_1']) ?>"><?= e($inviteFieldValues['manual_day_line_1']) ?></p>
          <p class="manual-copy manual-copy--wide" data-preview-day-line-2 data-preview-fallback="<?= e($inviteFieldPreviewFallbacks['manual_day_line_2']) ?>"><?= e($inviteFieldValues['manual_day_line_2']) ?></p>
          <p class="manual-copy" data-preview-day-line-3 data-preview-fallback="<?= e($inviteFieldPreviewFallbacks['manual_day_line_3']) ?>"><?= e($inviteFieldValues['manual_day_line_3']) ?></p>
        <?php manual_render_page_close(); ?>

        <?php manual_render_page_open('manual-page--closing'); ?>
          <h2 class="manual-thanks" data-preview-thanks data-preview-fallback="<?= e($inviteFieldPreviewFallbacks['manual_thanks_text']) ?>"><?= e($inviteFieldValues['manual_thanks_text']) ?></h2>
        <?php manual_render_page_close(); ?>
      </div>
    </div>
  </main>
</body>
</html>
        <?php

        return trim((string) ob_get_clean());
    })();

    $metrics = [
        'convites' => count($manualInvites),
        'ativos' => 0,
        'individuais' => 0,
        'casais' => 0,
    ];

    foreach ($manualInvites as $inviteRow) {
        if ((int) ($inviteRow['is_active'] ?? 0) === 1) {
            $metrics['ativos']++;
        }

        $guestCount = (int) ($inviteRow['total_convidados'] ?? 0);

        if ($guestCount === 1) {
            $metrics['individuais']++;
        }

        if ($guestCount === 2) {
            $metrics['casais']++;
        }
    }
} catch (Throwable $exception) {
    admin_render_runtime_error('Padrinhos', $exception->getMessage());
}

admin_render_header('Padrinhos');
admin_render_flashes();
?>

<section class="admin-two-col admin-two-col--padrinhos-top">
  <article class="admin-panel admin-panel--sticky">
    <p class="eyebrow"><?= $editingInvite !== null ? 'Editar convite' : 'Novo convite' ?></p>
    <form method="post" class="admin-form-grid">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="<?= $editingInvite !== null ? 'update' : 'create' ?>">
      <?php if ($editingInvite !== null): ?>
        <input type="hidden" name="id" value="<?= e((int) $editingInvite['id']) ?>">
      <?php endif; ?>

      <div class="admin-field">
        <label for="nome_grupo">Nome interno do convite</label>
        <input
          id="nome_grupo"
          name="nome_grupo"
          type="text"
          required
          value="<?= e($editingInvite['nome_grupo'] ?? '') ?>"
          placeholder="Ex.: Amanda & Felipe"
        >
      </div>

      <div class="admin-field">
        <label for="public_slug">Link curto</label>
        <input
          id="public_slug"
          name="public_slug"
          type="text"
          value="<?= e($editingInvite['public_slug'] ?? '') ?>"
          placeholder="Ex.: amanda-felipe"
          autocapitalize="off"
          spellcheck="false"
        >
      </div>

      <div class="admin-check-row">
        <input id="is_active" name="is_active" type="checkbox" value="1" <?= (int) ($editingInvite['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
        <label class="admin-check" for="is_active">Convite de padrinhos ativo</label>
      </div>

      <details class="admin-disclosure" open>
        <summary>Capa e abertura</summary>
        <div class="admin-disclosure__body admin-form-grid">
          <div class="admin-field">
            <label for="manual_question_text">Pergunta da capa</label>
            <input id="manual_question_text" name="manual_question_text" type="text" value="<?= e($inviteFieldValues['manual_question_text']) ?>" data-preview-source="question">
          </div>

          <div class="admin-field">
            <label for="manual_intro_title">Título do card 1</label>
            <input id="manual_intro_title" name="manual_intro_title" type="text" value="<?= e($inviteFieldValues['manual_intro_title']) ?>" data-preview-source="intro_title">
          </div>

          <div class="admin-field">
            <label for="manual_intro_line_1">Texto 1 do card 1</label>
            <textarea id="manual_intro_line_1" name="manual_intro_line_1" rows="3" data-preview-source="intro_line_1"><?= e($inviteFieldValues['manual_intro_line_1']) ?></textarea>
          </div>

          <div class="admin-field">
            <label for="manual_intro_line_2">Texto 2 do card 1</label>
            <textarea id="manual_intro_line_2" name="manual_intro_line_2" rows="3" data-preview-source="intro_line_2"><?= e($inviteFieldValues['manual_intro_line_2']) ?></textarea>
          </div>
        </div>
      </details>

      <details class="admin-disclosure">
        <summary>Calendário</summary>
        <div class="admin-disclosure__body admin-form-grid admin-form-grid--two">
          <div class="admin-field">
            <label for="manual_calendar_title">Título do card 2</label>
            <input id="manual_calendar_title" name="manual_calendar_title" type="text" value="<?= e($inviteFieldValues['manual_calendar_title']) ?>" data-preview-source="calendar_title">
          </div>
          <div class="admin-field">
            <label for="manual_calendar_month_label">Mês do card 2</label>
            <input id="manual_calendar_month_label" name="manual_calendar_month_label" type="text" value="<?= e($inviteFieldValues['manual_calendar_month_label']) ?>" data-preview-source="calendar_month">
          </div>
        </div>
      </details>

      <details class="admin-disclosure">
        <summary>Orientações finais</summary>
        <div class="admin-disclosure__body admin-form-grid">
          <div class="admin-form-grid admin-form-grid--two">
            <div class="admin-field">
              <label for="manual_day_title">Título do card final</label>
              <input id="manual_day_title" name="manual_day_title" type="text" value="<?= e($inviteFieldValues['manual_day_title']) ?>" data-preview-source="day_title">
            </div>
            <div class="admin-field">
              <label for="manual_thanks_text">Frase de encerramento</label>
              <input id="manual_thanks_text" name="manual_thanks_text" type="text" value="<?= e($inviteFieldValues['manual_thanks_text']) ?>" data-preview-source="thanks">
            </div>
          </div>

          <div class="admin-field">
            <label for="manual_day_line_1">Texto 1 do card final</label>
            <textarea id="manual_day_line_1" name="manual_day_line_1" rows="2" data-preview-source="day_line_1"><?= e($inviteFieldValues['manual_day_line_1']) ?></textarea>
          </div>

          <div class="admin-field">
            <label for="manual_day_line_2">Texto 2 do card final</label>
            <textarea id="manual_day_line_2" name="manual_day_line_2" rows="2" data-preview-source="day_line_2"><?= e($inviteFieldValues['manual_day_line_2']) ?></textarea>
          </div>

          <div class="admin-field">
            <label for="manual_day_line_3">Texto 3 do card final</label>
            <textarea id="manual_day_line_3" name="manual_day_line_3" rows="2" data-preview-source="day_line_3"><?= e($inviteFieldValues['manual_day_line_3']) ?></textarea>
          </div>
        </div>
      </details>

      <div class="admin-field">
        <label for="observacoes">Observações internas</label>
        <textarea id="observacoes" name="observacoes" rows="3" placeholder="Notas sobre este convite"><?= e($editingInvite['observacoes'] ?? '') ?></textarea>
      </div>

      <div class="admin-actions">
        <button class="admin-button" type="submit"><?= $editingInvite !== null ? 'Salvar alterações' : 'Cadastrar convite' ?></button>
        <?php if ($editingInvite !== null): ?>
          <a class="admin-button-secondary" href="<?= e(url('/admin/padrinhos')) ?>">Cancelar edição</a>
        <?php endif; ?>
      </div>
    </form>
  </article>

  <article class="admin-panel admin-panel--preview">
    <p class="eyebrow">Prévia</p>
    <div class="admin-manual-device">
      <div class="admin-manual-device__speaker" aria-hidden="true"></div>
      <div class="admin-manual-device__frame">
        <iframe
          class="admin-manual-device__screen"
          title="Prévia do convite de padrinhos"
          loading="eager"
          data-manual-preview-frame
          data-preview-recommendations='<?= e((string) json_encode($previewRecommendationCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
        ></iframe>
      </div>
    </div>
    <textarea class="admin-manual-device__srcdoc" hidden aria-hidden="true" data-manual-preview-srcdoc><?= e($previewDocumentHtml) ?></textarea>
  </article>
</section>

<?php if ($editingInvite !== null): ?>
  <section class="admin-two-col admin-two-col--padrinhos-bottom">
    <article class="admin-panel">
      <?php if (count($linkedGuests) >= 2): ?>
        <div class="admin-note">
          Este link já está com 2 participantes. Remova alguém antes de adicionar outra pessoa.
        </div>
      <?php else: ?>
        <form method="post" class="admin-form-grid">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="attach_guest">
          <input type="hidden" name="invite_id" value="<?= e((int) $editingInvite['id']) ?>">

          <div class="admin-field">
            <label for="guest_id">Convidado que entrará neste link</label>
            <select id="guest_id" name="guest_id" required>
              <option value="">Selecione</option>
              <?php foreach ($availableGuests as $availableGuest): ?>
                <option value="<?= e((int) $availableGuest['id']) ?>">
                  <?= e(normalized_full_name($availableGuest) . ' - ' . $availableGuest['familia_nome']) ?>
                  <?php if (trim((string) ($availableGuest['invite_slug'] ?? '')) !== ''): ?>
                    <?= e(' - hoje em /p/' . $availableGuest['invite_slug']) ?>
                  <?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small>Você pode puxar um convidado que esteja sem link de padrinhos ou mover de outro link para este.</small>
          </div>

          <div class="admin-form-grid admin-form-grid--two">
            <div class="admin-field">
              <label for="linked_manual_role_title">Título do card</label>
              <select id="linked_manual_role_title" name="manual_role_title" required data-manual-role-title-input>
                <option value="">Selecione</option>
                <?php foreach ($manualRoleOptions as $roleOption): ?>
                  <option value="<?= e($roleOption) ?>"><?= e($roleOption) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="admin-field">
              <label for="linked_manual_sort_order">Ordem no convite</label>
              <input id="linked_manual_sort_order" name="manual_sort_order" type="number" min="1" step="1" value="<?= e(count($linkedGuests) + 1) ?>">
            </div>
          </div>

          <div class="admin-field">
            <label for="linked_manual_role_text">Frase do card individual</label>
            <textarea
              id="linked_manual_role_text"
              name="manual_role_text"
              rows="3"
              placeholder="A frase sugerida aparece automaticamente ao escolher o título."
              data-manual-role-text-input
            ></textarea>
            <small>Se você não mexer, a frase base sugerida será salva automaticamente.</small>
          </div>

          <div class="admin-actions">
            <button class="admin-button" type="submit">Adicionar ao link</button>
          </div>
        </form>
      <?php endif; ?>
    </article>

    <article class="admin-panel">
      <p class="eyebrow">Cards do link</p>
      <h2>Quem está neste convite</h2>
      <?php if ($linkedGuests === []): ?>
        <div class="admin-note">
          Este convite ainda não tem participantes vinculados. Adicione o primeiro padrinho ou madrinha ao lado.
        </div>
      <?php else: ?>
        <div class="admin-linked-stack">
          <?php foreach ($linkedGuests as $linkedIndex => $linkedGuest): ?>
            <?php
            $linkedRoleTitle = trim((string) ($linkedGuest['manual_role_title'] ?? ''));
            $linkedRoleText = trim((string) ($linkedGuest['manual_role_text'] ?? ''));

            if ($linkedRoleTitle === '') {
                $linkedRoleTitle = 'Convidado';
            }

            if ($linkedRoleText === '') {
                $linkedRoleText = manual_default_role_text_for_title($linkedRoleTitle, trim((string) ($linkedGuest['nome'] ?? '')));
            }
            ?>
            <article class="admin-linked-card">
              <div class="admin-linked-card__head">
                <div>
                  <strong><?= e(normalized_full_name($linkedGuest)) ?></strong>
                  <span><?= e($linkedGuest['familia_nome']) ?></span>
                </div>
                <span class="status-pill is-pendente"><?= e($linkedRoleTitle) ?></span>
              </div>

              <form method="post" class="admin-form-grid">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="update_guest_card">
                <input type="hidden" name="invite_id" value="<?= e((int) $editingInvite['id']) ?>">
                <input type="hidden" name="guest_id" value="<?= e((int) $linkedGuest['id']) ?>">

                <div class="admin-form-grid admin-form-grid--two">
                  <div class="admin-field">
                    <label for="guest_role_title_<?= e((int) $linkedGuest['id']) ?>">Título do card</label>
                    <select
                      id="guest_role_title_<?= e((int) $linkedGuest['id']) ?>"
                      name="manual_role_title"
                      required
                      data-preview-role-title-source="<?= e((string) $linkedIndex) ?>"
                    >
                      <?php foreach ($manualRoleOptions as $roleOption): ?>
                        <option value="<?= e($roleOption) ?>" <?= $linkedRoleTitle === $roleOption ? 'selected' : '' ?>>
                          <?= e($roleOption) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="admin-field">
                    <label for="guest_sort_order_<?= e((int) $linkedGuest['id']) ?>">Ordem</label>
                    <input id="guest_sort_order_<?= e((int) $linkedGuest['id']) ?>" name="manual_sort_order" type="number" min="1" step="1" value="<?= e((int) ($linkedGuest['manual_sort_order'] ?? 1)) ?>">
                  </div>
                </div>

                <div class="admin-field">
                  <label for="guest_role_text_<?= e((int) $linkedGuest['id']) ?>">Texto do card</label>
                  <textarea
                    id="guest_role_text_<?= e((int) $linkedGuest['id']) ?>"
                    name="manual_role_text"
                    rows="3"
                    data-preview-role-text-source="<?= e((string) $linkedIndex) ?>"
                  ><?= e($linkedRoleText) ?></textarea>
                </div>

                <div class="admin-actions">
                  <button class="admin-button-secondary" type="submit">Salvar card</button>
                </div>
              </form>

              <form method="post" class="inline-form">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="unlink_guest">
                <input type="hidden" name="invite_id" value="<?= e((int) $editingInvite['id']) ?>">
                <input type="hidden" name="guest_id" value="<?= e((int) $linkedGuest['id']) ?>">
                <button class="admin-button-danger" type="submit">Remover deste link</button>
              </form>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </article>
  </section>
<?php endif; ?>

<section class="admin-panel">
  <p class="eyebrow">Lista</p>
  <h2>Convites de padrinhos cadastrados</h2>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Convite</th>
          <th>Link</th>
          <th>Participantes</th>
          <th>Status</th>
          <th>Atualizado</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($manualInvites === []): ?>
          <tr>
            <td colspan="6">Nenhum convite de padrinhos cadastrado ainda.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($manualInvites as $inviteRow): ?>
            <?php $inviteUrl = manual_invite_url_from_row($inviteRow); ?>
            <?php $guestCount = (int) ($inviteRow['total_convidados'] ?? 0); ?>
            <tr>
              <td>
                <strong><?= e($inviteRow['nome_grupo']) ?></strong><br>
                <small><?= e($inviteRow['observacoes'] ?? '') ?></small>
              </td>
              <td>
                <strong><?= e($inviteRow['public_slug']) ?></strong><br>
                <small><?= e('/p/' . $inviteRow['public_slug']) ?></small>
              </td>
              <td>
                <?= e($guestCount) ?> pessoa(s)<br>
                <small>
                  <?= e($guestCount === 2 ? 'Casal' : ($guestCount === 1 ? 'Individual' : 'Sem convidados')) ?>
                  <?php if (trim((string) ($inviteRow['participantes'] ?? '')) !== ''): ?>
                    <?= e(' - ' . $inviteRow['participantes']) ?>
                  <?php endif; ?>
                </small>
              </td>
              <td>
                <span class="status-pill <?= (int) ($inviteRow['is_active'] ?? 0) === 1 ? 'is-confirmado' : 'is-nao_ira' ?>">
                  <?= (int) ($inviteRow['is_active'] ?? 0) === 1 ? 'ativo' : 'inativo' ?>
                </span>
              </td>
              <td><?= e(format_datetime($inviteRow['updated_at'])) ?></td>
              <td>
                <div class="inline-form">
                  <a class="admin-button-secondary" href="<?= e(url('/admin/padrinhos?edit=' . (int) $inviteRow['id'])) ?>">Editar</a>
                  <?php if ($inviteUrl !== null): ?>
                    <button
                      class="admin-button-secondary js-copy-text"
                      type="button"
                      data-copy-text="<?= e($inviteUrl) ?>"
                      data-copy-success="Link copiado"
                      data-copy-error="Falhou"
                    >
                      Copiar link
                    </button>
                  <?php endif; ?>
                  <form method="post" class="inline-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= e((int) $inviteRow['id']) ?>">
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
<?php
admin_render_footer();
