<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/partials/layout.php';

mk_boot_or_die();
mk_session();

$cats = array_values(array_filter(Repo::categories(), fn($c) => $c['enabled']));
$boot = [
    'categories'  => array_map(fn($c) => ['code' => $c['code'], 'label' => t($c['label']), 'token' => $c['token'], 'color' => $c['color'], 'description' => t((string)$c['description'])], $cats),
    'samples'     => Seeds::samples(),
    'defaultMode' => Settings::get('default_mode', 'auto'),
    'maxKb'       => (int)Settings::get('max_input_kb', '4096'),
    'needLogin'   => (int)Settings::get('mask_requires_login', '0') === 1 && !Auth::check(),
];

$btn = 'inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700';
$card = 'flex min-w-0 flex-col rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900';

mk_head(t('Mask'), 'index');
?>
<div class="mx-auto grid max-w-[1800px] gap-4 p-4 xl:grid-cols-2">

  <!-- ============ INPUT ============ -->
  <section class="<?= $card ?>">
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-4 py-2.5 dark:border-slate-800">
      <h2 class="mr-1 text-sm font-semibold"><?= te('Input') ?></h2>
      <div class="inline-flex rounded-md bg-slate-100 p-0.5 text-xs dark:bg-slate-800" role="radiogroup" id="modeGroup">
        <?php foreach (['auto' => t('Auto'), 'log' => t('Log'), 'script' => t('Script')] as $k => $l): ?>
          <button type="button" data-mode="<?= $k ?>" class="mode-btn rounded px-2.5 py-1 font-medium text-slate-500 dark:text-slate-400"><?= h($l) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="ml-auto flex flex-wrap items-center gap-1.5">
        <div class="relative" id="sampleWrap">
          <button type="button" class="<?= $btn ?>" id="sampleBtn">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h10"/></svg><?= te('Sample') ?>
          </button>
          <div id="sampleMenu" class="absolute right-0 z-20 mt-1 hidden w-52 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg dark:border-slate-700 dark:bg-slate-800">
            <button type="button" data-sample="log" class="block w-full px-3 py-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-700"><?= te('Mixed log (Linux, FW, NAC, Windows)') ?></button>
            <button type="button" data-sample="bash" class="block w-full px-3 py-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-700"><?= te('Bash script') ?></button>
            <button type="button" data-sample="powershell" class="block w-full px-3 py-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-700"><?= te('PowerShell script') ?></button>
            <button type="button" data-sample="network" class="block w-full px-3 py-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-700"><?= te('Switch / router configuration') ?></button>
          </div>
        </div>
        <label class="<?= $btn ?> cursor-pointer">
          <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/></svg><?= te('Open file') ?>
          <input type="file" id="fileInput" class="hidden" accept=".log,.txt,.sh,.bash,.ps1,.psm1,.py,.bat,.cmd,.conf,.cfg,.ini,.json,.xml,.yml,.yaml,.csv,.env,.php,.js,.rb,.pl,.sql,.cnf,text/*">
        </label>
        <button type="button" class="<?= $btn ?>" id="clearBtn"><?= te('Clear') ?></button>
      </div>
    </div>

    <div class="relative flex-1">
      <textarea id="input" spellcheck="false" autocomplete="off"
        class="mk-code mk-scroll block h-[calc(100vh-330px)] min-h-[340px] w-full resize-none border-0 bg-transparent px-4 py-3 text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-0 dark:text-slate-200"
        placeholder="<?= te("Paste log or script content here, or drag and drop a file…\n\nE.g. switch/firewall/NAC logs, Linux/Windows/macOS server logs, application logs, bash / PowerShell / Python scripts.") ?>"></textarea>
      <div id="dropHint" class="pointer-events-none absolute inset-2 hidden items-center justify-center rounded-lg border-2 border-dashed border-violet-400 bg-violet-50/80 text-sm font-medium text-violet-700 dark:bg-violet-500/10 dark:text-violet-300"><?= te('Drop the file') ?></div>
    </div>

    <details id="catPanel" class="border-t border-slate-100 dark:border-slate-800">
      <summary class="flex cursor-pointer select-none items-center gap-2 px-4 py-2 text-xs font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M7 12h10M10 18h4"/></svg>
        <?= te('Categories to mask in this run') ?>
        <span id="catSummary" class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] dark:bg-slate-800"></span>
      </summary>
      <div id="catChips" class="flex flex-wrap gap-1.5 px-4 pb-3"></div>
    </details>

    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 px-4 py-2.5 text-xs dark:border-slate-800">
      <span id="inCount" class="text-slate-400"><?= te('0 characters') ?></span>
      <label class="flex cursor-pointer items-center gap-1.5 text-slate-600 dark:text-slate-300" title="<?= te('Mask automatically as you type') ?>">
        <input type="checkbox" id="liveChk" class="h-3.5 w-3.5 rounded border-slate-300 text-violet-600 focus:ring-violet-500"> <?= te('Live') ?>
      </label>
      <label class="flex cursor-pointer items-center gap-1.5 text-slate-600 dark:text-slate-300" title="<?= te('A detected value is also masked wherever else it appears in the text, even without context') ?>">
        <input type="checkbox" id="propChk" class="h-3.5 w-3.5 rounded border-slate-300 text-violet-600 focus:ring-violet-500" checked> <?= te('Propagation') ?>
      </label>
      <span class="hidden text-slate-400 sm:inline">Ctrl+Enter</span>
      <button type="button" id="maskBtn" class="ml-auto inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-violet-600 to-fuchsia-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:from-violet-700 hover:to-fuchsia-700 disabled:opacity-60">
        <svg id="maskIcon" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 9.5C3 8.7 3.7 8 4.5 8h15c.8 0 1.5.7 1.5 1.5v1c0 3.6-2.5 6.5-5.5 6.5-1.6 0-2.6-.9-3.5-2.3-.9 1.4-1.9 2.3-3.5 2.3C5.5 17 3 14.1 3 10.5z"/></svg>
        <svg id="maskSpin" class="mk-spin hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 3a9 9 0 1 0 9 9"/></svg>
        <?= te('Mask') ?>
      </button>
    </div>
  </section>

  <!-- ============ OUTPUT ============ -->
  <section class="<?= $card ?>">
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-2 py-1.5 dark:border-slate-800">
      <nav class="flex flex-wrap gap-0.5 text-sm" id="tabs">
        <?php foreach (['masked' => t('Masked'), 'original' => t('Highlighted original'), 'map' => t('Mapping table'), 'restore' => t('Restore')] as $k => $l): ?>
          <button type="button" data-tab="<?= $k ?>" class="tab-btn rounded-md px-3 py-1.5 font-medium"><?= h($l) ?><?php if ($k === 'map'): ?> <span id="mapCount" class="ml-1 rounded-full bg-slate-200 px-1.5 py-px text-[11px] text-slate-600 dark:bg-slate-700 dark:text-slate-300">0</span><?php endif; ?></button>
        <?php endforeach; ?>
      </nav>
      <div class="ml-auto flex flex-wrap items-center gap-1.5 pr-2">
        <button type="button" class="<?= $btn ?>" id="copyBtn" disabled>
          <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg><?= te('Copy') ?>
        </button>
        <button type="button" class="<?= $btn ?>" id="dlBtn" disabled>
          <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16"/></svg><?= te('Download') ?>
        </button>
        <div class="relative">
          <button type="button" class="<?= $btn ?>" id="mapMenuBtn" disabled><?= te('Mapping') ?> ▾</button>
          <div id="mapMenu" class="absolute right-0 z-20 mt-1 hidden w-56 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg dark:border-slate-700 dark:bg-slate-800">
            <button type="button" data-map="json" class="block w-full px-3 py-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-700"><?= te('Download mapping as JSON') ?></button>
            <button type="button" data-map="csv" class="block w-full px-3 py-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-700"><?= te('Download mapping as CSV') ?></button>
            <button type="button" data-map="prompt" class="block w-full px-3 py-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-700"><?= te('Copy with an AI explanation') ?></button>
          </div>
        </div>
      </div>
    </div>

    <div id="notice" class="hidden"></div>

    <div class="relative min-h-0 flex-1">
      <!-- Masked -->
      <div data-panel="masked" class="panel h-[calc(100vh-330px)] min-h-[340px] overflow-auto mk-scroll">
        <div id="empty" class="flex h-full flex-col items-center justify-center gap-3 p-8 text-center text-sm text-slate-400">
          <?= mk_logo_full('h-24 w-auto') ?>
          <div>
            <div class="font-medium text-slate-500 dark:text-slate-300"><?= te('The masked output will appear here') ?></div>
            <div class="mt-1 max-w-md text-xs"><?= te('IP addresses, hostnames, FQDNs, usernames (including first.last), passwords, API/SSH keys, tokens, MAC addresses, SIDs, e-mail addresses and personal data are detected and replaced with consistent tokens.') ?></div>
          </div>
        </div>
        <pre id="maskedOut" class="mk-pre mk-code hidden px-4 py-3"></pre>
      </div>
      <!-- Highlighted original -->
      <div data-panel="original" class="panel hidden h-[calc(100vh-330px)] min-h-[340px] overflow-auto mk-scroll">
        <div class="flex items-center gap-2 border-b border-amber-100 bg-amber-50 px-4 py-1.5 text-xs text-amber-800 dark:border-amber-900/40 dark:bg-amber-500/10 dark:text-amber-300">
          <svg class="h-3.5 w-3.5 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
          <?= te('This view contains the original data; it is only for checking what was masked. Give the AI the "Masked" output, not this tab.') ?>
        </div>
        <pre id="originalOut" class="mk-pre mk-code px-4 py-3"></pre>
      </div>
      <!-- Mapping -->
      <div data-panel="map" class="panel hidden h-[calc(100vh-330px)] min-h-[340px] overflow-auto mk-scroll">
        <div class="sticky top-0 z-10 flex flex-wrap items-center gap-2 border-b border-slate-100 bg-white/95 px-4 py-2 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
          <input type="search" id="mapSearch" placeholder="<?= te('Search token, category, rule…') ?>" class="w-56 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs focus:border-violet-400 focus:outline-none focus:ring-1 focus:ring-violet-400 dark:border-slate-700 dark:bg-slate-800">
          <select id="mapCat" class="rounded-md border border-slate-200 bg-white px-2 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-800"><option value=""><?= te('All categories') ?></option></select>
          <label class="ml-auto flex cursor-pointer items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
            <input type="checkbox" id="revealChk" class="h-3.5 w-3.5 rounded border-slate-300 text-violet-600"> <?= te('Show original values') ?>
          </label>
        </div>
        <table class="w-full text-left text-xs">
          <thead class="text-[11px] uppercase tracking-wide text-slate-400">
            <tr><th class="px-4 py-2 font-medium"><?= te('Token') ?></th><th class="px-2 py-2 font-medium"><?= te('Category') ?></th><th class="px-2 py-2 font-medium"><?= te('Original') ?></th><th class="px-2 py-2 text-right font-medium"><?= te('Count') ?></th><th class="px-4 py-2 font-medium"><?= te('Detected by rule(s)') ?></th></tr>
          </thead>
          <tbody id="mapBody" class="divide-y divide-slate-100 dark:divide-slate-800"></tbody>
        </table>
      </div>
      <!-- Restore -->
      <div data-panel="restore" class="panel hidden h-[calc(100vh-330px)] min-h-[340px] overflow-auto mk-scroll">
        <div class="space-y-3 p-4">
          <p class="text-xs text-slate-500 dark:text-slate-400"><?= t('Paste the AI\'s tokenized reply (e.g. {example}) below; it is converted back to the real values using the current mapping table. Everything happens in the browser.', ['example' => '<code class="rounded bg-slate-100 px-1 dark:bg-slate-800">' . h(t('<USER_2> on <HOST_1>…')) . '</code>']) ?></p>
          <textarea id="restoreIn" spellcheck="false" class="mk-code mk-scroll h-40 w-full rounded-lg border border-slate-200 bg-slate-50 p-3 focus:border-violet-400 focus:outline-none focus:ring-1 focus:ring-violet-400 dark:border-slate-700 dark:bg-slate-800/60" placeholder="<?= te('Paste the AI reply here…') ?>"></textarea>
          <div class="flex flex-wrap items-center gap-2">
            <button type="button" id="restoreBtn" class="rounded-md bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700"><?= te('Restore') ?></button>
            <label class="<?= $btn ?> cursor-pointer"><?= te('Load mapping JSON') ?><input type="file" id="mapFile" accept=".json,application/json" class="hidden"></label>
            <button type="button" id="restoreCopy" class="<?= $btn ?>"><?= te('Copy result') ?></button>
            <span id="restoreInfo" class="text-xs text-slate-500"></span>
          </div>
          <pre id="restoreOut" class="mk-pre mk-code min-h-[120px] rounded-lg border border-dashed border-slate-200 p-3 dark:border-slate-700"></pre>
        </div>
      </div>
    </div>

    <div id="stats" class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-t border-slate-100 px-4 py-2.5 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
      <span><?= te('Not run yet') ?></span>
    </div>
  </section>
</div>

<script>window.MK = window.MK || {}; MK.boot = <?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php mk_foot(['assets/js/app.js']); ?>
