<?php // User guide – English content (included by guide.php)
g_open(
    'User Guide',
    'Maskink stands between you and the AI: every message you write on the <b>Chat</b> screen is sent to the AI only after its sensitive information (IPs, server names, usernames, passwords, keys…) has been replaced with <b>consistent placeholders</b>; the reply is automatically restored to the real values. On the <b>Mask</b> screen you can mask a text by hand and copy it to another AI tool.',
    [
        'chat'          => 'Chat',
        'chat-screen'   => 'The chat screen',
        'screenshots'   => 'Adding screenshots',
        'chat-privacy'  => 'How chats are stored',
        'quick'         => 'Mask: quick start',
        'input'         => 'Loading text',
        'options'       => 'Mode and options',
        'what'          => 'What gets masked?',
        'output'        => 'Output tabs',
        'ai'            => 'Working with an AI',
        'warnings'      => 'Warnings and statistics',
        'privacy'       => 'Privacy',
        'practices'     => 'Good practices',
        'faq'           => 'Frequently asked questions',
    ],
    'admin-guide.php',
    'Administrator guide'
);
?>

<?php g_section('chat', 'Chat'); ?>
<p>The <b>Chat</b> screen (the first tab in the top menu) lets you talk safely to the AI models you have connected. You write with the real values; the AI only sees the masked version.</p>
<ol>
  <li><b>You write.</b> Type your question; paste logs, scripts or configuration as they are.</li>
  <li><b>Maskink masks.</b> The message is masked on the server; only tokens such as <?= g_tok('<IP_1>', '#3b82f6') ?> and <?= g_tok('<USER_1>', '#16a34a') ?> reach the AI.</li>
  <li><b>The AI replies.</b> The model uses the same tokens in its reply and in the commands it suggests.</li>
  <li><b>The reply is restored.</b> Tokens are turned back into the real values in your browser, so you can copy and use the commands directly.</li>
</ol>
<?php g_example(
    "I cannot connect to web01.acme.local (10.20.30.40)\nwith ssh admin@10.20.30.40.",
    "I cannot connect to <FQDN_1> (<IP_1>)\nwith ssh <USER_1>@<IP_1>."
); ?>
<p>If the model writes <code>ssh -vvv &lt;USER_1&gt;@&lt;IP_1&gt;</code> in its reply, you see <code>ssh -vvv admin@10.20.30.40</code> on screen. Restored values are highlighted in their category color; hover over one to see which token the AI saw.</p>

<h3>Consistent tokens throughout a chat</h3>
<p>A value keeps the token it got in the first message in every later message of the chat. Even if you later write a previously masked value without context (e.g. first <code>web01.acme.local</code>, then just <code>web01</code>), it is recognized and masked. This way the AI never loses track of "which server, which user" during the chat.</p>
<?php g_note('Maskink is an automatic helper. The rules may miss some values that appear without context in plain sentences (e.g. “log in as admin”). When sending sensitive content, turn on <b>Preview before sending</b>.', 'warn'); ?>
<?php if ($needLogin): ?>
  <?php g_note('On this installation, login is required for chat and masking. If sending asks you to log in, get access details from your administrator.', 'warn'); ?>
<?php endif; ?>

<?php g_section('chat-screen', 'The chat screen'); ?>
<h3>Choosing a model</h3>
<p>The <b>Model</b> list at the top shows the models available on the configured AI connections. The number next to a model is its context length (e.g. <code>128K</code>): how long a chat and how much log it can take. Your choice is remembered per chat. If the list is out of date, press the <b>refresh</b> button next to it.</p>
<ul>
  <li>Large general-purpose models are a good default for analysis and explanations.</li>
  <li>Coder models are better for writing scripts and fixing code.</li>
  <li>Smaller models answer faster. Models marked <b>· vision</b> in the list can also see screenshots.</li>
</ul>
<p>Some models “think” before they answer. That reasoning is shown above the reply in a collapsible <b>Reasoning</b> section; it is restored to the real values as well.</p>

