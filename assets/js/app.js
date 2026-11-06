/* Maskink – mask screen */
(function (MK) {
  'use strict';
  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));
  const t = MK.t;
  const B = MK.boot;
  const CAT = Object.fromEntries(B.categories.map(c => [c.code, c]));

  const state = {
    mode: MK.store.get('mode', B.defaultMode || 'auto'),
    disabled: new Set(MK.store.get('disabledCats', [])),
    live: MK.store.get('live', false),
    propagate: MK.store.get('propagate', true),
    tab: 'masked',
    result: null,
    map: {},        // token -> original
    busy: false,
    lastInput: null
  };

  const el = {
    input: $('#input'), maskBtn: $('#maskBtn'), maskIcon: $('#maskIcon'), maskSpin: $('#maskSpin'),
    maskedOut: $('#maskedOut'), originalOut: $('#originalOut'), empty: $('#empty'),
    mapBody: $('#mapBody'), mapCount: $('#mapCount'), mapSearch: $('#mapSearch'), mapCat: $('#mapCat'),
    reveal: $('#revealChk'), stats: $('#stats'), notice: $('#notice'), inCount: $('#inCount'),
    copyBtn: $('#copyBtn'), dlBtn: $('#dlBtn'), mapMenuBtn: $('#mapMenuBtn'), mapMenu: $('#mapMenu'),
    restoreIn: $('#restoreIn'), restoreOut: $('#restoreOut'), restoreInfo: $('#restoreInfo')
  };

  const color = code => (CAT[code] && CAT[code].color) || '#64748b';
  const label = code => (CAT[code] && CAT[code].label) || code;
  const tokStyle = c => `color:${c};background:${c}1f;`;
  const lc = s => s.toLocaleLowerCase(MK.locale);

  // ------------------------------------------------------------ mode / category selection
  function renderModes() {
    $$('.mode-btn').forEach(b => {
      const on = b.dataset.mode === state.mode;
      b.className = 'mode-btn rounded px-2.5 py-1 font-medium ' + (on
        ? 'bg-white text-violet-700 shadow-sm dark:bg-slate-700 dark:text-violet-300'
        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200');
    });
  }
  $$('.mode-btn').forEach(b => b.addEventListener('click', () => {
    state.mode = b.dataset.mode; MK.store.set('mode', state.mode); renderModes();
    if (el.input.value.trim()) run();
  }));

  function renderChips() {
    const box = $('#catChips');
    box.innerHTML = '';
    B.categories.forEach(c => {
      const off = state.disabled.has(c.code);
      const b = document.createElement('button');
      b.type = 'button';
      b.title = c.description || '';
      b.className = 'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium transition ' +
        (off ? 'border-slate-200 text-slate-400 line-through dark:border-slate-700' : 'border-transparent text-slate-700 dark:text-slate-200');
      if (!off) b.style.background = c.color + '1f';
      b.innerHTML = `<span class="h-2 w-2 rounded-full" style="background:${off ? '#cbd5e1' : c.color}"></span>${MK.esc(c.label)}`;
      b.addEventListener('click', () => {
        off ? state.disabled.delete(c.code) : state.disabled.add(c.code);
        MK.store.set('disabledCats', [...state.disabled]);
        renderChips();
        if (state.result) run();
      });
      box.appendChild(b);
    });
    const on = B.categories.length - state.disabled.size;
    $('#catSummary').textContent = t('{on}/{all} on', { on, all: B.categories.length });
  }

  // ------------------------------------------------------------ tabs
  function setTab(tab) {
    state.tab = tab;
    $$('.tab-btn').forEach(b => {
      const on = b.dataset.tab === tab;
      b.className = 'tab-btn rounded-md px-3 py-1.5 font-medium ' + (on
        ? 'bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300'
        : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800');
    });
    $$('.panel').forEach(p => p.classList.toggle('hidden', p.dataset.panel !== tab));
  }
  $$('.tab-btn').forEach(b => b.addEventListener('click', () => setTab(b.dataset.tab)));

  // ------------------------------------------------------------ masking
  async function run() {
    const text = el.input.value;
    if (!text.trim()) { MK.toast(t('Enter a log or script first.'), 'warn'); return; }
    if (state.busy) { state.pending = true; return; }
    state.busy = true; busy(true);
    try {
      const res = await MK.api('mask', { text, mode: state.mode, disabled_cats: [...state.disabled], propagate: state.propagate });
      state.result = res;
      state.lastInput = text;
      state.map = {};
      res.items.forEach(it => { state.map[it.token] = it.original; });
      render();
    } catch (e) {
      MK.toast(e.message, 'err', 5000);
      if (e.status === 401) setTimeout(() => location.href = 'settings.php', 1200);
    } finally {
      state.busy = false; busy(false);
      if (state.pending) { state.pending = false; run(); }
    }
  }
  function busy(on) {
    el.maskBtn.disabled = on;
    el.maskIcon.classList.toggle('hidden', on);
    el.maskSpin.classList.toggle('hidden', !on);
  }

  function render() {
    const r = state.result;
    if (!r) return;
    el.empty.classList.add('hidden');
    el.maskedOut.classList.remove('hidden');
    [el.copyBtn, el.dlBtn, el.mapMenuBtn].forEach(b => b.disabled = false);

    const fm = document.createDocumentFragment();
    const fo = document.createDocumentFragment();
    for (const s of r.segments) {
      if (s.t !== undefined) {
        fm.appendChild(document.createTextNode(s.t));
        fo.appendChild(document.createTextNode(s.t));
        continue;
      }
      const c = color(s.c);
      const a = document.createElement('span');
      a.className = 'mk-tok'; a.dataset.tok = s.m; a.style.cssText = tokStyle(c);
      a.title = `${label(s.c)} · ${s.r}`;
      a.textContent = s.m;
      fm.appendChild(a);
      const b = document.createElement('span');
      b.className = 'mk-tok'; b.dataset.tok = s.m; b.style.cssText = tokStyle(c);
      b.title = `${s.m} · ${label(s.c)} · ${s.r}`;
      b.textContent = s.o;
      fo.appendChild(b);
    }
    el.maskedOut.replaceChildren(fm);
    el.originalOut.replaceChildren(fo);

    renderNotice();
    renderMapFilter();
    renderMap();
    renderStats();
  }

  function renderNotice() {
    const r = state.result;
    const parts = [];
    if (r.warnings && r.warnings.length) {
      const rows = r.warnings.slice(0, 12).map(w =>
        `<li class="truncate"><button type="button" data-line="${w.line}" class="goto-line font-mono text-amber-900 hover:underline dark:text-amber-200">${MK.esc(t('L{n}', { n: w.line }))}</button> <span class="opacity-80">«${MK.esc(w.keyword)}»</span> <span class="font-mono opacity-70">${MK.esc(w.text)}</span></li>`).join('');
      parts.push(`<details class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-500/10 dark:text-amber-300">
        <summary class="cursor-pointer font-medium">⚠ ${MK.esc(t('{n} lines contain a sensitive keyword but nothing was masked — check them', { n: r.warnings.length }))}</summary>
        <ul class="mt-1.5 space-y-0.5">${rows}</ul>
        <div class="mt-1.5 opacity-80">${MK.esc(t('If needed, add a new rule under Settings → Rules, or add the term to the Custom dictionary.'))}</div></details>`);
    }
    if (r.errors && r.errors.length) {
      parts.push(`<div class="border-b border-rose-200 bg-rose-50 px-4 py-2 text-xs text-rose-700 dark:border-rose-900/50 dark:bg-rose-500/10 dark:text-rose-300">
        ${MK.esc(t('{n} rules could not run:', { n: r.errors.length }))} ${r.errors.map(e => MK.esc(e.rule)).join(', ')} — <a class="underline" href="settings.php#rules">${MK.esc(t('Settings'))}</a></div>`);
    }
    el.notice.innerHTML = parts.join('');
    el.notice.classList.toggle('hidden', !parts.length);
    el.notice.querySelectorAll('.goto-line').forEach(b => b.addEventListener('click', () => gotoLine(+b.dataset.line)));
  }

  function gotoLine(n) {
    const lines = el.input.value.split('\n');
    const start = lines.slice(0, n - 1).reduce((a, l) => a + l.length + 1, 0);
    el.input.focus();
    el.input.setSelectionRange(start, start + (lines[n - 1] || '').length);
    const lh = parseFloat(getComputedStyle(el.input).lineHeight) || 19;
    el.input.scrollTop = Math.max(0, (n - 4) * lh);
  }

  function renderMapFilter() {
    const cats = [...new Set(state.result.items.map(i => i.category))];
    const cur = el.mapCat.value;
    el.mapCat.innerHTML = `<option value="">${MK.esc(t('All categories'))}</option>` + cats.map(c => `<option value="${c}">${MK.esc(label(c))}</option>`).join('');
    if (cats.includes(cur)) el.mapCat.value = cur;
  }

  function renderMap() {
    const r = state.result;
    if (!r) return;
    el.mapCount.textContent = r.items.length;
    const q = lc(el.mapSearch.value.trim());
    const cf = el.mapCat.value;
    const reveal = el.reveal.checked;
    const rows = r.items.filter(it => {
      if (cf && it.category !== cf) return false;
      if (!q) return true;
      const hay = lc([it.token, it.category, label(it.category), it.rules.join(' '), reveal ? it.original : ''].join(' '));
      return hay.includes(q);
    });
    el.mapBody.innerHTML = rows.map(it => {
      const c = color(it.category);
      const variants = it.variants.length > 1 ? `<div class="mt-0.5 text-[10px] text-slate-400">${MK.esc(t('{n} spellings:', { n: it.variants.length }))} ${it.variants.map(MK.esc).join(', ')}</div>` : '';
      return `<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
        <td class="whitespace-nowrap px-4 py-1.5"><span class="mk-tok mk-code" data-tok="${MK.esc(it.token)}" style="${tokStyle(c)}">${MK.esc(it.token)}</span></td>
        <td class="whitespace-nowrap px-2 py-1.5"><span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full" style="background:${c}"></span>${MK.esc(label(it.category))}</span></td>
        <td class="max-w-[320px] px-2 py-1.5"><div class="mk-code break-all ${reveal ? '' : 'mk-blur'}" title="${reveal ? '' : MK.esc(t('Tick “Show original values” to see it'))}">${MK.esc(it.original)}</div>${reveal ? variants : ''}</td>
        <td class="px-2 py-1.5 text-right tabular-nums">${it.count}</td>
        <td class="px-4 py-1.5 text-slate-500 dark:text-slate-400">${it.rules.map(MK.esc).join('<br>')}</td>
      </tr>`;
    }).join('') || `<tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">${MK.esc(r.items.length ? t('No entries match the filter') : t('Nothing to mask was found'))}</td></tr>`;
  }
  el.mapSearch.addEventListener('input', MK.debounce(renderMap, 150));
  el.mapCat.addEventListener('change', renderMap);
  el.reveal.addEventListener('change', renderMap);

  function renderStats() {
    const s = state.result.stats;
    const chips = Object.entries(s.by_category).sort((a, b) => b[1].count - a[1].count).map(([code, v]) =>
      `<button type="button" data-cat="${code}" class="stat-cat inline-flex items-center gap-1 rounded-full px-2 py-0.5 hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-600" style="background:${color(code)}1f;color:${color(code)}" title="${MK.esc(t('{u} unique values, {c} occurrences', { u: v.unique, c: v.count }))}">
        <b>${v.unique}</b> ${MK.esc(label(code))}</button>`).join('');
    const modeTxt = { log: t('Log'), script: t('Script') }[s.mode] + (state.mode === 'auto' ? ' ' + t('(auto)') : '');
    el.stats.innerHTML = `
      <span title="${MK.esc(t('Detected / selected mode'))}">${MK.esc(t('Mode:'))} <b class="text-slate-700 dark:text-slate-200">${MK.esc(modeTxt)}</b></span>
      <span>${t('{m} masked · {u} unique', { m: `<b class="text-slate-700 dark:text-slate-200">${MK.fmt(s.matches)}</b>`, u: `<b class="text-slate-700 dark:text-slate-200">${MK.fmt(s.unique)}</b>` })}</span>
      <span>${MK.esc(t('{l} lines · {c} chars', { l: MK.fmt(s.lines), c: MK.fmt(s.chars_in) }))}</span>
      <span>${s.ms} ms</span>
      <span class="flex flex-wrap gap-1">${chips}</span>`;
    el.stats.querySelectorAll('.stat-cat').forEach(b => b.addEventListener('click', () => {
      el.mapCat.value = b.dataset.cat; setTab('map'); renderMap();
    }));
  }

  // highlight every occurrence of the clicked token
  document.addEventListener('click', e => {
    const tk = e.target.closest('.mk-tok');
    $$('.mk-tok.mk-hl').forEach(x => x.classList.remove('mk-hl'));
    if (!tk || !tk.dataset.tok) return;
    $$(`.mk-tok[data-tok="${CSS.escape(tk.dataset.tok)}"]`).forEach(x => x.classList.add('mk-hl'));
  });

  // ------------------------------------------------------------ output actions
  el.copyBtn.addEventListener('click', async () => {
    if (!state.result) return;
    await MK.copy(state.result.masked);
    MK.toast(t('Masked output copied to the clipboard'));
  });
  el.dlBtn.addEventListener('click', () => {
    if (!state.result) return;
    MK.download(`maskink-${stamp()}.txt`, state.result.masked);
  });
  el.mapMenuBtn.addEventListener('click', e => { e.stopPropagation(); el.mapMenu.classList.toggle('hidden'); });
  el.mapMenu.addEventListener('click', async e => {
    const k = e.target.closest('[data-map]')?.dataset.map;
    if (!k || !state.result) return;
    el.mapMenu.classList.add('hidden');
    const items = state.result.items;
    if (k === 'json') {
      MK.download(`maskink-mapping-${stamp()}.json`, JSON.stringify({
        app: 'maskink', created_at: new Date().toISOString(),
        items: items.map(i => ({ token: i.token, category: i.category, original: i.original, count: i.count, rules: i.rules }))
      }, null, 2), 'application/json');
    } else if (k === 'csv') {
      const q = v => '"' + String(v).replace(/"/g, '""') + '"';
      const csv = ['token;category;original;count;rules', ...items.map(i => [i.token, i.category, i.original, i.count, i.rules.join(' | ')].map(q).join(';'))].join('\r\n');
      MK.download(`maskink-mapping-${stamp()}.csv`, '﻿' + csv, 'text/csv;charset=utf-8');
    } else if (k === 'prompt') {
      const cats = [...new Set(items.map(i => i.category))].map(c => `${CAT[c]?.token || c}=${label(c)}`).join(', ');
      const pre = t('Sensitive data in the content below has been masked. Each token of the form <CATEGORY_n> stands for one consistent real value (same token = same value). Categories: {cats}. Keep the tokens exactly as they are in your answer and do not try to guess the real values.', { cats }) + '\n\n';
      await MK.copy(pre + '```\n' + state.result.masked + '\n```');
      MK.toast(t('Masked content copied with an explanation'));
    }
  });
  document.addEventListener('click', () => { el.mapMenu.classList.add('hidden'); $('#sampleMenu').classList.add('hidden'); });

  // ------------------------------------------------------------ restore
  function restore() {
    const text = el.restoreIn.value;
    const tokens = Object.keys(state.map).sort((a, b) => b.length - a.length);
    if (!tokens.length) { MK.toast(t('Run a masking first or load a mapping JSON file.'), 'warn'); return; }
    const re = new RegExp(tokens.map(x => x.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|'), 'g');
    const frag = document.createDocumentFragment();
    let last = 0, n = 0, m;
    while ((m = re.exec(text))) {
      frag.appendChild(document.createTextNode(text.slice(last, m.index)));
      const s = document.createElement('span');
      const item = state.result?.items.find(i => i.token === m[0]);
      const c = color(item?.category);
      s.className = 'mk-tok'; s.style.cssText = tokStyle(c); s.title = m[0];
      s.textContent = state.map[m[0]];
      frag.appendChild(s);
      last = m.index + m[0].length; n++;
    }
    frag.appendChild(document.createTextNode(text.slice(last)));
    el.restoreOut.replaceChildren(frag);
    const unknown = (text.replace(re, '').match(/<[A-Z][A-Z0-9_\-]*_\d+>/g) || []);
    el.restoreInfo.textContent = t('{n} tokens restored', { n }) + (unknown.length ? ' · ' + t('{n} unknown tokens:', { n: unknown.length }) + ' ' + [...new Set(unknown)].slice(0, 5).join(', ') : '');
    state.restored = el.restoreOut.textContent;
  }
  $('#restoreBtn').addEventListener('click', restore);
  $('#restoreCopy').addEventListener('click', async () => {
    if (!state.restored) return;
    await MK.copy(state.restored); MK.toast(t('Restored text copied'));
  });
  $('#mapFile').addEventListener('change', async e => {
    const f = e.target.files[0]; if (!f) return;
    try {
      const j = JSON.parse(await MK.readFile(f));
      if (!Array.isArray(j.items)) throw new Error(t('Invalid mapping file'));
      state.map = {}; j.items.forEach(i => { state.map[i.token] = i.original; });
      if (!state.result) state.result = { items: j.items.map(i => ({ ...i, variants: [i.original], rules: i.rules || [] })) };
      MK.toast(t('{n} mappings loaded', { n: j.items.length }));
    } catch (err) { MK.toast(err.message, 'err'); }
    e.target.value = '';
  });

  // ------------------------------------------------------------ input
  function updateCount() {
    const n = el.input.value.length;
    const kb = new Blob([el.input.value]).size / 1024;
    el.inCount.textContent = t('{c} characters · {l} lines', { c: MK.fmt(n), l: MK.fmt(el.input.value ? el.input.value.split('\n').length : 0) });
    el.inCount.classList.toggle('text-rose-500', kb > B.maxKb);
  }
  const liveRun = MK.debounce(() => { if (state.live && el.input.value.trim()) run(); }, 700);
  el.input.addEventListener('input', () => { updateCount(); liveRun(); });
  el.input.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); run(); }
    if (e.key === 'Tab' && !e.shiftKey) {
      e.preventDefault();
      const s = el.input.selectionStart;
      el.input.setRangeText('\t', s, el.input.selectionEnd, 'end');
      updateCount();
    }
  });
  el.maskBtn.addEventListener('click', run);
  $('#clearBtn').addEventListener('click', () => {
    el.input.value = ''; updateCount(); el.input.focus();
    state.result = null; state.map = {};
    el.maskedOut.classList.add('hidden'); el.empty.classList.remove('hidden');
    el.originalOut.textContent = ''; el.mapBody.innerHTML = ''; el.mapCount.textContent = '0';
    el.notice.classList.add('hidden');
    el.stats.innerHTML = `<span>${MK.esc(t('Not run yet'))}</span>`;
    [el.copyBtn, el.dlBtn, el.mapMenuBtn].forEach(b => b.disabled = true);
  });

  $('#liveChk').checked = state.live;
  $('#liveChk').addEventListener('change', e => { state.live = e.target.checked; MK.store.set('live', state.live); if (state.live) liveRun(); });
  $('#propChk').checked = state.propagate;
  $('#propChk').addEventListener('change', e => { state.propagate = e.target.checked; MK.store.set('propagate', state.propagate); if (state.result) run(); });

  $('#sampleBtn').addEventListener('click', e => { e.stopPropagation(); $('#sampleMenu').classList.toggle('hidden'); });
  $$('[data-sample]').forEach(b => b.addEventListener('click', () => {
    el.input.value = B.samples[b.dataset.sample] || '';
    updateCount(); run();
  }));

  async function loadFile(f) {
    if (f.size > B.maxKb * 1024 * 1.2) { MK.toast(t('File is too large (limit ~{kb} KB)', { kb: B.maxKb }), 'err'); return; }
    el.input.value = await MK.readFile(f);
    updateCount();
    MK.toast(t('{name} loaded', { name: f.name }));
    run();
  }
  $('#fileInput').addEventListener('change', e => { if (e.target.files[0]) loadFile(e.target.files[0]); e.target.value = ''; });
  const drop = $('#dropHint');
  el.input.addEventListener('dragover', e => { e.preventDefault(); drop.classList.remove('hidden'); drop.classList.add('flex'); });
  el.input.addEventListener('dragleave', () => { drop.classList.add('hidden'); drop.classList.remove('flex'); });
  el.input.addEventListener('drop', e => {
    e.preventDefault(); drop.classList.add('hidden'); drop.classList.remove('flex');
    if (e.dataTransfer.files[0]) loadFile(e.dataTransfer.files[0]);
  });

  function stamp() {
    const d = new Date(), p = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}-${p(d.getHours())}${p(d.getMinutes())}`;
  }

  // ------------------------------------------------------------ start
  renderModes();
  renderChips();
  setTab('masked');
  updateCount();
  if (B.needLogin) {
    el.notice.innerHTML = `<div class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800">${MK.esc(t('Masking requires sign-in on this installation.'))} <a class="underline" href="settings.php">${MK.esc(t('Sign in'))}</a></div>`;
    el.notice.classList.remove('hidden');
  }
})(window.MK);
