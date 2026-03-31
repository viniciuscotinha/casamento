<?php
declare(strict_types=1);

function manual_supports_schema(): bool
{
    return db_table_exists('convites_padrinhos')
        && db_column_exists('convites_padrinhos', 'public_slug')
        && db_column_exists('convites_padrinhos', 'is_active')
        && db_column_exists('convites_padrinhos', 'manual_question_text')
        && db_column_exists('convidados', 'manual_invite_id')
        && db_column_exists('convidados', 'manual_role_title')
        && db_column_exists('convidados', 'manual_role_text')
        && db_column_exists('convidados', 'manual_sort_order');
}

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

function manual_role_is_feminine(string $roleTitle): bool
{
    $roleTitle = function_exists('mb_strtolower')
        ? mb_strtolower(trim($roleTitle))
        : strtolower(trim($roleTitle));

    if ($roleTitle === '') {
        return false;
    }

    foreach (['madrinha', 'dama', 'florista'] as $needle) {
        if (str_contains($roleTitle, $needle)) {
            return true;
        }
    }

    return str_ends_with($roleTitle, 'a');
}

function manual_group_is_plural(array $guestRows): bool
{
    return count($guestRows) > 1;
}

function manual_guest_role_title(array $guestRow, int $index = 0): string
{
    $title = trim((string) ($guestRow['manual_role_title'] ?? ''));

    if ($title !== '') {
        return $title;
    }

    return $index === 0 ? 'Convidado' : 'Convidada';
}

function manual_role_title_options(): array
{
    return [
        'Padrinho',
        'Madrinha',
        'Pajem',
        'Dama',
        'Florista',
        'Convidado',
        'Convidada',
    ];
}

function manual_primary_name(array $guestRow): string
{
    $name = trim((string) ($guestRow['nome'] ?? ''));

    if ($name !== '') {
        $parts = preg_split('/\s+/', $name) ?: [];

        return trim((string) ($parts[0] ?? ''));
    }

    $fullName = normalized_full_name($guestRow);
    $parts = preg_split('/\s+/', $fullName) ?: [];

    return trim((string) ($parts[0] ?? ''));
}

function manual_default_role_text_for_title(string $roleTitle, string $guestName = ''): string
{
    $roleTitleLower = function_exists('mb_strtolower')
        ? mb_strtolower(trim($roleTitle))
        : strtolower(trim($roleTitle));
    $guestName = trim($guestName);

    if (str_contains($roleTitleLower, 'dama')) {
        return $guestName !== ''
            ? $guestName . ' segue a paleta esmeralda para o vestido.'
            : 'Siga a paleta esmeralda para o vestido.';
    }

    if (str_contains($roleTitleLower, 'madrinha')) {
        return $guestName !== ''
            ? $guestName . ' segue a paleta esmeralda para o vestido.'
            : 'Siga a paleta esmeralda para o vestido.';
    }

    if (str_contains($roleTitleLower, 'pajem')) {
        return $guestName !== ''
            ? $guestName . ' usa traje em tons escuros com detalhe dourado.'
            : 'Use traje em tons escuros com detalhe dourado.';
    }

    if (str_contains($roleTitleLower, 'padrinho')) {
        return $guestName !== ''
            ? $guestName . ' usa terno preto com gravata verde esmeralda.'
            : 'Use terno preto com gravata verde esmeralda.';
    }

    return manual_role_is_feminine($roleTitle)
        ? ($guestName !== ''
            ? $guestName . ' segue a paleta esmeralda para o look do grande dia.'
            : 'Siga a paleta esmeralda para o look do grande dia.')
        : ($guestName !== ''
            ? $guestName . ' usa traje social em tons escuros com detalhe verde esmeralda.'
            : 'Use traje social em tons escuros com detalhe verde esmeralda.');
}

function manual_default_role_text(array $guestRow, int $index = 0): string
{
    $roleTitle = manual_guest_role_title($guestRow, $index);
    $guestName = manual_primary_name($guestRow);

    return manual_default_role_text_for_title($roleTitle, $guestName);
}

