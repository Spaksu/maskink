/* Maskink – screenshot masking
 * The image is read with OCR in the browser (Tesseract.js, bundled with the app; the image is not sent anywhere),
 * the recognized text is masked with the Maskink rules and every masked word is covered with its token label.
 * The user can click words to add values (hints) or uncover wrongly covered ones (allow);
 * both are sent to the server, so the image and the text sent to the AI are masked the same way.
 */
(function (MK) {
  'use strict';
  const $ = s => document.querySelector(s);
  const esc = MK.esc;
  const t = MK.t;
  const BASE = new URL('assets/vendor/tesseract/', location.href).href;
  const MAX_SIDE = 2000;      // longest side of the image sent to the AI
  const MAX_OCR_PX = 14e6;    // maximum pixel count of the upscaled OCR image
  const LOW_CONF = 60;        // words below this confidence are flagged
  const CATS = () => (MK.boot && MK.boot.categories) || [];
  const hasCat = c => CATS().some(x => x.code === c);

  // ------------------------------------------------------------ OCR
  let workerP = null;
  let onProgress = null;
  const loadScript = src => new Promise((res, rej) => {
    const s = document.createElement('script');
    s.src = src; s.onload = res; s.onerror = () => rej(new Error(t('Could not load the OCR library.')));
    document.head.appendChild(s);
  });
  function worker() {
    if (!workerP) {
      workerP = (async () => {
        if (!window.Tesseract) await loadScript(BASE + 'tesseract.min.js');
        const w = await window.Tesseract.createWorker(['tur', 'eng'], 1, {
          workerPath: BASE + 'worker.min.js', corePath: BASE + 'core', langPath: BASE + 'lang',
          gzip: false, workerBlobURL: false,
          logger: m => onProgress && onProgress(m)
        });
        await w.setParameters({ preserve_interword_spaces: '0', user_defined_dpi: '300' });
        return w;
      })().catch(e => { workerP = null; throw e; });
    }
    return workerP;
  }

  /** OCR preparation: upscaling, grayscale, inversion for dark themes, contrast stretch */
  function prepare(bmp, W, H) {
    let k = Math.max(W, H) < 1000 ? 3 : Math.max(W, H) < 1600 ? 2 : 1;
    while (k > 1 && W * H * k * k > MAX_OCR_PX) k--;
    const c = document.createElement('canvas');
    c.width = W * k; c.height = H * k;
    const x = c.getContext('2d', { willReadFrequently: true });
    x.imageSmoothingQuality = 'high';
    x.drawImage(bmp, 0, 0, c.width, c.height);
    const img = x.getImageData(0, 0, c.width, c.height), d = img.data;
    const hist = new Uint32Array(256);
    for (let i = 0; i < d.length; i += 4) {
      const g = (d[i] * 299 + d[i + 1] * 587 + d[i + 2] * 114) / 1000 | 0;
      d[i] = g; hist[g]++;
    }
    const n = d.length / 4;
    let acc = 0, lo = 0, hi = 255;
    for (let i = 0; i < 256; i++) { acc += hist[i]; if (acc > n * 0.01) { lo = i; break; } }
    acc = 0; for (let i = 255; i >= 0; i--) { acc += hist[i]; if (acc > n * 0.01) { hi = i; break; } }
    const span = Math.max(1, hi - lo);
    // mostly dark images (terminal, dark theme) are inverted; in mixed images Tesseract inverts dark
    // regions itself (inverting region by region produced noise at tile edges)
    let sum = 0; for (let i = 0; i < 256; i++) sum += i * hist[i];
    const invert = sum / n < 100;
    for (let i = 0; i < d.length; i += 4) {
      let g = Math.min(255, Math.max(0, (d[i] - lo) * 255 / span));
      if (invert) g = 255 - g;
      d[i] = d[i + 1] = d[i + 2] = g;
    }
    x.putImageData(img, 0, 0);
    return { canvas: c, k };
  }

  /** Lines of the OCR result (the shape differs between Tesseract versions) */
  function linesOf(data) {
    let lines = data.lines;
    if (!lines || !lines.length) {
      lines = [];
      (data.blocks || []).forEach(b => (b.paragraphs || []).forEach(p => (p.lines || []).forEach(l => lines.push(l))));
    }
    return lines.map(l => (l.words || []).filter(w => w.text && w.text.trim()));
  }
  const iou = (a, b) => {
    const w = Math.min(a.x + a.w, b.x + b.w) - Math.max(a.x, b.x), h = Math.min(a.y + a.h, b.y + b.h) - Math.max(a.y, b.y);
    if (w <= 0 || h <= 0) return 0;
    return w * h / Math.min(a.w * a.h, b.w * b.h);
  };

  /** Two passes: page layout first (PSM 3), then sparse text (PSM 11; table cells, labels next to icons) */
  async function recognize(prep, step) {
    const w = await worker();
    const k = prep.k;
    const conv = (ls, tag) => ls.map(l => l.map(wd => ({ text: wd.text.trim(), conf: wd.confidence,
      x: wd.bbox.x0 / k, y: wd.bbox.y0 / k, w: (wd.bbox.x1 - wd.bbox.x0) / k, h: (wd.bbox.y1 - wd.bbox.y0) / k, pass: tag })));
    step(1);
    await w.setParameters({ tessedit_pageseg_mode: '3' });
    const A = conv(linesOf((await w.recognize(prep.canvas, {}, { text: true, blocks: true })).data), 'a');
    step(2);
    await w.setParameters({ tessedit_pageseg_mode: '11' });
    const B = conv(linesOf((await w.recognize(prep.canvas, {}, { text: true, blocks: true })).data), 'b');
    await w.setParameters({ tessedit_pageseg_mode: '3' });
    const aw = A.flat();
    // from the second pass, only words the first pass missed or read poorly are added
    const extra = B.map(l => l.filter(b => {
      const ov = aw.filter(a => iou(a, b) > 0.35);
      if (!ov.length) return true;
      return ov.every(a => a.text.toLowerCase() !== b.text.toLowerCase()) && b.conf > Math.max(...ov.map(a => a.conf)) + 10;
    })).filter(l => l.length);
    return A.filter(l => l.length).concat(extra);
  }

  /** Text and word positions from the lines; each word keeps its range (s, e) in the text */
  function build(lines) {
    const words = [];
    let text = '';
    lines.forEach((l, li) => {
      l.sort((a, b) => a.x - b.x);
      if (text) text += '\n';
      l.forEach((w, wi) => {
        if (wi) text += ' ';
        words.push({ ...w, s: text.length, e: text.length + w.text.length, line: li });
        text += w.text;
      });
    });
    return { words, text };
  }

  // ------------------------------------------------------------ table columns (English and Turkish headers)
  const HEAD = [
    [/^(kullan[ıi]c[ıi](\s*ad[ıi])?|user(\s*name)?|username|login(\s*name)?|logon|hesap(\s*ad[ıi])?|account(\s*name)?|owner|sahip|samaccountname|upn|uid|yazar|author|created\s*by|olu[şs]turan|g[öo]nderen|sender)$/i, 'USER'],
    [/^(sunucu(\s*ad[ıi])?|server(\s*name)?|host(\s*name)?|hostname|bilgisayar(\s*ad[ıi])?|computer(\s*name)?|makine(\s*ad[ıi])?|machine(\s*name)?|device(\s*name)?|cihaz(\s*ad[ıi])?|node(\s*name)?|vm(\s*name)?)$/i, 'HOST'],
    [/^(ad\s*soyad|ad[ıi]\s*soyad[ıi]|isim\s*soyisim|full\s*name|display\s*name|g[öo]r[üu]nen\s*ad|personel|employee)$/i, 'PERSON']
  ];
  const STOP = /^(-+|—|n\/?a|yok|none|null|true|false|evet|hay[ıi]r|yes|no|ok|tamam|var|bo[şs]|unknown|bilinmiyor|\d+([.,:/\-]\d+)*)$/i;
  /**
   * Marks the cells of columns headed "User", "Host", "Full name"… as values.
   * The table is followed row by row below the header; it is considered finished when a row crosses the
   * column boundaries (plain sentence), a user/server cell gets several words, or the row gap grows.
   */
  function columnHints(att) {
    const hints = [];
    const lines = [];
    att.words.forEach(w => { (lines[w.line] = lines[w.line] || []).push(w); });
    const rows = lines.filter(Boolean).map(ws => ({ ws: ws.slice().sort((a, b) => a.x - b.x), y: Math.min(...ws.map(w => w.y)), h: Math.max(...ws.map(w => w.h)) }))
      .sort((a, b) => a.y - b.y);
    const lineH = att.words.length ? att.words.map(w => w.h).sort((a, b) => a - b)[att.words.length >> 1] : 12;
    rows.forEach((row, ri) => {
      const ws = row.ws;
      // columns of the header row: each word (or two close words) is a column
      const cols = [];
      for (let i = 0; i < ws.length; i++) {
        const two = ws[i + 1] && ws[i + 1].x - (ws[i].x + ws[i].w) < lineH * 1.2 ? [ws[i], ws[i + 1]] : null;
        const lab = g => g.map(x => x.text.replace(/[:：]$/, '')).join(' ');
        const hd2 = two && HEAD.find(([re]) => re.test(lab(two)));
        const grp = hd2 ? two : [ws[i]];
        const hd = hd2 || HEAD.find(([re]) => re.test(lab(grp)));
        cols.push({ x: grp[0].x, x2: grp[grp.length - 1].x + grp[grp.length - 1].w, cat: hd && hasCat(hd[1]) ? hd[1] : null });
        if (hd2) i++;
      }
      if (!cols.some(c => c.cat) || cols.length < 2) return;
      const bounds = cols.slice(1).map((c, i) => (cols[i].x2 + c.x) / 2);
      const colOf = x => { let k = 0; while (k < bounds.length && x > bounds[k]) k++; return k; };
      let prevY = row.y, gap = 0;
      for (let rj = ri + 1; rj < rows.length; rj++) {
        const r = rows[rj];
        const g = r.y - prevY;
        if (g > lineH * 4 || (gap && g > gap * 1.8)) break;
        if (r.ws.some(w => bounds.some(b => w.x < b - 2 && w.x + w.w > b + 2))) break;   // a row crossing a column boundary: not a table
        const cells = cols.map(() => []);
        r.ws.forEach(w => cells[colOf(w.x + w.w / 2)].push(w));
        if (cols.some((c, k) => (c.cat === 'USER' || c.cat === 'HOST') && cells[k].length > 1)) break;
        cols.forEach((c, k) => {
          if (!c.cat || !cells[k].length) return;
          const vals = c.cat === 'PERSON' ? [att.text.slice(cells[k][0].s, cells[k][cells[k].length - 1].e)] : cells[k].map(w => w.text);
          vals.forEach(v0 => {
            const v = v0.replace(/^[\s"'(\[]+|[\s"',;)\]]+$/g, '');
            if (v.length < 2 || STOP.test(v) || HEAD.some(([re]) => re.test(v))) return;
            const cat = c.cat === 'HOST' && /\.[a-z]{2,}$/i.test(v) && hasCat('FQDN') ? 'FQDN' : c.cat;
            hints.push({ category: cat, value: v, auto: true });
          });
        });
        gap = gap || g; prevY = r.y;
      }
    });
    return dedupe(hints);
  }
  const dedupe = hs => { const seen = new Set(); return hs.filter(h => { const k = h.category + '\0' + h.value.toLowerCase(); if (seen.has(k)) return false; seen.add(k); return true; }); };

  // ------------------------------------------------------------ masking and boxes
  /** Masks the recognized text with Maskink and builds the boxes covering the words of every masked value */
  async function maskAtt(att, known, extra) {
    att.auto = []; att.items = []; att.segments = []; att.masked = '';
    if (!att.text.trim()) return att;
    const hints = dedupe(att.hints.concat((extra && extra.hints) || []));
    const allow = [...new Set(att.allow.concat((extra && extra.allow) || []))];
    const d = await MK.api('mask', { text: att.text, known, mode: 'auto', hints, allow, preview: true });
    att.items = d.items; att.segments = d.segments; att.masked = d.masked;
    const spans = [];
    let pos = 0;
    d.segments.forEach(s => {
      const str = s.t !== undefined ? s.t : s.o;
      if (s.m !== undefined) spans.push({ a: pos, b: pos + str.length, tok: s.m, cat: s.c, o: s.o });
      pos += str.length;
    });
    if (d.segments.map(s => s.t !== undefined ? s.t : s.o).join('') !== att.text) {
      // positions do not line up: find the values by searching the text
      spans.length = 0;
      d.segments.filter(s => s.m !== undefined).forEach(s => {
        for (let i = att.text.indexOf(s.o); i >= 0 && s.o; i = att.text.indexOf(s.o, i + 1)) spans.push({ a: i, b: i + s.o.length, tok: s.m, cat: s.c, o: s.o });
      });
    }
    spans.forEach(sp => {
      const byLine = new Map();
      att.words.filter(w => w.s < sp.b && w.e > sp.a).forEach(w => {
        const r = byLine.get(w.line);
        if (!r) byLine.set(w.line, { x: w.x, y: w.y, x2: w.x + w.w, y2: w.y + w.h });
        else { r.x = Math.min(r.x, w.x); r.y = Math.min(r.y, w.y); r.x2 = Math.max(r.x2, w.x + w.w); r.y2 = Math.max(r.y2, w.y + w.h); }
      });
      byLine.forEach(r => att.auto.push({ x: r.x - 2, y: r.y - 2, w: r.x2 - r.x + 4, h: r.y2 - r.y + 4, tok: sp.tok, cat: sp.cat, o: sp.o }));
    });
    // when both passes put a box in the same place, one is enough
    att.auto = att.auto.filter((b, i) => !att.auto.some((o, j) => j < i && o.tok === b.tok && iou(o, b) > 0.6));
    return att;
  }
  const inBox = (w, b) => w.x >= b.x - 1 && w.y >= b.y - 1 && w.x + w.w <= b.x + b.w + 1 && w.y + w.h <= b.y + b.h + 1;
  const covered = (att, w) => att.auto.concat(att.manual).some(b => inBox(w, b) || iou(w, b) > 0.6);
  // uncovered words that look like identifiers: j.doe, svc_backup, web-01, admin01, DOMAIN\user
  const ID_LIKE = /^(?=.*[a-z])[a-z0-9]+([._\-\\][a-z0-9]+)+$|^[a-z]{2,}\d{1,4}$|^[a-z0-9._%+-]+@[a-z0-9.-]+$/i;
  const VERSIONISH = /^v?\d+([._-]\d+)+$|\.(exe|dll|log|txt|js|php|py|json|xml|html?|css|png|jpe?g|sh|ps1|bat|cfg|conf|ini|yml|yaml|md|csv|zip)$/i;
  const flagged = att => att.words.filter(w => !covered(att, w) && !att.allow.some(a => a.toLowerCase() === w.text.toLowerCase()) && (
    (w.conf < LOW_CONF && /[\p{L}\d]{2,}/u.test(w.text)) || (ID_LIKE.test(w.text.replace(/[,:;)]+$/, '')) && !VERSIONISH.test(w.text) && w.text.length >= 4)));

  function draw(att, cv, opt = {}) {
    cv.width = att.W; cv.height = att.H;
    const x = cv.getContext('2d');
    x.drawImage(att.base, 0, 0);
    if (opt.original) return;
    x.textBaseline = 'middle';
    att.auto.forEach(b => {
      const col = MK.vision.color(b.cat);
      const fs = Math.max(11, Math.min(36, b.h * 0.75));
      x.font = `600 ${fs}px ui-monospace, Consolas, monospace`;
      const tw = x.measureText(b.tok).width + 8;
      const w = Math.max(b.w, tw);
      x.fillStyle = '#ffffff'; x.fillRect(b.x, b.y, w, b.h);          // fully covered first
      x.fillStyle = col + '2e'; x.fillRect(b.x, b.y, w, b.h);
      x.strokeStyle = col; x.lineWidth = 1.5; x.strokeRect(b.x + .75, b.y + .75, w - 1.5, b.h - 1.5);
      x.fillStyle = col; x.fillText(b.tok, b.x + 4, b.y + b.h / 2, w - 6);
      b.dw = w;
    });
    att.manual.forEach(b => {
      x.fillStyle = '#1e293b'; x.fillRect(b.x, b.y, b.w, b.h);
      if (b.h >= 12 && b.w >= 40) {
        x.font = `600 ${Math.min(16, b.h * 0.6)}px system-ui, sans-serif`; x.fillStyle = '#e2e8f0';
        x.fillText(t('[hidden]'), b.x + 4, b.y + b.h / 2, b.w - 8);
      }
    });
    if (opt.editor) {
      x.setLineDash([5, 3]); x.strokeStyle = '#f59e0b'; x.lineWidth = 2;
      flagged(att).forEach(w => x.strokeRect(w.x - 2, w.y - 2, w.w + 4, w.h + 4));
      x.setLineDash([]);
      if (opt.sel) { x.strokeStyle = '#7c3aed'; x.lineWidth = 2.5; opt.sel.forEach(w => x.strokeRect(w.x - 3, w.y - 3, w.w + 6, w.h + 6)); }
      if (opt.temp) { x.fillStyle = 'rgba(124,58,237,.18)'; x.strokeStyle = '#7c3aed'; x.lineWidth = 1.5; x.fillRect(opt.temp.x, opt.temp.y, opt.temp.w, opt.temp.h); x.strokeRect(opt.temp.x, opt.temp.y, opt.temp.w, opt.temp.h); }
    }
  }

  function toJpeg(cv) {
    let q = 0.88, url = cv.toDataURL('image/jpeg', q);
    while (url.length > 7 * 1024 * 1024 && q > 0.4) { q -= 0.15; url = cv.toDataURL('image/jpeg', q); }
    return url;
  }
  /** Image that goes to the AI (JPEG data URL) */
  function exportImg(att) { const cv = document.createElement('canvas'); draw(att, cv); return toJpeg(cv); }
  function exportOrig(att) { const cv = document.createElement('canvas'); draw(att, cv, { original: true }); return toJpeg(cv); }

  async function load(file, step) {
    const bmp = await createImageBitmap(file);
    const s = Math.min(1, MAX_SIDE / Math.max(bmp.width, bmp.height));
    const W = Math.round(bmp.width * s), H = Math.round(bmp.height * s);
    const base = document.createElement('canvas');
    base.width = W; base.height = H;
    base.getContext('2d').drawImage(bmp, 0, 0, W, H);
    const prep = prepare(bmp, W, H);
    bmp.close && bmp.close();
    const lines = await recognize(prep, step);
    const att = { name: file.name || 'screenshot', base, W, H, ...build(lines), manual: [], auto: [], items: [], segments: [], masked: '', hints: [], allow: [] };
    att.hints = columnHints(att);
    return att;
  }

  // ------------------------------------------------------------ editor
  /** Reads and masks the image and opens the editor; resolves to the prepared attachment on "Add to message", or null on cancel */
  async function open(file, known) {
    const dlg = $('#imgDlg'), cv = $('#imgCanvas'), st = $('#imgStatus'), pop = $('#imgPop'), orig = $('#imgOrig');
    const btnAdd = $('#imgAdd');
    btnAdd.disabled = true;
    $('#imgTools').classList.add('hidden');
    cv.classList.add('hidden'); pop.classList.add('hidden');
    orig.checked = false;
    $('#imgText').textContent = '';
    st.classList.remove('hidden');
    st.textContent = t('Reading the image…');
    dlg.showModal();
    let cancelled = false, att = null;
    const result = new Promise(res => {
      const done = v => { cancelled = v === null; pop.classList.add('hidden'); dlg.close(); res(v); };
      dlg.querySelector('[data-imgclose]').onclick = () => done(null);
      dlg.oncancel = () => done(null);
      btnAdd.onclick = () => done(att);
    });
    let pass = 1;
    const existing = file && file.base ? file : null;   // re-editing an attachment that was added before
    const before = existing && JSON.stringify({ h: existing.hints, a: existing.allow, m: existing.manual });
    try {
      if (existing) att = existing;
      else {
        onProgress = m => {
          if (m.status === 'recognizing text') st.textContent = t('Recognizing text ({pass}/2)… {pct}%', { pass, pct: Math.round(m.progress * 100) });
          else if (/load|initializ/i.test(m.status)) st.textContent = t('Preparing OCR (language files are loaded on first use)…');
        };
        att = await load(file, p => { pass = p; });
        onProgress = null;
      }
      if (cancelled) return result;
      st.textContent = t('Masking the recognized text…');
      await maskAtt(att, known);
    } catch (e) {
      onProgress = null;
      st.textContent = t('Could not process the image:') + ' ' + e.message;
      return result.then(() => null);
    }
    st.classList.add('hidden');
    cv.classList.remove('hidden');
    $('#imgTools').classList.remove('hidden');
    btnAdd.disabled = false;

    let sel = null;   // selected (not yet masked) words
    const undo = [];  // state before each edit (hints, allow, boxes)
    const snap = () => { undo.push({ hints: att.hints.slice(), allow: att.allow.slice(), manual: att.manual.slice() }); };
    const info = () => {
      const fl = flagged(att).length;
      const autoH = att.hints.filter(h => h.auto).length, userH = att.hints.length - autoH;
      const b = n => `<b>${n}</b>`;
      $('#imgInfo').innerHTML = t('{n} areas covered with tokens ({d} distinct values)', { n: b(att.auto.length), d: att.items.length }) +
        (autoH ? ' · ' + t('{n} values from table columns', { n: b(autoH) }) : '') + (userH ? ' · ' + t('{n} values added manually', { n: b(userH) }) : '') +
        (att.allow.length ? ' · ' + t('{n} values left uncovered', { n: b(att.allow.length) }) : '') +
        (att.manual.length ? ' · ' + t('{n} boxes', { n: b(att.manual.length) }) : '') +
        (fl ? ` · <span class="text-amber-600 dark:text-amber-400">${t('{n} words should be checked (orange frame)', { n: b(fl) })}</span>` : '') +
        (att.words.length ? '' : ` · <span class="text-amber-600">${esc(t('No text was found in the image'))}</span>`);
      $('#imgLow').classList.toggle('hidden', !fl);
      $('#imgUndo').disabled = !undo.length;
      $('#imgText').innerHTML = att.segments.length ? MK.vision.renderSegs(att.segments) : esc(att.text || '—');
    };
    const redraw = tmp => draw(att, cv, { editor: true, temp: tmp, sel, original: orig.checked });
    const remask = async () => {
      try { await maskAtt(att, known); } catch (e) { MK.toast(e.message, 'err'); }
      sel = null; redraw(); info();
    };
    redraw(); info();
    orig.onchange = () => { pop.classList.add('hidden'); sel = null; redraw(); };

    // ---- popup menu
    const showPop = (p, html) => {
      const box = pop.parentElement;
      pop.innerHTML = html;
      pop.classList.remove('hidden');
      const r = cv.getBoundingClientRect(), host = box.getBoundingClientRect();
      const px = r.left - host.left + p.x * r.width / cv.width + box.scrollLeft;
      const py = r.top - host.top + p.y * r.height / cv.height + box.scrollTop + 8;
      pop.style.left = Math.max(4, Math.min(px, box.scrollWidth - pop.offsetWidth - 4)) + 'px';
      pop.style.top = py + 'px';
    };
    const catBtn = (code, label) => `<button type="button" data-pcat="${esc(code)}" class="mk-pop-btn">${esc(label)}</button>`;
    const valueOf = ws => {
      // consecutive words on one line form one value; different lines are separate values
      const byL = new Map();
      ws.slice().sort((a, b) => a.line - b.line || a.s - b.s).forEach(w => { if (!byL.has(w.line)) byL.set(w.line, []); byL.get(w.line).push(w); });
      return [...byL.values()].map(l => att.text.slice(l[0].s, l[l.length - 1].e));
    };
    function wordMenu(p, ws) {
      sel = ws; redraw();
      const vals = valueOf(ws).map(v => v.replace(/^[\s"'(\[]+|[\s"',;:)\]]+$/g, '')).filter(v => v.length >= 2);
      const quick = [['USER', t('Username')], ['HOST', t('Server name')], ['PERSON', t('Person name')], ['FQDN', t('Domain name (FQDN)')]].filter(([c]) => hasCat(c));
      const others = CATS().filter(c => !quick.some(([q]) => q === c.code));
      showPop(p, `<div class="mb-1.5 max-w-[22rem] truncate text-[11px] text-slate-500">${esc(t('Selected:'))} <b class="font-mono text-slate-700 dark:text-slate-200">${esc(vals.join(' · ') || ws.map(w => w.text).join(' '))}</b></div>
        ${vals.length ? `<div class="mb-1 text-[11px] font-semibold text-slate-500">${esc(t('Mask (everywhere in the image and the text):'))}</div>
        <div class="flex flex-wrap gap-1">${quick.map(([c, l]) => catBtn(c, l)).join('')}
          ${others.length ? `<select data-pother class="rounded border border-slate-300 bg-white px-1 py-0.5 text-[11px] dark:border-slate-600 dark:bg-slate-800"><option value="">${esc(t('Other category…'))}</option>${others.map(c => `<option value="${esc(c.code)}">${esc(c.label)}</option>`).join('')}</select>` : ''}</div>` : ''}
        <div class="mt-1.5 flex gap-1 border-t border-slate-100 pt-1.5 dark:border-slate-700">
          <button type="button" data-pbox class="mk-pop-btn">${esc(t('Cover with a box only'))}</button>
          <button type="button" data-pclose class="mk-pop-btn">${esc(t('Cancel'))}</button></div>`);
      const apply = async code => {
        pop.classList.add('hidden');
        snap();
        vals.forEach(v => {
          att.allow = att.allow.filter(a => a.toLowerCase() !== v.toLowerCase());
          att.hints.push({ category: code, value: v });
        });
        att.hints = dedupe(att.hints);
        await remask();
      };
      pop.querySelectorAll('[data-pcat]').forEach(b => b.onclick = () => apply(b.dataset.pcat));
      const o = pop.querySelector('[data-pother]'); if (o) o.onchange = () => o.value && apply(o.value);
      pop.querySelector('[data-pbox]').onclick = () => {
        pop.classList.add('hidden');
        snap();
        ws.forEach(w => att.manual.push({ x: w.x - 2, y: w.y - 2, w: w.w + 4, h: w.h + 4 }));
        sel = null; redraw(); info();
      };
      pop.querySelector('[data-pclose]').onclick = () => { pop.classList.add('hidden'); sel = null; redraw(); };
    }
    function boxMenu(p, b) {
      const isHint = att.hints.some(h => h.value.toLowerCase() === (b.o || '').toLowerCase());
      showPop(p, `<div class="mb-1.5 text-[11px] text-slate-500"><span class="mk-tok font-mono" style="color:${MK.vision.color(b.cat)};background:${MK.vision.color(b.cat)}1f">${esc(b.tok)}</span> = <b class="font-mono text-slate-700 dark:text-slate-200">${esc(b.o || '')}</b></div>
        <div class="flex flex-wrap gap-1">
          <button type="button" data-pallow class="mk-pop-btn mk-pop-warn">${esc(t('Do not mask this value'))}</button>
          <button type="button" data-pclose class="mk-pop-btn">${esc(t('Cancel'))}</button></div>
        <div class="mt-1 max-w-[22rem] text-[11px] text-slate-400">${esc(isHint ? t('The value stays uncovered everywhere in the image and in the text sent to the AI.') : t('The value stays uncovered everywhere in the image and in the text sent to the AI, and in your message too if it appears there.'))}</div>`);
      pop.querySelector('[data-pallow]').onclick = () => unmask(b);
      pop.querySelector('[data-pclose]').onclick = () => pop.classList.add('hidden');
    }
    async function unmask(b) {
      pop.classList.add('hidden');
      snap();
      const v = (b.o || '').toLowerCase();
      att.hints = att.hints.filter(h => h.value.toLowerCase() !== v);
      if (!att.allow.some(a => a.toLowerCase() === v)) att.allow.push(b.o);
      await remask();
    }

    // ---- mouse: click = word/box menu, drag = select words, or draw a box on an empty area
    const pt = e => { const r = cv.getBoundingClientRect(); return { x: (e.clientX - r.left) * cv.width / r.width, y: (e.clientY - r.top) * cv.height / r.height }; };
    const hit = (p, r) => p.x >= r.x - 3 && p.x <= r.x + (r.dw || r.w) + 3 && p.y >= r.y - 3 && p.y <= r.y + r.h + 3;
    let start = null;
    cv.onpointerdown = e => { if (orig.checked || e.button !== 0) return; pop.classList.add('hidden'); start = pt(e); try { cv.setPointerCapture(e.pointerId); } catch (x) {} };
    // right click: unmasks a covered area, deletes a hand-drawn box, opens the menu on an uncovered word
    cv.oncontextmenu = e => {
      e.preventDefault();
      if (orig.checked) return;
      const p = pt(e);
      const mi = att.manual.findIndex(b => hit(p, b));
      if (mi >= 0) { snap(); att.manual.splice(mi, 1); redraw(); info(); return; }
      const ab = att.auto.find(b => hit(p, b));
      if (ab) { unmask(ab); MK.toast(t('{tok} unmasked · use Undo to bring it back', { tok: ab.tok }), 'ok', 2500); return; }
      const w = att.words.find(w => hit(p, w));
      if (w) wordMenu(p, [w]);
    };
    cv.onpointermove = e => {
      if (!start) return;
      const p = pt(e);
      redraw({ x: Math.min(start.x, p.x), y: Math.min(start.y, p.y), w: Math.abs(p.x - start.x), h: Math.abs(p.y - start.y) });
    };
    cv.onpointerup = e => {
      if (!start) return;
      const p = pt(e), r = { x: Math.min(start.x, p.x), y: Math.min(start.y, p.y), w: Math.abs(p.x - start.x), h: Math.abs(p.y - start.y) };
      start = null;
      if (r.w > 5 || r.h > 5) {
        const ws = att.words.filter(w => iou(w, r) > 0.5 && !att.auto.some(b => inBox(w, b)));
        if (ws.length) wordMenu(p, ws);
        else { snap(); att.manual.push(r); redraw(); info(); }
        return;
      }
      const mi = att.manual.findIndex(b => hit(p, b));
      if (mi >= 0) { snap(); att.manual.splice(mi, 1); redraw(); info(); return; }
      const ab = att.auto.find(b => hit(p, b));
      if (ab) { boxMenu(p, ab); return; }
      const w = att.words.find(w => hit(p, w));
      if (w) { wordMenu(p, [w]); return; }
      redraw();
    };
    $('#imgUndo').onclick = async () => {
      const s = undo.pop(); if (!s) return;
      const same = JSON.stringify([s.hints, s.allow]) === JSON.stringify([att.hints, att.allow]);
      att.hints = s.hints; att.allow = s.allow; att.manual = s.manual;
      if (same) { redraw(); info(); } else await remask();
    };
    $('#imgLow').onclick = () => { snap(); flagged(att).forEach(w => att.manual.push({ x: w.x - 2, y: w.y - 2, w: w.w + 4, h: w.h + 4 })); redraw(); info(); };
    if (existing) {
      // on cancel the edits are reverted and the attachment keeps its previous state
      return result.then(async v => {
        if (v) return v;
        const b = JSON.parse(before);
        existing.hints = b.h; existing.allow = b.a; existing.manual = b.m;
        try { await maskAtt(existing, known); } catch (e) {}
        return null;
      });
    }
    return result;
  }

  /**
   * Right before sending: the recognized text is re-masked with the current mapping (so values newly masked in
   * the message are covered in the image too) and the final image is produced. Because the server masks the same
   * text with the same mapping, hints and allow list, the tokens in the image match those in the text sent to the AI.
   */
  async function finalize(att, known, extra) {
    await maskAtt(att, known, extra);
    return { image: exportImg(att), orig: exportOrig(att), ocr: att.text, items: att.items };
  }

  MK.vision = {
    open, finalize,
    color: () => '#7c3aed',
    renderSegs: segs => segs.map(s => s.t !== undefined ? esc(s.t) : `<span class="mk-tok" style="color:${MK.vision.color(s.c)};background:${MK.vision.color(s.c)}1f" title="${esc(s.o)}">${esc(s.m)}</span>`).join(''),
    thumb: att => { const cv = document.createElement('canvas'); draw(att, cv); return cv.toDataURL('image/jpeg', 0.7); }
  };
})(window.MK);
