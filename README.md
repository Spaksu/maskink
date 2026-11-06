<p align="center"><img src="assets/img/logo.png" alt="Maskink logo" width="420"></p>

# Maskink

**Redact your logs, scripts and chat messages before they reach an AI, and get the real values back in the reply.**

**[Try the live demo](https://spaksu.github.io/maskink/)**: no sign-up; your text and settings stay in your browser, and AI replies are simulated.

> **Mask** + **ink**: like running a black marker over the sensitive lines of a document (*redaction*). The marker is in your hand, and so is the key: everything that gets inked out can be restored.

Maskink replaces sensitive values in switch, firewall, NAC, server and application logs and in bash / PowerShell / Python scripts (IP addresses, hostnames, FQDNs, usernames, first.last names, passwords, API/SSH keys and more) with **consistent placeholders** such as `<IP_1>`, `<USER_2>` and `<PASS_1>`. The AI only ever sees the masked text; its reply is turned back into the real values in your browser.

```text
Original :  Nov  2 09:14:07 web01 sshd[2211]: Accepted password for john.smith from 10.20.30.40 port 52144
            mysql -u app_user -p'S3cr3t!2024' -h db01.corp.local

Masked   :  Nov  2 09:14:07 <HOST_1> sshd[2211]: Accepted password for <USER_1> from <IP_1> port 52144
            mysql -u <USER_2> -p'<PASS_1>' -h <FQDN_1>
```

![How it works: your browser sends real text to Maskink, the AI receives only masked text, and the masked reply is restored for you](docs/how-it-works.png)

The interface is **English by default** with a **Turkish** option (one click in the top bar).

![Mask screen](docs/screenshots/mask.png)

---

## Screenshots

> All IPs, users, servers and passwords in the screenshots are **made-up sample data** shipped with the app.

### Masked AI chat

You write and read with the real values; only placeholders reach the model. **What the AI sees** shows the masked side of the conversation.

![Chat](docs/screenshots/chat.png)

### Mapping table

Which token stands for which value, how often it occurs and which rule caught it. Original values are blurred by default.

![Mapping table](docs/screenshots/mapping.png)

### Rules

95 built-in rules (regex, key = value and dictionary), each with a live test, plus your own rules. See the [full rule list](Rules.md).

![Rules](docs/screenshots/rules.png)

### Built-in guides and Turkish interface

| User guide | Turkish interface |
|---|---|
| ![User guide](docs/screenshots/guide.png) | ![Turkish interface](docs/screenshots/turkish.png) |

---

## Features

### Masking
- **Automatic / Log / Script** modes; choose which categories to mask per run.
- **Consistent tokens:** the same value always gets the same token throughout the text (and the whole chat).
- **Propagation:** a value caught as a password or username in one place is masked everywhere else in the text too.
- **Restore:** turn an AI reply back into the real values with the mapping table in one click.
- **Risk warnings:** lines that contain words such as `password`, `token` or `secret` but had nothing masked are flagged.
- Live preview, highlighted original view and mapping table export (JSON / CSV).

### Categories
| Category | Examples |
|---|---|
| Password, Key / Token | `password=…`, `-p'…'`, SNMP communities, PEM/SSH keys, JWTs, `Bearer …`, AWS/GitHub/Slack tokens |
| IPv4, IPv6, MAC | `10.20.30.40`, `fe80::1`, `00:1a:2b:3c:4d:5e` |
| Hostname, FQDN, AD domain | `web01`, `db01.corp.local`, `CORP\user` |
| Username, Person, E-mail | `john.smith`, `John Smith`, `john@example.com` |
| Windows SID, Phone | `S-1-5-21-…`, `+90 …` |
| Turkish ID (TCKN), IBAN, Card | checksum / mod-97 / Luhn validated |
| Custom term | your organization's own dictionary |

### AI chat
- Messages are masked on the server and **only the masked history** goes to the model; replies stream over SSE and are restored in the browser.
- **vLLM gateway**, **Ollama** and **OpenAI-compatible** connections (OpenAI, LM Studio, LiteLLM, …).
- Multiple connections, ordering, automatic or manual model lists, exclude and ordering rules.
- **Screenshots:** text in images is read with OCR in the browser (local Tesseract.js) and sensitive areas are covered with their tokens; an editor lets you mask or unmask by hand.
- Chats and mappings are kept only in the browser (localStorage), never on the server. API keys are never sent to the browser.

### Administration
- **Rule types:** regex, key = value (INI/JSON/YAML/XML, prefix/suffix matching) and dictionary rules.
- Filtering and validators, per-rule live test, bulk actions, duplicate and reset to built-in.
- Categories, token templates (`{CAT}`, `{n}`, `{hash}` …) and keep lists such as IP ranges that are never masked.
- Backup / export, import, change log.
- User guide (`guide.php`) and administrator guide (`admin-guide.php`) in English and Turkish.

---

## Requirements

- PHP 8.1+ (`pdo_mysql`, `mbstring`, `curl`)
- MySQL 8+ or MariaDB 10.4+
- Apache (`.htaccess` blocks access to `config.php`, `src/`, `partials/`, `guides/` and `lang/`)

No internet connection is needed: Tailwind and Tesseract.js ship with the repository; no CDN is used.

## Installation

There is no setup wizard and no Composer.

1. Copy the repository under your web server:
   ```bash
   git clone https://github.com/Spaksu/maskink.git
   ```
2. Create the configuration file and enter your database details:
   ```bash
   cp config.example.php config.php
   ```
3. Open the app in your browser. **The database, tables and default rules are created automatically on the first request.**
4. Open ⚙️ **Settings** at the top right and set the administrator password (at least 8 characters).
5. To use the chat, add at least one connection under **Settings → AI connections**. The app ships without any predefined connection.

> If the database user cannot create databases, create an empty `maskink` database first.

## Pages

| Page | Description |
|---|---|
| `chat.php` | Masked AI chat (default start page) |
| `index.php` | Mask / restore text |
| `settings.php` | Rules, categories, general settings, AI connections |
| `guide.php` | User guide |
| `admin-guide.php` | Administrator guide (login required) |

## Project structure

```text
├── api.php                 JSON API (mask, chat.send, rule.*, llm.* …)
├── chat.php / index.php    User screens
├── settings.php            Administration
├── guide.php, admin-guide.php
├── config.example.php      Sample configuration
├── Rules.md                List of built-in rules and categories
├── src/
│   ├── Engine.php          Masking engine
│   ├── Seeds.php           Built-in categories, rules and settings
│   ├── Llm.php             vLLM / Ollama / OpenAI client (SSE)
│   ├── I18n.php            Translation helpers t() / te()
│   ├── Repo.php, Db.php    Database and schema
│   ├── Settings.php, Auth.php
│   └── bootstrap.php       Automatic setup and schema version
├── lang/tr/                Turkish translations ([English => Turkish] arrays)
├── guides/                 Guide texts per language
├── partials/               Shared layout and guide helpers
├── docs/screenshots/       README screenshots
└── assets/
    ├── js/                 app, chat, settings, vision (OCR) scripts
    ├── css/app.css
    └── vendor/tesseract/   Local Tesseract.js + language data
```

## Adding a language

Source strings are English. To add a language, create `lang/<code>/*.php` files that return `['English text' => 'Translation']` arrays and add the code to `I18n::LANGS` in `src/I18n.php`. PHP uses `t()` / `te()`, JavaScript uses `MK.t()`; placeholders look like `{name}`.

## Live demo

The demo runs the app's own pages on GitHub Pages with the masking engine ported to JavaScript (`demo/src/`), so nothing leaves the browser. Each visitor's rules and settings are kept in their own browser until they press **Reset demo**. Chat replies are simulated samples; password and AI connection changes are disabled.

On every push, `demo/test/` runs `src/Engine.php` and the JavaScript port on the same inputs and blocks publishing if any result differs. If you change the engine, change both.

```bash
php demo/test/gen-fixtures.php && node demo/test/parity.mjs   # compare the engines
php demo/build.php                                            # build the static site into _site/
```

## Security notes

- Masking is rule-based; **no rule set catches everything.** Review the masked output and the risk warnings before sending.
- Masked text still carries business context. Check your data policy before adding an external AI service.
- `config.php` is not committed (`.gitignore`) and is blocked from web access by `.htaccess`.
- The administrator password is stored with `password_hash`; API keys stay on the server and are not included in exports.

## License

Maskink is released under the [MIT License](LICENSE). Bundled third-party components (Tailwind CSS and Tesseract.js in `assets/`) remain under their own licenses.