function manual_guest_role_text(array $guestRow, int $index = 0): string
{
    $text = trim((string) ($guestRow['manual_role_text'] ?? ''));

    return $text !== '' ? $text : manual_default_role_text($guestRow, $index);
}

function manual_role_swatches(string $roleTitle): array
{
    $roleTitleLower = function_exists('mb_strtolower')
        ? mb_strtolower($roleTitle)
        : strtolower($roleTitle);

    if (str_contains($roleTitleLower, 'pajem') || str_contains($roleTitleLower, 'padrinho')) {
        return ['manual-swatch--black', 'manual-swatch--emerald-1'];
    }

    return ['manual-swatch--emerald-1', 'manual-swatch--emerald-2', 'manual-swatch--emerald-3'];
}

function manual_cover_lines(array $guestRows): array
{
    if ($guestRows === []) {
        return ['Convidados'];
    }

    if (manual_group_is_plural($guestRows)) {
        return [
            trim((string) ($guestRows[0]['nome'] ?? normalized_full_name($guestRows[0]))),
            trim((string) ($guestRows[1]['nome'] ?? normalized_full_name($guestRows[1]))),
        ];
    }

    $fullName = normalized_full_name($guestRows[0]);
    $parts = preg_split('/\s+/', $fullName) ?: [];

    if (count($parts) <= 1) {
        return [$fullName];
    }

    return [
        array_shift($parts),
        implode(' ', $parts),
    ];
}

function manual_page_title(array $guestRows): string
{
    $names = [];

    foreach ($guestRows as $guestRow) {
        $name = normalized_full_name($guestRow);

        if ($name !== '') {
            $names[] = $name;
        }
    }

    return $names !== [] ? implode(' e ', $names) : 'Convite';
}

function manual_intro_defaults(array $guestRows): array
{
    if (manual_group_is_plural($guestRows)) {
        return [
            'title' => 'Queridos amigos',
            'line_1' => 'Vocês são pessoas muito especiais para nós e queremos que estejam presentes nesse momento tão importante.',
            'line_2' => 'Seria uma honra tê-los ao nosso lado como padrinhos nesse dia especial.',
        ];
    }

    $guest = $guestRows[0] ?? [];
    $roleTitle = manual_guest_role_title($guest);
    $isFeminine = manual_role_is_feminine($roleTitle);

    return [
        'title' => $isFeminine ? 'Querida amiga' : 'Querido amigo',
        'line_1' => 'Você é uma pessoa muito especial para nós e queremos que esteja presente nesse momento tão importante.',
        'line_2' => 'Seria uma honra ter você ao nosso lado nesse dia especial.',
    ];
}

function manual_question_default(array $guestRows): string
{
    if (manual_group_is_plural($guestRows)) {
        return 'Vocês aceitam ser nossos padrinhos?';
    }

    $guest = $guestRows[0] ?? [];
    $roleTitle = manual_guest_role_title($guest);
    $isFeminine = manual_role_is_feminine($roleTitle);
    $roleTitleLower = function_exists('mb_strtolower')
        ? mb_strtolower($roleTitle)
        : strtolower($roleTitle);

    return sprintf('Você aceita ser %s %s?', $isFeminine ? 'nossa' : 'nosso', $roleTitleLower);
}

function manual_day_defaults(array $guestRows): array
{
    if (manual_group_is_plural($guestRows)) {
        return [
            'title' => 'Para o Grande Dia',
            'line_1' => 'Cheguem com meia hora de antecedência no local da cerimônia. Não podemos nos atrasar.',
            'line_2' => 'Tirem muitas fotos e nos ajudem a eternizar este momento especial.',
            'line_3' => 'Divirtam-se muito!',
            'thanks' => 'Obrigado por fazerem parte deste momento importante!',
        ];
    }

    return [
        'title' => 'Para o Grande Dia',
        'line_1' => 'Chegue com meia hora de antecedência no local da cerimônia. Não podemos nos atrasar.',
        'line_2' => 'Tire muitas fotos e nos ajude a eternizar este momento especial.',
        'line_3' => 'Divirta-se muito!',
        'thanks' => 'Obrigado por fazer parte deste momento importante!',
    ];
}

