# Shield Security WP-CLI Commands

This document provides a complete reference for all WP-CLI commands available in the Shield Security plugin for WordPress.

## Overview

All Shield commands use the base command `shield` and follow the pattern:

```bash
wp shield <command> [<subcommand>] [--options]
```

Commands are organized into the following groups:

| Group | Description |
|-------|-------------|
| Configuration | Manage plugin settings and options |
| IP Rules | Manage IP blocklists and allowlists |
| Security Admin | Manage Security Admin users and PIN |
| Scans | Run security scans |
| CrowdSec | Manage CrowdSec integration |
| License | Manage ShieldPRO license |
| Translations | Manage translation downloads |
| Utilities | Debug mode, force-off, reset |

---

## Configuration Commands

### opt-list

List all the option keys and their names and current assignments.

**Synopsis:**
```bash
wp shield opt-list [--format=<format>] [--full]
```

**Options:**

| Option | Type | Required | Default | Description |
|--------|------|----------|---------|-------------|
| `--format` | string | No | `table` | Output format: `table`, `json`, `yaml`, `csv` |
| `--full` | flag | No | - | Display all option details including type and default value |

**Examples:**
```bash
# List all options in table format
wp shield opt-list

# List all options with full details as JSON
wp shield opt-list --format=json --full

# Export options to CSV
wp shield opt-list --format=csv > shield-options.csv
```

---

### opt-get

View the value of any configuration option.

**Synopsis:**
```bash
wp shield opt-get --key=<option_key>
```

**Options:**

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `--key` | string | Yes | The option key to get |

**Notes:**
- For checkbox options, values are displayed as "Y" (enabled) or "N" (disabled)
- Array values are displayed in bracket notation

**Examples:**
```bash
# Get the value of a specific option
wp shield opt-get --key=enable_firewall

# Check login protection setting
wp shield opt-get --key=enable_login_gasp
```

---

### opt-set

Set the value of a configuration option.

**Synopsis:**
```bash
wp shield opt-set --key=<option_key> --value=<new_value>
```

**Options:**

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `--key` | string | Yes | The option key to update |
| `--value` | string | Yes | The option's new value |

**Notes:**
- For checkbox options, use "Y" to enable or "N" to disable

**Examples:**
```bash
# Enable a feature (Y/N for checkboxes)
wp shield opt-set --key=enable_firewall --value=Y

# Disable a feature
wp shield opt-set --key=enable_firewall --value=N

# Set a numeric value
wp shield opt-set --key=login_limit_interval --value=10
```

---

### export

Export configuration to file.

**Synopsis:**
```bash
wp shield export --file=<path> [--force]
```

**Options:**

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `--file` | string | Yes | The absolute or relative (to ABSPATH) path to the file for export |
| `--force` | flag | No | Bypass confirmation to overwrite files and create necessary directories |

**Examples:**
```bash
# Export configuration to a file
wp shield export --file=/tmp/shield-config.txt

# Export with force (no confirmation prompts)
wp shield export --file=wp-content/shield-backup.txt --force

# Export to relative path
wp shield export --file=shield-config-backup.txt
```

---

### import

Import configuration from another WP site running Shield.

**Synopsis:**
```bash
wp shield import --source=<url_or_path> [--site-secret=<secret>] [--slave=<action>] [--force] [--delete-file]
```

**Options:**

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `--source` | string | Yes | The URL of the source site or absolute path to import file |
| `--site-secret` | string | No | The secret key on the source site. Not required if this site is already registered on the source site |
| `--slave` | string | No | `add` or `remove` - Add or remove this site as a registered slave (in the whitelist) on the source site. Secret is required to `add` |
| `--force` | flag | No | Bypass confirmation prompt |
| `--delete-file` | flag | No | Delete file after configurations have been imported |

