# Shield Security - Technology Stack

## Core Platform Requirements

### Runtime Environment
- **PHP**: 7.4+ (Modern PHP features, type declarations, performance optimizations)
- **WordPress**: 5.7+ (Current WordPress architecture and security APIs)  
- **MySQL**: 5.6+ (Reliable database foundation with modern SQL features)
- **Web Server**: Apache/Nginx (Standard WordPress hosting environment)

### Architecture Philosophy
**Modular Security Design**: 8 independent but interconnected security modules that can be enabled/disabled based on specific security requirements, allowing customized security postures while maintaining system integrity.

## Development Stack

### Language & Framework
- **Primary Language**: PHP 7.4+ with modern language features
- **Framework**: WordPress Plugin API with extensive hook integration
- **Architecture Pattern**: Modular MVC with centralized controller and action routing

### Frontend Technologies
- **JavaScript**: ES6+ with Webpack build system
- **CSS**: SCSS with component-based architecture
- **Build Tools**: 
  - Webpack for asset compilation and optimization
  - NPM for JavaScript dependency management
  - SASS for CSS preprocessing

### Dependency Management
**Dual-Composer Architecture** (Critical Design Decision):

**Root Level** (`composer.json`):
```json
{
  "require-dev": {
    "phpunit/phpunit": "^9.0",
    "squizlabs/php_codesniffer": "^3.6"
  }
}
```

**Library Level** (`src/lib/composer.json`):
```json
{
  "require": {
    "twig/twig": "^3.0",
    "monolog/monolog": "^2.0"
  }
}
```

**Rationale**: Separates development tools from runtime dependencies, enabling clean production builds and avoiding dependency conflicts.

### Dependency Prefixing
**Strauss Prefixing v0.19.4** (Version-Specific Requirement):
- **Purpose**: Prevents conflicts between Shield's dependencies and other plugins
- **Critical Version**: Must use v0.19.4 specifically (creates `autoload-classmap.php` vs newer versions creating `autoload_classmap.php`)
- **Process**: Automatically prefixes all third-party namespaces with Shield-specific prefixes
- **Location**: `src/lib/vendor_prefixed/` contains prefixed dependencies

## Key Architectural Components

### Controller Architecture
**Entry Point Flow**:
```
icwp-wpsf.php → plugin_init.php → Controller\Controller → ActionRoutingController
```

**Central Controller** (`src/lib/src/Controller/Controller.php`):
- Single point of plugin initialization
- Module management and coordination
- Global configuration management
- Event system coordination

**Action Router** (`src/lib/src/ActionRouter/ActionRoutingController.php`):
- Handles AJAX requests
- Manages REST API endpoints  
- Processes Shield-specific actions
- Request validation and routing

### Module System Architecture
**8 Security Modules**:
1. `admin_access_restriction` - Security Admin
2. `firewall` - Web Application Firewall
3. `login_protect` - Login Guard
4. `ips` - IP Management
5. `hack_protect` - HackGuard
6. `comments_filter` - Comments Filter
7. `audit_trail` - Audit Trail
8. `traffic` - Traffic Analysis

**Module Structure**:
```
src/lib/src/Modules/[ModuleName]/
├── ModuleCon.php          # Module controller
├── Options.php            # Configuration management
├── Processor.php          # Core functionality
├── UI/                    # User interface components
└── Rules/                 # Security rules and conditions
```

### Configuration Management
**Massive Configuration System** (`plugin.json` - 6,673 lines):
- All module options and settings defined in single JSON file
- Hierarchical configuration with inheritance
- Runtime configuration validation
- Dynamic option rendering and management

## Security Implementation

### Security Standards Adherence
- **Input Validation**: All user input validated and sanitized
- **SQL Injection Prevention**: Prepared statements throughout
- **XSS Prevention**: Output escaping and validation
- **CSRF Protection**: Nonce verification for all forms
- **Capability Checks**: WordPress capability-based access control
- **Error Handling**: Comprehensive error management and logging