function manual_invite_text(array $inviteRow, array $guestRows, string $field): string
{
    $storedValue = trim((string) ($inviteRow[$field] ?? ''));

    if ($storedValue !== '') {
        return $storedValue;
    }

    $introDefaults = manual_intro_defaults($guestRows);
    $dayDefaults = manual_day_defaults($guestRows);

    return match ($field) {
        'manual_question_text' => manual_question_default($guestRows),
        'manual_intro_title' => $introDefaults['title'],
        'manual_intro_line_1' => $introDefaults['line_1'],
        'manual_intro_line_2' => $introDefaults['line_2'],
        'manual_calendar_title' => 'Save the Date',
        'manual_calendar_month_label' => 'Agosto',
        'manual_day_title' => $dayDefaults['title'],
        'manual_day_line_1' => $dayDefaults['line_1'],
        'manual_day_line_2' => $dayDefaults['line_2'],
        'manual_day_line_3' => $dayDefaults['line_3'],
        'manual_thanks_text' => $dayDefaults['thanks'],
        default => '',
    };
}

function manual_invite_url_from_row(array $inviteRow): ?string
{
    $slug = normalize_public_slug((string) ($inviteRow['public_slug'] ?? ''));

    if ($slug === '' || !public_slug_is_valid($slug)) {
        return null;
    }

    return url('/p/' . rawurlencode($slug));
}

function manual_support_title(): string
{
    return 'Sugestões para se organizar';
}

function manual_support_profiles(array $guestRows): array
{
    $profiles = [
        'feminine' => false,
        'masculine' => false,
    ];

    foreach ($guestRows as $index => $guestRow) {
        $roleTitle = manual_guest_role_title($guestRow, $index);

        if (manual_role_is_feminine($roleTitle)) {
            $profiles['feminine'] = true;
            continue;
        }

        $profiles['masculine'] = true;
    }

    if (!$profiles['feminine'] && !$profiles['masculine']) {
        $profiles['feminine'] = true;
        $profiles['masculine'] = true;
    }

    return $profiles;
}

function manual_support_recommendations(array $guestRows = []): array
{
    $profiles = manual_support_profiles($guestRows);
    $recommendations = [
        [
            'audience' => 'feminine',
            'label' => 'Make e cabelo',
            'name' => 'Lisiany Alves',
            'phone' => '+55 62 9154-8060',
            'phone_href' => 'tel:+556291548060',
            'url' => 'https://www.instagram.com/alveslisiany_makeup?igsh=aWN3d2FneDBrbHZi',
            'note' => 'Obs.: salão onde a noiva irá se arrumar. Entre em contato o quanto antes para agendar seu horário.',
        ],
        [
            'audience' => 'feminine',
            'label' => 'Vestido das madrinhas',
            'name' => 'Atelier Giovana França',
            'phone' => '+55 62 8104-4873',
            'phone_href' => 'tel:+556281044873',
            'url' => 'https://www.instagram.com/giovana_franca?igsh=dmplb3lndHZ1aHp3',
            'note' => '',
        ],
        [
            'audience' => 'masculine',
            'label' => 'Sugestão terno padrinhos',
            'name' => 'Luan - La Beca (Campinas)',
            'phone' => '+55 62 9124-6742',
            'phone_href' => 'tel:+556291246742',
            'url' => 'https://www.instagram.com/la_beca?igsh=MWV3bWttc3c3MDV0cg==',
            'note' => '',
        ],
    ];

    return array_values(array_filter(
        $recommendations,
        static fn(array $recommendation): bool => (bool) ($profiles[$recommendation['audience']] ?? false)
    ));
}