**Examples:**
```bash
# Import from a file
wp shield import --source=/path/to/shield-config.txt

# Import from a file and delete it afterwards
wp shield import --source=/tmp/config.txt --delete-file --force

# Import from another site with secret
wp shield import --source=https://example.com --site-secret=abc123

# Import and register as a slave site
wp shield import --source=https://example.com --site-secret=abc123 --slave=add

# Import and remove slave registration
wp shield import --source=https://example.com --slave=remove
```

---

## IP Rules Commands

### ip-rules add

Add an IP address to one of your lists, white or black.

**Synopsis:**
```bash
wp shield ip-rules add --ip=<ip_address> --list=<list_name> [--label=<label>]
```

**Options:**

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `--ip` | string | Yes | The IP address (supports single IPs and CIDR notation) |
| `--list` | string | Yes | The IP list to update: `bypass`, `white`, `block`, or `black` |
| `--label` | string | No | The label to assign to this IP entry |

**Notes:**
- `bypass` and `white` are synonyms (whitelist/allowlist)
- `block` and `black` are synonyms (blacklist/blocklist)

**Examples:**
```bash
# Add an IP to the bypass list with a label
wp shield ip-rules add --ip=192.168.1.100 --list=bypass --label="Office IP"

# Block an IP address
wp shield ip-rules add --ip=10.0.0.50 --list=block --label="Known attacker"

# Add a CIDR range to bypass
wp shield ip-rules add --ip=192.168.1.0/24 --list=white --label="Internal network"

# Add without a label
wp shield ip-rules add --ip=203.0.113.50 --list=bypass
```

---

### ip-rules remove

Remove an IP address from one of your lists, white or black.

**Synopsis:**
```bash
wp shield ip-rules remove --ip=<ip_address> --list=<list_name>
```

**Options:**

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `--ip` | string | Yes | The IP address to remove |
| `--list` | string | Yes | The IP list to update: `bypass`, `white`, `block`, or `black` |

**Examples:**
```bash
# Remove an IP from the bypass list
wp shield ip-rules remove --ip=192.168.1.100 --list=bypass

# Unblock an IP address
wp shield ip-rules remove --ip=10.0.0.50 --list=block
```

---

### ip-rules print

Enumerate all IPs currently present on your lists.

**Synopsis:**
```bash
wp shield ip-rules print --list=<list_name>
```

**Options:**

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `--list` | string | Yes | The IP list to enumerate: `bypass`, `white`, `block`, `black`, or `crowdsec` |

**Notes:**
- The `block` list includes both automatically blocked IPs and manually blocked IPs
- The `crowdsec` list shows IPs blocked via CrowdSec integration

**Examples:**
```bash
# Show all bypassed IPs
wp shield ip-rules print --list=bypass

# Show all blocked IPs (auto and manual)
wp shield ip-rules print --list=block

# Show CrowdSec blocked IPs
wp shield ip-rules print --list=crowdsec
```

---

## Security Admin Commands

### secadmin admin-add

Add a Security Admin user to the list of automatic sec-admins.

**Synopsis:**
```bash
wp shield secadmin admin-add [--uid=<user_id>] [--username=<username>] [--email=<email>]
```

**Options:**

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `--uid` | integer | No* | Administrator User ID |
| `--username` | string | No* | Administrator username |
| `--email` | string | No* | Administrator email address |

*Exactly one identification method is required. The user must be a WordPress administrator.

**Examples:**
```bash
# Add by user ID
wp shield secadmin admin-add --uid=1

# Add by username
wp shield secadmin admin-add --username=admin

# Add by email
wp shield secadmin admin-add --email=admin@example.com
```

---

### secadmin admin-remove

Remove a Security Admin user from the list of automatic sec-admins.

**Synopsis:**
```bash
wp shield secadmin admin-remove [--uid=<user_id>] [--username=<username>] [--email=<email>]
```

**Options:**

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `--uid` | integer | No* | Administrator User ID |
| `--username` | string | No* | Administrator username |
| `--email` | string | No* | Administrator email address |

*Exactly one identification method is required.