<h3>Writing a message</h3>
<ul>
  <li><kbd>Enter</kbd> sends the message, <kbd>Shift</kbd> + <kbd>Enter</kbd> starts a new line.</li>
  <li><b>File:</b> Adds the contents of a text file to the message; you can also drag and drop the file onto the input area. Image files are added as <a href="#screenshots">screenshots</a>. At most <b><?= number_format($maxKb) ?> KB</b> can be sent in one message.</li>
  <li><b>Preview before sending:</b> When checked, pressing send shows the masked text that will go to the AI; check it and choose <b>Send</b> or <b>Cancel</b>.</li>
  <li><b>Stop:</b> Interrupts the stream while a reply is coming in; what has arrived so far is kept.</li>
</ul>
<p>Below a sent message you see how many values were masked (<b>4 values masked</b>). Masked values in the message are highlighted; hover to see which token went to the AI. If a risky word such as <code>password</code> or <code>token</code> appears on a line where nothing was masked, a <b>risk warning</b> is shown.</p>

<h3>Reply buttons</h3>
<ul>
  <li><b>Copy:</b> Copies the reply with the real values.</li>
  <li><b>Copy masked:</b> Copies the reply with its tokens (e.g. to share it with someone).</li>
  <li><b>Regenerate:</b> Deletes the last reply and asks the same question again.</li>
  <li><b>Copy</b> at the top right of code blocks copies the command with the real values.</li>
</ul>
<p>The model name, the duration and the number of tokens used are shown below the reply. If the model invents a token that is not in the mapping (e.g. an <?= g_tok('<IP_9>', '#b45309') ?> that never appeared), it is underlined with a dashed yellow line and a warning is shown; that value cannot be restored.</p>

<h3>What the AI sees</h3>
<p>If you check <b>What the AI sees</b> at the top right, the whole chat is shown as it was sent to and received from the AI, that is, with tokens. Use it to check what was hidden; uncheck it to return to the real values.</p>

<h3>Mapping</h3>
<p>The <b>Mapping</b> button lists which token stands for which real value in this chat; the number next to it is the count of distinct masked values. A file saved with <b>Download JSON</b> can also be loaded on the <b>Restore</b> tab of the Mask screen.</p>
<?php g_note('The mapping table and the JSON file contain the real values. Do not leave them open while sharing your screen, and do not share the file with anyone or with the AI.', 'warn'); ?>

<h3>Chat list</h3>
<p>Your earlier chats are in the list on the left. <b>New chat</b> opens an empty chat; every chat has its own mapping table, so tokens are not shared between chats. Hover over a chat and use the trash icon to delete it. On narrow screens the list opens with the menu button in the top bar.</p>

<?php g_section('screenshots', 'Adding screenshots'); ?>
<p>You can also ask about an error screen, a table or console output as a screenshot. Before the image goes to the AI, <b>the sensitive text in it is covered</b>.</p>
<ol>
  <li><b>Add it.</b> Paste the screenshot into the input area with <kbd>Ctrl</kbd> + <kbd>V</kbd>, drag and drop it, or choose it with <b>File</b>. Up to 4 images can be added to a message.</li>
  <li><b>The image is read.</b> The text in the image is read twice <b>on your own computer</b> (OCR): once with the page layout and once for scattered text such as table cells. The image is not sent anywhere for this. On first use, loading the OCR files can take a few seconds.</li>
  <li><b>It is covered.</b> The text that was read is masked with the Maskink rules and each masked value is covered with its token (e.g. <?= g_tok('<IP_1>', '#3b82f6') ?> is written where the IP address was). Cells in table columns with headers such as <i>User, Username, Account, Server, Host, Display Name</i> are masked even without context next to them.</li>
  <li><b>Check and correct.</b> The window that opens shows the image that will be sent to the AI (see the table below).</li>
  <li>Choose <b>Add to message</b>, then write your question and send it. Before sending, you can reopen the window with <b>Edit</b> on the attachment card.</li>
