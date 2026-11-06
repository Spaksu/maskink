# Built-in rules

Maskink ships with **95 built-in rules** in **19 categories**; 4 of them are off by default. This list is generated from `src/Seeds.php`. You can edit, disable, duplicate or reset every rule in **Settings → Rules**, and add your own.

- **Priority:** the higher value wins when two rules overlap (0–1000).
- **Mode:** `All` = automatic, log and script modes; `Log` / `Script` = only that mode.
- **Default:** whether the rule is on in a fresh installation.

## Categories

| Category | Token | Description |
|---|---|---|
| Password | `<PASS_n>` | Passwords, communities, PSKs, password hashes |
| Key / Token | `<KEY_n>` | API keys, SSH/PEM keys, JWTs, bearer tokens, cookies |
| IPv4 | `<IP_n>` | IPv4 addresses (excluding masks and kept ranges) |
| IPv6 | `<IPV6_n>` | IPv6 addresses |
| MAC address | `<MAC_n>` | MAC addresses (: - . forms) |
| Hostname | `<HOST_n>` | Computer / server / device names |
| FQDN / Domain name | `<FQDN_n>` | Fully qualified domain names, internal domain names |
| AD / NetBIOS domain | `<DOMAIN_n>` | Domain names in the CORP\user form |
| Username | `<USER_n>` | Usernames, first.last, account names |
| Person (full name) | `<PERSON_n>` | Full names, display names |
| E-mail | `<EMAIL_n>` | E-mail (when masked as a whole) |
| Windows SID | `<SID_n>` | Domain / Azure AD security identifiers |
| Phone | `<PHONE_n>` | Phone numbers |
| Turkish ID number (TCKN) | `<TCKN_n>` | TCKN with a verified checksum |
| IBAN | `<IBAN_n>` | IBAN verified with mod-97 |
| Card number | `<CARD_n>` | Card number verified with Luhn |
| Custom term | `<CUSTOM_n>` | Organization terms from the custom dictionary |
| Location | `<LOC_n>` | Physical location details such as building / floor / room / rack |
| Network label (VLAN / port) | `<NETLBL_n>` | VLAN names and VLAN / port descriptions |

## Rules by category

### Password (17)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| user:password in a URL | Regex | 97 | All | On | The scheme://user:password@host form (ftp, http, mysql, mongodb, smb ...). |
| user X group Y password ciphertext Z (Aruba CX / Comware) | Regex | 94 | All | On | Local user definition: username as USER, password / hash as PASSWORD. |
| Typed password/key (password 7 xxx, secret 5 xxx) | Regex | 93 | All | On | Cisco/Aruba "enable secret 5 ...", "password 7 ...", "key-string 7 ...", "pre-shared-key 0 ...". |
| Fortinet/Juniper "set password ..." | Regex | 93 | All | On | FortiGate "set password ENC ...", "set psksecret ...", Juniper set ... authentication encrypted-password. |
| SNMP community / v3 auth-priv key | Regex | 93 | All | On | snmp-server community X, snmp-server host IP [version 2c] X, auth sha X priv aes 128 Y. |
| ConvertTo-SecureString plain text | Regex | 92 | All | On | PowerShell ConvertTo-SecureString "password" -AsPlainText usage. |
| sshpass -p | Regex | 92 | All | On | sshpass -p password ssh ... |
| password/secret at line start | Regex | 92 | All | On | "password xxx" / "enable secret xxx" appearing on its own as a configuration line. |
| TACACS/RADIUS/NTP/OSPF keys | Regex | 92 | All | On | tacacs-server key, radius-server host ... key, ntp authentication-key N md5 X, message-digest-key N md5 X. |
| Password parameter (--password x, -Password "x") | Regex | 91 | All | On | --password, -Password, --passwd, -pwd parameters of Bash/PowerShell/CLI tools. |
| Database CLI password (-pXXX, -P x) | Regex | 91 | All | On | mysql -pPassword (attached), sqlcmd -P, mongo -p, redis-cli -a. |
| define('..PASS/KEY..', 'x') | Regex | 91 | All | On | Password, key, salt and token constants defined with PHP define(). |
| Password keys (key=value) | Key = value | 90 | All | On | All key=value uses such as password=, pwd:, "pass": , $Password = , DB_PASS=, <password>...</password>. Prefixes/suffixes are supported (db_password, smtp.auth.pass). |
| /pass: /password: /rp switches | Regex | 90 | All | On | cmdkey /pass:, schtasks /rp, /password: usage. |
| expect "Password:" → send "..." | Regex | 90 | All | On | The value sent after the password prompt in Expect scripts. |
| net use \\server <password> | Regex | 88 | All | On | Positional password in "net use Z: \\srv\share Password /user:...". |
| crypt / bcrypt password hash | Regex | 85 | All | On | Unix password hashes starting with $1$, $5$, $6$, $2y$, $y$, $apr1$. |

