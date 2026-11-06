// Compares demo/src/engine.js with src/Engine.php on the fixtures from gen-fixtures.php. Exits 1 on any difference.
import fs from 'node:fs';
import readline from 'node:readline';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const here = path.dirname(fileURLToPath(import.meta.url));
await import(path.join(here, '../src/pcre.js'));
await import(path.join(here, '../src/engine.js'));
const Engine = globalThis.MaskinkEngine, V = globalThis.MaskinkValidators, P = globalThis.MaskinkPcre;
const file = process.argv[2] || path.join(here, '.cache/fixtures.jsonl');
const show = +(process.env.SHOW || 12);

let base;
const t0 = Date.now();
const tally = { run: [0, 0], val: [0, 0], re: [0, 0] };
const fails = { run: [], val: [], re: [] };
const fired = new Map();
const cache = new Map();

function norm(res) {
  const r = JSON.parse(JSON.stringify(res));
  delete r.stats.ms;
  // PHP array keys turn numeric strings into ints and an empty map into []
  for (const it of r.items) { it.variants = it.variants.map(String); it.rules = it.rules.map(String); }
  if (Array.isArray(r.stats.by_category)) r.stats.by_category = {};
  if (Array.isArray(r.stats.by_rule)) r.stats.by_rule = {};
  // the compile error text differs by design (PCRE's message vs the browser's)
  r.errors = r.errors.map(e => ({ rule_id: e.rule_id, rule: e.rule }));
  return r;
}
function engineFor(v = {}) {
  const key = JSON.stringify(v);
  let e = cache.get(key);
  if (e) { e.errors = []; return e; }
  let rs = base.rules, cs = base.categories;
  const st = { ...base.settings, ...(v.settings || {}) };
  if (v.all_rules) rs = rs.map(r => ({ ...r, enabled: 1 }));
  if (v.only !== undefined) rs = rs.filter(r => r.id === v.only).map(r => ({ ...r, enabled: 1 }));
  if (v.extra_rules) rs = [...v.extra_rules, ...rs];
  if (v.cats) cs = cs.map(c => v.cats[c.code] ? { ...c, ...v.cats[c.code] } : c);
  e = new Engine(rs, cs, st, 'parity-secret');
  if (cache.size > 64) cache.clear();
  cache.set(key, e);
  return e;
}
function firstDiff(a, b, p = '') {
  if (a === b) return null;
  if (typeof a !== typeof b || a === null || b === null || typeof a !== 'object') return `${p}: php=${JSON.stringify(a)?.slice(0, 200)} js=${JSON.stringify(b)?.slice(0, 200)}`;
  for (const k of new Set([...Object.keys(a), ...Object.keys(b)])) {
    const d = firstDiff(a[k], b[k], p + '/' + k);
    if (d) return d;
  }
  const ka = JSON.stringify(Object.keys(a)), kb = JSON.stringify(Object.keys(b));
  return ka !== kb ? `${p}: key order php=${ka.slice(0, 200)} js=${kb.slice(0, 200)}` : null;
}
const toUtf8Off = (s, i) => Buffer.byteLength(s.slice(0, i));

const rl = readline.createInterface({ input: fs.createReadStream(file), crlfDelay: Infinity });
for await (const line of rl) {
  const row = JSON.parse(line);
  if (row.kind === 'base') { base = row; continue; }
  if (row.kind === 'run') {
    tally.run[0]++;
    let diff;
    try {
      const got = norm(engineFor(row.variant).run(row.text, row.opt));
      const want = norm(row.result);
      diff = firstDiff(want, got);
      if (!row.variant?.extra_rules) for (const k of Object.keys(want.stats.by_rule)) fired.set(k, (fired.get(k) || 0) + 1);
    } catch (e) { diff = 'THROW ' + e.message; }
    if (diff) { tally.run[1]++; fails.run.push(`${row.name}  ${diff}`); }
  } else if (row.kind === 'val') {
    tally.val[0]++;
    let got = V[row.fn](...row.args);
    if (row.fn === 'entropy') got = Math.round(got * 1e9) / 1e9;
    if (got !== row.out) { tally.val[1]++; fails.val.push(`${row.fn}(${JSON.stringify(row.args)}) php=${row.out} js=${got}`); }
  } else if (row.kind === 're') {
    tally.re[0]++;
    let diff = null;
    try {
      const { matches, groupMap } = P.matchAll(row.p, row.f, row.s);
      if (row.error) diff = 'php rejects the pattern, js accepts';
      else {
        const conv = m => {
          const out = {};
          for (const [o, n] of Object.entries(groupMap)) out[o] = m[n] === undefined ? null : [m[n], toUtf8Off(row.s, m.indices[n][0])];
          return out;
        };
        const want = row.out.map(m => { const o = {}; m.forEach((g, i) => { o[i] = g; }); return o; });
        const got = matches.map(conv);
        // PHP drops trailing unset groups: treat them as null
        want.forEach((w, i) => { if (got[i]) for (const k of Object.keys(got[i])) if (!(k in w)) w[k] = null; });
        diff = firstDiff(want, got);
      }
    } catch (e) { if (!row.error) diff = 'js rejects: ' + e.message; }
    if (diff) { tally.re[1]++; fails.re.push(`/${row.p}/${row.f} on ${JSON.stringify(row.s).slice(0, 50)}  ${diff}`); }
  }
}
const names = base.rules.map(r => r.name);
const unfired = names.filter(n => !fired.has(n));
for (const k of ['run', 're', 'val']) {
  console.log(`${k.padEnd(4)} ${tally[k][0]} cases, ${tally[k][1]} differences`);
  fails[k].slice(0, show).forEach(f => console.log('   ' + f.slice(0, 460)));
}
console.log(`rules that fired: ${names.length - unfired.length}/${names.length}`);
if (unfired.length) console.log('  never fired: ' + unfired.join(' | '));
console.log(`time ${(Date.now() - t0) / 1000}s`);
const total = tally.run[1] + tally.val[1] + tally.re[1];
if (process.env.JSON_OUT) fs.writeFileSync(process.env.JSON_OUT, JSON.stringify({ tally, unfired, fails }, null, 1));
process.exit(total ? 1 : 0);