</ol>
<div class="mk-table"><table>
  <thead><tr><th>What do you want to do?</th><th>How?</th></tr></thead>
  <tbody>
    <tr><td>Mask a username, server name… that was left visible</td><td><b>Click</b> the word (for several words, <b>drag to select</b>) and choose a category from the menu: <i>Username, Server name, Person name…</i> The value is masked with the same token <b>everywhere</b> in the image and the text, including your message if it appears there.</td></tr>
    <tr><td>Uncover something that was covered by mistake</td><td><b>Right-click</b> the covered area: its mask is removed at once and the value stays visible everywhere. (A left click does the same through the menu.)</td></tr>
    <tr><td>Cover something that is not text (a picture, a signature, a logo)</td><td><b>Drag</b> on an empty area to draw a dark box. Right-clicking a box deletes it.</td></tr>
    <tr><td>Undo my change</td><td><b>Undo</b> reverts masking, unmasking and box actions in order.</td></tr>
    <tr><td>See the uncovered version</td><td>Check <b>Show original</b>; it is shown only on your screen.</td></tr>
  </tbody>
</table></div>
<p>Words with a <b>dashed orange frame</b> need your attention: words that could not be read reliably, or that look like a username / server name (<code>j.doe</code>, <code>svc_backup</code>, <code>admin01</code>) but were not masked. Click to mask them if needed, or choose <b>Cover orange-marked words with boxes</b>.</p>
<p>The AI receives both the covered image and the masked version of the text read from it. If its reply says <?= g_tok('<IP_1>', '#3b82f6') ?>, you see the real IP address. The same value in your message and in the image gets the same token.</p>
<ul>
  <li><b>Vision-capable models</b> (marked <b>· vision</b> in the list) see the image; they can also interpret the table layout, colors and icons.</li>
  <li><b>Other models</b> do not receive the image; only the text read from it is sent, masked. The attachment card tells you so.</li>
  <li>The image stays in your sent message; click it to enlarge. The <b>What the AI sees</b> checkbox at the top right switches images just like text: unchecked shows the original image, checked shows the covered version. In the enlarged view you can also switch with <b>Show original</b>. The <b>Text read from the image</b> section shows the text that was read.</li>
</ul>
<?php g_note('Reading text in images is not perfect: very small, blurry, skewed or embedded text may not be read and therefore not covered. The rules may also not recognize every value, just as in plain text. Always review the image in the window before sending, and cover sensitive areas that are not text (faces, signatures, logos) by hand.', 'warn'); ?>

<?php g_section('chat-privacy', 'How chats are stored'); ?>
<ul>
  <li>Chats and mapping tables are stored <b>only on the computer you use, in that browser</b>. They are not saved on the server or in the database.</li>
  <li>When you sign in from another computer or another browser your chats are <b>not visible</b>; nobody else can see your chats either.</li>
  <li>Chats opened in a private (InPrivate/incognito) window are deleted when the window closes. Clearing the browser's site data also deletes the chats.</li>
  <li>Both the original and the covered version of your screenshots are stored <b>only in this browser</b>, like the texts with real values; only the covered version goes to the AI. If the browser storage fills up, the original images of the oldest chats are deleted first, then the covered images; text and mappings are kept.</li>
  <li>Messages are held in memory only while they pass from the server to the AI. The text sent to the AI contains no real values.</li>
</ul>
<?php g_note('Chats stay in the browser <b>with their real values</b>. If you share the computer with others under the same Windows user, use the <b>Delete all chats</b> button at the bottom left when you are done.', 'warn'); ?>

<?php g_section('quick', 'Mask: quick start'); ?>
<p>The <b>Mask</b> screen is for masking a text without sending it to the chat: you can copy the masked output to another AI tool or a ticket, and restore the reply here as well.</p>
<ol>
  <li>Open the <b>Mask</b> page from the top menu.</li>
  <li>Paste the log or script into the <b>Input</b> area on the left, or drag and drop the file onto it.</li>
  <li>Press <b>Mask</b> (shortcut: <kbd>Ctrl</kbd> + <kbd>Enter</kbd>).</li>
  <li>Check the <b>Masked</b> tab on the right for any value that was missed.</li>
  <li>Give the output to the AI with <b>Copy</b> or <b>Mapping ▾ → Copy with an AI explanation</b>.</li>
  <li>Paste the AI's reply into the <b>Restore</b> tab to turn the tokens back into the real values.</li>
