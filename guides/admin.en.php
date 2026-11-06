<?php // Admin guide – English content (included by admin-guide.php)
g_open(
    'Administrator Guide',
    'This guide covers Maskink\'s masking engine, rules, lists, the configuration of the AI chat and the administration settings. Here you can find what each section of the Settings screen does, how to find out why a value was or was not masked, and how to add rules specific to your organization.',
    [
        'access'     => 'Login and access',
        'engine'     => 'How does the engine work?',
        'overview'   => 'Overview',
        'rules'      => 'Rules',
        'editor'     => 'Rule editor',
        'example'    => 'Example: a new rule',
        'dictionary' => 'Custom dictionary',
        'categories' => 'Categories and tokens',
        'lists'      => 'Lists and exceptions',
        'settings'   => 'General settings',
        'chat'       => 'Chat (AI)',
        'connections'=> 'AI connections',
        'backup'     => 'Backup / restore',
        'security'   => 'Security and change log',
        'trouble'    => 'Troubleshooting',
        'technical'  => 'Technical notes',
    ],
    'guide.php',
    'User guide',
    'Administrator'
);
?>

<?php g_section('access', 'Login and access'); ?>
<ul>
  <li>Settings are reached through the <b>gear</b> icon at the top right.</li>
  <li><b>First use:</b> If there is no administrator password yet, the Settings screen asks you to set one (at least 8 characters). There is no separate installation step; the database and the default rules are created automatically on the first request.</li>
  <li><b>Lockout:</b> After 5 failed logins in a row, that IP address is locked for 5 minutes.</li>
  <li><b>Session:</b> The session ends after a period of inactivity (set in the Security section). You can also end it with <b>Sign out</b> at the top right.</li>
  <li><b>Masking screen:</b> By default anyone on the network can mask; settings are always password-protected. You can require login for masking as well.</li>
</ul>
<?php g_note('If you forget the password, delete the <code>admin_hash</code> row from the <code>mk_settings</code> table in the database; the next visit to Settings opens the set-password screen.', 'info'); ?>

<?php g_section('engine', 'How does the engine work?'); ?>
<p>For every masking request the engine applies these steps in order:</p>
<ol>
  <li><b>Mode detection.</b> If the user chose Automatic, the content is classified as <i>log</i> or <i>script</i> from its line structure. Some rules only run in one mode.</li>
  <li><b>Candidate collection.</b> All enabled rules that match the mode run on the text; every match (or capture group of the rule) becomes a <i>candidate</i>.</li>
  <li><b>Filtering and validation.</b> Each candidate goes through the keep lists, the variable-reference check, length limits, the exclude regex and the validators (Luhn, TCKN, IBAN, entropy…). Rejected candidates are recorded with a <i>rejection reason</i>.</li>
  <li><b>Reclassification.</b> In rules that allow it, the category is corrected based on the value itself: a value that looks like an IP becomes IP, <code>CORP\john</code> is split into DOMAIN + USER, <code>PC01$</code> becomes HOST and a dotted name becomes FQDN.</li>
  <li><b>Overlap resolution.</b> Among overlapping candidates the one with the <b>higher priority</b> wins; if equal, the <b>longer</b> one; if still equal, the one that <b>starts first</b>.</li>
  <li><b>Propagation.</b> Found values (if propagation is on for their category and they meet the minimum length) are also masked where they appear elsewhere in the text without context. The first label of an FQDN (<code>db01.corp.local</code> → <code>db01</code>) propagates as HOST. Parts inside a path (after <code>/</code> or <code>\</code>) are not masked by propagation.</li>
  <li><b>Token generation.</b> Each unique value is turned into a token with its category's placeholder template. Numbers that would clash with a token already present in the text are skipped.</li>
  <li><b>Chat mapping (Chat screen only).</b> The browser sends the chat's mapping table so far with the request. Values masked in earlier messages are found even without context in this message and get <b>the same token</b>; new values get the next unused number. The first labels of earlier FQDNs are also searched for as HOST. These matches appear in the mapping table with the rule <i>Chat mapping (earlier message)</i>.</li>
  <li><b>Warnings.</b> If a risk keyword appears on a line where nothing was masked, the user is warned.</li>
</ol>
<?php g_note('The fastest way to see why a value was not masked is to open the relevant rule and paste the line into the <b>Live test</b> area. The "Rejected candidates" table shows at which step and why each candidate was dropped.', 'tip'); ?>

<?php g_section('overview', 'Overview'); ?>
<p>The opening screen of Settings:</p>
<ul>
  <li><b>Usage cards:</b> Total runs, characters processed, values masked and average time (if statistics are enabled).</li>
  <li><b>Last 14 days:</b> A chart of daily runs.</li>
  <li><b>Rules:</b> Total, enabled, custom and broken rule counts; Maskink, PHP and PCRE versions and the JIT status.</li>
  <li><b>Rules per category:</b> How many rules are enabled in each category. Clicking a category filters the Rules screen by it.</li>
</ul>
<p>If a regex fails to compile, it is listed here in a red box and a counter appears next to <b>Rules</b> in the side menu.</p>

