# WordPress Development Gotchas: A Survival Guide

**Last Updated**: 2025-01-19  
**Status**: Essential Reference Document  
**Purpose**: Consolidate hard-won lessons from Shield Security development to help developers avoid WordPress-specific pitfalls

## Executive Summary

WordPress development presents unique challenges that differ significantly from standard PHP development. This document captures critical gotchas, their root causes, and proven workarounds discovered through Shield Security's development journey. These are not theoretical issues - each represents hours of debugging and real production problems.

## Table of Contents

1. [Dynamic Properties and PHP 8.2+ Deprecations](#dynamic-properties-and-php-82-deprecations)
2. [Magic Methods Chaos](#magic-methods-chaos)
3. [Hook-Based Architecture Implications](#hook-based-architecture-implications)
4. [Global State and Side Effects](#global-state-and-side-effects)
5. [Third-Party Plugin Class Dependencies](#third-party-plugin-class-dependencies)
6. [Autoloader Conflicts in Plugin Ecosystem](#autoloader-conflicts-in-plugin-ecosystem)
7. [Database Abstraction Quirks](#database-abstraction-quirks)
8. [WordPress Coding Standards vs Modern PHP](#wordpress-coding-standards-vs-modern-php)
9. [Testing Challenges](#testing-challenges)
10. [Performance Implications of WordPress Patterns](#performance-implications-of-wordpress-patterns)
11. [Security Considerations Unique to WordPress](#security-considerations-unique-to-wordpress)
12. [Static Analysis Nightmares](#static-analysis-nightmares)
13. [CI/CD Pipeline Gotchas](#cicd-pipeline-gotchas)
14. [Windows Development Environment Issues](#windows-development-environment-issues)

---

## Dynamic Properties and PHP 8.2+ Deprecations

### The Problem

PHP 8.2 deprecated dynamic properties, but WordPress core and plugins rely heavily on them:

```php
// WordPress pattern that triggers deprecation warnings
class WP_User {
    public function __construct($id) {
        // Properties added dynamically from database
        $this->custom_capability = true;  // PHP 8.2: Deprecated
        $this->user_meta_field = 'value';  // PHP 8.2: Deprecated
    }
}
```

### Why It Happens

- WordPress was designed for PHP 5.2 and maintains backward compatibility
- Core objects (WP_User, WP_Post, WP_Query) populate properties from database dynamically
- Metadata systems add arbitrary properties at runtime
- Plugin ecosystem expects this behavior

### Workarounds

```php
// Option 1: Use #[AllowDynamicProperties] attribute (PHP 8.2+)
#[AllowDynamicProperties]
class MyWordPressClass {
    // Now can add properties dynamically without warnings
}

// Option 2: Implement magic methods
class MyWordPressClass {
    private array $dynamicProperties = [];
    
    public function __set($name, $value) {
        $this->dynamicProperties[$name] = $value;
    }
    
    public function __get($name) {
        return $this->dynamicProperties[$name] ?? null;
    }
}

// Option 3: Declare expected properties
class MyWordPressClass {
    public $id;
    public $name;
    public $meta = [];  // Store dynamic data in array
}
```

### Real-World Example from Shield

```php
// Shield's approach to handle WordPress dynamic properties
class ModuleBase {
    // Declare known properties explicitly
    protected $slug;
    protected $options;
    
    // Use typed array for dynamic data
    protected array $runtimeData = [];
    
    // Controlled dynamic property access
    public function __get($key) {
        if (property_exists($this, $key)) {
            return $this->$key;
        }
        return $this->runtimeData[$key] ?? null;
    }
}
```

## Magic Methods Chaos

### The Problem

WordPress objects use magic methods (`__get`, `__set`, `__call`) extensively, creating unpredictable behavior:

```php
// Looks simple, but...
$post->custom_field;  // Might call __get(), check meta, filter hooks, cache...

// Method doesn't exist but works via __call()
$query->get_posts_by_custom_criteria();  // Magic routing to internal methods
```

### Why It Happens

- Backward compatibility with legacy code
- Flexible metadata system
- Plugin extensibility requirements
- Database field mapping

### Workarounds

```php
// Be explicit about property access
// Instead of relying on magic:
$value = $post->magic_property;

// Use explicit methods:
$value = get_post_meta($post->ID, 'magic_property', true);

// Document magic behavior
class MyClass {
    /**
     * Magic properties:
     * @property string $virtual_prop Loaded via __get from options
     * @property array $dynamic_data Populated from database
     */
    public function __get($name) {
        // Make magic behavior predictable
        switch($name) {
            case 'virtual_prop':
                return get_option('my_' . $name);
            case 'dynamic_data':
                return $this->loadDynamicData();
            default:
                trigger_error("Undefined property: $name", E_USER_NOTICE);
                return null;
        }
    }
}
```

## Hook-Based Architecture Implications

### The Problem

WordPress's filter/action system makes code flow non-linear and hard to trace:

```php
// You write this:
$data = apply_filters('my_data', $data);

// But 50 plugins might modify $data in unpredictable ways
// Plugin A might add fields
// Plugin B might remove fields
// Plugin C might completely replace the structure
```

### Why It Happens

- Core extensibility philosophy
- Plugin interaction model
- Theme customization system
- No type safety on filters

### Workarounds

```php
// 1. Defensive filtering with validation
$data = apply_filters('my_data', $data);
// Validate critical structure remains
if (!isset($data['required_field'])) {
    // Restore or error
    $data['required_field'] = $original['required_field'];
}

// 2. Use priority system strategically
add_filter('the_content', 'my_filter', 999);  // Run last
add_filter('init', 'my_early_init', 1);  // Run first

// 3. Type checking after filters
$filtered = apply_filters('my_numeric_value', $value);
if (!is_numeric($filtered)) {
    $filtered = (int) $filtered;  // Force type
}

// 4. Document expected filter behavior
/**
 * Filter the security scan results
 * 
 * @param array $results {
 *     @type bool $passed Whether scan passed
 *     @type array $threats Detected threats
 * }
 * @return array Must maintain structure above
 */
$results = apply_filters('shield_scan_results', $results);
```

### Real Shield Example

```php
// Shield's defensive filter approach
class FirewallModule {
    public function getPatterns() {
        $patterns = $this->loadDefaultPatterns();
        
        // Allow filtering but validate result
        $filtered = apply_filters('shield_firewall_patterns', $patterns);
        
        // Ensure we still have valid patterns
        if (!is_array($filtered) || empty($filtered)) {
            // Log the issue and use defaults
            error_log('Invalid firewall patterns from filter');
            return $patterns;
        }
        
        // Validate each pattern structure
        return array_filter($filtered, [$this, 'isValidPattern']);
    }
}
```

## Global State and Side Effects

### The Problem

WordPress relies on numerous globals that can change unexpectedly:

```php
global $wpdb, $post, $wp_query, $wp_rewrite, $wp_locale;

// Any function might modify these
do_action('init');  // Now all globals might be different

// Classic bug source
function my_function() {
    global $post;  // Might be null, different post, or modified
    echo $post->ID;  // Fatal error if $post is null
}
```

### Why It Happens

- Legacy architecture from PHP 4 days
- WordPress loads everything globally
- Plugins expect global access
- Template system uses globals

### Workarounds

```php
// 1. Cache global state when needed
class SafeGlobalAccess {
    private $savedPost;
    
    public function doWork() {
        global $post;
        $this->savedPost = $post;  // Save state
        
        try {
            // Do work that might change $post
            $this->processData();
        } finally {
            $post = $this->savedPost;  // Restore state
        }
    }
}

// 2. Use WordPress functions instead of globals
// Instead of: global $wpdb;
// Use: $wpdb = $this->getWpdb();
private function getWpdb() {
    global $wpdb;
    if (!$wpdb instanceof wpdb) {
        throw new RuntimeException('Database not initialized');
    }
    return $wpdb;
}

// 3. Avoid globals in critical code
class DatabaseQuery {
    private wpdb $database;
    
    public function __construct(wpdb $database = null) {
        $this->database = $database ?: $GLOBALS['wpdb'];
    }
    
    public function query($sql) {
        return $this->database->get_results($sql);
    }
}
```

## Third-Party Plugin Class Dependencies

### The Problem

Code that depends on other plugins' classes breaks when those plugins aren't active:

```php
// This breaks if WooCommerce isn't active
class MyShipping extends WC_Shipping_Method {
    // Fatal error: Class 'WC_Shipping_Method' not found
}

// This breaks static analysis
if (class_exists('NF_Abstracts_Action')) {
    class MyNinjaForm extends NF_Abstracts_Action {
        // PHPStan: Class NF_Abstracts_Action not found
    }
}
```

### Why It Happens

- WordPress doesn't enforce plugin dependencies
- Plugins load in unpredictable order
- Optional integrations are common
- No standard interface definitions

### Workarounds

```php
// 1. Late binding with factories
class IntegrationFactory {
    public static function createWooCommerceIntegration() {
        if (!class_exists('WooCommerce')) {
            return new NullWooCommerceIntegration();
        }
        
        // Load only when needed
        require_once __DIR__ . '/integrations/class-woocommerce.php';
        return new WooCommerceIntegration();
    }
}

// 2. Interface-based design
interface ShippingInterface {
    public function calculateRate($package);
}

class WooCommerceShipping implements ShippingInterface {
    public function calculateRate($package) {
        if (!class_exists('WC_Shipping_Method')) {
            return null;
        }
        // Implementation
    }
}

// 3. Feature detection
class PluginDetector {
    private static $cache = [];
    
    public static function hasWooCommerce(): bool {
        if (!isset(self::$cache['woocommerce'])) {
            self::$cache['woocommerce'] = class_exists('WooCommerce') 
                && defined('WC_VERSION');
        }
        return self::$cache['woocommerce'];
    }
}

// 4. Conditional loading
add_action('plugins_loaded', function() {
    if (PluginDetector::hasWooCommerce()) {
        require_once __DIR__ . '/modules/woocommerce-integration.php';
    }
});
```

## Autoloader Conflicts in Plugin Ecosystem

### The Problem

Multiple plugins using different autoloader strategies cause conflicts:

```php
// Plugin A uses Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Plugin B uses same library but different version
require_once __DIR__ . '/vendor/autoload.php';

// Fatal error: Cannot redeclare class Monolog\Logger
```

### Why It Happens

- No dependency management system in WordPress
- Plugins bundle their own dependencies
- Composer not designed for plugin environment
- Global namespace pollution

### Workarounds

```php
// 1. Use Strauss for prefixing (Shield's approach)
// composer.json
{
    "extra": {
        "strauss": {
            "namespace_prefix": "ShieldVendor\\",
            "classmap_prefix": "ShieldVendor_",
            "target_directory": "vendor_prefixed"
        }
    }
}

// 2. Scoped autoloader pattern
class PluginAutoloader {
    private static $loaded = false;
    
    public static function load() {
        if (self::$loaded) {
            return;
        }
        
        // Check if another version exists
        if (class_exists('Monolog\\Logger')) {
            // Use existing version
            self::$loaded = true;
            return;
        }
        
        // Load our version
        require_once __DIR__ . '/vendor/autoload.php';
        self::$loaded = true;
    }
}

// 3. WordPress-style prefixing
// Instead of: use Monolog\Logger;
// Use: use MyPlugin_Monolog_Logger;

// 4. Isolation with namespace imports
namespace MyPlugin\Isolated;

// Import and alias to avoid conflicts
use \Monolog\Logger as MonologLogger;

class Logger {
    private $logger;
    
    public function __construct() {
        // Only instantiate if available
        if (class_exists('\\Monolog\\Logger')) {
            $this->logger = new MonologLogger('shield');
        }
    }
}
```

### Real Shield Example

```php
// Shield's approach with Strauss and lazy loading
class Controller {
    private static $vendorLoaded = false;
    
    public static function includePrefixedVendor() {
        if (self::$vendorLoaded) {
            return;
        }
        
        $vendorAutoload = __DIR__ . '/vendor_prefixed/autoload.php';
        if (file_exists($vendorAutoload)) {
            require_once $vendorAutoload;
            self::$vendorLoaded = true;
        }
    }
    
    // Only load when needed
    public function initializeLogging() {
        self::includePrefixedVendor();
        // Now safe to use prefixed Monolog
        $logger = new \AptowebDeps\Monolog\Logger('shield');
    }
}
```

## Database Abstraction Quirks

### The Problem

WordPress's `$wpdb` has surprising behaviors:

```php
// Prepare doesn't work like PDO
$wpdb->prepare("SELECT * FROM table WHERE id = %d", $id);  // Works
$wpdb->prepare("SELECT * FROM %s WHERE id = %d", $table, $id);  // BROKEN!

// Results format inconsistencies
$wpdb->get_results($query);  // Array of objects
$wpdb->get_results($query, ARRAY_A);  // Array of arrays
$wpdb->get_row($query);  // Single object
$wpdb->get_var($query);  // Scalar value

// Silent failures
$wpdb->insert('table', ['column' => 'value']);
// Returns false on error, but need to check $wpdb->last_error for details
```

### Why It Happens

- Legacy database layer predates PDO
- Backward compatibility constraints
- MySQL-specific assumptions
- Incomplete abstraction

### Workarounds

```php
// 1. Table name handling
class DatabaseHelper {
    private $wpdb;
    
    public function getTableName(string $table): string {
        global $wpdb;
        // Safely construct table names
        return $wpdb->prefix . preg_replace('/[^a-z0-9_]/i', '', $table);
    }
    
    public function query(string $table, int $id) {
        global $wpdb;
        $tableName = $this->getTableName($table);
        // Use concatenation for table names
        $sql = "SELECT * FROM `{$tableName}` WHERE id = %d";
        return $wpdb->get_results($wpdb->prepare($sql, $id));
    }
}

// 2. Error handling wrapper
class SafeDatabase {
    public function insert(string $table, array $data): int {
        global $wpdb;
        
        $result = $wpdb->insert($table, $data);
        
        if ($result === false) {
            throw new DatabaseException(
                "Insert failed: " . $wpdb->last_error
            );
        }
        
        return $wpdb->insert_id;
    }
    
    public function transactional(callable $callback) {
        global $wpdb;
        
        $wpdb->query('START TRANSACTION');
        try {
            $result = $callback($wpdb);
            $wpdb->query('COMMIT');
            return $result;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
}

// 3. Type-safe results
class TypedDatabase {
    public function getUsers(): array {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->users}",
            ARRAY_A
        );
        
        // Ensure consistent types
        return array_map(function($row) {
            return [
                'ID' => (int) $row['ID'],
                'user_login' => (string) $row['user_login'],
                'user_registered' => new DateTime($row['user_registered'])
            ];
        }, $results ?: []);
    }
}
```

## WordPress Coding Standards vs Modern PHP

### The Problem

WordPress standards conflict with modern PHP practices:

```php
// WordPress style (required by WordPress.org)
if ( ! function_exists( 'my_function' ) ) {
    function my_function( $param_one, $param_two ) {
        return $param_one . $param_two;
    }
}

// Modern PHP style
if (!function_exists('my_function')) {
    function my_function(string $paramOne, string $paramTwo): string {
        return $paramOne . $paramTwo;
    }
}

// WordPress discourages namespaces, modern PHP requires them
// WordPress uses snake_case, PSR uses camelCase
// WordPress uses Yoda conditions, modern PHP doesn't
```

### Why It Happens

- WordPress predates PSR standards
- Huge legacy codebase
- Plugin repository requirements
- Community momentum

### Workarounds

```php
// 1. Dual standard approach
namespace MyPlugin\Modern;

// Modern PHP for internal code
class ServiceContainer {
    private array $services = [];
    
    public function register(string $name, callable $factory): void {
        $this->services[$name] = $factory;
    }
}

// WordPress style for public APIs
function my_plugin_get_service( $service_name ) {
    $container = \MyPlugin\Modern\ServiceContainer::getInstance();
    return $container->get( $service_name );
}

// 2. PHPCS configuration compromise
// .phpcs.xml.dist
<?xml version="1.0"?>
<ruleset>
    <!-- Use WordPress standards for public code -->
    <rule ref="WordPress">
        <!-- But allow modern PHP features -->
        <exclude name="WordPress.NamingConventions.ValidFunctionName"/>
        <exclude name="WordPress.Files.FileName"/>
    </rule>
    
    <!-- Different rules for different directories -->
    <rule ref="PSR12">
        <include-pattern>*/src/Internal/*</include-pattern>
    </rule>
</ruleset>

// 3. Facade pattern for compatibility
// Internal modern code
namespace MyPlugin\Services;

class UserService {
    public function findById(int $id): ?User {
        // Modern implementation
    }
}

// WordPress-compatible facade
class My_Plugin_Users {
    private static $service;
    
    public static function get_user( $user_id ) {
        if ( ! self::$service ) {
            self::$service = new \MyPlugin\Services\UserService();
        }
        return self::$service->findById( (int) $user_id );
    }
}
```

## Testing Challenges

### The Problem

WordPress's architecture makes testing extremely difficult:

```php
// Can't test this without full WordPress
function my_function() {
    $current_user = wp_get_current_user();
    if (current_user_can('manage_options')) {
        update_option('my_option', 'value');
        do_action('my_action');
    }
}

// Mocking is complex due to:
// - Global functions
// - Database queries
// - Hook system
// - Global state
```

### Why It Happens

- Procedural architecture
- Tight coupling
- Global dependencies
- Side effects everywhere

### Workarounds

```php
// 1. Dependency injection pattern
class ServiceClass {
    private $database;
    private $auth;
    private $hooks;
    
    public function __construct($database = null, $auth = null, $hooks = null) {
        $this->database = $database ?: new WordPressDatabase();
        $this->auth = $auth ?: new WordPressAuth();
        $this->hooks = $hooks ?: new WordPressHooks();
    }
    
    public function doWork() {
        if ($this->auth->userCan('manage_options')) {
            $this->database->updateOption('my_option', 'value');
            $this->hooks->doAction('my_action');
        }
    }
}

// 2. BrainMonkey for unit testing
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;

class MyTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
    }
    
    public function testFunction() {
        // Mock WordPress functions
        Functions\expect('wp_get_current_user')
            ->once()
            ->andReturn((object)['ID' => 1]);
            
        Functions\expect('current_user_can')
            ->with('manage_options')
            ->andReturn(true);
            
        Functions\expect('update_option')
            ->with('my_option', 'value')
            ->once();
            
        Actions\expectDone('my_action')->once();
        
        // Now can test
        my_function();
    }
    
    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }
}

// 3. Integration testing with WordPress test suite
class IntegrationTest extends WP_UnitTestCase {
    public function test_with_real_wordpress() {
        // Create real user
        $user_id = $this->factory->user->create([
            'role' => 'administrator'
        ]);
        
        wp_set_current_user($user_id);
        
        // Test with real WordPress
        my_function();
        
        // Assert real database changes
        $this->assertEquals('value', get_option('my_option'));
    }
}

// 4. Testable architecture pattern
interface OptionsInterface {
    public function get(string $key, $default = null);
    public function update(string $key, $value): bool;
}

class WordPressOptions implements OptionsInterface {
    public function get(string $key, $default = null) {
        return get_option($key, $default);
    }
    
    public function update(string $key, $value): bool {
        return update_option($key, $value);
    }
}

class TestableService {
    public function __construct(private OptionsInterface $options) {}
    
    public function saveSettings(array $settings): bool {
        return $this->options->update('my_settings', $settings);
    }
}
```

## Performance Implications of WordPress Patterns

### The Problem

Common WordPress patterns have hidden performance costs:

```php
// Each call potentially hits database
get_option('my_option');  // DB query if not cached
get_post_meta($id, 'key');  // DB query per call
get_user_meta($id, 'key');  // Another DB query

// Hook system overhead
apply_filters('the_content', $content);  // Might call 50+ functions
do_action('init');  // Could trigger hundreds of callbacks

// Autoloading everything
plugins_loaded();  // Loads ALL active plugins regardless of page
```

### Why It Happens

- No lazy loading by default
- Hook system processes everything
- Object cache optional
- Legacy architecture assumptions

### Workarounds

```php
// 1. Batch loading and caching
class MetaCache {
    private array $cache = [];
    
    public function preloadPostMeta(array $postIds): void {
        // Single query for all meta
        update_meta_cache('post', $postIds);
    }
    
    public function getPostMeta(int $postId, string $key) {
        $cacheKey = "{$postId}:{$key}";
        
        if (!isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = get_post_meta($postId, $key, true);
        }
        
        return $this->cache[$cacheKey];
    }
}

// 2. Lazy loading pattern
class LazyLoader {
    private array $loaded = [];
    
    public function loadModule(string $module): void {
        if (isset($this->loaded[$module])) {
            return;
        }
        
        // Only load when needed
        require_once __DIR__ . "/modules/{$module}/init.php";
        $this->loaded[$module] = true;
    }
}

// 3. Strategic hook usage
class OptimizedHooks {
    public function __construct() {
        // Use specific hooks instead of generic ones
        add_action('admin_init', [$this, 'adminOnly']);
        
        // Conditional loading
        if (is_admin()) {
            add_action('init', [$this, 'adminInit']);
        } else {
            add_action('template_redirect', [$this, 'frontendInit']);
        }
    }
    
    public function removeExpensiveFilters(): void {
        // Remove unnecessary filters on specific pages
        if (is_page('fast-page')) {
            remove_all_filters('the_content');
        }
    }
}

// 4. Option caching strategy
class OptionCache {
    private static array $cache = [];
    
    public static function getOptions(array $keys): array {
        $missing = array_diff($keys, array_keys(self::$cache));
        
        if (!empty($missing)) {
            // Batch load missing options
            foreach ($missing as $key) {
                self::$cache[$key] = get_option($key);
            }
        }
        
        return array_intersect_key(self::$cache, array_flip($keys));
    }
}
```

## Security Considerations Unique to WordPress

### The Problem

WordPress has unique security challenges:

```php
// Nonce system isn't really a nonce
wp_create_nonce('action');  // Valid for 24 hours, not single use

// Capability system is confusing
current_user_can('edit_posts');  // Might be true for many roles
current_user_can('edit_post', $id);  // Object-specific capability

// Sanitization functions are inconsistent
sanitize_text_field($input);  // Strips HTML
wp_kses_post($input);  // Allows some HTML
esc_html($input);  // Escapes for display
```

### Why It Happens

- Backward compatibility with insecure patterns
- Complex permission system
- Multiple sanitization approaches
- Legacy before modern security practices

### Workarounds

```php
// 1. Enhanced nonce system
class TrueNonce {
    private const OPTION_KEY = 'true_nonces';
    
    public static function create(string $action): string {
        $nonce = wp_create_nonce($action);
        $key = md5($nonce);
        
        // Store for single use
        $nonces = get_option(self::OPTION_KEY, []);
        $nonces[$key] = time();
        update_option(self::OPTION_KEY, $nonces);
        
        return $nonce;
    }
    
    public static function verify(string $nonce, string $action): bool {
        if (!wp_verify_nonce($nonce, $action)) {
            return false;
        }
        
        $key = md5($nonce);
        $nonces = get_option(self::OPTION_KEY, []);
        
        // Check single use
        if (!isset($nonces[$key])) {
            return false;  // Already used
        }
        
        // Remove after use
        unset($nonces[$key]);
        update_option(self::OPTION_KEY, $nonces);
        
        return true;
    }
}

// 2. Strict capability checking
class StrictCapabilities {
    public static function userCan(string $capability, $object = null): bool {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        
        // Check both capability and object ownership
        if ($object && is_numeric($object)) {
            $post = get_post($object);
            if ($post->post_author != $user->ID && !user_can($user, $capability, $object)) {
                return false;
            }
        }
        
        return user_can($user, $capability, $object);
    }
}

// 3. Consistent sanitization wrapper
class Sanitizer {
    public static function input(string $input, string $context = 'display'): string {
        switch ($context) {
            case 'database':
                return sanitize_text_field($input);
                
            case 'display':
                return esc_html($input);
                
            case 'attribute':
                return esc_attr($input);
                
            case 'url':
                return esc_url($input);
                
            case 'js':
                return esc_js($input);
                
            case 'rich_text':
                return wp_kses_post($input);
                
            default:
                return sanitize_text_field($input);
        }
    }
    
    public static function validateAndSanitize(array $data, array $rules): array {
        $sanitized = [];
        
        foreach ($rules as $key => $rule) {
            if (!isset($data[$key])) {
                continue;
            }
            
            $value = $data[$key];
            
            // Apply validation
            if (isset($rule['validate'])) {
                if (!call_user_func($rule['validate'], $value)) {
                    continue;  // Skip invalid data
                }
            }
            
            // Apply sanitization
            $sanitized[$key] = self::input($value, $rule['context'] ?? 'database');
        }
        
        return $sanitized;
    }
}
```

## Static Analysis Nightmares

### The Problem

PHPStan and similar tools fail spectacularly with WordPress:

```php
// PHPStan sees hundreds of "errors" that aren't really errors
$post->custom_field;  // Property does not exist
apply_filters('hook', $data);  // Return type uncertain
do_action('init');  // Side effects unknown
$wpdb->prepare($sql, $arg);  // Method signature doesn't match
```

### Why It Happens

- Dynamic typing everywhere
- Runtime class generation
- Hook system obscures flow
- WordPress predates static analysis

### Workarounds

```php
// 1. Give up on PHPStan for WordPress code
// After extensive testing, Shield abandoned PHPStan because:
// - 500+ false positives
// - Days of configuration yielded minimal value
// - WordPress ecosystem incompatible with static analysis

// 2. Use WordPress-specific tools instead
// PHPCS with WordPress Coding Standards
// This catches REAL issues:
$wpdb->query("SELECT * FROM table WHERE id = $_GET[id]");  // SQL injection
echo $_POST['data'];  // XSS vulnerability

// 3. Type hints for new code only
namespace MyPlugin\Modern;

// Use strict types in isolated modern code
declare(strict_types=1);

class TypedService {
    public function process(array $data): array {
        return array_map(fn($item) => (string) $item, $data);
    }
}

// 4. Document dynamic behavior
/**
 * @property string $dynamic_field Loaded from post meta
 * @method void customMethod() Handled via __call
 */
class DocumentedMagic {
    use MagicMethodsTrait;
}

// 5. Stub files for WordPress
// Create stubs for WordPress functions if you must use PHPStan
namespace {
    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool {}
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed {}
}
```

## CI/CD Pipeline Gotchas

### The Problem

WordPress CI/CD has unique challenges:

```bash
# SVN no longer available on GitHub Actions Ubuntu 24.04
./bin/install-wp-tests.sh  # Fails: svn command not found

# Multiple test environments needed
# - Unit tests without WordPress
# - Integration tests with WordPress
# - Package tests with built plugin

# Dependency conflicts
composer install  # Installs dev dependencies
composer strauss  # Prefixes dependencies
# Now you have both prefixed and unprefixed versions!
```

### Why It Happens

- WordPress test suite uses SVN
- Complex build process with prefixing
- Multiple test types required
- Platform differences (Linux/Windows/Mac)

### Workarounds

```yaml
# 1. Modern WordPress test installation (no SVN)
- name: Setup WordPress Test Suite
  run: |
    # Download WordPress via HTTPS
    curl -L https://wordpress.org/latest.tar.gz | tar xz -C /tmp
    
    # Get test suite from GitHub
    git clone --depth=1 https://github.com/WordPress/wordpress-develop.git /tmp/wordpress-tests-lib

# 2. Docker-based testing
services:
  mysql:
    image: mysql:8.0
    env:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: wordpress_test

  wordpress:
    image: wordpress:latest
    volumes:
      - .:/var/www/html/wp-content/plugins/my-plugin

# 3. Proper build sequence
- name: Build Package
  run: |
    # Install production dependencies only
    composer install --no-dev --no-interaction
    
    # Run Strauss prefixing
    cd src/lib && composer strauss && cd ../..
    
    # Clean up duplicate files
    rm -rf src/lib/vendor/twig
    rm -rf src/lib/vendor/monolog
    
    # Fix autoloader files
    sed -i '/twig\/twig/d' src/lib/vendor/composer/autoload_*.php
```

### Real Shield Example

```powershell
# Shield's Windows PowerShell testing script
param([switch]$SkipCleanup)

$ErrorActionPreference = "Stop"

# Use direct PHP executable, not Herd wrapper
$PhpPath = "C:\Users\paulg\.config\herd\bin\php83\php.exe"

# Create isolated test directory
$TestDir = "test-$(Get-Date -Format 'yyyyMMddHHmmss')"
New-Item -ItemType Directory -Path $TestDir

try {
    # Copy plugin
    Copy-Item -Path . -Destination $TestDir -Recurse -Exclude @('.git', $TestDir)
    Set-Location $TestDir
    
    # Install dependencies with non-interactive flags
    & $PhpPath (Get-Command composer).Path install --no-interaction --no-progress
    
    # Build package
    Set-Location src/lib
    & $PhpPath (Get-Command composer).Path strauss
    Set-Location ../..
    
    # Run tests
    & $PhpPath vendor/bin/phpunit
    
} finally {
    Set-Location ..
    if (-not $SkipCleanup) {
        Remove-Item -Path $TestDir -Recurse -Force
    }
}
```

## Windows Development Environment Issues

### The Problem

Windows development with WordPress has unique issues:

```powershell
# Herd PHP wrapper causes problems
php --version  # Error: "/c: /c: Is a directory"

# Path issues with spaces
cd "C:\Users\John Doe\Projects"  # Breaks many scripts

# Line ending problems
git clone repo  # CRLF vs LF issues

# Symbolic links require admin
ln -s target link  # Access denied
```

### Why It Happens

- PHP tools designed for Unix
- Path handling differences
- Permission model differences
- Line ending incompatibilities

### Workarounds

```powershell
# 1. Use direct PHP executable
$PhpPath = "C:\Users\$env:USERNAME\.config\herd\bin\php83\php.exe"
& $PhpPath script.php  # Works reliably

# 2. Handle paths properly
$ProjectPath = $PSScriptRoot
$SafePath = $ProjectPath -replace ' ', '` '  # Escape spaces

# Or use quotes
& somecommand "$ProjectPath\file.php"

# 3. Configure Git for line endings
git config core.autocrlf input  # Convert CRLF to LF on commit

# 4. WSL2 for better compatibility
wsl -d Ubuntu -- bash -c "cd /mnt/c/project && ./run-tests.sh"

# 5. PowerShell error handling
$ErrorActionPreference = "Stop"
try {
    # Your code here
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
    exit 1
}
```

## Key Takeaways

After months of WordPress development on Shield Security, these are the critical lessons:

1. **WordPress is Not Modern PHP**: Stop trying to apply modern PHP practices directly. WordPress has its own patterns for good reasons.

2. **Embrace the Chaos**: Don't fight WordPress patterns, work with them. Use defensive programming and validate everything.

3. **Testing Strategy**: Give up on 100% coverage. Focus on critical business logic with BrainMonkey, integration tests for WordPress-specific code.

4. **Static Analysis is Futile**: PHPStan and WordPress are incompatible. Use PHPCS with WordPress Coding Standards instead.

5. **Performance Matters**: WordPress's hook system and global queries add up. Cache aggressively and load lazily.

6. **Security is Different**: WordPress security isn't just about OWASP. Understand nonces, capabilities, and sanitization functions.

7. **Dependency Hell is Real**: Use Strauss or similar prefixing tools. Never assume your dependencies won't conflict.

8. **CI/CD Requires Special Care**: WordPress testing needs multiple environments. Docker is your friend.

9. **Windows Needs Extra Work**: If developing on Windows, use PowerShell properly and understand path issues.

10. **Document Everything**: Future you (or your team) will thank you for documenting these workarounds.

## Conclusion

WordPress development is a unique challenge that requires abandoning many "best practices" from modern PHP development. This isn't because WordPress is "bad" - it's because WordPress solves different problems with different constraints than typical PHP applications.

Success in WordPress development comes from:
- Understanding why WordPress works the way it does
- Using WordPress-specific tools and patterns
- Defensive programming against the unexpected
- Accepting that perfect is the enemy of good
- Learning from the pain of others (like Shield Security's journey)

These gotchas represent hundreds of hours of debugging, failed attempts, and hard-won knowledge. Use them to avoid the same pitfalls and build robust WordPress solutions that work with the platform, not against it.