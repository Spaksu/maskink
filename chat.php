<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/partials/layout.php';

mk_boot_or_die();
mk_session();

$cats = array_values(array_filter(Repo::categories(), fn($c) => $c['enabled']));
$boot = [
    'categories'   => array_map(fn($c) => ['code' => $c['code'], 'label' => t($c['label']), 'token' => $c['token'], 'color' => $c['color']], $cats),
    'defaultModel' => (string)Settings::get('chat_default_model', ''),
    'maxKb'        => (int)Settings::get('max_input_kb', '4096'),
    'needLogin'    => (int)Settings::get('mask_requires_login', '0') === 1 && !Auth::check(),
];

$icon = 'inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200';

mk_head(t('Chat'), 'chat');
?>
<div class="mx-auto flex h-[calc(100vh-3.5rem-2.6rem)] max-w-[1800px] gap-0">

  <!-- ============ CHAT LIST ============ -->
  <aside id="chatSide" class="hidden w-72 flex-none flex-col border-r border-slate-200 bg-white/60 dark:border-slate-800 dark:bg-slate-900/40 lg:flex">
    <div class="p-3">
      <button type="button" id="newChat" class="flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg><?= te('New chat') ?>
      </button>
    </div>
    <div class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400"><?= te('Chats') ?></div>
    <div id="chatList" class="mk-scroll flex-1 space-y-0.5 overflow-y-auto px-2 pb-2 text-sm"></div>
    <div class="border-t border-slate-200 p-3 text-[11px] leading-relaxed text-slate-500 dark:border-slate-800 dark:text-slate-400">
      <?= t('Chats and mapping tables are stored <b>only in this browser</b>; they are not saved on the server.') ?>
      <button type="button" id="clearAll" class="mt-1 block font-medium text-rose-600 hover:underline dark:text-rose-400"><?= te('Delete all chats') ?></button>
    </div>
  </aside>

  <!-- ============ CHAT ============ -->
  <section class="flex min-w-0 flex-1 flex-col">
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white/70 px-4 py-2 dark:border-slate-800 dark:bg-slate-900/50">
      <button type="button" id="sideToggle" class="<?= $icon ?> lg:hidden" title="<?= te('Chats') ?>">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <label class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <?= te('Model') ?>
        <select id="modelSel" class="max-w-[16rem] rounded-md border border-slate-200 bg-white px-2 py-1.5 text-sm font-medium text-slate-700 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
          <option><?= te('Loading…') ?></option>
        </select>
      </label>
      <button type="button" id="modelRefresh" class="<?= $icon ?>" title="<?= te('Refresh the model list') ?>">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5"/></svg>
      </button>
      <div class="ml-auto flex items-center gap-1">
        <label class="<?= $icon ?> cursor-pointer select-none" title="<?= te('Shows the text sent to and received from the AI in its masked form') ?>">
          <input type="checkbox" id="aiView" class="h-3.5 w-3.5 rounded border-slate-300 text-violet-600">
          <?= te('What the AI sees') ?>
        </label>
        <button type="button" id="mapBtn" class="<?= $icon ?>" title="<?= te('Mapping table of this chat') ?>">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          <?= te('Mapping') ?> <span id="mapCount" class="rounded-full bg-slate-100 px-1.5 text-[10px] font-semibold tabular-nums text-slate-600 dark:bg-slate-800 dark:text-slate-300">0</span>
        </button>
      </div>
    </div>

    <div id="scroller" class="mk-scroll flex-1 overflow-y-auto">
      <div id="messages" class="mx-auto max-w-4xl space-y-6 px-4 py-6"></div>
      <div id="welcome" class="mx-auto hidden max-w-2xl px-4 py-14 text-center">
        <div class="mx-auto mb-4 w-fit"><?= mk_logo_full('h-28 w-auto') ?></div>
        <h1 class="text-2xl font-bold tracking-tight"><?= te('Chat with {app}', ['app' => MK_APP]) ?></h1>
        <p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-slate-500 dark:text-slate-400">
          <?= te('Every message you write passes through {app} before it reaches the AI. IP addresses, server names, usernames, passwords and keys are replaced with placeholders; when the reply arrives, the placeholders are turned back into the real values.', ['app' => MK_APP]) ?>
        </p>
        <div class="mx-auto mt-6 grid max-w-xl gap-2 text-left text-xs sm:grid-cols-3">
          <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900"><div class="mb-1 font-semibold text-slate-700 dark:text-slate-200"><?= te('1 · You write') ?></div><span class="text-slate-500 dark:text-slate-400"><?= te('A log, a script or a question; with the real values.') ?></span></div>
          <div class="rounded-lg border border-violet-200 bg-violet-50 p-3 dark:border-violet-900/60 dark:bg-violet-500/10"><div class="mb-1 font-semibold text-violet-700 dark:text-violet-300"><?= te('2 · {app} masks', ['app' => MK_APP]) ?></div><span class="text-slate-600 dark:text-slate-400"><?= t('The AI only sees tokens like {tok}.', ['tok' => '<code class="font-mono">&lt;IP_1&gt;</code>']) ?></span></div>
          <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900"><div class="mb-1 font-semibold text-slate-700 dark:text-slate-200"><?= te('3 · The reply is restored') ?></div><span class="text-slate-500 dark:text-slate-400"><?= te('You read the reply with the real values.') ?></span></div>
        </div>
      </div>
    </div>

    <!-- ============ COMPOSER ============ -->
    <div class="border-t border-slate-200 bg-white/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/60">
      <div class="mx-auto max-w-4xl">
        <div id="dropZone" class="rounded-xl border border-slate-300 bg-white shadow-sm focus-within:border-violet-400 focus-within:ring-2 focus-within:ring-violet-500/20 dark:border-slate-700 dark:bg-slate-900">
          <div id="attachBar" class="hidden flex-wrap gap-2 px-3 pt-3"></div>
          <textarea id="prompt" rows="1" spellcheck="false" placeholder="<?= te('Write your message, paste a log/script or a screenshot, drag a file… (Enter: send, Shift+Enter: new line)') ?>"
            class="mk-scroll block max-h-[40vh] min-h-[3rem] w-full resize-none rounded-xl border-0 bg-transparent px-4 py-3 text-sm focus:outline-none focus:ring-0"></textarea>
          <div class="flex flex-wrap items-center gap-1 px-2 pb-2">
            <label class="<?= $icon ?> cursor-pointer" title="<?= te('Attach a text file or a screenshot (images are read and sensitive text is covered)') ?>">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.4 11.1l-9.2 9.2a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5"/></svg><?= te('File') ?>
              <input type="file" id="fileIn" class="hidden" multiple>
            </label>
            <label class="<?= $icon ?> cursor-pointer select-none" title="<?= te('Show the masked text that will go to the AI and ask for confirmation before sending') ?>">
              <input type="checkbox" id="previewChk" class="h-3.5 w-3.5 rounded border-slate-300 text-violet-600"><?= te('Preview before sending') ?>
            </label>
            <span id="promptInfo" class="ml-2 text-[11px] text-slate-400"></span>
            <div class="ml-auto flex items-center gap-2">
              <button type="button" id="stopBtn" class="hidden items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg><?= te('Stop') ?>
              </button>
              <button type="button" id="sendBtn" class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><?= te('Mask and send') ?>
              </button>
            </div>
          </div>
        </div>
        <div class="mt-1.5 text-center text-[11px] text-slate-400"><?= te('{app} is an automated helper; before sending sensitive content, use “Preview before sending” if needed.', ['app' => MK_APP]) ?></div>
      </div>
    </div>
  </section>