### Cryptographic Implementation
- **Password Security**: Integration with HaveIBeenPwned using K-anonymity
- **File Locking**: Encryption-based critical file protection
- **Session Security**: Secure session management and validation
- **Token Generation**: Cryptographically secure random token generation

## Testing Infrastructure

### Test Suite Architecture
**Three-Tier Testing Strategy**:

1. **Unit Tests** (`tests/Unit/`):
   - Individual component testing
   - Mocked dependencies
   - Fast execution (117ms for 33 tests)
   - Configuration: `phpunit-unit.xml`

2. **Integration Tests** (`tests/Integration/`):
   - WordPress environment testing
   - MySQL database interactions
   - Plugin lifecycle validation
   - Configuration: `phpunit-integration.xml`

3. **Package Validation** (`PluginPackageValidationTest.php`):
   - Built package structure verification
   - Dependency prefixing validation
   - Asset compilation verification
   - Environment: `SHIELD_PACKAGE_PATH` testing

### CI/CD Pipeline
**GitHub Actions Workflow**:
- **Minimal Test**: Syntax validation (8s)
- **Unit Tests**: PHPUnit with package build (56s)  
- **Integration Tests**: WordPress + MySQL Docker (1m40s)
- **Code Standards**: PHPCS validation (optional)

**Performance Metrics**:
- Test execution: 117ms for comprehensive test suite
- Memory usage: 58.50 MB peak
- Total CI runtime: ~3 minutes for full pipeline

## Build Process

### Asset Compilation
**Webpack Configuration**:
```javascript
// webpack.config.js
module.exports = {
  entry: {
    'plugin-main': './assets/js/app/AppMain.js',
    'plugin-wpadmin': './assets/js/app/AppWpAdmin.js'
  },
  output: {
    path: path.resolve(__dirname, 'assets/dist'),
    filename: '[name].min.js'
  }
};
```

### Package Building
**Build Script** (`bin/build-package.sh`):
1. Copy core plugin files
2. Install production dependencies (`composer install --no-dev`)
3. Run Strauss prefixing (v0.19.4 specifically)
4. Remove development artifacts
5. Compile and optimize assets
6. Validate package structure

## Third-Party Integrations

### Security Intelligence
- **CrowdSec**: Community threat intelligence sharing
- **HaveIBeenPwned**: Compromised password detection (K-anonymity)
- **IP Reputation Services**: Real-time threat assessment

### WordPress Ecosystem
- **MainWP**: Multi-site management integration
- **WP-CLI**: Command-line administration support
- **Major Plugins**: Form builders, e-commerce, membership systems

### Development Tools
- **PHPUnit**: Unit and integration testing
- **PHPCS**: Code standards enforcement  
- **Strauss**: Dependency prefixing
- **Webpack**: Asset building and optimization

## Performance Optimization

### Efficiency Strategies
- **Lazy Loading**: Modules loaded only when needed
- **Database Optimization**: Indexed queries and minimal database calls
- **Caching**: Strategic caching of expensive operations
- **Asynchronous Processing**: Background processing for intensive tasks

### Resource Management
- **Memory Efficiency**: Optimized data structures and object lifecycle
- **CPU Optimization**: Efficient algorithms for pattern matching
- **Network Optimization**: Minimal external API calls
- **Storage Efficiency**: Compressed and optimized asset delivery

## Architectural Decisions

### Design Principles
1. **Modularity**: Independent modules with clean interfaces
2. **Security First**: Security considerations in every design decision
3. **Performance**: Optimization without compromising security
4. **Compatibility**: Broad WordPress ecosystem compatibility
5. **Maintainability**: Clean, documented, testable code

### Technology Choices Rationale
- **Twig Templates**: Security through template sandboxing
- **Monolog**: Professional logging with multiple handlers
- **Dual-Composer**: Clean separation of concerns
- **Strauss Prefixing**: Conflict prevention in plugin ecosystem
- **Modern PHP**: Language features for better security and performance

This technology stack represents a mature, enterprise-grade approach to WordPress security, balancing security requirements with performance needs while maintaining broad compatibility across the WordPress ecosystem.