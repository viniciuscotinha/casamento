<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$identifier = trim((string) ($_GET['token'] ?? ''));
$family = null;
$guests = [];
$representative = null;
$pageError = null;

try {
    if ($identifier === '' || !preg_match('/^[A-Za-z0-9_-]{3,128}$/', $identifier)) {
        throw new RuntimeException('Este link de confirmação é inválido ou expirou.');
    }

    $supportsPublicSlug = db_column_exists('familias', 'public_slug');
    $familyColumns = $supportsPublicSlug
        ? 'id, nome_grupo, observacoes, public_slug'
        : 'id, nome_grupo, observacoes';

    if ($supportsPublicSlug) {
        $normalizedIdentifier = normalize_public_slug($identifier);

        if ($normalizedIdentifier !== '' && public_slug_is_valid($normalizedIdentifier)) {
            $family = db_one(
                "SELECT {$familyColumns}
                 FROM familias
                 WHERE public_slug = :public_slug
                   AND token_ativo = 1
                 LIMIT 1",
                ['public_slug' => $normalizedIdentifier]
            );
        }
    }

    if ($family === null) {
        $family = db_one(
            "SELECT {$familyColumns}
             FROM familias
             WHERE token_hash = :token_hash
               AND token_ativo = 1
             LIMIT 1",
            ['token_hash' => hash_public_token($identifier)]
        );
    }

    if ($family === null) {
        throw new RuntimeException('Este link de confirmação é inválido ou expirou.');
    }

    $publicIdentifier = family_public_identifier($family) ?? $identifier;

    $guests = db_all(
        "SELECT
            id,
            nome,
            sobrenome,
            telefone,
            is_responsavel,
            status,
            responded_at
         FROM convidados
         WHERE familia_id = :familia_id
           AND deleted_at IS NULL
         ORDER BY is_responsavel DESC, nome ASC, sobrenome ASC",
        ['familia_id' => (int) $family['id']]
    );

    foreach ($guests as $guest) {
        if ((int) $guest['is_responsavel'] === 1) {
            $representative = $guest;
            break;
        }
    }

    if (is_post()) {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'verify_gate') {
            $mode = public_gate_mode();
            $providedValue = trim((string) ($_POST['verification_value'] ?? ''));

            if ($mode === 'none') {
                mark_family_verified((int) $family['id']);
            } elseif ($representative === null) {
                throw new RuntimeException('A confirmação desta família ainda não foi configurada corretamente.');
            } elseif ($mode === 'phone_last4') {
                $expected = substr(preg_replace('/\D+/', '', (string) ($representative['telefone'] ?? '')), -4);
                $providedDigits = preg_replace('/\D+/', '', $providedValue);

                if ($expected === '' || $expected !== $providedDigits) {
                    throw new RuntimeException('Não foi possível validar o telefone do responsável.');
                }

                mark_family_verified((int) $family['id']);
            } elseif ($mode === 'surname') {
                $expected = function_exists('mb_strtolower')
                    ? mb_strtolower(trim((string) ($representative['sobrenome'] ?? '')))
                    : strtolower(trim((string) ($representative['sobrenome'] ?? '')));

                $providedSurname = function_exists('mb_strtolower')
                    ? mb_strtolower($providedValue)
                    : strtolower($providedValue);

                if ($expected === '' || $expected !== $providedSurname) {
                    throw new RuntimeException('Não foi possível validar o sobrenome do responsável.');
                }

                mark_family_verified((int) $family['id']);
            }

            flash('success', 'Validação concluída. Agora você já pode confirmar a família.');
            redirect('/c/' . $publicIdentifier);
        }

        if ($action === 'save_rsvp') {
            if (public_gate_mode() !== 'none' && !family_is_verified((int) $family['id'])) {
                throw new RuntimeException('Valide primeiro o acesso desta família antes de enviar a confirmação.');
            }

            $submittedStatuses = $_POST['status'] ?? [];

            if (!is_array($submittedStatuses) || $submittedStatuses === []) {
                throw new RuntimeException('Selecione ao menos um status para os convidados listados.');
            }

            db_transaction(function () use ($family, $guests, $submittedStatuses): void {
                foreach ($guests as $guest) {
                    $guestId = (int) $guest['id'];
                    $status = (string) ($submittedStatuses[$guestId] ?? $guest['status']);

                    if (!in_array($status, ['pendente', 'confirmado', 'nao_ira'], true)) {
                        $status = 'pendente';
                    }

                    db_execute(
                        'UPDATE convidados
                         SET status = :status,
                             responded_at = :responded_at,
                             updated_by_origin = :updated_by_origin
                         WHERE id = :id
                           AND familia_id = :familia_id',
                        [
                            'status' => $status,
                            'responded_at' => $status === 'pendente' ? null : date('Y-m-d H:i:s'),
                            'updated_by_origin' => 'public',
                            'id' => $guestId,
                            'familia_id' => (int) $family['id'],
                        ]
                    );
                }
            });

            audit_log('public', 'confirm_family', 'familia', (int) $family['id'], (int) $family['id']);
            flash('success', 'Confirmação registrada com sucesso. Obrigado por responder.');
            redirect('/c/' . $publicIdentifier);
        }
    }
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    $internalFailure = stripos($message, 'config/env.php') !== false
        || stripos($message, 'MySQL') !== false;

    $pageError = $internalFailure
        ? 'A confirmação está temporariamente indisponível. Tente novamente mais tarde.'
        : $message;
}