</div>

<div id="aiAvatar" hidden><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-violet-600 to-pink-600 text-white shadow-sm"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg></span></div>

<!-- Preview -->
<dialog id="previewDlg" class="w-[min(900px,calc(100vw-2rem))] rounded-xl border border-slate-200 bg-white p-0 text-slate-800 shadow-2xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
  <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-3 dark:border-slate-800">
    <h3 class="text-sm font-semibold"><?= te('Text that will go to the AI') ?></h3>
    <span id="previewInfo" class="text-xs text-slate-500"></span>
  </div>
  <pre id="previewBody" class="mk-pre mk-code mk-scroll max-h-[60vh] overflow-auto px-5 py-4"></pre>
  <div id="previewWarn" class="hidden border-t border-amber-200 bg-amber-50 px-5 py-2 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-500/10 dark:text-amber-200"></div>
  <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3 dark:border-slate-800">
    <button type="button" data-close class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800"><?= te('Cancel') ?></button>
    <button type="button" id="previewSend" class="rounded-lg bg-violet-600 px-3.5 py-1.5 text-sm font-semibold text-white hover:bg-violet-700"><?= te('Send') ?></button>
  </div>
</dialog>

<!-- Screenshot masking -->
<dialog id="imgDlg" class="w-[min(1200px,calc(100vw-2rem))] rounded-xl border border-slate-200 bg-white p-0 text-slate-800 shadow-2xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
  <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-3 dark:border-slate-800">
    <h3 class="text-sm font-semibold"><?= te('Mask the screenshot') ?></h3>
    <span class="text-xs text-slate-500"><?= te('The image is read on this computer; only the covered version below goes to the AI.') ?></span>
  </div>
  <div id="imgStatus" class="px-5 py-10 text-center text-sm text-slate-500"></div>
  <div class="mk-scroll relative max-h-[62vh] overflow-auto bg-slate-100 p-3 dark:bg-slate-950">
    <canvas id="imgCanvas" class="mx-auto hidden max-w-full cursor-crosshair touch-none rounded shadow"></canvas>
    <div id="imgPop" class="absolute z-10 hidden rounded-lg border border-slate-200 bg-white p-2.5 text-xs shadow-xl dark:border-slate-700 dark:bg-slate-900"></div>
  </div>
  <div id="imgTools" class="hidden border-t border-slate-100 px-5 py-3 text-xs dark:border-slate-800">
    <div class="flex flex-wrap items-start gap-3">
      <div id="imgInfo" class="flex-1 text-slate-600 dark:text-slate-300"></div>
      <label class="inline-flex cursor-pointer select-none items-center gap-1.5 font-medium text-slate-600 dark:text-slate-300" title="<?= te('Show the uncovered original image (only on your screen)') ?>">
        <input type="checkbox" id="imgOrig" class="h-3.5 w-3.5 rounded border-slate-300 text-violet-600"> <?= te('Show original') ?>
      </label>
    </div>
    <div class="mt-2 flex flex-wrap items-center gap-2">
      <span class="text-slate-500"><?= t('<b>Click an uncovered word</b> or <b>drag to select words</b> → pick a category and mask it. <b>Right-click a covered area</b> → its mask is removed (left click: menu). Dragging on an empty area draws a dark box; right-click a box to delete it.') ?></span>
      <button type="button" id="imgLow" class="hidden rounded-md border border-amber-300 px-2 py-1 font-medium text-amber-700 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-300 dark:hover:bg-amber-500/10"><?= te('Cover orange-marked words with boxes') ?></button>
      <button type="button" id="imgUndo" class="rounded-md border border-slate-300 px-2 py-1 font-medium hover:bg-slate-50 disabled:opacity-40 dark:border-slate-600 dark:hover:bg-slate-800"><?= te('Undo') ?></button>
    </div>
    <details class="mt-2"><summary class="cursor-pointer text-slate-500"><?= te('Text read from the image (goes to the AI in masked form)') ?></summary>
      <pre id="imgText" class="mk-pre mk-code mk-scroll mt-1 max-h-48 overflow-auto rounded bg-slate-50 p-2 dark:bg-slate-800/60"></pre>
    </details>
  </div>
  <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3 dark:border-slate-800">
    <button type="button" data-imgclose class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800"><?= te('Cancel') ?></button>
    <button type="button" id="imgAdd" class="rounded-lg bg-violet-600 px-3.5 py-1.5 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"><?= te('Add to message') ?></button>
  </div>
