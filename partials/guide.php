<?php
declare(strict_types=1);

// Shared helpers for the guide pages (guide.php, admin-guide.php).

/** @var array<string,int> section id => sequence number (filled by g_open) */
$GLOBALS['mk_guide_toc'] = [];

/** @param array<string,string> $toc section id => title */
function g_open(string $title, string $lead, array $toc, string $otherHref, string $otherLabel, string $badge = ''): void
{
    $GLOBALS['mk_guide_toc'] = array_flip(array_keys($toc));
    $badge = $badge !== '' ? $badge : t('End user');
    ?>
<div class="mx-auto flex max-w-[1800px] gap-8 px-4 py-6">
  <aside class="hidden w-60 flex-none lg:block">
    <nav class="mk-toc sticky top-20 text-sm" id="guideToc">
      <div class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400"><?= h(t('Contents')) ?></div>
      <div class="space-y-0.5">
        <?php $i = 0; foreach ($toc as $id => $label): $i++; ?>
          <a href="#<?= h($id) ?>" data-toc="<?= h($id) ?>" class="mk-toc-link"><span class="mk-toc-num"><?= $i ?></span><?= h($label) ?></a>
        <?php endforeach; ?>
      </div>
      <a href="<?= h($otherHref) ?>" class="mt-5 flex items-center justify-between rounded-lg border border-violet-200 bg-violet-50/60 px-3 py-2.5 text-xs font-semibold text-violet-700 hover:bg-violet-100 dark:border-violet-900/60 dark:bg-violet-500/10 dark:text-violet-300 dark:hover:bg-violet-500/20"><?= h($otherLabel) ?> <span aria-hidden="true">→</span></a>
    </nav>
  </aside>
  <article class="mk-guide min-w-0 max-w-5xl flex-1">
    <header class="mk-hero">
      <div class="flex items-start gap-4">
        <?= mk_logo('h-12 w-12 flex-none rounded-xl bg-white p-1 shadow') ?>
        <div class="min-w-0">
          <span class="mk-hero-badge"><?= h($badge) ?></span>
          <h1 class="mt-1.5 text-[26px] font-bold leading-tight tracking-tight"><?= h($title) ?></h1>
          <p class="mt-2 max-w-3xl text-[15px] leading-relaxed text-white/85"><?= $lead ?></p>
          <a href="<?= h($otherHref) ?>" class="mt-3 inline-block text-sm font-semibold text-white underline-offset-4 hover:underline lg:hidden"><?= h($otherLabel) ?> →</a>
        </div>
      </div>
    </header>
<?php
}

function g_close(): void
{
    ?>
  </article>
</div>
<script>
  // Table of contents: highlight the section being read
  (function () {
    var links = {}, heads = document.querySelectorAll('.mk-guide h2[id]');
    document.querySelectorAll('[data-toc]').forEach(function (a) { links[a.dataset.toc] = a; });
    if (!('IntersectionObserver' in window) || !heads.length) return;
    var seen = {};
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) { seen[e.target.id] = e.isIntersecting ? e.boundingClientRect.top : undefined; });
      var cur = null;
      heads.forEach(function (h) { if (seen[h.id] !== undefined && cur === null) cur = h.id; });
      if (cur === null) {
        heads.forEach(function (h) { if (h.getBoundingClientRect().top < 120) cur = h.id; });
      }
      Object.keys(links).forEach(function (k) { links[k].classList.toggle('is-active', k === cur); });
    }, { rootMargin: '-64px 0px -55% 0px' });
    heads.forEach(function (h) { io.observe(h); });
  })();
</script>
<?php
}

function g_section(string $id, string $title): void
{
    $n = ($GLOBALS['mk_guide_toc'][$id] ?? 0) + 1;
    echo '<h2 id="' . h($id) . '"><span class="mk-h2-num">' . str_pad((string)$n, 2, '0', STR_PAD_LEFT) . '</span><span>' . h($title) . '</span></h2>';
}

/** Note box: $kind = tip | warn | info */
function g_note(string $html, string $kind = 'info'): void
{
    $label = ['tip' => t('Tip'), 'warn' => t('Caution'), 'info' => t('Note')][$kind] ?? t('Note');
    $icon = [
        'tip'  => '<path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/>',
        'warn' => '<path d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
    ][$kind] ?? '';
    echo '<div class="mk-note mk-note-' . h($kind) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $icon . '</svg><div><b>' . $label . '</b> ' . $html . '</div></div>';
}

/** Token shown in its category color */
function g_tok(string $text, string $color = '#7c3aed'): string
{
    return '<span class="mk-tok mk-code text-[13px]" style="color:' . h($color) . ';background:' . h($color) . '1f">' . h($text) . '</span>';
}

/** Before / after code example */
function g_example(string $before, string $after): void
{
    echo '<div class="mk-example">'
        . '<div><div class="mk-example-label">' . h(t('Input')) . '</div><pre class="mk-pre mk-code">' . h($before) . '</pre></div>'
        . '<div class="mk-example-arrow" aria-hidden="true">→</div>'
        . '<div><div class="mk-example-label mk-example-label-out">' . h(t('Masked')) . '</div><pre class="mk-pre mk-code">' . h($after) . '</pre></div>'
        . '</div>';
}

/** Category table – built from the current categories in the database */
function g_category_table(): void
{
    $tpl = (string)Settings::get('placeholder_template', '<{CAT}_{n}>');
    echo '<div class="mk-table"><table><thead><tr><th>' . h(t('Category')) . '</th><th>' . h(t('Example token')) . '</th><th>' . h(t('What does it cover?')) . '</th></tr></thead><tbody>';
    foreach (Repo::categories() as $c) {
        if (!$c['enabled']) continue;
        $ex = strtr($c['placeholder'] ?: $tpl, ['{CAT}' => $c['token'], '{cat}' => strtolower($c['token']), '{n}' => '1', '{hash}' => '3fa9c01e', '{len}' => '8', '{stars}' => '********']);
        echo '<tr><td class="whitespace-nowrap font-medium"><span class="mr-2 inline-block h-2 w-2 rounded-full align-middle" style="background:' . h($c['color']) . '"></span>' . h(t($c['label'])) . '</td><td>' . g_tok($ex, $c['color']) . '</td><td>' . h(t($c['description'])) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
