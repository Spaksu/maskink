/* Maskink – shared helpers */
window.MK = window.MK || {};
(function (MK) {
  MK.csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  const I = window.MK_I18N || { lang: 'en', locale: 'en-US', dict: {} };
  MK.lang = I.lang;
  MK.locale = I.locale;
  /** Translate an English source string; {name} placeholders are filled from vars */
  MK.t = function (s, vars) {
    let out = (I.dict && I.dict[s]) || s;
    if (vars) for (const k in vars) out = out.split('{' + k + '}').join(String(vars[k]));
    return out;
  };

  MK.api = async function (action, body = {}) {
    const res = await fetch('api.php?action=' + encodeURIComponent(action), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': MK.csrf },
      body: JSON.stringify(body),
      credentials: 'same-origin'
    });
    let j;
    try { j = await res.json(); } catch (e) { throw new Error(MK.t('Invalid response from the server (HTTP {status})', { status: res.status })); }
    if (!res.ok || !j.ok) {
      const err = new Error(j.error || ('HTTP ' + res.status));
      err.status = res.status;
      throw err;
    }
    return j.data;
  };

  MK.esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  MK.toast = function (msg, type = 'ok', ms = 3200) {
    const box = document.getElementById('toasts');
    if (!box) return alert(msg);
    const el = document.createElement('div');
    const tone = {
      ok: 'bg-slate-900 text-white dark:bg-white dark:text-slate-900',
      err: 'bg-rose-600 text-white',
      warn: 'bg-amber-500 text-white'
    }[type] || '';
    el.className = 'mk-toast pointer-events-auto max-w-sm rounded-lg px-4 py-2.5 text-sm shadow-lg ' + tone;
    el.textContent = msg;
    box.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 300); }, ms);
  };

  MK.copy = async function (text) {
    try {
      await navigator.clipboard.writeText(text);
    } catch (e) {
      const ta = document.createElement('textarea');
      ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
    }
  };

  MK.download = function (name, content, type = 'text/plain;charset=utf-8') {
    const blob = content instanceof Blob ? content : new Blob([content], { type });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1500);
  };

  /** Reads a file by BOM (UTF-8 / UTF-16 LE/BE), falling back from UTF-8 to Windows-1254 */
  MK.readFile = function (file) {
    return new Promise((resolve, reject) => {
      const fr = new FileReader();
      fr.onerror = () => reject(fr.error);
      fr.onload = () => {
        const b = new Uint8Array(fr.result);
        let enc = 'utf-8';
        if (b[0] === 0xFF && b[1] === 0xFE) enc = 'utf-16le';
        else if (b[0] === 0xFE && b[1] === 0xFF) enc = 'utf-16be';
        else if (b.length > 3 && b[1] === 0 && b[3] === 0 && b[0] !== 0) enc = 'utf-16le';
        try {
          resolve(new TextDecoder(enc, { fatal: enc === 'utf-8' }).decode(b).replace(/^﻿/, ''));
        } catch (e) {
          resolve(new TextDecoder('windows-1254').decode(b));
        }
      };
      fr.readAsArrayBuffer(file);
    });
  };

  MK.store = {
    get(k, d) { try { const v = localStorage.getItem('mki-' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem('mki-' + k, JSON.stringify(v)); } catch (e) {} }
  };

  MK.debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  MK.fmt = n => Number(n || 0).toLocaleString(MK.locale);

  document.getElementById('themeToggle')?.addEventListener('click', () => {
    const dark = document.documentElement.classList.toggle('dark');
    try { localStorage.setItem('mki-theme', dark ? 'dark' : 'light'); } catch (e) {}
  });

  document.getElementById('langToggle')?.addEventListener('click', e => {
    const lang = e.currentTarget.dataset.lang;
    const path = location.pathname.replace(/[^/]*$/, '');
    document.cookie = 'mki_lang=' + lang + '; path=' + path + '; max-age=31536000; samesite=lax';
    location.reload();
  });
})(window.MK);