</dialog>

<!-- Image zoom -->
<dialog id="zoomDlg" class="max-h-[95vh] max-w-[95vw] rounded-xl border border-slate-200 bg-white p-2 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
  <div class="mb-1 flex items-center gap-2 px-1 text-xs text-slate-500"><span id="zoomLabel"><?= te('Image as the AI sees it') ?></span>
    <button type="button" id="zoomSwap" class="hidden rounded border border-slate-300 px-2 py-0.5 font-medium hover:bg-slate-100 dark:border-slate-600 dark:hover:bg-slate-800"><?= te('Show original') ?></button>
    <button type="button" data-close class="ml-auto rounded px-2 py-0.5 hover:bg-slate-100 dark:hover:bg-slate-800"><?= te('Close') ?></button></div>
  <img id="zoomImg" alt="" class="max-h-[85vh] max-w-full">
</dialog>

<!-- Mapping table -->
<dialog id="mapDlg" class="w-[min(900px,calc(100vw-2rem))] rounded-xl border border-slate-200 bg-white p-0 text-slate-800 shadow-2xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
  <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-3 dark:border-slate-800">
    <h3 class="text-sm font-semibold"><?= te('Mapping table of this chat') ?></h3>
    <span class="text-xs text-slate-500"><?= te('Token → real value · only in this browser') ?></span>
    <div class="ml-auto flex gap-1">
      <button type="button" id="mapJson" class="<?= $icon ?>"><?= te('Download JSON') ?></button>
      <button type="button" data-close class="<?= $icon ?>"><?= te('Close') ?></button>
    </div>
  </div>
  <div class="mk-scroll max-h-[65vh] overflow-auto">
    <table class="w-full text-left text-sm">
      <thead class="sticky top-0 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400"><tr><th class="px-4 py-2"><?= te('Token') ?></th><th class="px-4 py-2"><?= te('Category') ?></th><th class="px-4 py-2"><?= te('Real value') ?></th></tr></thead>
      <tbody id="mapBody" class="divide-y divide-slate-100 dark:divide-slate-800"></tbody>
    </table>
  </div>
</dialog>

<script>window.MK = window.MK || {}; MK.boot = <?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php mk_foot(['assets/js/vision.js', 'assets/js/chat.js']); ?>