### Key / Token (11)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| PEM / PuTTY private key block | Regex | 100 | All | On | BEGIN ... PRIVATE KEY blocks (RSA, EC, OPENSSH, PGP) and PuTTY .ppk contents are masked entirely. |
| JWT | Regex | 96 | All | On | Three-part JSON Web Token starting with eyJ... |
| Known service keys | Regex | 96 | All | On | AWS (AKIA/ASIA), GitHub (ghp_/github_pat_), GitLab (glpat-), Slack (xox?-), Google (AIza, ya29.), Stripe, OpenAI/Anthropic (sk-), SendGrid, npm, DigitalOcean, HuggingFace, Shopify. |
| Authorization header | Regex | 95 | All | On | Value of Authorization / Proxy-Authorization: Basic\|Bearer\|Digest\|NTLM\|Negotiate ... |
| Bearer token (free text) | Regex | 94 | All | On | "Bearer xxxxx" expressions without a header. |
| SSH public key body | Regex | 92 | All | On | Base64 body of ssh-rsa / ssh-ed25519 / ecdsa keys. The trailing user@host comment is caught by a separate rule. |
| API key / token keys | Key = value | 91 | All | On | api_key=, access_token:, client_secret, AccountKey=, x-api-key, sessionid, csrf token ... |
| Token parameter (--token x, -ApiKey x) | Regex | 89 | All | On | --token, --api-key, -AccessToken, --client-secret, --private-key (excluding path values). |
| Cookie / Set-Cookie header | Regex | 86 | All | On | Value of HTTP Cookie and Set-Cookie lines (contains session ids). |
| LM:NTLM hash pair | Regex | 80 | All | On | LM/NT hash pairs in the 32 hex : 32 hex form. |
| High-entropy secret value | Regex | 40 | All | On | Strings of 32+ characters with letters and digits whose Shannon entropy is above the threshold (default 4.0). Hex hashes and GUIDs stay below the threshold. |

### IPv4 (1)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| IPv4 address | Regex | 60 | All | On | IPv4 with octet validation. Version numbers (10.0.19045.1) and subnet masks are excluded; kept ranges come from the settings. |

### IPv6 (1)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| IPv6 address | Regex | 60 | All | On | Full and compressed (::) IPv6 forms, including zone ids (%eth0). Validated with filter_var. |

### MAC address (2)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| MAC keys (key=value) | Key = value | 70 | All | On | Calling-Station-ID, mac=, EndPointMACAddress, hwaddr ... (including 12 digits without separators). |
| MAC address | Regex | 65 | All | On | aa:bb:cc:dd:ee:ff, AA-BB-CC-DD-EE-FF and Cisco aabb.ccdd.eeff forms. |