<?php g_section('rules', 'Rules'); ?>
<p>Each rule defines how one kind of data is recognized. On the list screen:</p>
<ul>
  <li><b>Search and filters:</b> Search in name, description, pattern or keys; filter by category, type and state (Enabled, Disabled, Custom, Built-in, Broken).</li>
  <li><b>Enabled switch:</b> Turns the rule on or off immediately.</li>
  <li><b>Bulk actions:</b> Select with the checkboxes and choose <b>Enable / Disable selected</b>.</li>
  <li><b>Edit:</b> Opens the rule editor.</li>
  <li><b>Duplicate:</b> Creates a disabled copy of the rule. Ideal for experimenting without changing a built-in rule.</li>
  <li><b>Delete:</b> Only custom rules can be deleted. Built-in rules cannot be deleted; they can be disabled or reset to default.</li>
</ul>
<p>Names and descriptions of built-in rules are shown in the interface language; the edit form shows the stored (English) values.</p>
<h3>Rule types</h3>
<div class="my-4 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800"><table class="w-full text-left text-sm">
  <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400 dark:bg-slate-900"><tr><th class="px-3 py-2 font-medium">Type</th><th class="px-3 py-2 font-medium">When to use it</th><th class="px-3 py-2 font-medium">Example</th></tr></thead>
  <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
    <tr><td class="px-3 py-2 font-medium">Regex</td><td class="px-3 py-2">Values with a known shape: IPs, token patterns, device configuration lines, the organization's host naming scheme.</td><td class="px-3 py-2"><code>\bACME-[A-Z0-9]{3,}\b</code></td></tr>
    <tr><td class="px-3 py-2 font-medium">Key = value</td><td class="px-3 py-2">Fields whose value follows a key name: password, user and server fields. You only write the key names; all spellings are matched automatically.</td><td class="px-3 py-2"><code>db password</code></td></tr>
    <tr><td class="px-3 py-2 font-medium">Dictionary</td><td class="px-3 py-2">Fixed terms that cannot be recognized by a pattern: organization server names, project code names, customer names.</td><td class="px-3 py-2"><code>Project Falcon | CUSTOM</code></td></tr>
  </tbody>
</table></div>

<?php g_section('editor', 'Rule editor'); ?>
<p>The editor has two parts: the rule fields on the left and the <b>Live test</b> on the right. Every change is tried on the sample text at the right immediately, before saving.</p>

<h3>Common fields</h3>
<ul>
  <li><b>Name / Description:</b> Shown in the list and in the "Detected by rule" column of the mapping table.</li>
  <li><b>Main category:</b> Which token the match is masked with.</li>
  <li><b>Priority (0-1000):</b> The higher one wins on overlaps. Guide values: passwords/keys ~90, contextual rules ~70-80, generic patterns (IP, FQDN, first.last) ~40-60.</li>
  <li><b>Runs in mode:</b> All, Log only or Script only.</li>
  <li><b>Enabled:</b> Whether the rule runs.</li>
</ul>

<h3>Regex rules</h3>
<ul>
  <li><b>Pattern:</b> PCRE syntax, written without delimiters (<code>/…/</code>). Unicode (<code>u</code>) is always on.</li>
  <li><b>Templates:</b> <code>{{TLD}}</code> expands to the TLD list, <code>{{FIRSTNAME}}</code> to the first-name dictionary (lowercase) and <code>{{FIRSTNAME_CAP}}</code> to capitalized names. When you update a list, all rules using that template are updated too.</li>
  <li><b>Flags:</b> <code>i</code> case-insensitive, <code>m</code> multiline, <code>s</code> dot also matches line breaks, <code>x</code> extended (whitespace and comments allowed), <code>U</code> ungreedy.</li>
  <li><b>Capture group → category:</b> If empty, the whole match is masked with the main category. If you define groups, only those groups are masked and each group can get its own category. For example, for <code>https://user:password@server</code>: group 1 → USER, group 2 → PASSWORD.</li>
</ul>
<?php g_note('To match the context but mask only the value, keep the context outside the group: with the pattern <code>enable secret \d (\S+)</code> and group 1 → PASSWORD, <code>enable secret 5</code> stays visible and only the hash is masked.', 'tip'); ?>

<h3>Key = value rules</h3>
<ul>
  <li><b>Keys:</b> One key per line. Space, <code>_</code>, <code>-</code> and <code>.</code> match loosely; writing <code>user name</code> catches <code>username</code>, <code>user_name</code>, <code>User-Name</code>, <code>UserName</code> and <code>"user name"</code>. Case-insensitive.</li>
  <li><b>Supported forms:</b> <code>key=value</code>, <code>key: value</code>, <code>"key": "value"</code> (JSON), <code>$Key = 'value'</code> (PowerShell), <code>key =&gt; value</code> (PHP), <code>['key'] = value</code>.</li>
  <li><b>Prefix / suffix:</b> <i>None</i> catches only the exact key; <i>Prefix</i> also catches prefixed keys such as <code>db_user</code> and <code>smtp.user</code>; <i>Prefix + suffix</i> also catches keys with additions on both sides such as <code>db_password_enc</code>.</li>
  <li><b>Separator:</b> Standard (<code>= : =&gt; := ==</code>), only <code>=</code>, or standard + space (for CLI forms such as <code>password S3cret</code>).</li>
  <li><b>Value regex:</b> If empty, a quoted value or an unquoted value up to the next separator is taken. Fill it in to enforce a specific format.</li>
  <li><b>Also scan XML tags:</b> Also catches the <code>&lt;password&gt;…&lt;/password&gt;</code> form.</li>
