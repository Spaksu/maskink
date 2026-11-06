/* Maskink – chat: messages are masked by Maskink, replies are restored in the browser */
(function (MK) {
  'use strict';
  const $ = s => document.querySelector(s);
  const t = MK.t;
  const B = MK.boot;
  const CAT = Object.fromEntries(B.categories.map(c => [c.code, c]));
  const color = code => (CAT[code] && CAT[code].color) || '#64748b';
  const esc = MK.esc;

  const el = {
    list: $('#chatList'), side: $('#chatSide'), messages: $('#messages'), welcome: $('#welcome'), scroller: $('#scroller'),
    prompt: $('#prompt'), send: $('#sendBtn'), stop: $('#stopBtn'), model: $('#modelSel'), info: $('#promptInfo'),
    aiView: $('#aiView'), preview: $('#previewChk'), mapCount: $('#mapCount'), attach: $('#attachBar')
  };
  MK.vision.color = color;

  // ------------------------------------------------------------ state and local storage
  const S = {
    chats: MK.store.get('chats', []),
    cur: MK.store.get('chat-cur', null),
    models: [],
    busy: null,              // { ctrl, msg }
    pending: [],             // screenshots waiting to be sent (already covered)
    aiView: MK.store.get('chat-aiview', false)
  };
  function save() {
    for (;;) {
      try { localStorage.setItem('mki-chats', JSON.stringify(S.chats)); break; }
      catch (e) {
        // out of space: drop original images of the oldest chats first, then covered images (text and mapping are kept)
        const all = S.chats.slice().sort((a, b) => a.updated - b.updated).flatMap(c => c.messages).flatMap(m => m.attach || []);
        const o = all.find(a => a.orig), old = o ? null : all.find(a => a.img);
        if (!o && !old) { MK.toast(t('Browser storage is full; delete old chats.'), 'err', 6000); break; }
        if (o) o.orig = null; else { old.img = null; old.dropped = true; }
        if (!S.dropWarned) { S.dropWarned = true; MK.toast(t('Browser storage is full, so some images in older chats were removed from this browser.'), 'warn', 6000); }
      }
    }
    MK.store.set('chat-cur', S.cur);
  }
  const chat = () => S.chats.find(c => c.id === S.cur) || null;
  const uid = () => Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
  const title = c => c.untitled ? t('New chat') : c.title;

  function newChat() {
    const c = { id: uid(), title: '', untitled: true, model: el.model.value || B.defaultModel, created: Date.now(), updated: Date.now(), messages: [], map: [] };
    S.chats.unshift(c); S.cur = c.id; save(); renderAll();
    el.prompt.focus();
    return c;
  }

  // ------------------------------------------------------------ mapping and restore
  function mergeItems(c, items) {
    const byTok = new Map(c.map.map(i => [i.token, i]));
    (items || []).forEach(it => {
      const ex = byTok.get(it.token);
      if (ex) { ex.variants = [...new Set([...(ex.variants || []), ...(it.variants || [])])]; return; }
      const n = { token: it.token, category: it.category, label: it.label, original: it.original, variants: it.variants || [it.original] };
      c.map.push(n); byTok.set(n.token, n);
    });
  }
  const reEsc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const restoreCache = new WeakMap();
  /** Regex that finds mapped tokens (and slightly mangled forms of them) in the model's reply */
  function restorer(c) {
    const hit = restoreCache.get(c.map);
    if (hit && hit.n === c.map.length) return hit;
    const forms = new Map();
    c.map.forEach(i => {
      forms.set(i.token, i);
      const m = /^([^\w\s]{1,2})([\w\-:.]+?)([^\w\s]{1,2})$/.exec(i.token);
      if (m) forms.set('&lt;' + m[2] + '&gt;', i);   // form the model wrote HTML-escaped
    });
    const keys = [...forms.keys()].sort((a, b) => b.length - a.length);
    const loose = c.map.map(i => /^([^\w\s]{1,2})([\w\-:.]+?)([^\w\s]{1,2})$/.exec(i.token)).filter(Boolean)
      .map(m => m[2]).filter(x => /_\d+$|:[0-9a-f]{6,}$/i.test(x)).sort((a, b) => b.length - a.length);
    const parts = [];
    if (keys.length) parts.push(keys.map(reEsc).join('|'));
    if (loose.length) parts.push('(?<![\\w<\\[{])(?:' + loose.map(reEsc).join('|') + ')(?![\\w>\\]}])');
    const byLoose = new Map();
    c.map.forEach(i => { const m = /^([^\w\s]{1,2})([\w\-:.]+?)([^\w\s]{1,2})$/.exec(i.token); if (m) byLoose.set(m[2], i); });
    const r = { n: c.map.length, re: parts.length ? new RegExp(parts.join('|'), 'g') : null, forms, byLoose };
    restoreCache.set(c.map, r);
    return r;
  }
  /**
   * Splits text into parts: [{t:'plain'}, {tok, item}] – tokens to restore are marked.
   * Token-shaped strings that are not in the mapping (made up by the model) come back as {unknown}.
   */
  function splitTokens(c, text) {
    const R = restorer(c);
    const out = [];
    let last = 0, m;
    if (R.re) {
      R.re.lastIndex = 0;
      while ((m = R.re.exec(text))) {
        if (m.index > last) out.push({ t: text.slice(last, m.index) });
        out.push({ tok: m[0], item: R.forms.get(m[0]) || R.byLoose.get(m[0]) });
        last = m.index + m[0].length;
      }
    }
    if (last < text.length) out.push({ t: text.slice(last) });
    // unknown tokens
    const res = [];
    out.forEach(p => {
      if (p.tok) { res.push(p); return; }
      let l = 0; const re = /<[A-Z][A-Z0-9_\-]*_\d+>/g; let u;
      while ((u = re.exec(p.t))) { if (u.index > l) res.push({ t: p.t.slice(l, u.index) }); res.push({ unknown: u[0] }); l = u.index + u[0].length; }
      if (l < p.t.length) res.push({ t: p.t.slice(l) });
    });
    return res;
  }
  const restoreText = (c, text) => splitTokens(c, text).map(p => p.tok ? p.item.original : (p.unknown || p.t)).join('');
  const catLabel = item => (CAT[item.category] && CAT[item.category].label) || item.label || item.category;
  const tokHtml = (item, tok, masked) => {
    const col = color(item.category);
    return masked
      ? `<span class="mk-tok" style="color:${col};background:${col}1f" title="${esc(item.original)} · ${esc(catLabel(item))}">${esc(item.token)}</span>`
      : `<span class="mk-tok" style="color:${col};background:${col}1f" title="${esc(tok)} · ${esc(catLabel(item))} · ${esc(t('restored'))}">${esc(item.original)}</span>`;
  };
  const unkHtml = u => `<span class="mk-tok mk-tok-unknown" title="${esc(t('Token not in the mapping (the model may have made it up)'))}">${esc(u)}</span>`;

  // ------------------------------------------------------------ small, safe Markdown
  // Tokens are first replaced by private-use placeholders (U+E000..U+E001) and become spans after Markdown rendering.
  function md(src, slot) {
    const lines = src.replace(/\r\n?/g, '\n').split('\n');
    const html = [];
    const inline = s => {
      const codes = [];
      s = s.replace(/`([^`\n]+)`/g, (_, x) => { codes.push(x); return '' + (codes.length - 1) + ''; });
      s = esc(s)
        .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|[^\w*])\*([^*\n]+)\*(?!\w)/g, '$1<em>$2</em>')
        .replace(/(^|[^\w])_([^_\n]+)_(?!\w)/g, '$1<em>$2</em>')
        .replace(/~~([^~\n]+)~~/g, '<del>$1</del>')
        .replace(/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
      return s.replace(/(\d+)/g, (_, i) => '<code>' + esc(codes[+i]) + '</code>');
    };
    let i = 0;
    while (i < lines.length) {
      const ln = lines[i];
      let m;
      if ((m = /^\s*(`{3,}|~{3,})\s*([\w+#.\-]*)/.exec(ln))) {
        const fence = m[1]; const lang = m[2]; const body = [];
        i++;
        while (i < lines.length && !lines[i].trim().startsWith(fence)) body.push(lines[i++]);
        i++;
        html.push(`<div class="mk-codeblock"><div class="mk-codehead"><span>${esc(lang || t('code'))}</span><button type="button" class="mk-copy" data-copy>${esc(t('Copy'))}</button></div><pre class="mk-code">${esc(body.join('\n'))}</pre></div>`);
        continue;
      }
      if (/^\s*$/.test(ln)) { i++; continue; }
      if ((m = /^(#{1,6})\s+(.*)$/.exec(ln))) { const n = Math.min(6, m[1].length + 1); html.push(`<h${n}>${inline(m[2])}</h${n}>`); i++; continue; }
      if (/^\s*([-*_])(\s*\1){2,}\s*$/.test(ln)) { html.push('<hr>'); i++; continue; }
      if (/^\s*>/.test(ln)) {
        const q = [];
        while (i < lines.length && /^\s*>/.test(lines[i])) q.push(lines[i++].replace(/^\s*>\s?/, ''));
        html.push('<blockquote>' + md(q.join('\n')) + '</blockquote>');
        continue;
      }
      if (/^\s*\|.*\|\s*$/.test(ln) && i + 1 < lines.length && /^\s*\|?\s*:?-{2,}/.test(lines[i + 1])) {
        const row = r => r.trim().replace(/^\||\|$/g, '').split('|').map(x => x.trim());
        const head = row(ln); i += 2;
        const rows = [];
        while (i < lines.length && /^\s*\|.*\|\s*$/.test(lines[i])) rows.push(row(lines[i++]));
        html.push('<div class="mk-mdtable"><table><thead><tr>' + head.map(h => `<th>${inline(h)}</th>`).join('') + '</tr></thead><tbody>' +
          rows.map(r => '<tr>' + r.map(d => `<td>${inline(d)}</td>`).join('') + '</tr>').join('') + '</tbody></table></div>');
        continue;
      }
      if ((m = /^(\s*)([-*+]|\d+[.)])\s+/.exec(ln))) {
        const ordered = /\d/.test(m[2]);
        const start = ordered ? parseInt(m[2], 10) : 1;
        const items = [];
        while (i < lines.length) {
          const l = lines[i];
          const mm = /^(\s*)([-*+]|\d+[.)])\s+(.*)$/.exec(l);
          if (mm && (/\d/.test(mm[2]) === ordered || mm[1].length > 1)) {
            if (mm[1].length > 1 && items.length) items[items.length - 1].sub.push(l.slice(Math.min(mm[1].length, 4)));
            else items.push({ text: mm[3], sub: [] });
            i++;
          } else if (/^\s{2,}\S/.test(l) && items.length) { items[items.length - 1].sub.push(l.replace(/^\s{2,4}/, '')); i++; }
          else if (/^\s*$/.test(l) && items.length) {
            // blank line: the list continues if the next non-empty line is indented or an item of the same kind
            let j = i + 1;
            while (j < lines.length && /^\s*$/.test(lines[j])) j++;
            const nx = j < lines.length ? lines[j] : '';
            const nm = /^(\s*)([-*+]|\d+[.)])\s+/.exec(nx);
            if (/^\s{2,}\S/.test(nx) || (nm && /\d/.test(nm[2]) === ordered)) { items[items.length - 1].sub.push(''); i = j; }
            else break;
          }
          else break;
        }
        const tag = ordered ? 'ol' : 'ul';
        html.push(`<${tag}${ordered && start !== 1 ? ` start="${start}"` : ''}>` + items.map(it => `<li>${inline(it.text)}${it.sub.some(s => s.trim()) ? md(it.sub.join('\n')) : ''}</li>`).join('') + `</${tag}>`);
        continue;
      }
      const para = [];
      while (i < lines.length && !/^\s*$/.test(lines[i]) && !/^\s*(`{3,}|~{3,}|#{1,6}\s|>|\|)/.test(lines[i]) && !/^\s*([-*+]|\d+[.)])\s+/.test(lines[i])) para.push(lines[i++]);
      if (!para.length) para.push(lines[i++]);
      html.push('<p>' + para.map(inline).join('<br>') + '</p>');
    }
    return html.join('');
  }
  /** Renders Markdown while keeping tokens; with masked=false the real values are shown */
  function renderRich(c, text, masked) {
    const parts = splitTokens(c, text);
    const slots = [];
    const src = parts.map(p => {
      if (p.t !== undefined) return p.t;
      slots.push(p);
      return '' + (slots.length - 1) + '';
    }).join('');
    return md(src).replace(/(\d+)/g, (_, k) => {
      const p = slots[+k];
      return p.unknown ? unkHtml(p.unknown) : tokHtml(p.item, p.tok, masked);
    });
  }
  /** User message: highlighted plain text from Maskink segments */
  function renderUser(m, masked) {
    if (!m.segments) return esc(masked ? (m.masked || m.raw) : m.raw);
    return m.segments.map(s => {
      if (s.t !== undefined) return esc(s.t);
      const col = color(s.c);
      return masked
        ? `<span class="mk-tok" style="color:${col};background:${col}1f" title="${esc(s.o)} · ${esc(s.r)}">${esc(s.m)}</span>`
        : `<span class="mk-tok" style="color:${col};background:${col}1f" title="${esc(t('Sent to the AI as {tok}', { tok: s.m }))} · ${esc(s.r)}">${esc(s.o)}</span>`;
    }).join('');
  }

  // ------------------------------------------------------------ rendering
  function renderList() {
    el.list.innerHTML = S.chats.length ? S.chats.map(c => `
      <div class="group flex items-center rounded-lg ${c.id === S.cur ? 'bg-violet-50 text-violet-800 dark:bg-violet-500/15 dark:text-violet-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}">
        <button type="button" data-open="${c.id}" class="min-w-0 flex-1 truncate px-3 py-2 text-left" title="${esc(title(c))}">${esc(title(c))}</button>
        <button type="button" data-del="${c.id}" class="mr-1 hidden rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 group-hover:block dark:hover:bg-rose-500/10" title="${esc(t('Delete'))}">
          <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
        </button>
      </div>`).join('') : `<div class="px-3 py-2 text-xs text-slate-400">${esc(t('No chats yet.'))}</div>`;
  }

  const fmtMs = ms => ms >= 1000 ? (ms / 1000).toLocaleString(MK.locale, { maximumFractionDigits: 1 }) + ' ' + t('s') : ms + ' ms';
  function msgHtml(c, m, idx) {
    const masked = S.aiView;
    if (m.role === 'user') {
      const n = (m.items || []).length;
      // with "What the AI sees" off, show the original image (if any); with it on, the covered image
      const att = (m.attach || []).map((a, i) => {
        const src = masked ? a.img : (a.orig || a.img);
        const which = !src ? '' : masked ? `<span class="font-medium text-violet-600 dark:text-violet-300">${esc(t('What the AI sees'))}</span> · `
          : a.orig ? `<span class="font-medium text-slate-500">${esc(t('Original image (only in this browser)'))}</span> · ` : '';
        return `<div class="mk-attach">
          ${src ? `<img src="${src}" alt="${esc(t('Attachment {n}', { n: i + 1 }))}" data-zoom="${idx}:${i}" class="max-h-48 max-w-full cursor-zoom-in rounded-md border border-slate-200 dark:border-slate-700">`
            : `<div class="rounded-md border border-dashed border-slate-300 px-3 py-4 text-xs text-slate-400 dark:border-slate-600">${esc(a.dropped ? t('The image was removed from this browser to free storage') : t('No image'))}</div>`}
          <div class="mt-1 text-[11px] text-slate-400">${which}${esc(a.sent ? t('Covered image + recognized text were sent') : t('The model does not support images, so only the recognized text was sent'))}</div>
          ${a.segments && a.segments.length ? `<details class="mt-1 text-xs"><summary class="cursor-pointer text-slate-500">${esc(t('Text read from the image'))}</summary><div class="mk-pre mk-code mt-1 max-h-60 overflow-auto rounded bg-white/60 p-2 dark:bg-slate-900/60">${renderUser({ raw: a.ocr || '', segments: a.segments }, masked)}</div></details>` : ''}
        </div>`;
      }).join('');
      return `<div class="flex justify-end" data-idx="${idx}">
        <div class="max-w-[85%]">
          <div class="mk-user-bubble">${att ? `<div class="mb-2 grid gap-2">${att}</div>` : ''}${m.raw ? `<div class="mk-pre text-sm">${renderUser(m, masked)}</div>` : ''}</div>
          <div class="mt-1 flex items-center justify-end gap-2 text-[11px] text-slate-400">
            ${masked ? `<span class="font-medium text-violet-600 dark:text-violet-300">${esc(t('As sent to the AI'))}</span>` : ''}
            <span class="inline-flex items-center gap-1 ${n ? 'text-emerald-600 dark:text-emerald-400' : ''}" title="${esc((m.items || []).map(i => i.token).join(', '))}">
              <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              ${esc(n ? t('{n} values masked', { n }) : t('No values to mask'))}</span>
            ${(m.warnings || []).length ? `<span class="text-amber-600 dark:text-amber-400" title="${esc(m.warnings.map(w => t('line {n}: {kw}', { n: w.line, kw: w.keyword })).join('\n'))}">⚠ ${esc(t('{n} risk warnings', { n: m.warnings.length }))}</span>` : ''}
          </div>
        </div>
      </div>`;
    }
    const unknown = [...new Set(splitTokens(c, m.content || '').filter(p => p.unknown).map(p => p.unknown))];
    const thinking = m.reasoning ? `<details class="mk-think" ${m.streaming && !m.content ? 'open' : ''}><summary>${esc(t('Reasoning'))}${m.streaming && !m.content ? '…' : ''}</summary><div class="mk-pre">${renderUser({ raw: masked ? m.reasoning : restoreText(c, m.reasoning) }, false)}</div></details>` : '';
    const body = m.content ? renderRich(c, m.content, masked) : (m.streaming ? '<div class="mk-typing"><span></span><span></span><span></span></div>' : '');
    const meta = [m.modelName, m.ms ? fmtMs(m.ms) : '', m.usage ? t('{n} tokens', { n: MK.fmt(m.usage.total_tokens) }) : ''].filter(Boolean).join(' · ');
    return `<div class="flex gap-3" data-idx="${idx}">
      <div class="mt-0.5 flex-none">${document.getElementById('aiAvatar').innerHTML}</div>
      <div class="min-w-0 flex-1">
        ${thinking}
        <div class="mk-md ${masked ? 'mk-md-masked' : ''}">${body}</div>
        ${m.error ? `<div class="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-500/10 dark:text-rose-300">${esc(m.error)}</div>` : ''}
        ${m.aborted ? `<div class="mt-1 text-xs text-slate-400">${esc(t('Reply stopped.'))}</div>` : ''}
        ${unknown.length ? `<div class="mt-2 text-xs text-amber-700 dark:text-amber-300">⚠ ${esc(t('The model used {n} tokens that are not in the mapping:', { n: unknown.length }))} ${unknown.slice(0, 6).map(esc).join(', ')} — ${esc(t('they could not be restored.'))}</div>` : ''}
        ${!m.streaming ? `<div class="mt-1.5 flex flex-wrap items-center gap-1 text-[11px] text-slate-400">
          ${masked ? `<span class="mr-1 font-medium text-violet-600 dark:text-violet-300">${esc(t('As received from the AI'))}</span>` : ''}
          <button type="button" class="mk-act" data-act="copy" title="${esc(t('Copy with the real values'))}">${esc(t('Copy'))}</button>
          <button type="button" class="mk-act" data-act="copym" title="${esc(t('Copy in masked form'))}">${esc(t('Copy masked'))}</button>
          ${idx === c.messages.length - 1 ? `<button type="button" class="mk-act" data-act="regen">${esc(t('Regenerate'))}</button>` : ''}
          ${meta ? `<span class="ml-1">${esc(meta)}</span>` : ''}
        </div>` : ''}
      </div>
    </div>`;
  }

  function renderMessages(stick) {
    const c = chat();
    const near = el.scroller.scrollHeight - el.scroller.scrollTop - el.scroller.clientHeight < 120;
    if (!c || !c.messages.length) {
      el.messages.innerHTML = '';
      el.welcome.classList.remove('hidden');
    } else {
      el.welcome.classList.add('hidden');
      el.messages.innerHTML = c.messages.map((m, i) => msgHtml(c, m, i)).join('');
    }
    el.mapCount.textContent = c ? c.map.length : 0;
    if (stick || near) el.scroller.scrollTop = el.scroller.scrollHeight;
  }
  function renderAll() { renderList(); renderMessages(true); syncModel(); }

  let raf = 0;
  const renderSoon = () => { if (!raf) raf = requestAnimationFrame(() => { raf = 0; renderMessages(false); }); };

  // ------------------------------------------------------------ models
  const ctxLabel = n => n >= 1000 ? Math.round(n / 1024) + 'K' : '';
  async function loadModels(refresh) {
    try {
      const d = await MK.api('chat.models', { refresh: !!refresh });
      S.models = d.models;
      const groups = {};
      d.models.forEach(m => (groups[m.provider] = groups[m.provider] || []).push(m));
      el.model.innerHTML = Object.entries(groups).map(([g, ms]) => `<optgroup label="${esc(g)}">` +
        ms.map(m => `<option value="${esc(m.id)}">${esc(m.name)}${m.ctx ? ' · ' + ctxLabel(m.ctx) : ''}${m.vision ? ' · ' + esc(t('vision')) : ''}</option>`).join('') + '</optgroup>').join('');
      B.defaultModel = d.default || B.defaultModel;
      syncModel(); renderAttach();
      if ((d.errors || []).length) MK.toast(t('Some connections could not be reached:') + ' ' + d.errors.join(' · '), 'warn', 7000);
      else if (refresh) MK.toast(t('Model list refreshed'));
    } catch (e) {
      el.model.innerHTML = `<option value="">${esc(t('Could not load the model list'))}</option>`;
      MK.toast(e.message, 'err', 6000);
    }
  }
  function syncModel() {
    if (!S.models.length) return;
    const c = chat();
    const want = (c && c.model) || MK.store.get('chat-model', '') || B.defaultModel;
    el.model.value = S.models.some(m => m.id === want) ? want : (S.models.some(m => m.id === B.defaultModel) ? B.defaultModel : S.models[0].id);
  }
  el.model.addEventListener('change', () => {
    MK.store.set('chat-model', el.model.value);
    const c = chat(); if (c) { c.model = el.model.value; save(); }
    renderAttach();
  });
  const visionModel = () => !!(S.models.find(m => m.id === el.model.value) || {}).vision;
  $('#modelRefresh').addEventListener('click', () => loadModels(true));

  // ------------------------------------------------------------ sending
  const known = c => c.map.map(i => ({ token: i.token, category: i.category, original: i.original, variants: i.variants }));
  const history = c => c.messages.filter(m => !m.error || m.content).map(m => m.role === 'user'
    ? { role: 'user', content: m.modelText || m.masked, images: (m.attach || []).filter(a => a.sent && a.img).map(a => a.img) }
    : { role: m.role, content: m.content }).filter(m => m.content);
  const asKnown = items => items.map(i => ({ token: i.token, category: i.category, original: i.original, variants: i.variants }));

  function setBusy(b) {
    el.send.classList.toggle('hidden', !!b);
    el.stop.classList.toggle('hidden', !b); el.stop.classList.toggle('inline-flex', !!b);
  }

  async function preview(c, text) {
    const d = await MK.api('mask', { text, known: known(c) });
    const dlg = $('#previewDlg');
    $('#previewBody').innerHTML = renderUser({ raw: text, segments: d.segments }, true);
    $('#previewInfo').textContent = t('{n} distinct values masked · {m} matches', { n: d.items.length, m: MK.fmt(d.stats.matches) });
    const w = $('#previewWarn');
    w.classList.toggle('hidden', !d.warnings.length);
    w.textContent = d.warnings.length ? '⚠ ' + t('Suspicious keyword on unmasked lines:') + ' ' + d.warnings.slice(0, 5).map(x => t('line {n} ({kw})', { n: x.line, kw: x.keyword })).join(', ') : '';
    dlg.showModal();
    return new Promise(res => {
      const done = v => { dlg.close(); $('#previewSend').onclick = null; res(v); };
      $('#previewSend').onclick = () => done(true);
      dlg.querySelector('[data-close]').onclick = () => done(false);
      dlg.oncancel = () => done(false);
    });
  }

  /**
   * reuse: attachments of the previous message when regenerating (images are already stored covered).
   * Otherwise pending screenshots are re-masked with the current mapping right before sending.
   */
  async function send(text, reuse) {
    if (S.busy || S.preparing) return;
    text = text.replace(/\s+$/, '');
    const pend = reuse ? [] : S.pending.slice();
    if (!text.trim() && !pend.length && !(reuse && reuse.length)) return;
    if (B.needLogin) { MK.toast(t('Sign in on the Settings screen before chatting.'), 'warn'); return; }
    if (new Blob([text]).size > B.maxKb * 1024) { MK.toast(t('The message is too large (limit {kb} KB).', { kb: B.maxKb }), 'err'); return; }
    if (!el.model.value) { MK.toast(t('Choose a model.'), 'warn'); return; }
    const c = chat() || newChat();
    if (el.preview.checked && text.trim()) {
      try { if (!(await preview(c, text))) return; }
      catch (e) { MK.toast(e.message, 'err'); return; }
    }
    c.model = el.model.value;
    const vis = visionModel();
    let attach = [], stored = [];
    if (reuse && reuse.length) {
      attach = reuse.map(a => ({ image: vis && a.img ? a.img : '', ocr: a.ocr || '', hints: a.hints || [], allow: a.allow || [] }));
      stored = reuse.map(a => ({ img: a.img, orig: a.orig, ocr: a.ocr || '', hints: a.hints || [], allow: a.allow || [], dropped: a.dropped, sent: vis && !!a.img }));
    } else if (pend.length) {
      S.preparing = true; el.send.disabled = true;
      try {
        // the server applies the manual fixes (hints/allow) of all attachments to the message and to each attachment; do the same here
        const extra = { hints: pend.flatMap(p => p.hints.map(h => ({ category: h.category, value: h.value }))), allow: pend.flatMap(p => p.allow) };
        let k = known(c);
        if (text.trim()) { const d = await MK.api('mask', { text, known: k, mode: 'auto', ...extra, preview: true }); k = k.concat(asKnown(d.items)); }
        for (const p of pend) {
          const f = await MK.vision.finalize(p, k, extra);
          k = k.concat(asKnown(f.items));
          const hints = p.hints.map(h => ({ category: h.category, value: h.value }));
          attach.push({ image: vis ? f.image : '', ocr: f.ocr, hints, allow: p.allow });
          stored.push({ img: f.image, orig: f.orig, ocr: f.ocr, hints, allow: p.allow, sent: vis });
        }
      } catch (e) { MK.toast(t('Could not prepare the screenshots:') + ' ' + e.message, 'err', 6000); return; }
      finally { S.preparing = false; el.send.disabled = false; }
      S.pending = []; renderAttach();
    }
    const modelName = (S.models.find(m => m.id === c.model) || {}).name || c.model;
    const hist = history(c);
    // to keep requests small, only the last few images of the history are sent (the server limits this too)
    let budget = Math.max(0, 6 - attach.filter(a => a.image).length);
    for (let i = hist.length - 1; i >= 0; i--) { if (hist[i].images) { hist[i].images = hist[i].images.slice(0, budget); budget -= hist[i].images.length; } }
    const user = { role: 'user', raw: text, masked: null, segments: null, items: [], attach: stored };
    const bot = { role: 'assistant', content: '', reasoning: '', streaming: true, modelName };
    c.messages.push(user, bot);
    if (c.untitled || c.title === 'Yeni sohbet') { c.title = (text.replace(/\s+/g, ' ').trim() || t('Screenshot')).slice(0, 60); c.untitled = false; }
    c.updated = Date.now();
    el.prompt.value = ''; grow(); renderList(); renderMessages(true);

    const ctrl = new AbortController();
    S.busy = { ctrl }; setBusy(true);
    let gotMask = false;
    try {
      const res = await fetch('api.php?action=chat.send', {
        method: 'POST', signal: ctrl.signal, credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': MK.csrf },
        body: JSON.stringify({ model: c.model, text, history: hist, known: known(c), attach })
      });
      if (!(res.headers.get('content-type') || '').includes('text/event-stream')) {
        let j = null; try { j = await res.json(); } catch (e) {}
        throw new Error((j && j.error) || t('Server error (HTTP {status})', { status: res.status }));
      }
      const reader = res.body.getReader();
      const dec = new TextDecoder();
      let buf = '';
      for (;;) {
        const { value, done } = await reader.read();
        if (done) break;
        buf += dec.decode(value, { stream: true });
        let p;
        while ((p = buf.indexOf('\n\n')) >= 0) {
          const block = buf.slice(0, p); buf = buf.slice(p + 2);
          let ev = 'message', data = '';
          block.split('\n').forEach(l => { if (l.startsWith('event:')) ev = l.slice(6).trim(); else if (l.startsWith('data:')) data += l.slice(5).trim(); });
          if (!data) continue;
          const d = JSON.parse(data);
          if (ev === 'mask') {
            gotMask = true;
            user.masked = d.masked; user.segments = d.segments; user.items = d.items; user.warnings = d.warnings;
            user.modelText = d.model_text;
            (d.attach || []).forEach((x, i) => { if (user.attach[i]) { user.attach[i].segments = x.segments; user.attach[i].masked = x.masked; } });
            if (!d.images_sent) user.attach.forEach(a => { a.sent = false; });
            mergeItems(c, d.items);
            save();
          } else if (ev === 'content') bot.content += d.t;
          else if (ev === 'reasoning') bot.reasoning += d.t;
          else if (ev === 'done') { bot.ms = d.ms; bot.usage = d.usage; bot.finish = d.finish; }
          else if (ev === 'error') bot.error = d.message;
          renderSoon();
        }
      }
      if (!bot.content && !bot.error) bot.error = t('The model returned an empty reply.');
      if (bot.finish === 'length') bot.error = t('The reply was cut off because it reached the model\'s output limit.');
    } catch (e) {
      if (e.name === 'AbortError') bot.aborted = true;
      else bot.error = e.message;
      if (!gotMask) { // the message failed before it was masked: roll it back
        c.messages.splice(c.messages.indexOf(user), 2);
        el.prompt.value = text; grow();
        if (pend.length) { S.pending = pend; renderAttach(); }
        if (!bot.aborted) MK.toast(bot.error, 'err', 6000);
      }
    } finally {
      bot.streaming = false;
      S.busy = null; setBusy(false);
      c.updated = Date.now();
      save(); renderMessages(false);
    }
  }

  el.send.addEventListener('click', () => send(el.prompt.value));
  el.stop.addEventListener('click', () => S.busy && S.busy.ctrl.abort());
  el.prompt.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(el.prompt.value); }
  });
  function grow() {
    el.prompt.style.height = 'auto';
    el.prompt.style.height = Math.min(el.prompt.scrollHeight, window.innerHeight * 0.4) + 'px';
    const n = el.prompt.value.length;
    el.info.textContent = n > 200 ? t('{n} characters', { n: MK.fmt(n) }) : '';
  }
  el.prompt.addEventListener('input', grow);

  // attaching files / drag and drop
  async function addImage(f) {
    if (S.pending.length >= 4) { MK.toast(t('At most 4 images can be attached to a message.'), 'warn'); return; }
    if (f.size > 25 * 1024 * 1024) { MK.toast(t('{name} is too large (limit 25 MB).', { name: f.name || t('Image') }), 'err'); return; }
    const c = chat();
    const att = await MK.vision.open(f, c ? known(c) : []);
    if (!att) return;
    att.thumb = MK.vision.thumb(att);
    S.pending.push(att); renderAttach();
  }
  function renderAttach() {
    el.attach.classList.toggle('hidden', !S.pending.length);
    el.attach.classList.toggle('flex', !!S.pending.length);
    const vis = visionModel();
    el.attach.innerHTML = S.pending.map((a, i) => `
      <div class="relative flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 p-1.5 pr-7 text-[11px] dark:border-slate-700 dark:bg-slate-800">
        <img src="${a.thumb}" alt="" data-zoom="p:${i}" class="h-12 w-16 cursor-zoom-in rounded object-cover">
        <div class="leading-tight">
          <div class="max-w-[12rem] truncate font-medium text-slate-700 dark:text-slate-200">${esc(a.name)}</div>
          <div class="text-slate-500">${esc(t('{n} areas covered', { n: a.auto.length + a.manual.length }))} · <button type="button" data-reedit="${i}" class="font-medium text-violet-600 hover:underline dark:text-violet-300">${esc(t('Edit'))}</button></div>
          <div class="${vis ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}">${esc(vis ? t('Image + recognized text will be sent') : t('The model does not support images: only the recognized text will be sent'))}</div>
        </div>
        <button type="button" data-unattach="${i}" class="absolute right-1 top-1 rounded p-0.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="${esc(t('Remove'))}">✕</button>
      </div>`).join('');
  }
  el.attach.addEventListener('click', e => {
    const x = e.target.closest('[data-unattach]');
    if (x) { S.pending.splice(+x.dataset.unattach, 1); renderAttach(); }
    const r = e.target.closest('[data-reedit]');
    if (r) {
      const att = S.pending[+r.dataset.reedit], c = chat();
      MK.vision.open(att, c ? known(c) : []).then(v => { if (v) v.thumb = MK.vision.thumb(v); renderAttach(); });
    }
  });
  async function addFiles(files) {
    for (const f of files) {
      if (/^image\//.test(f.type)) { await addImage(f); continue; }
      if (f.size > B.maxKb * 1024) { MK.toast(t('{name} is too large (limit {kb} KB).', { name: f.name, kb: B.maxKb }), 'err'); continue; }
      const txt = await MK.readFile(f);
      const sep = el.prompt.value && !el.prompt.value.endsWith('\n') ? '\n\n' : '';
      el.prompt.value += `${sep}${f.name}:\n\`\`\`\n${txt.replace(/\s+$/, '')}\n\`\`\`\n`;
    }
    grow(); el.prompt.focus();
  }
  $('#fileIn').addEventListener('change', e => { addFiles([...e.target.files]); e.target.value = ''; });
  const dz = $('#dropZone');
  dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('ring-2', 'ring-violet-500/30'); });
  dz.addEventListener('dragleave', () => dz.classList.remove('ring-2', 'ring-violet-500/30'));
  dz.addEventListener('drop', e => { e.preventDefault(); dz.classList.remove('ring-2', 'ring-violet-500/30'); if (e.dataTransfer.files.length) addFiles([...e.dataTransfer.files]); });
  // pasting a screenshot from the clipboard (Ctrl+V)
  el.prompt.addEventListener('paste', e => {
    const imgs = [...(e.clipboardData?.items || [])].filter(i => i.kind === 'file' && /^image\//.test(i.type)).map(i => i.getAsFile()).filter(Boolean);
    if (!imgs.length) return;
    e.preventDefault();
    addFiles(imgs.map((f, i) => f.name && f.name !== 'image.png' ? f : new File([f], `screenshot-${new Date().toTimeString().slice(0, 8).replace(/:/g, '')}${i ? '-' + i : ''}.png`, { type: f.type })));
  });
  // image zoom: one button switches between the original and what the AI sees
  const Z = { ai: '', orig: '', showOrig: false };
  const zoomShow = () => {
    const both = Z.ai && Z.orig;
    $('#zoomImg').src = Z.showOrig && Z.orig ? Z.orig : Z.ai || Z.orig;
    $('#zoomLabel').textContent = Z.showOrig && Z.orig ? t('Original image (only in this browser)') : t('Image as the AI sees it');
    $('#zoomSwap').classList.toggle('hidden', !both);
    $('#zoomSwap').textContent = Z.showOrig ? t('Show what the AI sees') : t('Show original');
  };
  $('#zoomSwap').addEventListener('click', () => { Z.showOrig = !Z.showOrig; zoomShow(); });
  document.addEventListener('click', e => {
    const im = e.target.closest('img[data-zoom]');
    if (!im) return;
    const [a, b] = im.dataset.zoom.split(':');
    if (a === 'p') { const p = S.pending[+b]; Z.ai = p.thumb; Z.orig = p.base.toDataURL('image/jpeg', 0.85); }
    else { const x = (chat().messages[+a].attach || [])[+b] || {}; Z.ai = x.img || ''; Z.orig = x.orig || ''; }
    Z.showOrig = !S.aiView && !!Z.orig;
    zoomShow(); $('#zoomDlg').showModal();
  });

  // ------------------------------------------------------------ message actions
  el.messages.addEventListener('click', async e => {
    const cp = e.target.closest('[data-copy]');
    if (cp) {
      await MK.copy(cp.closest('.mk-codeblock').querySelector('pre').textContent);
      cp.textContent = t('Copied'); setTimeout(() => (cp.textContent = t('Copy')), 1200);
      return;
    }
    const a = e.target.closest('[data-act]');
    if (!a) return;
    const c = chat();
    const idx = +a.closest('[data-idx]').dataset.idx;
    const m = c.messages[idx];
    if (a.dataset.act === 'copy') { await MK.copy(restoreText(c, m.content)); MK.toast(t('Reply copied with the real values')); }
    else if (a.dataset.act === 'copym') { await MK.copy(m.content); MK.toast(t('Reply copied in masked form')); }
    else if (a.dataset.act === 'regen' && !S.busy) {
      const u = c.messages[idx - 1];
      if (!u || u.role !== 'user') return;
      c.messages.splice(idx - 1, 2); save();
      send(u.raw, u.attach || []);
    }
  });

  // ------------------------------------------------------------ chat list
  el.list.addEventListener('click', e => {
    const o = e.target.closest('[data-open]'), d = e.target.closest('[data-del]');
    if (S.busy) { MK.toast(t('Wait for the reply to finish or stop it.'), 'warn'); return; }
    if (o) { S.cur = o.dataset.open; save(); renderAll(); el.side.classList.remove('mk-side-open'); }
    if (d && confirm(t('Delete this chat and its mapping table?'))) {
      S.chats = S.chats.filter(c => c.id !== d.dataset.del);
      if (S.cur === d.dataset.del) S.cur = S.chats[0]?.id || null;
      save(); renderAll();
    }
  });
  $('#newChat').addEventListener('click', () => { if (S.busy) return; const c = chat(); if (c && !c.messages.length) { el.prompt.focus(); return; } newChat(); el.side.classList.remove('mk-side-open'); });
  $('#clearAll').addEventListener('click', () => {
    if (S.busy || !S.chats.length || !confirm(t('Delete all chats and mapping tables from this browser?'))) return;
    S.chats = []; S.cur = null; save(); renderAll();
  });
  $('#sideToggle').addEventListener('click', () => el.side.classList.toggle('mk-side-open'));

  // ------------------------------------------------------------ view / mapping table
  el.aiView.checked = S.aiView;
  el.aiView.addEventListener('change', () => { S.aiView = el.aiView.checked; MK.store.set('chat-aiview', S.aiView); renderMessages(false); });
  el.preview.checked = MK.store.get('chat-preview', false);
  el.preview.addEventListener('change', () => MK.store.set('chat-preview', el.preview.checked));

  $('#mapBtn').addEventListener('click', () => {
    const c = chat();
    $('#mapBody').innerHTML = c && c.map.length ? c.map.map(i => `<tr>
      <td class="px-4 py-2"><span class="mk-tok mk-code" style="color:${color(i.category)};background:${color(i.category)}1f">${esc(i.token)}</span></td>
      <td class="px-4 py-2 text-xs text-slate-500">${esc(catLabel(i))}</td>
      <td class="px-4 py-2 mk-code break-all">${esc(i.original)}${(i.variants || []).length > 1 ? ` <span class="text-[11px] text-slate-400">(${esc(t('+{n} spellings', { n: i.variants.length - 1 }))})</span>` : ''}</td></tr>`).join('')
      : `<tr><td colspan="3" class="px-4 py-6 text-center text-sm text-slate-400">${esc(t('No values have been masked in this chat yet.'))}</td></tr>`;
    $('#mapDlg').showModal();
  });
  $('#mapJson').addEventListener('click', () => {
    const c = chat(); if (!c || !c.map.length) return;
    MK.download(`maskink-chat-mapping-${new Date().toISOString().slice(0, 10)}.json`, JSON.stringify({
      app: 'maskink', created_at: new Date().toISOString(),
      items: c.map.map(i => ({ token: i.token, category: i.category, original: i.original }))
    }, null, 2), 'application/json');
  });
  document.querySelectorAll('dialog [data-close]').forEach(b => b.addEventListener('click', () => b.closest('dialog').close()));

  // ------------------------------------------------------------ start
  if (!chat()) S.cur = S.chats[0]?.id || null;
  // close replies that were still streaming when the page was closed
  S.chats.forEach(c => c.messages.forEach(m => { if (m.streaming) { m.streaming = false; m.aborted = true; } }));
  renderAll();
  loadModels(false);
  el.prompt.focus();
})(window.MK);
