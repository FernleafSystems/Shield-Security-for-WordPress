# Shield Security Plugin - Knowledge Base

## Project Overview
Enterprise-grade WordPress security plugin implementing defense-in-depth security principles through 8 interconnected modules.

## Quick Reference Links
- **Project Documentation**: `CLAUDE.md` (comprehensive context)
- **Parent Guidelines**: `../../../CLAUDE.md` (mandatory PHP coding standards)
- **Main Controller**: `src/lib/src/Controller/Controller.php`
- **Configuration**: `plugin.json` (6,673 lines)

## Critical Architecture Points
- **Entry Flow**: `icwp-wpsf.php` → `plugin_init.php` → `Controller\Controller`
- **Action System**: `ActionRoutingController` handles AJAX/REST/Shield actions
- **Module System**: 8 security modules with independent configuration
- **Rules Engine**: Complex condition-based security rule system

## Security Modules Quick Map
1. **Security Admin** - Plugin access control with PIN auth
2. **Firewall** - WAF with pattern-based attack detection
3. **Login Guard** - MFA system (6 methods) + brute force protection
4. **IPs** - Bot detection + IP blocking + CrowdSec integration
5. **HackGuard** - File scanning + vulnerability detection + malware protection
6. **Comments Filter** - SPAM protection
7. **Audit Trail** - Activity logging
8. **Traffic** - Request logging and analysis

## Development Standards
- **PHP Style**: Follow `../../../CLAUDE.md` mandatory standards
- **Spacing**: `methodName( $param )` with spaces around parentheses
- **Types**: Always use type hints and return types
- **Modern PHP**: Use typed properties, match expressions, union types

## Common File Patterns
- **Modules**: `src/lib/src/Modules/[ModuleName]/`
- **Actions**: `src/lib/src/ActionRouter/Actions/`
- **Rules**: `src/lib/src/Rules/Conditions/`
- **Database**: `src/lib/src/DBs/`

## Testing & Building
- **Tests**: PHPUnit configuration present (`phpunit.xml`)
- **Build**: Webpack for assets (`webpack.config.js`)
- **Dependencies**: Composer for PHP, npm for JavaScript

## Key Security Implementations
- **Firewall**: 5 attack categories, 50+ patterns, regex-based matching
- **MFA**: Login Intent system, progressive authentication, provider selection
- **Bot Detection**: ML-based scoring, behavioral analysis, CrowdSec integration
- **File Protection**: Encryption-based locking, integrity monitoring, quarantine

## Integration Points
- **MainWP**: Multi-site management support
- **WP-CLI**: Command-line administration
- **REST API**: Programmatic access
- **CrowdSec**: Community threat intelligence
- **Third-party**: Forms, e-commerce, membership plugin support

## Premium Features
- Advanced vulnerability scanning with auto-updates
- Enhanced threat intelligence and bot protection
- Persistent security admin users
- Advanced reporting and analytics

This knowledge base should be updated as new insights are discovered during development work.