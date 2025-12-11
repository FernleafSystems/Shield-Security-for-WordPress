# Shield Security Development Guide

## Development Standards & Patterns

### PHP Coding Standards
- **Spacing Style**: `methodName( $param )` with spaces around parentheses
- **Type Hints**: Always use type hints and return types
- **Modern PHP**: Use typed properties, match expressions, union types
- **Follow Global Standards**: Adhere to `D:\Work\Dev\AI\Projects\CLAUDE.md` mandatory PHP standards

### Common File Patterns
- **Modules**: `src/lib/src/Modules/[ModuleName]/`
- **Actions**: `src/lib/src/ActionRouter/Actions/`
- **Rules**: `src/lib/src/Rules/Conditions/`
- **Database**: `src/lib/src/DBs/`

### Critical Architecture Flow
- **Entry Flow**: `icwp-wpsf.php` → `plugin_init.php` → `Controller\Controller`
- **Action System**: `ActionRoutingController` handles AJAX/REST/Shield actions
- **Module System**: 8 security modules with independent configuration
- **Rules Engine**: Complex condition-based security rule system

## Key Development Files
- **Main Controller**: `src/lib/src/Controller/Controller.php`
- **Configuration**: `plugin.json` (6,673 lines)
- **Tests**: PHPUnit configuration present (`phpunit.xml`)
- **Build**: Webpack for assets (`webpack.config.js`)
- **Dependencies**: Composer for PHP, npm for JavaScript

## Security Implementation Details

### Firewall System
- **Attack Categories**: 5 types (Directory Traversal, SQL Injection, Field Truncation, PHP Code, Aggressive)
- **Pattern Matching**: 50+ attack signatures, regex-based matching
- **Performance**: Efficient pattern matching and database optimization

### Multi-Factor Authentication
- **Login Intent System**: Progressive authentication flow
- **Provider Selection**: 6 MFA methods supported
- **Bot Detection**: ML-based scoring, behavioral analysis

### File Protection System
- **Encryption-based Locking**: Advanced file protection
- **Integrity Monitoring**: WordPress core file verification
- **Quarantine System**: Malware isolation and removal

## Integration Architecture

### External Integrations
- **MainWP**: Multi-site management support
- **WP-CLI**: Command-line administration
- **REST API**: Programmatic access
- **CrowdSec**: Community threat intelligence
- **Third-party**: Forms, e-commerce, membership plugin support

### Premium Feature Architecture
- Advanced vulnerability scanning with auto-updates
- Enhanced threat intelligence and bot protection
- Persistent security admin users
- Advanced reporting and analytics

## Development Workflow Notes
- This document complements the main project CLAUDE.md
- Focus on implementation details and development patterns
- Updated as new insights are discovered during development work
- Reference alongside the development checklist for quality assurance