</ol>

<?php g_example(
    "Nov  2 09:14:07 web01 sshd[2211]: Accepted password for john.smith from 10.20.30.40 port 52144\nmysql -u app_user -p'S3cr3t!2024' -h db01.corp.local",
    "Nov  2 09:14:07 <HOST_1> sshd[2211]: Accepted password for <USER_1> from <IP_1> port 52144\nmysql -u <USER_2> -p'<PASS_1>' -h <FQDN_1>"
); ?>
<p>The same value is replaced with the <b>same token</b> everywhere in the text: even if <code>web01</code> appears a hundred times, it is always <?= g_tok('<HOST_1>', '#f59e0b') ?>. This lets the AI work out "what happened on which server".</p>
<?php if ($needLogin): ?>
  <?php g_note('On this installation, login is required for the masking screen. If the Mask button asks you to log in, get access details from your administrator.', 'warn'); ?>
<?php endif; ?>

<?php g_section('input', 'Loading text'); ?>
<ul>
  <li><b>Paste:</b> Paste the content directly into the Input area.</li>
  <li><b>Open file:</b> You can choose <code>.log</code>, <code>.txt</code>, <code>.sh</code>, <code>.ps1</code>, <code>.py</code>, <code>.conf</code>, <code>.cfg</code>, <code>.ini</code>, <code>.json</code>, <code>.xml</code>, <code>.yml</code>, <code>.csv</code>, <code>.env</code>, <code>.sql</code> and similar text files.</li>
  <li><b>Drag and drop:</b> Drop the file onto the Input area.</li>
  <li><b>Character encoding:</b> UTF-8, UTF-16 (Windows event exports) and Turkish Windows-1254 files are detected automatically; non-ASCII characters are preserved.</li>
  <li><b>Sample:</b> Loads ready-made, made-up samples to try the tool (mixed log, bash, PowerShell, switch/router configuration).</li>
  <li><b>Clear:</b> Deletes the input and the results.</li>
</ul>
<p>At most <b><?= number_format($maxKb) ?> KB</b> of text can be masked at once. Split larger files into parts.</p>

<?php g_section('options', 'Mode and options'); ?>
<h3>Mode: Automatic / Log / Script</h3>
<p>Some rules only make sense for a particular kind of content. For example, in script mode code expressions such as <code>$user.Name</code> are not taken for usernames, while in log mode the server name in the syslog header is caught.</p>
<ul>
  <li><b>Automatic:</b> Maskink guesses from the line structure whether the content is a log or a script. Use this in most cases.</li>
  <li><b>Log:</b> For server, firewall, switch, NAC and application logs.</li>
  <li><b>Script:</b> For bash, PowerShell, Python and similar code/configuration files.</li>
</ul>
<p>The mode that was used is shown as <b>Mode:</b> in the statistics line below the output.</p>

<h3>Categories to mask in this run</h3>
<p>Open this section below the Input area to turn off some categories for the current run. For example, when analyzing a network problem you may want to leave IP addresses visible. Your choice is remembered in your browser.</p>
<?php g_note('Do not turn off the Password and Key/Token categories. This information is never needed for analysis and does the most damage when leaked.', 'warn'); ?>

<h3>Live</h3>
<p>When checked, the output updates by itself shortly after you type or paste.</p>

<h3>Propagation</h3>
<p>When a value is found through its context (e.g. <code>user=john</code>), the same value is also masked where it appears elsewhere in the text without context (e.g. <code>john logged in</code>). It is on by default and should stay on.</p>