$pageTitle = 'Confirmação de Presença | ' . app_config('app_name', 'RSVP Casamento');
$isVerified = $family !== null && (public_gate_mode() === 'none' || family_is_verified((int) $family['id']));
$guestTotals = [
    'total' => count($guests),
    'confirmados' => 0,
    'nao_irao' => 0,
    'pendentes' => 0,
];

foreach ($guests as $guestRow) {
    if (($guestRow['status'] ?? 'pendente') === 'confirmado') {
        $guestTotals['confirmados']++;
    } elseif (($guestRow['status'] ?? 'pendente') === 'nao_ira') {
        $guestTotals['nao_irao']++;
    } else {
        $guestTotals['pendentes']++;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <link rel="icon" type="image/png" href="<?= e(url('/favicon.png')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Great+Vibes&family=Manrope:wght@400;500;600;700&family=Oswald:wght@300;400;500;600&display=swap"
    rel="stylesheet"
  >
  <link rel="stylesheet" href="<?= e(url('/assets/css/styles.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('/assets/css/rsvp.css')) ?>">
</head>
<body class="site-body rsvp-body">
  <div class="site-decor site-decor--page-top-left" aria-hidden="true">
    <span class="decor-svg decor-svg--page-cord-top-left"></span>
    <span class="decor-svg decor-svg--page-shadow-top-left"></span>
    <span class="decor-svg decor-svg--page-leaf-top-left"></span>
  </div>
  <div class="site-decor site-decor--page-bottom-right" aria-hidden="true">
    <span class="decor-svg decor-svg--page-cord-bottom-right"></span>
    <span class="decor-svg decor-svg--page-shadow-bottom-right"></span>
    <span class="decor-svg decor-svg--page-leaf-bottom-right"></span>
    <span class="decor-svg decor-svg--page-dust-bottom-right"></span>
  </div>
  <header class="site-header">
    <nav class="nav container">
      <a class="brand" href="/">Vinícius & Evelyn</a>
      <div class="nav-menu rsvp-nav-menu">
        <a href="/">Início</a>
      </div>
    </nav>
  </header>
  <main class="section invitation-shell">
    <div class="container paper-frame paper-frame--form rsvp-layout">
      <div class="frame-decor" aria-hidden="true">
        <span class="decor-svg decor-svg--frame-cord-top-left"></span>
        <span class="decor-svg decor-svg--frame-shadow-bottom-left"></span>
        <span class="decor-svg decor-svg--frame-leaf-bottom-left"></span>
        <span class="decor-svg decor-svg--frame-dust-bottom-left"></span>
        <span class="decor-svg decor-svg--frame-shadow-top-right"></span>
        <span class="decor-svg decor-svg--frame-leaf-top-right"></span>
        <span class="decor-svg decor-svg--frame-dust-top-right"></span>
        <span class="decor-svg decor-svg--frame-cord-bottom-right"></span>
      </div>

      <section class="rsvp-hero-block">
        <!-- <p class="eyebrow">RSVP</p> -->
        <p class="script-kicker">Confirme as<br>presenças</p>
        <!-- <h1>Vamos preparar cada lugar com carinho para esse dia.</h1>
        <p class="rsvp-lead">
          Este convite foi reservado para uma família específica. O responsável pode revisar os nomes abaixo
          e responder por todos diretamente do celular.
        </p> -->

        <!-- <div class="rsvp-glance-grid">
          <article class="rsvp-glance-card">
            <span class="rsvp-glance-label">Data</span>
            <strong>09 de agosto de 2026</strong>
            <p>Domingo, 16:00</p>
          </article>
          <article class="rsvp-glance-card">
            <span class="rsvp-glance-label">Local</span>
            <strong>Mansão Country Fest</strong>
            <p>Av. Gercina Borges Teixeira</p>
          </article>
          <article class="rsvp-glance-card">
            <span class="rsvp-glance-label">Resposta</span>
            <strong><?= e($guestTotals['total']) ?> nome(s)</strong>
            <p>Uma resposta por pessoa da família</p>
          </article>
        </div> -->
      </section>

      <section class="rsvp-content-block">
        <?php foreach (consume_flashes() as $flashMessage): ?>
          <div class="rsvp-banner rsvp-banner--<?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div>
        <?php endforeach; ?>

        <?php if ($pageError !== null): ?>
          <div class="rsvp-banner rsvp-banner--error"><?= e($pageError) ?></div>
        <?php elseif ($family === null): ?>
          <div class="rsvp-banner rsvp-banner--error">Este link não está disponível no momento.</div>
        <?php else: ?>
          <div class="rsvp-summary-card">
            <div class="rsvp-summary-head">
              <div>
                <p class="eyebrow">Família</p>
                <h2><?= e($family['nome_grupo']) ?></h2>
              </div>
              <?php if ($representative !== null): ?>
                <div class="rsvp-summary-person">
                  <span>Responsável</span>
                  <strong><?= e(normalized_full_name($representative)) ?></strong>
                </div>
              <?php endif; ?>
            </div>

            <!-- <div class="rsvp-summary-metrics">
              <article>
                <span>Total</span>
                <strong><?= e($guestTotals['total']) ?></strong>
              </article>
              <article>
                <span>Confirmados</span>
                <strong><?= e($guestTotals['confirmados']) ?></strong>
              </article>
              <article>
                <span>Não irão</span>
                <strong><?= e($guestTotals['nao_irao']) ?></strong>
              </article>
              <article>
                <span>Pendentes</span>
                <strong><?= e($guestTotals['pendentes']) ?></strong>
              </article>
            </div> -->
          </div>

          <?php if (!$isVerified): ?>
            <form method="post" class="rsvp-form rsvp-form--gate">
              <?= csrf_input() ?>
              <input type="hidden" name="action" value="verify_gate">
              <div class="rsvp-form-title">
                <p class="eyebrow">Validação</p>
                <h3>Confirme que este convite está com a família certa</h3>
              </div>
              <label>
                <?php if (public_gate_mode() === 'phone_last4'): ?>
                  Digite os 4 últimos números do telefone do responsável
                <?php else: ?>
                  Digite o sobrenome do responsável
                <?php endif; ?>
                <input type="text" name="verification_value" required>
              </label>
              <div class="rsvp-actions">
                <button class="button" type="submit">Validar acesso</button>
              </div>
            </form>
          <?php else: ?>
            <form method="post" class="rsvp-form rsvp-form--family">
              <?= csrf_input() ?>
              <input type="hidden" name="action" value="save_rsvp">
              <div class="rsvp-form-title">
                <p class="eyebrow">Convidados</p>
                <h3>Confirme para cada pessoa da família</h3>
              </div>
              <div class="rsvp-family-grid">
                <?php foreach ($guests as $guest): ?>
                  <article class="rsvp-family-card">
                    <div class="rsvp-family-card-head">
                      <div>
                        <h3><?= e(normalized_full_name($guest)) ?></h3>
                        <!-- <p><?= (int) $guest['is_responsavel'] === 1 ? 'Responsável da família' : 'Convidado vinculado' ?></p> -->
                      </div>
                      <!-- <span class="status-pill is-<?= e($guest['status']) ?>"><?= e($guest['status']) ?></span> -->
                    </div>
                    <!-- <p class="rsvp-response-date">Última resposta: <?= e(format_datetime($guest['responded_at'], 'Ainda não respondeu')) ?></p> -->

                    <div class="rsvp-status-options">
                      <?php
                      $selectedStatus = (string) ($guest['status'] ?? 'pendente');
                      $options = [
                          'confirmado' => 'Estarei presente',
                          'nao_ira' => 'Não poderei ir',
                          'pendente' => 'Ainda não sei',
                      ];
                      ?>
                      <?php foreach ($options as $value => $label): ?>
                        <label class="rsvp-status-option rsvp-status-option--<?= e($value) ?>">
                          <input
                            type="radio"
                            name="status[<?= e((int) $guest['id']) ?>]"
                            value="<?= e($value) ?>"
                            <?= $selectedStatus === $value ? 'checked' : '' ?>
                          >
                          <span><?= e($label) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
              <div class="rsvp-actions">
                <button class="button" type="submit">Salvar confirmação</button>
              </div>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    </div>
  </main>
  <footer class="site-footer">
    <div class="container footer-content">
      <p>Vinícius & Evelyn</p>
      <p>Confirmação de presença</p>
    </div>
  </footer>
</body>
</html>