</ul>

<h3>Dictionary rules</h3>
<p>Write one term per line. A term can get its own category with the <code>term | CATEGORY</code> form; lines starting with <code>#</code> are comments. Terms are matched as whole words; if <b>Case-sensitive</b> is off, differences in case are ignored.</p>

<h3>Filtering and validation</h3>
<div class="my-4 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800"><table class="w-full text-left text-sm">
  <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
    <tr><td class="w-56 px-3 py-2 font-medium">Minimum / maximum length</td><td class="px-3 py-2">Values outside this range are dropped.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Validator</td><td class="px-3 py-2"><b>Luhn</b> (cards), <b>TCKN checksum</b>, <b>IBAN mod-97</b>, <b>Phone digit count</b>, <b>Shannon entropy</b> (random-looking secrets), <b>first.last</b> (drops file extensions and code expressions), <b>First Last</b> (drops common words).</td></tr>
    <tr><td class="px-3 py-2 font-medium">Min. entropy</td><td class="px-3 py-2">Used only by the entropy validator. Raising it reduces false positives but increases the risk of missing real keys. Default 4.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Exclude regex</td><td class="px-3 py-2">Applied to the value; matching values are not masked. E.g. <code>^/</code> to skip path values.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Skip variable references</td><td class="px-3 py-2">Skips values that are not real, such as <code>$var</code>, <code>${VAR}</code>, <code>%VAR%</code>, <code>{{x}}</code>, <code>&lt;placeholder&gt;</code> and <code>****</code>. Recommended to keep on for scripts.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Skip numbers only</td><td class="px-3 py-2">Does not mask numeric values such as <code>uid=1000</code>.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Reclassify by value</td><td class="px-3 py-2">Corrects the category based on the shape of the value (IP, FQDN, HOST, SID).</td></tr>
    <tr><td class="px-3 py-2 font-medium">Split DOMAIN\user values</td><td class="px-3 py-2"><code>CORP\john</code> → <?= g_tok('<DOMAIN_1>', '#65a30d') ?>\<?= g_tok('<USER_1>', '#16a34a') ?></td></tr>
    <tr><td class="px-3 py-2 font-medium">Take part in propagation</td><td class="px-3 py-2">If turned off, a value found by this rule is masked only where it was found.</td></tr>
  </tbody>
</table></div>

<h3>Live test</h3>
<p>Type or paste sample text into the area on the right; try log/script behavior with the mode selector. The test results:</p>
<ul>
  <li><b>Highlighted sample:</b> Found values in their category color; hover to see the rule and category.</li>
  <li><b>Masked:</b> The result with all enabled rules plus the unsaved version of the rule you are editing.</li>
  <li><b>Rejected candidates:</b> Every dropped value and the reason. Common reasons: <i>Variable / placeholder reference</i>, <i>Numbers only</i>, <i>Short numeric value</i>, <i>Code reference (object.property)</i>, <i>Script variable</i>, <i>Subnet mask / wildcard</i>, <i>Luhn / TCKN / IBAN check failed</i>, <i>Looks like a domain name</i>.</li>
  <li><b>Compiled regex:</b> The actual pattern produced by key=value and dictionary rules. Useful when debugging.</li>
</ul>
<p>The test text is remembered per rule in your browser; it is not saved on the server. If you try to save a broken regex, the editor shows the error and does not save. For built-in rules, <b>Reset to default</b> reverts your changes.</p>

<?php g_section('example', 'Example: a rule for organization server names'); ?>
<p>If your servers are named like <code>ACME-WEB01</code> and <code>ACME-DB02</code>:</p>
<ol>
  <li><b>Rules → + New rule</b>.</li>
  <li>Name: <code>Organization server names</code>, Main category: <b>Hostname (HOST)</b>, Type: <b>Regex</b>, Priority: <code>75</code>, Mode: <b>All</b>.</li>
  <li>Pattern: <code>\bACME-[A-Z]{2,5}\d{2,3}\b</code>, flag: <code>i</code>.</li>
  <li>Type <code>ACME-WEB01 restarted; acme-web01 reachable</code> into the live test area and check that both spellings get the same <?= g_tok('<HOST_1>', '#f59e0b') ?> token.</li>
  <li><b>Save</b>.</li>
</ol>
<?php g_note('For a few fixed names without a pattern, use the <b>Custom dictionary</b> instead of writing a regex; it is faster and less error-prone.', 'tip'); ?>

