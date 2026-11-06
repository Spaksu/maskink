/*
 * Stands in for api.php in the GitHub Pages demo. The app's own pages and scripts run
 * unchanged; every request they send to api.php is answered here from the visitor's
 * localStorage, so nothing leaves the browser and one visitor cannot affect another.
 */
(function () {
  'use strict';
  const Engine = window.MaskinkEngine, P = window.MaskinkPcre, SEED = window.MK_DEMO_SEED;
  const STATE_KEY = 'mki-demo-state-v1';
  const VERSION = SEED.version;
  const REPO_URL = 'https://github.com/Spaksu/maskink';

  const LANG = (window.MK_I18N && window.MK_I18N.lang) === 'tr' ? 'tr' : 'en';
  const TXT = {
    en: {
      banner: 'Demo: your text and settings stay in this browser. AI replies are simulated samples.',
      reset: 'Reset demo', confirm: 'Delete your changes, chats and usage from this browser and start over?',
      code: 'Source on GitHub', off: 'This is disabled in the demo. Install Maskink to use it.',
      foot: 'Everything on this page runs in your browser; no data is sent anywhere.',
      conn: 'Demo (simulated)', model: 'Simulated reply (no AI)'
    },
    tr: {
      banner: 'Demo: metniniz ve ayarlarınız bu tarayıcıda kalır. Yapay zekâ yanıtları örnek simülasyondur.',
      reset: 'Demoyu sıfırla', confirm: 'Değişiklikleriniz, sohbetleriniz ve kullanım verileriniz bu tarayıcıdan silinip baştan başlansın mı?',
      code: "GitHub'da kaynak kod", off: "Bu özellik demoda kapalı. Kullanmak için Maskink'i kurun.",
      foot: 'Bu sayfadaki her şey tarayıcınızda çalışır; hiçbir veri bir yere gönderilmez.',
      conn: 'Demo (simülasyon)', model: 'Simülasyon yanıtı (yapay zekâ yok)'
    }
  }[LANG];
  const t = (s, a) => window.MK && window.MK.t ? window.MK.t(s, a) : s;
  class Fail extends Error { constructor(msg, status = 400) { super(msg); this.status = status; } }

  // ---------------------------------------------------------------- state
  const clone = o => JSON.parse(JSON.stringify(o));
  const now = () => new Date().toISOString().slice(0, 19).replace('T', ' ');
  let memory = null; // used when storage is unavailable (private mode, blocked site data)
  function fresh() {
    const bytes = new Uint8Array(32);
    crypto.getRandomValues(bytes);
    return {
      settings: {}, rules: clone(SEED.rules), categories: clone(SEED.categories), audit: [], usage: [], nextId: SEED.rules.length + 1,
      secret: Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('')
    };
  }
  function load() {
    try {
      const raw = localStorage.getItem(STATE_KEY);
      if (raw) return JSON.parse(raw);
    } catch (e) { /* falls through to memory */ }
    return memory || (memory = fresh());
  }
  function save(s) {
    memory = s;
    try { localStorage.setItem(STATE_KEY, JSON.stringify(s)); } catch (e) { /* kept in memory until the page closes */ }
  }
  function resetDemo() {
    memory = null;
    try {
      Object.keys(localStorage).filter(k => k.startsWith('mki-') && k !== 'mki-theme').forEach(k => localStorage.removeItem(k));
    } catch (e) { /* nothing stored */ }
    location.reload();
  }

  const allSettings = s => ({ ...SEED.settings, ...s.settings });
  const sortRules = rules => rules.slice().sort((a, b) => (b.priority - a.priority) || (a.sort - b.sort) || (a.id - b.id));
  const sortCats = cats => cats.slice().sort((a, b) => (a.sort - b.sort) || (a.code < b.code ? -1 : a.code > b.code ? 1 : 0));
  const engine = (s, rules) => new Engine(rules || sortRules(s.rules), sortCats(s.categories), allSettings(s), s.secret, t);
  const audit = (s, action, detail = '') => {
    s.audit.push({ id: s.audit.length ? s.audit[s.audit.length - 1].id + 1 : 1, at: now(), action, detail: String(detail).slice(0, 2000), ip: 'browser' });
    if (s.audit.length > 500) s.audit.splice(0, s.audit.length - 500);
  };
  const logUsage = (s, st) => {
    if (parseInt(allSettings(s).log_usage, 10)) {
      s.usage.push({ at: now(), mode: st.mode, chars: st.chars_in, matches: st.matches, uniq: st.unique, ms: st.ms });
      if (s.usage.length > 1000) s.usage.splice(0, s.usage.length - 1000);
    }
  };
  function usageStats(s) {
    const u = s.usage, n = u.length;
    const sum = k => u.reduce((a, r) => a + (r[k] || 0), 0);
    const days = {};
    const since = new Date(Date.now() - 13 * 864e5).toISOString().slice(0, 10);
    u.forEach(r => {
      const d = r.at.slice(0, 10);
      if (d < since) return;
      const x = days[d] || (days[d] = { d, runs: 0, matches: 0 });
      x.runs++; x.matches += r.matches;
    });
    return {
      total: { runs: n, chars: sum('chars'), matches: sum('matches'), avg_ms: n ? sum('ms') / n : 0 },
      days: Object.values(days).sort((a, b) => (a.d < b.d ? -1 : 1)),
      rules: { total: s.rules.length, enabled: s.rules.filter(r => r.enabled).length, custom: s.rules.filter(r => !r.builtin).length }
    };
  }

  // ---------------------------------------------------------------- rules (src/Repo.php)
  const isTrue = v => v === true || v === 1 || (typeof v === 'string' && ['1', 'true', 'on', 'yes'].includes(v.trim().toLowerCase()));
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
  const toInt = v => { const n = parseInt(v, 10); return Number.isNaN(n) ? 0 : n; };
  const invalid = m => new Fail(m, 422);

  function sanitizeRule(s, input) {
    const cats = s.categories.map(c => c.code);
    const name = String(input.name ?? '').trim();
    if (name === '' || Array.from(name).length > 150) throw invalid(t('The rule name must be 1-150 characters.'));
    const cat = String(input.category ?? '');
    if (!cats.includes(cat)) throw invalid(t('Invalid category.'));
    const type = String(input.type ?? 'regex');
    if (!['regex', 'keyvalue', 'keyword'].includes(type)) throw invalid(t('Invalid rule type.'));
    const modes = String(input.modes ?? 'all');
    if (!['all', 'log', 'script'].includes(modes)) throw invalid(t('Invalid mode.'));
    const groups = {};
    const rawGroups = input.groups || {};
    (Array.isArray(rawGroups) ? rawGroups.map((v, i) => [i, v]) : Object.entries(rawGroups)).forEach(([k0, v0]) => {
      let k = k0, v = v0;
      if (v && typeof v === 'object') { k = v.group; v = v.category; }
      if (k === null || k === undefined || k === '' || Number.isNaN(Number(k)) || +k < 0 || +k > 99) return;
      if (!cats.includes(v)) throw invalid(t('Invalid category for group {n}.', { n: k }));
      groups[toInt(k)] = String(v);
    });
    const o = input.options || {};
    const opt = {};
    if (['prefix', 'both'].includes(o.affix)) opt.affix = o.affix;
    if (['eq', 'space'].includes(o.seps)) opt.seps = o.seps;
    ['value_regex', 'skip_regex'].forEach(k => { if (o[k] !== undefined && String(o[k]).trim() !== '') opt[k] = String(o[k]).trim(); });
    ['min_len', 'max_len'].forEach(k => { if (o[k] && toInt(o[k]) > 0) opt[k] = toInt(o[k]); });
    ['skip_numeric', 'reclassify', 'split_domain', 'xml', 'case_sensitive'].forEach(k => { if (o[k] && isTrue(o[k])) opt[k] = true; });
    ['skip_vars', 'propagate'].forEach(k => { if (k in o && !isTrue(o[k])) opt[k] = false; });
    const val = String(o.validate ?? '');
    if (!['', 'luhn', 'tckn', 'iban', 'phone', 'entropy', 'dotted', 'person'].includes(val)) throw invalid(t('Invalid validator.'));
    if (val !== '') {
      opt.validate = val;
      if (val === 'entropy') opt.entropy_min = clamp(parseFloat(o.entropy_min ?? 4) || 0, 1, 8);
    }
    const rule = {
      id: toInt(input.id), name, description: String(input.description ?? '').trim(), category: cat, type,
      pattern: type === 'regex' ? String(input.pattern ?? '').trim() : '',
      flags: String(input.flags ?? '').replace(/[^imsxU]/g, ''),
      keys_text: type === 'regex' ? '' : String(input.keys_text ?? '').replace(/\r\n/g, '\n'),
      groups: type === 'regex' ? groups : {}, options: opt,
      priority: clamp(toInt(input.priority ?? 50), 0, 1000), modes, enabled: input.enabled ? 1 : 0
    };
    if (type === 'regex' && rule.pattern === '') throw invalid(t('The regex pattern cannot be empty.'));
    if (type === 'keyvalue' && !Engine.lines(rule.keys_text, true).length) throw invalid('Enter at least one key.');
    try { engine(s, [rule]).compile(rule); } catch (e) { throw invalid(e.message); }
    return rule;
  }
  const findRule = (s, id) => {
    const r = s.rules.find(x => x.id === toInt(id));
    if (!r) throw invalid(t('Rule not found.'));
    return r;
  };
  function saveRule(s, input) {
    const r = sanitizeRule(s, input);
    if (r.id > 0) {
      Object.assign(findRule(s, r.id), r, { updated_at: now() });
      audit(s, 'rule.update', `#${r.id} ${r.name}`);
      return r.id;
    }
    const id = s.nextId++;
    const sort = s.rules.reduce((a, x) => Math.max(a, x.sort || 0), 0) + 10;
    s.rules.push({ ...r, id, bkey: null, builtin: 0, sort, updated_at: now() });
    audit(s, 'rule.create', `#${id} ${r.name}`);
    return id;
  }
  function validateAll(s) {
    const e = engine(s), bad = [];
    for (const r of s.rules) {
      try { e.compile(r); } catch (x) { bad.push({ id: r.id, name: r.name, error: x.message }); }
    }
    return bad;
  }
  function saveCategory(s, input, isNew) {
    const code = String(input.code ?? '').trim().toUpperCase();
    if (!/^[A-Z][A-Z0-9_]{1,31}$/.test(code)) throw invalid(t('The code must be 2-32 characters of uppercase letters, digits and underscores.'));
    const label = String(input.label ?? '').trim();
    const token = String(input.token ?? code).trim();
    const color = String(input.color ?? '#64748b').trim();
    const ph = String(input.placeholder ?? '').trim();
    if (label === '' || Array.from(label).length > 100) throw invalid(t('The label must be 1-100 characters.'));
    if (!/^[A-Za-z0-9_-]{1,32}$/.test(token)) throw invalid(t('The token prefix may contain letters, digits, _ and -.'));
    if (!/^#[0-9a-fA-F]{6}$/.test(color)) throw invalid(t('The color must be in #RRGGBB format.'));
    const existing = s.categories.find(c => c.code === code);
    if (isNew && existing) throw invalid('This code already exists.');
    const vals = {
      label, token, color, placeholder: ph === '' ? null : ph.slice(0, 120), description: String(input.description ?? '').trim().slice(0, 255),
      propagate: input.propagate ? 1 : 0, case_sensitive: input.case_sensitive ? 1 : 0, enabled: input.enabled ? 1 : 0, sort: toInt(input.sort ?? 500)
    };
    if (existing) { Object.assign(existing, vals); audit(s, 'category.update', code); }
    else { s.categories.push({ code, builtin: 0, ...vals }); audit(s, 'category.create', code); }
  }
  function saveSettings(s, input) {
    const out = {};
    for (const [k, type] of Object.entries(SEED.editable)) {
      if (!(k in input)) continue;
      const v = input[k];
      if (type === 'bool') out[k] = isTrue(v) ? '1' : '0';
      else if (type === 'int') out[k] = String(Math.max(0, toInt(v)));
      else if (type.startsWith('enum:')) {
        if (!type.slice(5).split(',').includes(v)) throw invalid(t('Invalid value: {key}', { key: k }));
        out[k] = v;
      } else out[k] = String(v).replace(/\r\n/g, '\n');
    }
    if (out.placeholder_template !== undefined && out.placeholder_template.trim() === '') throw invalid(t('The placeholder template cannot be empty.'));
    if (out.risk_keywords) {
      try { P.compile(out.risk_keywords, 'iu'); } catch (e) { throw invalid(t('The risk keyword regex is invalid.')); }
    }
    if (out.chat_temperature !== undefined) {
      const v = out.chat_temperature.trim().replace(',', '.');
      if (v !== '' && (Number.isNaN(Number(v)) || +v < 0 || +v > 2)) throw invalid(t('Temperature must be between 0 and 2 (empty = model default).'));
      out.chat_temperature = v;
    }
    Object.assign(s.settings, out);
    return Object.keys(out);
  }
  const editableSettings = s => {
    const all = allSettings(s), out = {};
    Object.keys(SEED.editable).forEach(k => { if (k in all) out[k] = all[k]; });
    return out;
  };
  function reseed(s, overwrite) {
    let cats = 0, rules = 0;
    for (const c of SEED.categories) {
      const i = s.categories.findIndex(x => x.code === c.code);
      if (i < 0) { s.categories.push(clone(c)); cats++; } else if (overwrite) { s.categories[i] = clone(c); cats++; }
    }
    for (const r of SEED.rules) {
      const cur = s.rules.find(x => x.bkey === r.bkey);
      if (cur && !overwrite) continue;
      if (cur) Object.assign(cur, clone(r), { id: cur.id, updated_at: now() });
      else s.rules.push({ ...clone(r), id: s.nextId++, updated_at: now() });
      rules++;
    }
    return { categories: cats, rules };
  }
  function exportData(s) {
    return {
      app: 'maskink', version: VERSION, exported_at: new Date().toISOString(), settings: editableSettings(s), categories: sortCats(s.categories),
      rules: sortRules(s.rules).map(r => { const c = { ...r }; delete c.id; delete c.updated_at; return c; })
    };
  }
  function importData(s0, data, replaceCustom) {
    if (!['maskink', 'maskara'].includes(data.app)) throw invalid(t('This file is not a Maskink export.'));
    const s = clone(s0); // all or nothing, like the transaction in Repo::import
    (data.categories || []).forEach(c => saveCategory(s, c, false));
    if (data.settings && typeof data.settings === 'object') saveSettings(s, data.settings);
    if (replaceCustom) s.rules = s.rules.filter(r => r.builtin);
    let n = 0;
    for (const r of data.rules || []) {
      const hit = r.bkey ? s.rules.find(x => x.bkey === r.bkey)
        : s.rules.find(x => !x.builtin && x.name === String(r.name ?? '') && x.type === String(r.type ?? '') && x.category === String(r.category ?? ''));
      saveRule(s, { ...r, id: hit ? hit.id : 0 });
      n++;
    }
    Object.assign(s0, s);
    return { rules: n, categories: (data.categories || []).length };
  }

  // ---------------------------------------------------------------- run options (helpers at the top of api.php)
  const knownMap = v => (Array.isArray(v) ? v.slice(0, 2000) : []).filter(k => k && typeof k === 'object').map(k => ({
    token: k.token, category: k.category, original: k.original, variants: Array.isArray(k.variants) ? k.variants : []
  }));
  const hintList = v => (Array.isArray(v) ? v.slice(0, 500) : []).filter(h => h && typeof h.category === 'string' && typeof h.value === 'string' && h.value.length <= 300)
    .map(h => ({ category: h.category, value: h.value }));
  const allowList = v => (Array.isArray(v) ? v.slice(0, 500) : []).filter(x => typeof x === 'string' && x !== '' && x.length <= 300);
  const knownAdd = (known, items) => known.concat(items.map(it => ({ token: it.token, category: it.category, original: it.original, variants: it.variants || [] })));
  const byteLength = str => new TextEncoder().encode(str).length;
  const maxKb = s => Math.max(16, toInt(allSettings(s).max_input_kb));

  // ---------------------------------------------------------------- simulated chat
  function simulatedReply(items, history) {
    const seen = new Set();
    const list = items.filter(i => !seen.has(i.token) && seen.add(i.token));
    const tok = cat => (list.find(i => i.category === cat) || {}).token;
    const tr = LANG === 'tr';
    const head = tr ? '**Simülasyon yanıtı: gerçek bir yapay zekâ modeli çağrılmadı.**' : '**Simulated reply: no AI model was called.**';
    if (!list.length) {
      return head + '\n\n' + (tr
        ? 'Mesajınızda etkin kurallara uyan bir değer bulunmadı, bu yüzden model metni yazdığınız gibi görürdü. Parola, IP adresi veya kullanıcı adı içeren bir günlük satırı yapıştırmayı deneyin.'
        : 'Nothing in your message matched the active rules, so a model would see it exactly as you wrote it. Try pasting a log line with a password, an IP address or a username.');
    }
    const lines = list.slice(0, 8).map(i => '- ' + i.token + ' (' + i.label + ')');
    const more = list.length > 8 ? '\n- ' + (tr ? `ve ${list.length - 8} değer daha` : `and ${list.length - 8} more`) : '';
    const tips = [];
    const ip = tok('IP'), user = tok('USER'), host = tok('HOST') || tok('FQDN'), pass = tok('PASSWORD') || tok('SECRET');
    if (ip) tips.push(tr ? `${ip} adresinden gelen bağlantıların beklenen bir kaynaktan gelip gelmediğini kontrol edin.` : `Check whether connections from ${ip} come from an expected source.`);
    if (user) tips.push(tr ? `${user} hesabının son oturum açma kayıtlarını inceleyin.` : `Review the recent sign-ins of the ${user} account.`);
    if (host) tips.push(tr ? `${host} üzerindeki servis günlüklerini aynı zaman aralığı için karşılaştırın.` : `Compare the service logs on ${host} for the same time window.`);
    if (pass) tips.push(tr ? `${pass} değeri bir yerde açık metin olarak paylaşıldıysa değiştirin.` : `Rotate ${pass} if it was ever shared in clear text.`);
    if (!tips.length) tips.push(tr ? `${list[0].token} değerinin bu bağlamda neden geçtiğini doğrulayın.` : `Confirm why ${list[0].token} appears in this context.`);
    const again = history > 0 ? '\n\n' + (tr ? 'Aynı değerler önceki mesajlardaki etiketlerini korur.' : 'The same values keep the tokens they had in earlier messages.') : '';
    return head + ' ' + (tr
      ? 'Bir model yalnızca maskelenmiş metni görürdü. Aşağıdaki etiketleri gerçek değerlere tarayıcınız geri çevirdi; model bunları hiç görmedi:'
      : 'A model would only see the masked text. Your browser restored the tokens below to the real values; the model never saw them:')
      + '\n\n' + lines.join('\n') + more + '\n\n' + (tr ? '**Örnek öneriler**' : '**Example suggestions**') + '\n\n'
      + tips.map((x, i) => `${i + 1}. ${x}`).join('\n') + again;
  }

  function chatSend(input, signal) {
    const s = load();
    const text = String(input.text ?? '');
    const attach = (Array.isArray(input.attach) ? input.attach.slice(0, 4) : []).filter(a => a && typeof a === 'object').map(a => ({
      ocr: typeof a.ocr === 'string' ? a.ocr : '', hints: hintList(a.hints), allow: allowList(a.allow)
    }));
    if (text.trim() === '' && !attach.length) throw new Fail(t('The message is empty.'));
    if (byteLength(text) + attach.reduce((n, a) => n + byteLength(a.ocr), 0) > maxKb(s) * 1024) {
      throw new Fail(t('The message is too large (limit {kb} KB).', { kb: maxKb(s) }), 413);
    }
    const opt = {
      mode: String(input.mode ?? 'auto'), known: knownMap(input.known),
      hints: attach.flatMap(a => a.hints), allow: attach.flatMap(a => a.allow)
    };
    const e = engine(s);
    const empty = { masked: '', segments: [], items: [], warnings: [], stats: { matches: 0 } };
    let res = empty;
    if (text.trim() !== '') {
      res = e.run(text, opt);
      logUsage(s, res.stats);
      opt.known = knownAdd(opt.known, res.items);
    }
    let modelText = res.masked;
    let items = res.items;
    const attOut = attach.map((a, i) => {
      let r = empty;
      if (a.ocr.trim() !== '') {
        r = e.run(a.ocr, opt);
        logUsage(s, r.stats);
        opt.known = knownAdd(opt.known, r.items);
        items = items.concat(r.items);
      }
      modelText += (modelText !== '' ? '\n\n' : '') + `[Attachment ${i + 1}: screenshot (recognized text only)`
        + (r.masked !== '' ? '. Text read from the image:]\n```\n' + r.masked + '\n```' : '. No text could be read from the image.]');
      return { masked: r.masked, segments: r.segments, items: r.items };
    });
    save(s);

    const reply = simulatedReply(items, Array.isArray(input.history) ? input.history.length : 0);
    const enc = new TextEncoder();
    const started = performance.now();
    const body = new ReadableStream({
      async start(ctrl) {
        const send = (ev, d) => ctrl.enqueue(enc.encode(`event: ${ev}\ndata: ${JSON.stringify(d)}\n\n`));
        const abort = () => { try { ctrl.error(new DOMException('Aborted', 'AbortError')); } catch (x) { /* already closed */ } };
        if (signal) signal.addEventListener('abort', abort);
        send('mask', { masked: res.masked, segments: res.segments, items, warnings: res.warnings, stats: res.stats, model_text: modelText, attach: attOut, images_sent: 0 });
        const chunks = reply.match(/\s*\S+\s*/g) || [];
        for (const c of chunks) {
          if (signal && signal.aborted) return;
          await new Promise(r => setTimeout(r, 14));
          send('content', { t: c });
        }
        send('done', { finish: 'stop', usage: null, ms: Math.round(performance.now() - started) });
        ctrl.close();
      }
    });
    return new Response(body, { status: 200, headers: { 'content-type': 'text/event-stream' } });
  }

  // ---------------------------------------------------------------- api.php actions
  const simConnection = () => ({
    id: 'demo', name: TXT.conn, type: 'openai', base_url: 'simulated://in-browser', models_mode: 'manual', models: 'simulated', exclude: '', order: '', vision: '',
    enabled: true, verify_ssl: true, timeout: 0, has_key: false, key_hint: ''
  });
  const disabled = () => { throw new Fail(TXT.off, 403); };
  const handlers = {
    mask(s, i) {
      const text = String(i.text ?? '');
      if (byteLength(text) > maxKb(s) * 1024) throw new Fail(t('The input is too large (limit {kb} KB). It can be raised in the settings.', { kb: maxKb(s) }), 413);
      if (text.trim() === '') throw new Fail(t('The text to mask is empty.'));
      const res = engine(s).run(text, {
        mode: String(i.mode ?? allSettings(s).default_mode ?? 'auto'),
        disabled_cats: (Array.isArray(i.disabled_cats) ? i.disabled_cats : []).filter(x => typeof x === 'string'),
        propagate: i.propagate === undefined || isTrue(i.propagate),
        known: knownMap(i.known), hints: hintList(i.hints), allow: allowList(i.allow)
      });
      if (!i.preview) logUsage(s, res.stats);
      return res;
    },
    'chat.models': s => ({
      models: [{ id: 'demo:simulated', provider: TXT.conn, conn: 'demo', name: TXT.model, ctx: 0, vision: false }],
      errors: [], default: 'demo:simulated'
    }),
    'llm.list': () => ({ connections: [simConnection()], types: { openai: 'OpenAI compatible' } }),
    'llm.save': disabled, 'llm.delete': disabled, 'llm.reorder': disabled, 'llm.test': disabled, 'password.change': disabled,
    meta: s => ({
      categories: sortCats(s.categories).map(c => ({ code: c.code, label: t(c.label), token: c.token, color: c.color, enabled: c.enabled, description: t(c.description || '') })),
      default_mode: allSettings(s).default_mode, admin: true
    }),
    login: () => ({ csrf: 'demo' }), 'admin.setup': () => ({ csrf: 'demo' }), logout: () => null,
    'admin.bootstrap': s => ({
      rules: sortRules(s.rules), categories: sortCats(s.categories), settings: editableSettings(s), invalid: validateAll(s), stats: usageStats(s),
      version: VERSION, php: 'none (browser demo)', pcre: 'JS port', jit: false
    }),
    'rules.list': s => ({ rules: sortRules(s.rules), invalid: validateAll(s) }),
    'rule.save': (s, i) => { const id = saveRule(s, i.rule || {}); return { id, rule: findRule(s, id) }; },
    'rule.delete'(s, i) {
      const r = findRule(s, i.id);
      if (r.builtin) throw invalid(t('Built-in rules cannot be deleted; you can disable them.'));
      s.rules = s.rules.filter(x => x !== r);
      audit(s, 'rule.delete', `#${r.id} ${r.name}`);
      return null;
    },
    'rule.toggle'(s, i) {
      const r = findRule(s, i.id);
      r.enabled = i.enabled ? 1 : 0; r.updated_at = now();
      audit(s, i.enabled ? 'rule.enable' : 'rule.disable', `#${r.id} ${r.name}`);
      return null;
    },
    'rule.reset'(s, i) {
      const r = findRule(s, i.id);
      const d = SEED.rules.find(x => x.bkey && x.bkey === r.bkey);
      if (!d) throw invalid(t('This rule is not built in; it has no default.'));
      Object.assign(r, clone(d), { id: r.id, updated_at: now() });
      audit(s, 'rule.reset', `#${r.id} ${r.name}`);
      return { rule: r };
    },
    'rule.duplicate'(s, i) {
      const r = findRule(s, i.id);
      const id = saveRule(s, { ...r, id: 0, name: (r.name + ' (kopya)').slice(0, 150), enabled: 0 });
      return { id, rule: findRule(s, id) };
    },
    'rule.test'(s, i) {
      const raw = { ...(i.rule || {}) };
      if (String(raw.name ?? '').trim() === '') raw.name = 'Test rule';
      const rule = sanitizeRule(s, raw);
      rule.enabled = 1;
      rule.id = rule.id || 'test';
      const e = engine(s, [rule]);
      const compiled = e.compile(rule).map(x => ({ regex: x[0].src, groups: x[1] }));
      return { compiled, result: e.run(String(i.sample ?? ''), { mode: String(i.mode ?? 'auto'), propagate: false, debug: true }) };
    },
    'rules.bulk'(s, i) {
      const ids = i.ids || [];
      if (!['enable', 'disable'].includes(i.op)) throw new Fail(t('Invalid bulk operation.'));
      ids.forEach(id => { findRule(s, id).enabled = i.op === 'enable' ? 1 : 0; });
      audit(s, 'rules.bulk', i.op + ' ' + ids.length);
      return { count: ids.length };
    },
    'category.save': (s, i) => { saveCategory(s, i.category || {}, !!i.is_new); return { categories: sortCats(s.categories) }; },
    'category.delete'(s, i) {
      const c = s.categories.find(x => x.code === String(i.code ?? ''));
      if (!c) throw invalid(t('Category not found.'));
      if (c.builtin) throw invalid(t('Built-in categories cannot be deleted.'));
      const n = s.rules.filter(r => r.category === c.code).length;
      if (n) throw invalid(`${n} rule(s) use this category.`);
      s.categories = s.categories.filter(x => x !== c);
      audit(s, 'category.delete', c.code);
      return { categories: sortCats(s.categories) };
    },
    'settings.save'(s, i) {
      const keys = saveSettings(s, i.settings || {});
      audit(s, 'settings.update', keys.join(', '));
      return { settings: editableSettings(s), invalid: validateAll(s) };
    },
    'settings.reset'(s, i) {
      const k = String(i.key ?? '');
      if (!(k in SEED.editable) || !(k in SEED.settings)) throw new Fail(t('This setting cannot be reset.'));
      delete s.settings[k];
      audit(s, 'settings.reset', k);
      return { value: SEED.settings[k] };
    },
    export(s) { audit(s, 'export', 'Configuration exported'); return exportData(s); },
    import: (s, i) => importData(s, i.data || {}, !!i.replace_custom),
    reseed(s, i) {
      const r = reseed(s, !!i.overwrite);
      audit(s, 'reseed', (i.overwrite ? 'Built-ins reset' : 'Missing defaults added') + ' – ' + JSON.stringify(r));
      return r;
    },
    audit: (s, i) => s.audit.slice(-Math.max(1, Math.min(1000, toInt(i.limit ?? 300)))).reverse()
  };

  const json = (obj, status = 200) => new Response(JSON.stringify(obj), { status, headers: { 'content-type': 'application/json' } });
  const realFetch = window.fetch.bind(window);
  window.fetch = async function (url, opts = {}) {
    const m = typeof url === 'string' && /^api\.php\?action=([^&]+)/.exec(url);
    if (!m) return realFetch(url, opts);
    const action = decodeURIComponent(m[1]);
    let input = {};
    try { input = opts.body ? JSON.parse(opts.body) : {}; } catch (e) { input = {}; }
    try {
      if (action === 'chat.send') return chatSend(input, opts.signal);
      const fn = Object.prototype.hasOwnProperty.call(handlers, action) ? handlers[action] : null;
      if (!fn) throw new Fail(t('Unknown action.'), 404);
      const s = load();
      const data = fn(s, input);
      save(s);
      return json({ ok: true, data: data === undefined ? null : data });
    } catch (e) {
      if (e instanceof Fail) return json({ ok: false, error: e.message }, e.status);
      console.error('[maskink demo]', e);
      return json({ ok: false, error: t('Server error:') + ' ' + e.message }, 500);
    }
  };

  // ---------------------------------------------------------------- page chrome
  const current = location.pathname.split('/').pop() || 'index.html';
  const page = current.replace(/(\.tr)?\.html$/, '');
  const fileFor = (name, lang) => name + (lang === 'tr' ? '.tr' : '') + '.html';

  // the pages were rendered at build time; bring their boot data up to this visitor's settings
  if (window.MK && window.MK.boot) {
    const s = load(), all = allSettings(s), b = window.MK.boot;
    b.categories = sortCats(s.categories).filter(c => c.enabled).map(c => ({
      code: c.code, label: t(c.label), token: c.token, color: c.color, description: t(c.description || '')
    }));
    if ('defaultMode' in b) b.defaultMode = all.default_mode;
    if ('maxKb' in b) b.maxKb = toInt(all.max_input_kb);
    if ('defaultModel' in b) b.defaultModel = 'demo:simulated';
    b.needLogin = false;
  }

  // links in the app's scripts still say .php
  document.addEventListener('click', e => {
    const lang = e.target.closest && e.target.closest('#langToggle');
    if (lang) {
      e.preventDefault(); e.stopImmediatePropagation();
      location.href = fileFor(page, lang.dataset.lang) + location.hash;
      return;
    }
    const a = e.target.closest && e.target.closest('a[href]');
    const hit = a && /^(index|chat|settings|guide|admin-guide)\.php(#.*)?$/.exec(a.getAttribute('href'));
    if (hit) { e.preventDefault(); location.href = fileFor(hit[1], LANG) + (hit[2] || ''); }
  }, true);

  document.getElementById('logoutBtn')?.remove();
  const foot = document.querySelector('footer');
  if (foot) foot.textContent = 'Maskink v' + VERSION + ' · ' + TXT.foot;

  const style = document.createElement('style');
  style.textContent = '.mk-demo-bar{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:4px 14px;min-height:34px;padding:5px 16px;font-size:12px;line-height:1.35;background:#4c1d95;color:#fff}'
    + '.mk-demo-bar a{color:#ddd6fe;text-decoration:underline}.mk-demo-bar button{border:1px solid #c4b5fd;border-radius:6px;padding:1px 8px;background:transparent;color:#fff;font:inherit;cursor:pointer}'
    + '.mk-demo-bar button:hover{background:#5b21b6}'
    + 'body>.min-h-full{min-height:calc(100% - var(--mk-demo-bar,34px))}'
    + '.h-\\[calc\\(100vh-3\\.5rem-2\\.6rem\\)\\]{height:calc(100vh - 3.5rem - 2.6rem - var(--mk-demo-bar,34px))}';
  document.head.appendChild(style);
  const bar = document.createElement('div');
  bar.className = 'mk-demo-bar';
  const msg = document.createElement('span');
  msg.textContent = TXT.banner;
  const reset = document.createElement('button');
  reset.type = 'button';
  reset.id = 'demoReset';
  reset.textContent = TXT.reset;
  reset.addEventListener('click', () => { if (confirm(TXT.confirm)) resetDemo(); });
  const link = document.createElement('a');
  link.href = REPO_URL; link.rel = 'noopener'; link.target = '_blank'; link.textContent = TXT.code;
  bar.append(msg, reset, link);
  document.body.insertBefore(bar, document.body.firstChild);
  document.documentElement.style.setProperty('--mk-demo-bar', bar.offsetHeight + 'px');

  // first visit to the Mask screen: start with a sample instead of an empty box
  if (page === 'index') {
    window.addEventListener('load', () => {
      let first = true;
      try { first = !localStorage.getItem('mki-demo-seen'); localStorage.setItem('mki-demo-seen', '1'); } catch (e) { /* treat as first */ }
      const input = document.getElementById('input');
      const sample = document.querySelector('[data-sample="log"]');
      if (first && input && !input.value && sample) sample.click();
    });
  }
})();