**Examples:**
```bash
# Remove by user ID
wp shield secadmin admin-remove --uid=2

# Remove by username
wp shield secadmin admin-remove --username=oldadmin

# Remove by email
wp shield secadmin admin-remove --email=oldadmin@example.com
```

---

### secadmin pin

Set or remove the Security Admin PIN.

**Synopsis:**
```bash
wp shield secadmin pin [--set=<new_pin>] [--remove]
```

**Options:**

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `--set` | string | No* | Set a new Security Admin PIN |
| `--remove` | flag | No* | Use this to remove any existing PIN |

*Exactly one of `--set` or `--remove` must be provided. You cannot use both simultaneously.

**Examples:**
```bash
# Set a new PIN
wp shield secadmin pin --set=MySecurePin123

# Remove the existing PIN
wp shield secadmin pin --remove
```

---

## Scans Commands

### scans run

Run All Shield Scans.

**Synopsis:**
```bash
wp shield scans run [--all] [--<scan_slug>]
```

**Options:**

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `--all` | flag | No | Run all available scans |
| `--<scan_slug>` | flag | No | Run a specific scan by its slug |

**Notes:**
- Available scan slugs are determined dynamically based on your Shield configuration
- You must specify either `--all` or at least one scan slug
- If no scans are specified, the command will display available scan slugs

**Examples:**
```bash
# Run all available scans
wp shield scans run --all

# Run specific scans (scan slugs vary by configuration)
wp shield scans run --apc --wpv

# Run a single scan
wp shield scans run --mal
```

---

## CrowdSec Commands

### crowdsec signals

Perform actions with pending CrowdSec signals.

**Synopsis:**
```bash
wp shield crowdsec signals --action=<action>
```

**Options:**

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `--action` | string | Yes | Action to take with the signals: `list` or `push` |

**Examples:**
```bash
# List pending signals
wp shield crowdsec signals --action=list

# Push signals to CrowdSec
wp shield crowdsec signals --action=push
```

---

## License Commands

### pro-license

Manage the ShieldPRO license.

**Synopsis:**
```bash
wp shield pro-license --action=<action> [--api-key=<api_key>] [--force]
```

**Options:**

| Option | Type | Required | Default | Description |
|--------|------|----------|---------|-------------|
| `--action` | string | Yes | `status` | Action to perform on the ShieldPRO license: `status`, `verify`, `remove`, or `activate` |
| `--api-key` | string | No* | - | API key used for `activate` action |
| `--force` | flag | No | - | Bypass confirmation prompt (for remove action) or force reactivation |

*Required for `--action=activate`.

**Examples:**
```bash
# Check license status
wp shield pro-license --action=status

# Verify/refresh the license from the license server
wp shield pro-license --action=verify

# Remove the license (with confirmation)
wp shield pro-license --action=remove

# Remove the license without confirmation
wp shield pro-license --action=remove --force

# Activate this site with an API key and verify
wp shield pro-license --action=activate --api-key=YOUR_API_KEY

# Force activation flow even if a license is already active
wp shield pro-license --action=activate --api-key=YOUR_API_KEY --force
```

---

## Translation Commands

### translations

Manage Shield plugin translation downloads.

**Synopsis:**
```bash
wp shield translations --action=<action> [--locale=<locale_code>] [--force]
```

**Options:**

| Option | Type | Required | Default | Description |
|--------|------|----------|---------|-------------|
| `--action` | string | Yes | `status` | Action to perform on translations: `list`, `queue`, `download`, or `status` |
| `--locale` | string | No | - | Locale code for download action (e.g., `de_DE`, `fr_FR`) |
| `--force` | flag | No | - | Force download even if already cached |

**Actions:**
- `list` - Display available translations from the ShieldNet API
- `queue` - Show locales currently queued for download
- `download` - Download a specific locale translation (requires `--locale`)
- `status` - Show translation cache status and download attempts