<?php g_section('dictionary', 'Custom dictionary'); ?>
<p>For terms specific to your organization that cannot be recognized by a pattern: server names, project code names, customer and partner names, internal system names. Line format:</p>
<pre class="mk-pre mk-code my-3 rounded-lg border border-slate-200 bg-white p-3 text-[13px] dark:border-slate-800 dark:bg-slate-900"># Servers
ACME-DC01 | HOST
SECRET-SRV | HOST
# Projects and customers
Project Falcon
AcmeBank | CUSTOM
special.person | USER</pre>
<p>If no category is given, the rule's main category (Custom term) is used. <b>Advanced editor</b> opens the dictionary rule in the full editor, giving access to the live test and the case setting. You can create several dictionary rules for separate topics (Rules → New rule → Type: Dictionary).</p>

<?php g_section('categories', 'Categories and tokens'); ?>
<p>These fields are set for each category:</p>
<ul>
  <li><b>Label:</b> The name shown in the interface. Labels of built-in categories are stored in English and translated on display.</li>
  <li><b>Token prefix:</b> Replaces <code>{CAT}</code> in the template (e.g. <code>PASS</code> for PASSWORD).</li>
  <li><b>Color:</b> The highlight color in the output.</li>
  <li><b>Placeholder template:</b> If empty, the general template is used.</li>
  <li><b>Propagation:</b> Are values of this category searched for everywhere in the text?</li>
  <li><b>Case-sensitive:</b> If on, <code>John</code> and <code>john</code> get separate tokens. On for categories where case matters, such as passwords and keys.</li>
  <li><b>Enabled / Order:</b> Disabled categories are never masked and do not appear on the user screen; the order sets the layout of chips and lists.</li>
</ul>
<h3>Template variables</h3>
<div class="my-4 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800"><table class="w-full text-left text-sm">
  <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
    <tr><td class="w-28 px-3 py-2"><code>{CAT}</code></td><td class="px-3 py-2">Token prefix (<code>HOST</code>)</td></tr>
    <tr><td class="px-3 py-2"><code>{cat}</code></td><td class="px-3 py-2">Lowercase prefix (<code>host</code>)</td></tr>
    <tr><td class="px-3 py-2"><code>{n}</code></td><td class="px-3 py-2">Sequence number within the run (1, 2, 3…)</td></tr>
    <tr><td class="px-3 py-2"><code>{hash}</code></td><td class="px-3 py-2">A stable 8-digit digest of the value. The same value gets the same token in every run; since the digest is produced with an installation-specific secret key, it cannot be reversed.</td></tr>
    <tr><td class="px-3 py-2"><code>{len}</code></td><td class="px-3 py-2">Length of the original value</td></tr>
    <tr><td class="px-3 py-2"><code>{stars}</code></td><td class="px-3 py-2">Asterisks as long as the value</td></tr>
  </tbody>
</table></div>
<?php g_note('Without <code>{n}</code> or <code>{hash}</code> in the template, different values get the same token; then the mapping table and <b>Restore</b> do not work. Use templates such as <code>[MASKED_{CAT}]</code> only when restoring is not needed.', 'warn'); ?>

<?php g_section('lists', 'Lists and exceptions'); ?>
<p>These control how the engine recognizes and excludes values. Changes apply to all rules as soon as they are saved. <b>reset to default</b> next to each list restores it. Items are separated by lines or commas; lines starting with <code>#</code> are comments.</p>
<div class="my-4 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800"><table class="w-full text-left text-sm">
  <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400 dark:bg-slate-900"><tr><th class="px-3 py-2 font-medium">List</th><th class="px-3 py-2 font-medium">Effect</th></tr></thead>
  <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
    <tr><td class="w-64 px-3 py-2 font-medium">Values that are never masked</td><td class="px-3 py-2">Applies to all categories; values such as <code>root</code>, <code>localhost</code>, <code>n/a</code> and <code>true</code> stay visible.</td></tr>
    <tr><td class="px-3 py-2 font-medium">IPv4 ranges that are not masked</td><td class="px-3 py-2">A single IP or a CIDR, e.g. <code>127.0.0.0/8</code>. Add <code>10.0.0.0/8</code> if you want to leave the internal network visible for analysis, but keep in mind that this reveals your network structure.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Domains that are not masked</td><td class="px-3 py-2">These domains and all their subdomains (FQDNs and e-mail domains) stay visible: <code>microsoft.com</code>, <code>gmail.com</code>…</td></tr>
    <tr><td class="px-3 py-2 font-medium">TLD list</td><td class="px-3 py-2">The FQDN rule only treats names ending in these extensions as domain names. <b>Be sure to add the extension of your internal domain (corp, local, lan, intra…).</b> Do not add file extensions (py, sh, json).</td></tr>
    <tr><td class="px-3 py-2 font-medium">First-name dictionary</td><td class="px-3 py-2">List of first names for the <code>john.smith</code> and <code>John Smith</code> patterns. ASCII equivalents of names with Turkish characters (şükrü → sukru) are generated automatically.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Parts excluded from first.last</td><td class="px-3 py-2">Prevents expressions such as <code>sshd.service</code>, <code>app.log</code> and <code>config.json</code> from being taken for users.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Code tokens</td><td class="px-3 py-2">Prevents code expressions such as <code>os.path</code>, <code>self.name</code> and <code>logger.info</code> from being taken for first.last or an FQDN.</td></tr>
    <tr><td class="px-3 py-2 font-medium">FQDN first-label exceptions</td><td class="px-3 py-2">Names starting with these labels (<code>System.Net</code>, <code>ASP.NET</code>) are not treated as FQDNs.</td></tr>
    <tr><td class="px-3 py-2 font-medium">First Last stop words</td><td class="px-3 py-2">Filters out phrases that start with a name but are not people, such as "Can Not" or "Deniz Error".</td></tr>
  </tbody>