<?php g_section('what', 'What gets masked?'); ?>
<p>Maskink recognizes the categories below. The token format can be changed by your administrator; the table shows the current settings of this installation.</p>
<?php g_category_table(); ?>
<p>Examples of what is recognized:</p>
<ul>
  <li><b>Passwords:</b> <code>password="…"</code>, <code>pwd=…</code>, <code>-p'…'</code>, <code>ConvertTo-SecureString "…"</code>, <code>sshpass -p</code>, <code>user:password@</code> in URLs, Cisco <code>enable secret</code>, SNMP communities, <code>/etc/shadow</code> hashes…</li>
  <li><b>Keys:</b> PEM/SSH private key blocks, JWTs, AWS/GitHub/Slack and similar token patterns, <code>Authorization: Bearer …</code>, cookies, high-entropy secret values.</li>
  <li><b>Users:</b> the <code>first.last</code> pattern, <code>CORP\user</code>, <code>user@host</code>, account names in Windows event logs, <code>C:\Users\…</code> and <code>/home/…</code> paths.</li>
  <li><b>Network:</b> IPv4/IPv6, MAC, FQDNs, server names in URLs, UNC paths (<code>\\server\share</code>), LDAP DNs.</li>
  <li><b>Personal data:</b> full names, e-mail, phone numbers, checksum-verified Turkish ID numbers (TCKN), IBANs and card numbers.</li>
</ul>
<p>If server, project or customer names specific to your organization cannot be recognized by a pattern, your administrator can add them to the <b>Custom dictionary</b>.</p>

<?php g_section('output', 'Output tabs'); ?>
<h3>Masked</h3>
<p>This is the text to give to the AI. Tokens are shown in their category color; clicking a token highlights it everywhere in the text.</p>
<h3>Highlighted original</h3>
<p>Shows the original text with the masked parts highlighted. Use it to check what was masked and what was missed.</p>
<?php g_note('This tab contains the real data. Copy the <b>Masked</b> tab to the AI, not this one.', 'warn'); ?>
<h3>Mapping table</h3>
<p>Lists which real value each token stands for, how many times it occurs and which rule found it. Original values are blurred by default; check <b>Show original values</b> to see them. You can search by token, category or rule name.</p>
<h3>Restore</h3>
<p>Paste the AI's tokenized reply here and press <b>Restore</b>; the tokens are turned back into the real values using the current mapping table. If you closed the page, you can also restore by loading a mapping JSON file you downloaded earlier with <b>Load mapping JSON</b>. Everything happens in your browser.</p>

<h3>Top buttons</h3>
<ul>
  <li><b>Copy:</b> Copies the masked text to the clipboard.</li>
  <li><b>Download:</b> Downloads the masked text as a file.</li>
  <li><b>Mapping ▾ → Download JSON / CSV:</b> Saves the mapping table. This file <b>contains the real values</b>; keep it somewhere safe and do not share it with anyone or with the AI.</li>
  <li><b>Mapping ▾ → Copy with an AI explanation:</b> Copies the masked text with a short note at the top that tells the AI what the tokens mean and that it must keep them unchanged. No real values are included.</li>
</ul>

<?php g_section('ai', 'Working with an AI'); ?>
<?php g_note('If you use the AI models connected to Maskink, you do not need the steps below: the <a class="font-semibold underline" href="chat.php">Chat</a> screen masks and restores every message by itself. These steps are for copying by hand to another AI tool.', 'info'); ?>
<ol>
  <li>Mask the text and review the <b>Masked</b> tab.</li>
  <li>Choose <b>Mapping ▾ → Copy with an AI explanation</b> and paste it into the AI together with your question.</li>
  <li>The AI will use tokens such as <?= g_tok('<HOST_1>', '#f59e0b') ?> and <?= g_tok('<USER_2>', '#16a34a') ?> in its reply.</li>
  <li>Paste the reply (e.g. the suggested commands) into the <b>Restore</b> tab; it becomes usable with the real values.</li>
</ol>
<?php g_note('To avoid losing the mapping table during a long session, back it up with <b>Mapping ▾ → Download JSON</b>. If you need to add a new log to the same conversation, remember that numbers may change in a new run; mention the new output as a separate part.', 'tip'); ?>