**Examples:**
```bash
# Check translation status
wp shield translations --action=status

# List available translations
wp shield translations --action=list

# Download German translations
wp shield translations --action=download --locale=de_DE

# Force re-download of French translations
wp shield translations --action=download --locale=fr_FR --force

# Check download queue
wp shield translations --action=queue
```

---

## Utility Commands

### activity-log print

Print the activity log.

**Synopsis:**
```bash
wp shield activity-log print
```

**Options:** None

**Example:**
```bash
wp shield activity-log print
```

---

### debug-mode

Manage the debug mode.

**Synopsis:**
```bash
wp shield debug-mode --action=<action>
```

**Options:**

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `--action` | string | Yes | Action to take with the debug mode: `enable`, `disable`, or `status` |

**Notes:**
- Debug mode can be enabled via a mode file or a PHP constant
- If enabled via PHP constant, it cannot be disabled via WP-CLI
- The `status` action will indicate how debug mode is enabled

**Examples:**
```bash
# Check debug mode status
wp shield debug-mode --action=status

# Enable debug mode
wp shield debug-mode --action=enable

# Disable debug mode
wp shield debug-mode --action=disable
```

---

### forceoff

Manage the `forceoff` file.

**Synopsis:**
```bash
wp shield forceoff --action=<action>
```

**Options:**

| Option | Type | Required | Default | Description |
|--------|------|----------|---------|-------------|
| `--action` | string | Yes | `status` | Action to take with the `forceoff` file: `create`, `delete`, or `status` |

**Notes:**
- The `forceoff` file, when present in the plugin directory, completely prevents Shield from loading
- This is useful for emergency troubleshooting or when Shield is causing issues

**Examples:**
```bash
# Check if forceoff file exists
wp shield forceoff --action=status

# Create forceoff file (disable Shield)
wp shield forceoff --action=create

# Delete forceoff file (re-enable Shield)
wp shield forceoff --action=delete
```

---

### reset

Reset the Shield plugin to default settings.

**Synopsis:**
```bash
wp shield reset [--force]
```

**Options:**

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `--force` | flag | No | Bypass confirmation prompt |

**Warning:** This command resets all Shield settings to their defaults. Use with caution.

**Examples:**
```bash
# Reset with confirmation prompt
wp shield reset

# Reset without confirmation
wp shield reset --force
```

---

## Command Summary Table

| Command | Description |
|---------|-------------|
| `wp shield opt-list` | List all configuration options |
| `wp shield opt-get` | Get a configuration option value |
| `wp shield opt-set` | Set a configuration option value |
| `wp shield export` | Export configuration to file |
| `wp shield import` | Import configuration from file/URL |
| `wp shield ip-rules add` | Add IP to bypass/block list |
| `wp shield ip-rules remove` | Remove IP from a list |
| `wp shield ip-rules print` | Display IPs on a list |
| `wp shield secadmin admin-add` | Add a Security Admin user |
| `wp shield secadmin admin-remove` | Remove a Security Admin user |
| `wp shield secadmin pin` | Set or remove Security Admin PIN |
| `wp shield scans run` | Run security scans |
| `wp shield crowdsec signals` | Manage CrowdSec signals |
| `wp shield pro-license` | Manage ShieldPRO license |
| `wp shield translations` | Manage translation downloads |
| `wp shield activity-log print` | Print activity log |
| `wp shield debug-mode` | Manage debug mode |
| `wp shield forceoff` | Manage forceoff file |
| `wp shield reset` | Reset plugin to defaults |

---

## Notes

### Access Control

Most commands require WP-CLI Level 2 capability. The `pro-license` command only requires Level 1 capability. These levels can be configured in Shield's settings to restrict WP-CLI access for premium users.

### Execution Timing

All Shield WP-CLI commands execute during the `before_wp_load` phase, ensuring they run early in the WordPress bootstrap process.

### Getting Help

For command-specific help, use:

```bash
wp help shield <command>
```

For example:
```bash
wp help shield ip-rules add
wp help shield opt-set
```