</table></div>

<?php g_section('settings', 'General settings'); ?>
<ul>
  <li><b>General placeholder template:</b> The default token format for all categories (default <code>&lt;{CAT}_{n}&gt;</code>). Click the ready-made options to preview them.</li>
  <li><b>Propagation:</b> The global switch. The per-category setting applies in addition.</li>
  <li><b>Minimum length for propagation:</b> Prevents short values such as <code>it</code> and <code>db</code> from being masked everywhere in the text.</li>
  <li><b>Default mode:</b> The mode selected when the user screen first opens.</li>
  <li><b>Keep subnet masks:</b> Values such as <code>255.255.255.0</code> and <code>0.0.0.255</code> are not treated as IPs; they stay visible for network analysis.</li>
  <li><b>Remaining-risk keywords:</b> If this regex matches on a line where nothing was masked, the user is warned. If left empty, no warning is given.</li>
  <li><b>Maximum input (KB):</b> Size of text that can be masked at once. Very large values may hit PHP memory and time limits.</li>
  <li><b>Keep usage statistics:</b> Records only numeric statistics (characters, match count, duration); content is never stored. Each chat message also counts as a masking run.</li>
</ul>
<p>Chat-related settings are in the <b>Chat (AI)</b> box at the bottom of the same screen; see the next section.</p>

<?php g_section('chat', 'Chat (AI)'); ?>
<p>The Chat screen places Maskink between the user and the AI service. The browser never talks to the AI service directly; all requests go through <code>api.php</code> on the server.</p>
<ol>
  <li>The browser sends the new message <b>as is</b>, the earlier messages <b>masked</b> and the chat's mapping table to the server (session + CSRF protected).</li>
  <li>The server runs the new message through the engine (with the chat mapping). The request to the model is: <b>system prompt + masked history + masked new message</b>. Real values never reach the AI service.</li>
  <li>The model's reply arrives as a stream (SSE) and is passed to the browser <b>masked</b>.</li>
  <li>Restoring happens in the browser with the chat's mapping table. Besides the exact token form, small distortions models often make (dropped angle brackets, HTML escaping such as <code>&amp;lt;IP_1&amp;gt;</code>) are recognized. Tokens not in the mapping are shown to the user as a warning.</li>
</ol>

<h3>Settings → General settings → Chat</h3>
<ul>
  <li><b>Default model:</b> The model selected in new chats. The list is read live from the connections; users can pick another model on the chat screen.</li>
  <li><b>Temperature:</b> Between 0 and 2; if left empty the model's own default is used. Low values (0–0.3) are more consistent for technical support and command generation.</li>
  <li><b>System prompt:</b> Added at the start of every request. <code>{EXAMPLE}</code> is filled with sample tokens built from the current token template (e.g. <code>&lt;IP_1&gt;, &lt;HOST_1&gt;</code>) and <code>{CATEGORIES}</code> with the list of enabled categories. The default prompt is in English and tells the model to reply in the user's language. <b>Reset to default</b> restores it.</li>
</ul>
<?php g_note('Do not remove the “write placeholders exactly as they are, do not invent new placeholders, do not guess real values” instructions from the system prompt. Without them the model may change tokens and the reply cannot be restored.', 'warn'); ?>

