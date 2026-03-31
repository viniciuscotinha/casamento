<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

function manual_render_botanical_svg(string $extraClass): void
{
    ?>
    <svg class="manual-botanical <?= e($extraClass) ?>" viewBox="0 0 220 240" aria-hidden="true">
      <path class="botanical-fill" d="M162 36c22 9 39 27 46 52c7 27 0 58-15 87c-10-22-23-36-41-49c-15-10-32-20-44-37c-13-19-18-44-15-68c23 4 47 6 69 15z"></path>
      <path class="botanical-fill" d="M66 166c15-10 26-24 34-41c8-18 14-38 31-52c9 23 8 48-2 72c-11 24-31 43-56 54c-8-10-11-21-7-33z"></path>
      <path class="botanical-line" d="M196 12c-20 32-38 67-50 105c-10 34-24 70-50 108"></path>
      <path class="botanical-line" d="M174 44c-19-1-37 5-49 20c17 4 34 12 48 27"></path>
      <path class="botanical-line" d="M162 90c-21 0-40 7-53 24c17 3 35 13 47 29"></path>
      <path class="botanical-line" d="M141 136c-18 3-33 13-42 30c14 0 29 6 40 18"></path>
      <path class="botanical-line" d="M110 186c-15 2-28 11-36 25c13-1 24 2 34 10"></path>
      <path class="botanical-line" d="M131 61c5-13 14-23 28-31c-2 17-10 30-24 39"></path>
      <path class="botanical-line" d="M117 108c7-15 18-26 34-35c-3 19-13 32-30 42"></path>
      <path class="botanical-line" d="M96 157c9-15 21-25 37-31c-4 18-15 31-32 38"></path>
      <path class="botanical-line" d="M74 204c9-13 21-21 35-25c-5 15-15 26-29 32"></path>
    </svg>
    <?php
}

function manual_render_page_open(string $pageClass = '', string $contentClass = 'manual-page-content'): void
{
    ?>
    <section class="manual-page<?= $pageClass !== '' ? ' ' . e($pageClass) : '' ?>">
      <span class="manual-curve manual-curve--top-left" aria-hidden="true"></span>
      <span class="manual-curve manual-curve--bottom-right" aria-hidden="true"></span>
      <span class="manual-dots manual-dots--top-right" aria-hidden="true"></span>
      <span class="manual-dots manual-dots--bottom-left" aria-hidden="true"></span>
      <?php manual_render_botanical_svg('manual-botanical--top-right'); ?>
      <?php manual_render_botanical_svg('manual-botanical--bottom-left'); ?>
      <div class="<?= e($contentClass) ?>">
    <?php
}

function manual_render_page_close(): void
{
    ?>
      </div>
    </section>
    <?php
}

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