### Hostname (14)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| define('..HOST..', 'x') | Regex | 82 | All | On | Server constants defined with PHP define() (DB_HOST ...). |
| Database CLI server (-h, -S) | Regex | 80 | All | On | mysql -h, psql -h, redis-cli -h, sqlcmd -S. |
| Windows event XML: computer fields | Regex | 80 | All | On | <Computer>X</Computer>, <Data Name="WorkstationName">X</Data> ... |
| Server parameter (-ComputerName x, --host x) | Regex | 78 | All | On | -ComputerName, -Server, --host, --hostname, -Target, -DomainController parameters. |
| Device hostname definition | Regex | 75 | All | On | hostname X, host-name X; (Juniper), sysname X (Huawei), set hostname "X" (Fortinet), switchname X (NX-OS). |
| Hostname keys (key=value) | Key = value | 72 | All | On | hostname=, host:, ComputerName, Workstation Name:, NAS-Identifier, NetworkDeviceName, rhost=, shost ... IP/FQDN values are moved to the right category automatically. |
| Connection string server (Server=) | Key = value | 71 | All | On | Server keys used only with "=", such as Server= and Data Source= (so that the HTTP "Server: nginx" header is not caught). |
| CLI prompt (SW01#, FW01(config)#) | Regex | 70 | All | On | The prompt name at the start of lines in network device CLI output. |
| UNC path server (\\srv\share) | Regex | 70 | All | On | \\server\share and JSON-escaped \\\\server forms. |
| Hostname in the syslog header | Regex | 66 | Log | On | The field after the timestamp in "Nov  2 10:00:01 srv01 ..." (BSD) lines; in lines with ISO timestamps only if followed by "program[pid]:". |
| RFC5424 syslog hostname | Regex | 66 | Log | On | The "<34>1 2024-01-01T10:00:00Z host app ..." form. |
| Server name in a URL | Regex | 62 | All | On | Host part of URLs, including dotless names such as http://intranet/, smb://nas01/, ldap://dc01:389. |
| Windows default computer name | Regex | 58 | All | On | Automatic names in the DESKTOP-XXXXXXX, LAPTOP-XXXXXXX, WIN-XXXXXXXX form. |
| Organization naming pattern (example – edit it) | Regex | 57 | All | Off | Edit it to match your organization's server/device naming standard and enable it. E.g. SRV-DC01, SW-CORE-01, FW_EDGE1. |

### FQDN / Domain name (3)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| Device domain-name definition | Regex | 75 | All | On | ip domain-name X, ip domain name X, domain-name X, set system domain-name X. |
| FQDN keys (key=value) | Key = value | 73 | All | On | fqdn=, DnsHostName, dns name, ad-host-dns-name, realm, search domain ... |
| FQDN / domain name | Regex | 55 | All | On | Domain names ending in a known TLD (from the settings): srv01.corp.local, portal.acme.local. File names (config.json) are skipped because they are not in the TLD list. |

### AD / NetBIOS domain (2)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| Windows event XML: domain fields | Regex | 80 | All | On | <Data Name="TargetDomainName">CORP</Data> ... |
| Domain keys (key=value) | Key = value | 72 | All | On | Account Domain:, TargetDomainName, domain=, workgroup, logon domain ... Dotted values are marked as FQDNs. |

### Username (29)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| username X [privilege N] secret/password Y | Regex | 93 | All | On | Cisco/Arista/Aruba local user definitions. |
| curl/wget -u user:password | Regex | 90 | All | On | curl -u user:pass, --user, --proxy-user usage. |
| auth=("user","password") tuple | Regex | 90 | All | On | Python requests auth=(...), HTTPBasicAuth(...), basic_auth=[...] usage. |
| net user <user> <password> | Regex | 88 | All | On | Windows "net user user Password /add" commands. |
| PsExec -u / -p | Regex | 88 | All | On | psexec \\host -u user -p password |
| Huawei "local-user X password ..." | Regex | 88 | All | On | Huawei / H3C local-user definitions and passwords. |
| PSCredential username | Regex | 86 | All | On | New-Object PSCredential("user", ...) / -ArgumentList "user", ... |
| Juniper "set system login user X" | Regex | 85 | All | On | Juniper user definitions. |
| secretsdump / pwdump line user | Regex | 85 | All | On | Lines in the DOMAIN\user:RID:LM:NT::: form. |
| /user: /u: /ru switches | Regex | 84 | All | On | runas /user:, net use /user:, cmdkey /user:, schtasks /ru usage. |
| Database CLI -u / -U | Regex | 84 | All | On | mysql -u, psql -U, sqlcmd -U, mongo -u usage. |
| define('..USER..', 'x') | Regex | 84 | All | On | User constants defined with PHP define() (DB_USER ...). |
| expect "Username:" → send "..." | Regex | 84 | All | On | The value sent after the username prompt in Expect scripts. |
| /etc/shadow line | Regex | 82 | All | On | Username of user:$6$...:19000:0:99999:7::: lines. |
| /etc/passwd line | Regex | 82 | All | On | User and GECOS fields of user:x:1000:1000:Full Name,,,:/home/user:/bin/bash lines. |
| User parameter (--username x, -User x) | Regex | 80 | All | On | --username, -UserName, --user=, -User (PowerShell), find -user, --admin-user. Excludes the value-less "--user" flag (pip, systemctl). |
| E-mail (user + domain separately) | Regex | 80 | All | On | john.smith@acme.local → <USER_1>@<FQDN_1>. Public domains (gmail.com etc.) stay visible according to the list in the settings. |
| Windows event XML: user fields | Regex | 80 | All | On | <Data Name="TargetUserName">X</Data>, SubjectUserName, SamAccountName ... |
| Windows profile path (C:\Users\X) | Regex | 79 | All | On | C:\Users\john.smith\..., C:\Documents and Settings\X, JSON-escaped paths. |
| Linux/macOS home directory (/home/X, /Users/X) | Regex | 79 | All | On | Username in /home/jdoe/..., /Users/jdoe/... paths. |
| sudo / su log user | Regex | 78 | All | On | "sudo:   jdoe : TTY=pts/0 ; ..." ve "su: (to root) jdoe on pts/0". |
| User 'x' / user "x" / user [x] | Regex | 77 | All | On | Usernames given in quotes/square brackets in network device and application logs. |
| Authentication log patterns | Regex | 76 | All | On | sshd "Failed password for (invalid user) X", "Invalid user X", "session opened for user X", "Login failed for user", "user X logged in", "Disconnected from user X" ... |
| DOMAIN\user | Regex | 75 | All | On | Uppercase NetBIOS domain + backslash + user (CORP\john). Registry roots (HKLM\...) are excluded. |
| User keys (key=value) | Key = value | 74 | All | On | user=, username:, Account Name:, TargetUserName, User-Name =, login, acct=, kullanıcı adı ... DOMAIN\user values are split in two; numeric uids are skipped. |
| LDAP DN (CN=..., DC=...) | Regex | 74 | All | On | CN=John Smith,OU=Users,DC=corp,DC=local → the CN value is masked as a person/user and the DC chain as a single FQDN token. |
| user@host (ssh, prompt) | Regex | 70 | All | On | ssh root@srv01, [jdoe@web01 ~]$, scp x jdoe@10.0.0.5: forms. |
| first.last (with a known name) | Regex | 68 | All | On | first.last / first.last.x users whose first part is in the first-name dictionary (settings): ahmet.yilmaz, john.smith. Runs in all modes. |
| first.last (generic pattern) | Regex | 52 | Log | On | Lowercase first.last pattern even if the name is not in the dictionary. Code tokens and file extensions are excluded. Log mode only. |

### Person (full name) (4)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| Person name keys (key=value) | Key = value | 71 | All | On | displayName, full name, ad soyad, manager, contact ... The whole quoted value or 1-4 capitalized words. |
| snmp-server contact | Regex | 70 | All | On | SNMP contact information (person / e-mail / phone). |
| SNMP system contact (system-contact / sys-info contact) | Regex | 70 | All | On | snmp-server system-contact X, snmp-agent sys-info contact X, set snmp contact X. |
| First Last (with a known name) | Regex | 54 | All | On | Full names starting with a name from the dictionary whose second word is capitalized: "John Smith", "AYŞE KAYA". |

### E-mail (1)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| E-mail (whole) | Regex | 81 | All | Off | To mask an e-mail as a single token, enable this rule and disable "E-mail (user + domain separately)". |

### Windows SID (1)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| Windows / Azure AD SID | Regex | 70 | All | On | S-1-5-21-x-y-z-RID and S-1-12-1-... forms (excluding well-known short SIDs). |

### Phone (1)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| Phone (TR) | Regex | 45 | All | On | +90 5xx xxx xx xx, 0 (212) xxx xx xx forms. |

### Turkish ID number (TCKN) (1)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| Turkish ID number (TCKN) | Regex | 46 | All | On | 11-digit TCKN with a verified checksum. |

### IBAN (1)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| IBAN | Regex | 47 | All | On | TR and international IBAN (mod-97 verified). |

### Card number (1)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| Card number | Regex | 44 | All | On | 13-19 digit card numbers verified with Luhn and the BIN prefix. |

### Custom term (2)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| Custom dictionary (organization terms) | Dictionary | 99 | All | On | One term per line. Write "term \| CATEGORY" to choose a category. E.g. "ACME-DC01 \| HOST", "Project Falcon". |
| LDAP OU names | Regex | 73 | All | Off | Organizational unit names (OU=Accounting). Enable it to hide your organization's structure. |

### Location (1)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| SNMP system location (system-location / location) | Regex | 72 | All | On | snmp-server system-location X, snmp-server location X, snmp-agent sys-info location X, set snmp location X. |

### Network label (VLAN / port) (2)

| Rule | Type | Priority | Mode | Default | Description |
|---|---|---:|---|---|---|
| VLAN block: name / description | Regex | 71 | All | On | The name (VLAN name) and description values on the indented lines of a "vlan N" block. |
| Interface / port description | Regex | 69 | All | Off | Indented "description X" lines (interface, port-channel etc.). Off by default; descriptions give the AI useful context. |