<h3>Screenshots</h3>
<p>Users can add screenshots to the chat. Sensitive parts of the image are covered <b>in the browser</b>; the server does not read, change or store the image.</p>
<ol>
  <li>The text in the image is read with OCR in the browser (Tesseract.js, Turkish + English; the files are in <code>assets/vendor/tesseract/</code>, no internet needed). The image is not sent anywhere for reading. It is preprocessed first (2–3x upscaling if small, grayscale, contrast stretching, inversion if mostly dark) and read in two passes: page layout (PSM 3) and sparse text (PSM 11). The second pass only adds words the first missed or read poorly.</li>
  <li><b>Table columns:</b> Cells in columns with headers such as <i>User, Username, Account, Owner, Server, Host, Computer, Display Name</i> (and their Turkish equivalents) are masked in the matching category even without rule context. A line crossing the column boundary (a plain sentence) or a large vertical gap marks the end of the table.</li>
  <li>The text that was read is masked through the <code>mask</code> endpoint using the chat's mapping; the word boxes of every masked value are fully covered and its token is written on top.</li>
  <li><b>Manual correction:</b> The user can select a word and give it a category (<code>hints</code>: the value is masked in that category everywhere) or right-click a covered area to unmask it (<code>allow</code>: the value stays visible everywhere). Both are engine options and are also sent to the server; they apply equally to the message text and the text read from the image. Words that could not be read reliably, or that look like identifiers but were not masked, get an orange frame.</li>
  <li>Right before sending, the text read from the image is <b>masked again</b> with the current mapping, including the new values of the message, and the image is rendered in its final form (longest side 2000 px, JPEG). This way a value masked in the message is also covered in the image.</li>
  <li>The original image is also stored in the user's browser (only there; it is compared using the <b>What the AI sees</b> switch in the chat). The image that reaches the server is always the covered one.</li>
  <li>The server masks the text read from the image once more with the same mapping, <code>hints</code> and <code>allow</code>, and sends the model <b>message + masked image text + covered image</b>. If the model does not support images, only the text is sent. At most 6 images from the history are sent to the model again.</li>
</ol>
<p>Which models accept images is set on the <b>AI connections</b> screen (Ollama reports this itself).</p>
<?php g_note('OCR is not perfect: text that cannot be read is not covered, and sensitive content that is not text (faces, signatures, QR codes, logos) is not detected at all. Users must check the image in the window before sending. To block image sending entirely, empty the <b>Vision-capable models</b> field of the connections and exclude Ollama vision models; then only masked text is sent.', 'warn'); ?>

<p>Where the models come from is managed on the <b>AI connections</b> screen, described in the next section.</p>

<h3>Storage and privacy</h3>
<ul>
  <li>Chats and mapping tables live only in the user's browser (<code>localStorage</code>); there is no copy on the server or in the database. Administrators cannot see users' chats.</li>
  <li>Messages are held in memory on the server only for the duration of the request. Only numeric usage statistics (if enabled) are written to the database.</li>
  <li>The AI service sees only masked text. Whether it logs requests on its side is outside the control of this application.</li>
  <li>If <b>Require login for the masking screen too</b> is on, the chat can also be used only with an administrator session.</li>
</ul>

<?php g_section('connections', 'AI connections'); ?>
<p>The <b>Settings → AI connections</b> screen lists the endpoints used by the chat. Each connection consists of an address, an optional API key and a model list. Models of <b>enabled</b> connections appear in the chat's model list, grouped under the connection name.</p>
<ul>
  <li><b>+ New connection:</b> Opens an empty form.</li>
  <li><b>Edit:</b> Opens the connection's form; changes are applied with <b>Save</b>.</li>
  <li><b>Test connection:</b> Fetches the model list with the (not yet saved) details in the form and shows the models found. With a manual model list, a very short test request is sent to the first model.</li>
  <li><b>↑ / ↓:</b> Changes the order of the connection; saved immediately. Groups in the chat's model list follow this order.</li>
  <li><b>Delete:</b> Removes the connection. Chats using its models will have to choose another model on the next message; if the default model is on this connection, choose a new one under <b>General settings</b>.</li>
</ul>

<h3>Form fields</h3>
<div class="mk-table"><table>
  <thead><tr><th>Field</th><th>Description</th></tr></thead>
  <tbody>
    <tr><td>Name</td><td>The group title in the model list. The connection id (e.g. <code>vllm</code>) is derived from the name on first save and does not change; model ids have the form <code>&lt;id&gt;:&lt;model&gt;</code> (e.g. <code>vllm:gpt-oss-120b</code>).</td></tr>
    <tr><td>Type</td><td>Determines how the addresses are built (see the table below).</td></tr>
    <tr><td>Base address</td><td>The root of the endpoint. The form shows the full addresses used for the model list and the chat below it.</td></tr>
    <tr><td>API key</td><td>Sent with the <code>Authorization: Bearer</code> header. After saving it is never sent to the screen again; only its last 4 characters are shown. If left empty while editing, the current key is kept; <b>Delete the saved key</b> removes it.</td></tr>
    <tr><td>Timeout</td><td>The longest time to wait for a reply (seconds).</td></tr>
    <tr><td>Model list</td><td><b>Automatic:</b> read from the endpoint's model list (cached for 10 minutes; the refresh button on the chat screen bypasses the cache). Embedding models are not shown; you can hide other models by writing a name or <code>/regex/i</code> under <b>Models to exclude</b>. <b>Manual:</b> for endpoints whose model list cannot be read, or when only specific models should appear; one model name per line. Models are listed in the order written.</td></tr>
    <tr><td>Model order</td><td>Automatic list only. Models to show first, top to bottom; one name or <code>/regex/i</code> per line (e.g. <code>/qwen/i</code> brings all Qwen models to the front together). Models not listed are appended in the endpoint's order, so a model newly added to the endpoint does not disappear from the list. The <b>Copy this list to the order field</b> link in the <b>Test connection</b> result writes the current list into the field; just rearrange the lines and save. On the chat screen the first model is selected if the user has no earlier choice and there is no default model.</td></tr>
    <tr><td>Vision-capable models</td><td>Models that accept screenshots; one name or <code>/regex/i</code> per line. These models also receive the covered image; others only receive the text read from it. Ollama reports vision support (<code>vision</code>) itself. If you are not sure whether a model accepts images, do not list it: sending an image to a model that does not support it gives a “not a multimodal model” error.</td></tr>
    <tr><td>Enabled</td><td>Models of a disabled connection are not listed and cannot be used.</td></tr>
    <tr><td>Verify the TLS certificate</td><td>Turn off only temporarily for internal certificate problems.</td></tr>
  </tbody>
