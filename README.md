# Shield Security Library

Core library for the Shield Security WordPress plugin, providing comprehensive website protection through modular security components.

## Requirements

- PHP 7.4+
- WordPress 5.7+
- Required PHP extensions: json, openssl, zlib, curl, sqlite3, zip

## Architecture Overview

The library follows a modular architecture with these key components:

- **Controller** - Central singleton managing plugin lifecycle and configuration
- **Modules** - Feature-specific security implementations
- **ActionRouter** - Unified request handling for AJAX, REST, and internal actions
- **Rules Engine** - Condition-based security decision framework
- **Components** - Lazy-loaded functional units

## Security Modules

| Module | Description |
|--------|-------------|
| **IPs** | Bot detection, IP blocking/allowing, CrowdSec integration, geo-blocking |
| **Login Guard** | Multi-factor authentication (TOTP, Email, Passkeys/WebAuthn, YubiKey, Backup Codes) |
| **HackGuard** | File integrity scanning, malware detection, vulnerability monitoring |
| **Firewall** | Web Application Firewall with pattern-based request filtering |
| **Audit Trail** | Comprehensive activity logging and security event tracking |
| **Traffic** | Request logging, rate limiting, live traffic monitoring |
| **Comments Filter** | SPAM protection for comments using bot detection |
| **Security Admin** | PIN-protected access to plugin settings |
| **User Management** | Password policies, session control, idle timeout, user suspension |
| **Integrations** | Support for 20+ form plugins (WooCommerce, Gravity Forms, Contact Form 7, etc.) |
| **License** | ShieldPRO license management and feature activation |
| **Plugin** | Core plugin configuration and global settings |
| **Data** | Database management and data handling |

## API Systems

### ActionRouter
 
Centralized request handling through `ActionRouter/ActionRoutingController.php`:
- AJAX actions for admin interface
- REST API endpoint routing
- Internal shield action processing
- Render actions for UI components

### REST API v1

RESTful endpoints under `/wp-json/shield/v1/`:

| Category | Endpoints |
|----------|-----------|
| IP Management | Get IP info, add/query IP rules |
| License | Check and query license status |
| Options | Get/set individual or bulk configuration options |
| Scans | Start scans, check status, retrieve results |
| Plugin | Execute plugin actions |

### ShieldNetApi

Cloud service integration for:
- License validation
- Reputation data synchronization
- Threat intelligence feeds
- Plugin telemetry (optional)

## Rules Engine

A flexible condition-action framework for security decisions.

### Conditions (~90 types)

Examples of available conditions:
- Request matching: path, method, user agent, parameters, country code
- IP status: whitelisted, blacklisted, blocked, high reputation
- User state: logged in, admin, security admin, 2FA status
- WordPress context: admin, AJAX, cron, REST API, XML-RPC
- Shield-specific: rate limits, bot detection scores, session validity

### Response Actions (~30 types)

Examples of available responses:
- Block actions: display block page, firewall block, die
- IP actions: trigger offense, trigger block, update geo data
- User actions: logout, suspend, clear cookies, rotate auth
- System actions: fire events, set headers, redirect, log

## Key Dependencies

| Package | Purpose |
|---------|---------|
| `crowdsec/capi-client` | CrowdSec threat intelligence integration |
| `web-auth/webauthn-lib` | Passkey/WebAuthn authentication support |
| `dolondro/google-authenticator` | TOTP two-factor authentication |
| `fernleafsystems/zxcvbn-php` | Password strength estimation |
| `twig/twig` | Template rendering engine |
| `monolog/monolog` | Logging infrastructure |

## Directory Structure

```
src/lib/
├── src/
│   ├── ActionRouter/     # Request routing and action handling
│   ├── Components/       # Lazy-loaded feature components
│   ├── Controller/       # Main plugin controller
│   ├── Crons/            # Scheduled task definitions
│   ├── DBs/              # Database table handlers
│   ├── Enum/             # Enumeration classes
│   ├── Events/           # Event definitions and handlers
│   ├── Extensions/       # Third-party extension support
│   ├── License/          # License management
│   ├── Logging/          # Log handling
│   ├── Modules/          # Security module implementations
│   ├── Profiles/         # Security profile configurations
│   ├── Render/           # UI rendering utilities
│   ├── Request/          # Request parsing and handling
│   ├── Rest/             # REST API v1 implementation
│   ├── Rules/            # Rules engine (conditions & responses)
│   ├── Scans/            # File and security scanners
│   ├── ShieldNetApi/     # Cloud API integration
│   ├── Tables/           # Admin table renderers
│   ├── Users/            # User management utilities
│   ├── Utilities/        # Helper classes
│   ├── WpCli/            # WP-CLI command integration
│   └── Zones/            # Security zone definitions
├── functions/            # Global helper functions
└── vendor_prefixed/      # Prefixed third-party dependencies
```

## Namespace

All classes use the namespace: `FernleafSystems\Wordpress\Plugin\Shield`