<?php g_section('warnings', 'Warnings and statistics'); ?>
<p>If a sensitive keyword such as <code>password</code>, <code>secret</code> or <code>token</code> appears on a line but nothing on that line was masked, a yellow warning appears above the output. Click the line number to jump to that line. This suggests a value may have been missed; always check the line and report it to your administrator if needed.</p>
<p>The statistics line below the output shows the detected mode, the unique values and occurrences per category, the total number of matches and the processing time. Click a category to filter the mapping table by it.</p>

<?php g_section('privacy', 'Privacy'); ?>
<ul>
  <li>The text you mask is <b>not stored</b> on the server; it is only kept in memory while it is processed.</li>
  <li>On the Mask screen the mapping table (token → real value) stays only in the open browser page; it is deleted when you close the page. For how the Chat screen stores data, see <a href="#chat-privacy" class="font-medium text-violet-700 underline dark:text-violet-300">How chats are stored</a>.</li>
  <li>If the administrator has enabled statistics, only numeric information (character count, match count, duration) is recorded; content is never recorded.</li>
  <li>Your browser only remembers your preferences (mode, Live, Propagation, the categories you turned off, the interface language).</li>
</ul>

<?php g_section('practices', 'Good practices'); ?>
<ul>
  <li><b>Always review.</b> Maskink is an automatic helper and does not give a hundred percent guarantee. Take a quick look at the Masked output before sending.</li>
  <li><b>Do not send more than needed.</b> Pick the lines related to the problem; take the relevant time range instead of the whole log file.</li>
  <li><b>Protect mapping files.</b> JSON/CSV mapping files contain the real data; delete them when you are done.</li>
  <li><b>Report what was missed.</b> If you see an unmasked password, server name or organization term, report it to your administrator with a sample line (with the real value changed); a rule can be added.</li>
  <li><b>Mind your screen.</b> Do not share your screen while the highlighted original or the mapping table is open.</li>
</ul>

<?php g_section('faq', 'Frequently asked questions'); ?>
<h3>Can I see my chat from another computer?</h3>
<p>No. Chats are stored only in the browser of the computer you wrote them on. To keep a reply, copy it with <b>Copy</b> and add it to your own notes.</p>
<h3>I still see tokens like &lt;IP_1&gt; in the AI's reply in the chat.</h3>
<p>If the model wrote a token incorrectly (e.g. with an extra space) or invented a number that is not in the mapping, it cannot be restored; such tokens are highlighted in yellow. Try <b>Regenerate</b> or remind the model to “use the placeholders exactly as they are”. Also make sure <b>What the AI sees</b> is not checked.</p>
<h3>The model list is empty or says “Could not load the model list”.</h3>
<p>The AI service may be unreachable at the moment. Press the refresh button next to the model list after a few minutes; if the problem persists, tell your administrator.</p>
<h3>The reply stopped halfway.</h3>
<p>In long chats the model's context limit may fill up. Open a <b>New chat</b> and ask again with only the necessary information, or choose a model with a longer context.</p>
<h3>A value was not masked. What should I do?</h3>
<p>First make sure its category is enabled under "Categories to mask in this run" and that you chose the right mode. If the value is still not masked, change it by hand before sending and tell your administrator.</p>
<h3>Something that should not be masked was masked.</h3>
<p>If you need it for analysis, you can turn off that category for the run. If it happens often (e.g. internal IP ranges, <code>localhost</code>), your administrator can add it to a permanent exception list.</p>
<h3>Are the numbers in tokens always the same?</h3>
<p>Numbers are consistent within one run, but may change when you mask a different text again. If your administrator set the token format to use <code>{hash}</code>, the same value always gets the same token.</p>
<h3>The AI changed the tokens and restoring does not work.</h3>
<p>Restoring only works when the tokens are kept exactly. Tell the AI not to change the tokens; <b>Copy with an AI explanation</b> says this automatically.</p>
<h3>I refreshed the page and the mapping is gone.</h3>
<p>For security, the mapping is kept only in the page. If you downloaded a mapping JSON file earlier you can load it on the Restore tab; otherwise mask the same input again.</p>

<?php g_close(); ?>