</table></div>

<h3>Connection types</h3>
<div class="mk-table"><table>
  <thead><tr><th>Type</th><th>Model list</th><th>Chat request</th></tr></thead>
  <tbody>
    <tr><td>vLLM gateway</td><td><code>{address}/v1/models</code></td><td><code>{address}/&lt;model-path&gt;/v1/chat/completions</code> – the model path is derived from the name: <code>Qwen3.8-27B</code> → <code>qwen3-8-27b</code></td></tr>
    <tr><td>Ollama</td><td><code>{address}/models</code>, falling back to <code>{address}/api/tags</code></td><td><code>{address}/v1/chat/completions</code></td></tr>
    <tr><td>OpenAI compatible</td><td><code>{address}/models</code></td><td><code>{address}/chat/completions</code> – a single vLLM model, OpenAI, LM Studio, LiteLLM, etc.</td></tr>
  </tbody>
</table></div>

<h3>Default connections</h3>
<p>The connections used until you save your own are defined in <code>src/Seeds.php</code> → <code>llmConnections()</code>. Adjust them to your environment on this screen; your changes are stored in the database.</p>
<?php g_note('Connections and API keys are stored in the database (<code>mk_settings.llm_connections</code>) and are not included in the <b>Backup / export</b> file. If nothing has been changed, the defaults are used; if you delete all connections, the chat does not work.', 'info'); ?>
<?php g_note('Only masked text is ever sent to the AI, whichever connection you add. Still, check your data policy before adding an external (internet) service: masked text still contains business context.', 'warn'); ?>

<?php g_section('backup', 'Backup / restore'); ?>
<ul>
  <li><b>Export:</b> Downloads all rules, categories and editable settings as JSON. The administrator password and the installation-specific secret key are not exported.</li>
  <li><b>Import:</b> Built-in rules are matched by their key (bkey) and updated; custom rules are matched by name + type + category and added if missing. Importing the same file twice does not duplicate rules. With <b>Delete existing custom rules first</b>, your custom rules are replaced by those in the file. Exports from the earlier Turkish-only version (<code>maskara</code>) are accepted too.</li>
  <li><b>Add missing defaults:</b> Adds built-in rules introduced in a new version; existing rules are left untouched.</li>
  <li><b>Reset built-ins:</b> Resets all built-in rules and categories to their original state. Your custom rules are kept; changes made to built-in rules are lost.</li>
</ul>
<?php g_note('Always export a backup before a big change (e.g. before resetting the built-ins or importing from another installation).', 'tip'); ?>

<?php g_section('security', 'Security and change log'); ?>
<ul>
  <li><b>Change the administrator password:</b> Asks for the current password and a new one of at least 8 characters.</li>
  <li><b>Require login for the masking screen too:</b> When on, masking is possible only with an administrator session.</li>
  <li><b>Session timeout:</b> In minutes, at least 5.</li>
  <li><b>Change log:</b> Rule, category and settings changes and successful/failed login attempts are recorded with time and IP address (the last 500 entries are shown).</li>
</ul>
<p>All administrative requests are protected by the session and a CSRF token. Masked texts are not stored on the server; the mapping table stays only in the user's browser page.</p>

<?php g_section('trouble', 'Troubleshooting'); ?>
<h3>A value is not masked</h3>
<ol>
  <li>Paste the line into the Live test area of a suitable rule (e.g. <i>kv_password</i> for a password).</li>
  <li>If the value appears under <b>Rejected candidates</b>, fix it according to the reason: remove it from the keep list, relax the validator or length limit, review the "Skip variable references" option.</li>
  <li>If there is no candidate at all, no rule catches the value: add a new key to a key=value rule, write a new regex rule or add the term to the custom dictionary.</li>
  <li>If an FQDN is not recognized, its extension is most likely missing from the <b>TLD list</b>.</li>
  <li>If the rule runs in only one mode, try changing the test mode.</li>
</ol>
<h3>Unneeded things are masked</h3>
<ul>
  <li>If a value should stay visible everywhere, add it to <b>Values that are never masked</b>; for a domain use <b>Domains that are not masked</b>, for an IP range <b>IPv4 ranges that are not masked</b>.</li>
  <li>If a specific rule produces false positives (find it in the "Detected by rule" column of the mapping table), add an exclude regex, lower its priority or disable it.</li>
  <li>If code expressions such as <code>logger.info</code> are taken for first.last, add them to <b>Code tokens</b>; if file names are taken for users, add them to <b>Parts excluded from first.last</b>.</li>
