<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$invite = null;
$guestRows = [];
$pageError = null;

send_noindex_headers();

try {
    if ($slug === '' || !preg_match('/^[A-Za-z0-9_-]{3,128}$/', $slug)) {
        throw new RuntimeException('Este convite individual não está disponível.');
    }

    if (!manual_supports_schema()) {
        throw new RuntimeException('manual_schema_missing');
    }

    $normalizedSlug = normalize_public_slug($slug);

    $invite = db_one(
        'SELECT
            id,
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
            manual_thanks_text
         FROM convites_padrinhos
         WHERE is_active = 1
           AND public_slug = :public_slug
         LIMIT 1',
        [
            'public_slug' => $normalizedSlug,
        ]
    );

    if ($invite === null) {
        throw new RuntimeException('manual_invite_missing');
    }

    $guestRows = db_all(
        'SELECT
            id,
            nome,
            sobrenome,
            manual_role_title,
            manual_role_text,
            manual_sort_order
         FROM convidados
         WHERE manual_invite_id = :manual_invite_id
           AND deleted_at IS NULL
         ORDER BY manual_sort_order ASC, id ASC',
        ['manual_invite_id' => (int) $invite['id']]
    );

    $guestRows = array_slice($guestRows, 0, 2);

    if ($guestRows === []) {
        throw new RuntimeException('Este convite ainda não possui convidados configurados.');
    }
} catch (Throwable $exception) {
    $pageError = $exception->getMessage() === 'manual_schema_missing'
        ? 'O convite individual ainda não foi configurado nesta hospedagem.'
        : 'Este convite individual não está disponível no momento.';
}

$isPlural = manual_group_is_plural($guestRows);
$coverLines = $guestRows !== [] ? manual_cover_lines($guestRows) : [];
$pageTitle = ($guestRows !== [] ? manual_page_title($guestRows) : 'Convite') . ' | Convite';
$roleCards = [];
$supportRecommendations = manual_support_recommendations($guestRows);

foreach ($guestRows as $index => $guestRow) {
    $roleTitle = manual_guest_role_title($guestRow, $index);

    $roleCards[] = [
        'title' => $roleTitle,
        'text' => manual_guest_role_text($guestRow, $index),
        'swatches' => manual_role_swatches($roleTitle),
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($pageTitle) ?></title>
  <link rel="icon" type="image/png" href="<?= e(url('/favicon.png')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700&family=Oswald:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('/assets/css/styles.css')) ?>">
</head>
<body class="manual-body">
  <main class="private-manual">
    <div class="manual-container">
      <div class="manual-stack">
        <?php if ($pageError !== null): ?>
          <?php manual_render_page_open('manual-page--cover', 'manual-page-content manual-page-content--wide'); ?>
            <h1 class="manual-question"><?= e($pageError) ?></h1>
          <?php manual_render_page_close(); ?>
        <?php else: ?>
          <?php manual_render_page_open('manual-page--cover', 'manual-page-content manual-page-content--wide'); ?>
            <div class="manual-cover-title<?= !$isPlural ? ' manual-cover-title--single' : '' ?>">
              <?php if ($isPlural): ?>
                <span><?= e($coverLines[0] ?? '') ?></span>
                <span class="manual-ampersand">&amp;</span>
                <span><?= e($coverLines[1] ?? '') ?></span>
              <?php else: ?>
                <?php foreach ($coverLines as $coverLine): ?>
                  <span><?= e($coverLine) ?></span>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="manual-rule"></div>
            <h1 class="manual-question"><?= e(manual_invite_text($invite, $guestRows, 'manual_question_text')) ?></h1>
          <?php manual_render_page_close(); ?>

          <?php manual_render_page_open('', 'manual-page-content manual-page-content--wide'); ?>
            <h2 class="manual-title"><?= e(manual_invite_text($invite, $guestRows, 'manual_intro_title')) ?></h2>
            <p class="manual-copy manual-copy--wide"><?= e(manual_invite_text($invite, $guestRows, 'manual_intro_line_1')) ?></p>
            <p class="manual-copy manual-copy--wide"><?= e(manual_invite_text($invite, $guestRows, 'manual_intro_line_2')) ?></p>
          <?php manual_render_page_close(); ?>

          <?php manual_render_page_open(); ?>
            <h2 class="manual-title"><?= e(manual_invite_text($invite, $guestRows, 'manual_calendar_title')) ?></h2>
            <p class="manual-month"><?= e(manual_invite_text($invite, $guestRows, 'manual_calendar_month_label')) ?></p>
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

          <?php foreach ($roleCards as $roleCard): ?>
            <?php manual_render_page_open(); ?>
              <div class="manual-role-card">
                <h3><?= e($roleCard['title']) ?></h3>
                <p class="manual-copy manual-copy--small"><?= e($roleCard['text']) ?></p>
                <div class="manual-swatches">
                  <?php foreach ($roleCard['swatches'] as $swatchClass): ?>
                    <span class="manual-swatch <?= e($swatchClass) ?>"></span>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php manual_render_page_close(); ?>
          <?php endforeach; ?>

          <?php manual_render_page_open('', 'manual-page-content manual-page-content--wide'); ?>
            <h2 class="manual-title"><?= e(manual_support_title()) ?></h2>
            <div class="manual-recommendations">
              <?php foreach ($supportRecommendations as $recommendation): ?>
                <article class="manual-recommendation-card">
                  <p class="manual-recommendation-kicker"><?= e($recommendation['label']) ?></p>
                  <h3><?= e($recommendation['name']) ?></h3>
                  <div class="manual-recommendation-links">
                    <a class="manual-recommendation-link" href="<?= e($recommendation['phone_href']) ?>">
                      <?= e($recommendation['phone']) ?>
                    </a>
                    <a class="manual-recommendation-link" href="<?= e($recommendation['url']) ?>" target="_blank" rel="noreferrer">
                      Instagram
                    </a>
                  </div>
                  <?php if (trim((string) ($recommendation['note'] ?? '')) !== ''): ?>
                    <p class="manual-recommendation-note"><?= e($recommendation['note']) ?></p>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php manual_render_page_close(); ?>

          <?php manual_render_page_open('', 'manual-page-content manual-page-content--wide'); ?>
            <h2 class="manual-title"><?= e(manual_invite_text($invite, $guestRows, 'manual_day_title')) ?></h2>
            <p class="manual-copy manual-copy--wide"><?= e(manual_invite_text($invite, $guestRows, 'manual_day_line_1')) ?></p>
            <p class="manual-copy manual-copy--wide"><?= e(manual_invite_text($invite, $guestRows, 'manual_day_line_2')) ?></p>
            <p class="manual-copy"><?= e(manual_invite_text($invite, $guestRows, 'manual_day_line_3')) ?></p>
          <?php manual_render_page_close(); ?>

          <?php manual_render_page_open('manual-page--closing'); ?>
            <h2 class="manual-thanks"><?= e(manual_invite_text($invite, $guestRows, 'manual_thanks_text')) ?></h2>
          <?php manual_render_page_close(); ?>
        <?php endif; ?>
      </div>
    </div>
  </main>
</body>
</html>