</ul>
<h3>"broken" label in the rules list</h3>
<p>The rule's regex cannot be compiled; the engine skips this rule and the others keep working. Hover over the label to see the error message. Usually a change made to a list (e.g. a special character added to the TLD list) breaks rules that use a template.</p>
<h3>Chat: the model list cannot be loaded</h3>
<ul>
  <li>Open the connection on the <b>AI connections</b> screen and press <b>Test connection</b>; the error message shows the problem. The chat screen also reports unreachable connections with a yellow warning.</li>
  <li>Check access from the server to the endpoint: the model list address (e.g. <code>{address}/v1/models</code>) must open from the server itself (proxy, firewall).</li>
  <li>If the error is about TLS/certificates, check PHP's <code>curl.cainfo</code> setting; as a last resort, turn off <b>Verify the TLS certificate</b> for the connection.</li>
  <li>If a model says “HTTP 302/401” or “authorization required”, it needs an API key: enter the key on the relevant connection and exclude the model from the connection without a key.</li>
</ul>
<h3>Chat: the reply arrives all at once instead of streaming</h3>
<p>A compression or buffering layer in between (e.g. Apache <code>mod_deflate</code>, a reverse proxy) is holding the stream. Turn off compression and buffering for <code>text/event-stream</code> responses; the application already sends the <code>X-Accel-Buffering: no</code> header.</p>
<h3>Chat: unrestored tokens in the reply</h3>
<p>The model has distorted or invented a token. Check that the token instructions in the system prompt are in place; if the problem keeps happening with a particular model, choose another model as the default. Keeping the token template short and distinctive, such as <code>&lt;{CAT}_{n}&gt;</code>, makes it easier for models to preserve tokens.</p>
<h3>Chat: a screenshot is not read</h3>
<ul>
  <li>If the window stays in the “Preparing OCR” state, check that the browser can fetch the files under <code>assets/vendor/tesseract/</code> (e.g. the <code>…/lang/tur.traineddata</code> address should open). The files are about 13 MB; they are cached in the browser after first use.</li>
  <li>If text is read incompletely, the image may be too small or blurry; zoom in and take a new screenshot. The user must cover unreadable areas by hand.</li>
  <li>If a model gives a “not a multimodal model” error, remove it from the connection's <b>Vision-capable models</b> list.</li>
</ul>
<h3>Masking is slow or times out</h3>
<p>Check on the Overview that PCRE JIT is on, lower <b>Maximum input</b> and simplify custom regexes with broad, nested repetition such as <code>.*</code>.</p>

<?php g_section('technical', 'Technical notes'); ?>
<ul>
  <li><b>There is no installation.</b> When the folder is copied to the web server, the database, tables and default rules are created automatically on the first request.</li>
  <li><b>Database connection:</b> <code>config.php</code> (host, port, database name, user, password). This file is not accessible over the web.</li>
  <li><b>Tables:</b> <code>mk_settings</code> (settings), <code>mk_categories</code>, <code>mk_rules</code>, <code>mk_audit</code> (change log), <code>mk_usage</code> (numeric statistics).</li>
  <li><b>Built-in rules</b> are defined in <code>src/Seeds.php</code>; the engine is in <code>src/Engine.php</code>. After upgrading, run <b>Backup → Add missing defaults</b>.</li>
  <li><b>The interface</b> uses no CDN; Tailwind is loaded from the local <code>assets/js/tailwind.js</code> file, so no internet connection is needed.</li>
  <li><b>Access:</b> the <code>src/</code>, <code>partials/</code>, <code>guides/</code> and <code>lang/</code> folders are not accessible over the web.</li>
  <li><b>Chat components:</b> page <code>chat.php</code>, client <code>assets/js/chat.js</code>, screenshot reading and covering <code>assets/js/vision.js</code> (Tesseract.js 5.1.1, <code>assets/vendor/tesseract/</code>), connection client <code>src/Llm.php</code> (default connections in <code>src/Seeds.php</code> → <code>llmConnections()</code>); endpoints <code>api.php?action=chat.models</code>, <code>chat.send</code> and, for administration, <code>llm.list</code>, <code>llm.save</code>, <code>llm.test</code>, <code>llm.reorder</code>, <code>llm.delete</code>. The application root opens the Chat screen directly.</li>
  <li><b>Language:</b> The interface is English by default; the language button in the top bar switches to Turkish (the choice is kept in the <code>mki_lang</code> cookie). Translations live in <code>lang/tr/*.php</code>, each returning an <code>[English =&gt; Turkish]</code> array; PHP uses <code>t()</code> / <code>te()</code> and JavaScript uses <code>MK.t()</code>. Names of built-in rules and categories are stored in English and translated on display.</li>
  <li><b>Database and session:</b> Maskink uses its own <code>maskink</code> database, the <code>MASKINKSESS</code> session cookie and <code>mki-</code> prefixed browser storage, so it is independent of other installations on the same server.</li>
</ul>

<?php g_close(); ?>
