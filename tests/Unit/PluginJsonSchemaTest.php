<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Comprehensive test suite for validating the plugin.json configuration file.
 * 
 * This test class ensures that the plugin.json file maintains a valid structure,
 * contains all required fields, and that all cross-references between sections
 * are valid.
 */
class PluginJsonSchemaTest extends TestCase {

	use PluginPathsTrait;

	/**
	 * @var array The parsed plugin.json configuration
	 */
	private $config;

	/**
	 * @var string The path to the plugin.json file
	 */
	private $configPath;

	/**
	 * Set up the test environment before each test method
	 */
	protected function set_up() :void {
		parent::set_up();
		$this->configPath = $this->getPluginJsonPath();
		$this->assertFileExists( $this->configPath, 'plugin.json file must exist' );

		$jsonContent = file_get_contents( $this->configPath );
		$this->config = json_decode( $jsonContent, true );
		
		$this->assertNotNull( $this->config, 'plugin.json must be valid JSON' );
		$this->assertEquals( JSON_ERROR_NONE, json_last_error(), 'JSON parsing error: '.json_last_error_msg() );
	}

	/**
	 * Test that all required top-level keys exist in the configuration
	 */
	public function testRequiredTopLevelKeysExist() :void {
		$requiredKeys = [
			'properties',
			'requirements',
			'paths',
			'includes',
			'menu',
			'labels',
			'meta',
			'plugin_meta',
			'action_links',
			'config_spec'
		];

		foreach ( $requiredKeys as $key ) {
			$this->assertArrayHasKey( $key, $this->config, "Required top-level key '{$key}' is missing" );
		}
	}

	/**
	 * Test the properties section structure and data types
	 */
	public function testPropertiesSectionStructure() :void {
		$this->assertArrayHasKey( 'properties', $this->config );
		$properties = $this->config['properties'];

		// Test required property fields
		$requiredProperties = [
			'version' => 'string',
			'release_timestamp' => 'integer',
			'build' => 'string',
			'slug_parent' => 'string',
			'slug_plugin' => 'string',
			'text_domain' => 'string',
			'base_permissions' => 'string',
			'wpms_network_admin_only' => 'boolean',
			'logging_enabled' => 'boolean',
			'show_dashboard_widget' => 'boolean',
			'show_admin_bar_menu' => 'boolean',
			'autoupdate' => 'string',
			'autoupdate_days' => 'integer',
			'enable_premium' => 'boolean'
		];

		foreach ( $requiredProperties as $property => $expectedType ) {
			$this->assertArrayHasKey( $property, $properties, "Required property '{$property}' is missing" );
			
			$actualType = gettype( $properties[$property] );
			$this->assertEquals( $expectedType, $actualType, "Property '{$property}' should be {$expectedType}, got {$actualType}" );
		}

		// Validate version format (semantic versioning)
		$this->assertMatchesRegularExpression( 
			'/^\d+\.\d+\.\d+$/', 
			$properties['version'], 
			'Version must follow semantic versioning format (X.Y.Z)' 
		);

		// Validate build format (YYYYMM.DDNN)
		$this->assertMatchesRegularExpression(
			'/^\d{6}\.\d{4}$/',
			$properties['build'],
			'Build must follow YYYYMM.DDNN format'
		);

		// Validate text domain format
		$this->assertMatchesRegularExpression(
			'/^[a-z0-9-]+$/',
			$properties['text_domain'],
			'Text domain must contain only lowercase letters, numbers, and hyphens'
		);

		// Validate autoupdate values
		$this->assertContains(
			$properties['autoupdate'],
			['immediate', 'confidence', 'stable', 'manual'],
			'autoupdate must be one of: immediate, confidence, stable, manual'
		);
	}

	/**
	 * Test the requirements section
	 */
	public function testRequirementsSection() :void {
		$this->assertArrayHasKey( 'requirements', $this->config );
		$requirements = $this->config['requirements'];

		// Check required keys
		$this->assertArrayHasKey( 'php', $requirements, 'PHP version requirement is missing' );
		$this->assertArrayHasKey( 'wordpress', $requirements, 'WordPress version requirement is missing' );
		$this->assertArrayHasKey( 'mysql', $requirements, 'MySQL version requirement is missing' );

		// Validate PHP version (should be >= 7.4)
		$phpVersion = $requirements['php'];
		$this->assertIsString( $phpVersion );
		$this->assertGreaterThanOrEqual( 
			version_compare( '7.4', $phpVersion ), 
			0,
			'PHP version requirement should be at least 7.4' 
		);

		// Validate WordPress version (should be >= 5.7)
		$wpVersion = $requirements['wordpress'];
		$this->assertIsString( $wpVersion );
		$this->assertGreaterThanOrEqual(
			version_compare( '5.7', $wpVersion ),
			0,
			'WordPress version requirement should be at least 5.7'
		);

		// Validate MySQL version
		$mysqlVersion = $requirements['mysql'];
		$this->assertIsString( $mysqlVersion );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+$/', $mysqlVersion, 'MySQL version format should be X.Y' );
	}

	/**
	 * Test the paths section and verify critical directories exist
	 */
	public function testPathsSectionAndDirectoryExistence() :void {
		$this->assertArrayHasKey( 'paths', $this->config );
		$paths = $this->config['paths'];

		$requiredPaths = [
			'config',
			'source',
			'autoload',
			'assets',
			'languages',
			'templates',
			'custom_templates',
			'flags',
			'cache'
		];

		foreach ( $requiredPaths as $pathKey ) {
			$this->assertArrayHasKey( $pathKey, $paths, "Required path '{$pathKey}' is missing" );
			$this->assertIsString( $paths[$pathKey], "Path '{$pathKey}' must be a string" );
			$this->assertNotEmpty( $paths[$pathKey], "Path '{$pathKey}' cannot be empty" );
		}

		// Verify critical directories exist
		$baseDir = dirname( $this->configPath );
		$criticalDirs = [
			'source' => $paths['source'],
			'assets' => $paths['assets'],
			'languages' => $paths['languages'],
			'templates' => $paths['templates']
		];

		foreach ( $criticalDirs as $key => $relativePath ) {
			$fullPath = $baseDir.'/'.$relativePath;
			$this->assertDirectoryExists( $fullPath, "Critical directory '{$key}' at '{$relativePath}' does not exist" );
		}
	}

	/**
	 * Test the includes section structure
	 */
	public function testIncludesSection() :void {
		$this->assertArrayHasKey( 'includes', $this->config );
		$includes = $this->config['includes'];

		// Test third-party includes
		$this->assertArrayHasKey( 'tp', $includes, 'Third-party includes section is missing' );
		$tp = $includes['tp'];

		// Validate Bootstrap configuration
		$this->assertArrayHasKey( 'bootstrap', $tp, 'Bootstrap configuration is missing' );
		$this->assertArrayHasKey( 'css', $tp['bootstrap'], 'Bootstrap CSS URL is missing' );
		$this->assertArrayHasKey( 'js', $tp['bootstrap'], 'Bootstrap JS URL is missing' );
		
		$this->assertStringStartsWith( 'https://', $tp['bootstrap']['css'], 'Bootstrap CSS should use HTTPS' );
		$this->assertStringStartsWith( 'https://', $tp['bootstrap']['js'], 'Bootstrap JS should use HTTPS' );

		// Validate Vimeo player configuration
		$this->assertArrayHasKey( 'vimeo_player', $tp, 'Vimeo player configuration is missing' );
		$this->assertArrayHasKey( 'js', $tp['vimeo_player'], 'Vimeo player JS URL is missing' );
		$this->assertStringStartsWith( 'https://', $tp['vimeo_player']['js'], 'Vimeo player JS should use HTTPS' );

		// Test dist includes
		$this->assertArrayHasKey( 'dist', $includes, 'Dist includes section is missing' );
		$this->assertIsArray( $includes['dist'], 'Dist includes must be an array' );
		$this->assertNotEmpty( $includes['dist'], 'Dist entries should not be empty' );

		foreach ( $includes['dist'] as $index => $dist ) {
			$this->assertArrayHasKey( 'handle', $dist, "Dist entry {$index} missing 'handle'" );
			$this->assertArrayHasKey( 'types', $dist, "Dist entry {$index} missing 'types'" );
			$this->assertArrayHasKey( 'flags', $dist, "Dist entry {$index} missing 'flags'" );
			$this->assertArrayHasKey( 'admin_only', $dist['flags'], "Dist entry {$index} flags missing 'admin_only'" );
			$this->assertIsBool( $dist['flags']['admin_only'], "Dist entry {$index} admin_only must be boolean" );
			$this->assertIsArray( $dist['types'], "Dist entry {$index} 'types' must be an array" );
			
			// Validate each type
			foreach ( $dist['types'] as $type ) {
				$this->assertContains( $type, ['js', 'css'], "Dist entry {$index} type must be 'js' or 'css'" );
			}
			
			// zones is optional
			if ( isset( $dist['zones'] ) ) {
				$this->assertIsArray( $dist['zones'], "Dist entry {$index} 'zones' must be an array" );
			}
			
			// deps is optional
			if ( isset( $dist['deps'] ) ) {
				$this->assertIsArray( $dist['deps'], "Dist entry {$index} 'deps' must be an array" );
			}
		}
	}

	/**
	 * Test the menu section configuration
	 */
	public function testMenuSection() :void {
		$this->assertArrayHasKey( 'menu', $this->config );
		$menu = $this->config['menu'];

		$requiredMenuKeys = [
			'show' => 'boolean',
			'top_level' => 'boolean',
			'do_submenu_fix' => 'boolean',
			'has_submenu' => 'boolean'
		];

		foreach ( $requiredMenuKeys as $key => $expectedType ) {
			$this->assertArrayHasKey( $key, $menu, "Menu key '{$key}' is missing" );
			$actualType = gettype( $menu[$key] );
			$this->assertEquals( $expectedType, $actualType, "Menu '{$key}' should be {$expectedType}, got {$actualType}" );
		}
	}

	/**
	 * Test the labels section
	 */
	public function testLabelsSection() :void {
		$this->assertArrayHasKey( 'labels', $this->config );
		$labels = $this->config['labels'];

		$requiredLabels = [
			'Name',
			'MenuTitle',
			'Description',
			'Title',
			'Author',
			'AuthorName',
			'PluginURI',
			'AuthorURI'
		];

		foreach ( $requiredLabels as $label ) {
			$this->assertArrayHasKey( $label, $labels, "Required label '{$label}' is missing" );
			$this->assertIsString( $labels[$label], "Label '{$label}' must be a string" );
			$this->assertNotEmpty( $labels[$label], "Label '{$label}' cannot be empty" );
		}

		// Validate URLs
		if ( isset( $labels['PluginURI'] ) ) {
			$this->assertStringStartsWith( 'https://', $labels['PluginURI'], 'PluginURI should use HTTPS' );
		}
		if ( isset( $labels['AuthorURI'] ) ) {
			$this->assertStringStartsWith( 'https://', $labels['AuthorURI'], 'AuthorURI should use HTTPS' );
		}

		// Validate image paths
		$imagePaths = [
			'url_img_pagebanner',
			'url_img_logo_small',
			'icon_url_16x16',
			'icon_url_16x16_grey',
			'icon_url_32x32',
			'icon_url_128x128'
		];

		foreach ( $imagePaths as $imageKey ) {
			if ( isset( $labels[$imageKey] ) ) {
				$this->assertMatchesRegularExpression(
					'/\.(png|jpg|jpeg|gif|svg)$/i',
					$labels[$imageKey],
					"Image path '{$imageKey}' should have valid image extension"
				);
			}
		}
	}

	/**
	 * Test that all security modules are properly defined in config_spec.modules
	 * 
	 * @dataProvider securityModulesProvider
	 */
	public function testSecurityModulesDefinitions( string $moduleKey, array $requiredFields ) :void {
		$this->assertArrayHasKey( 'config_spec', $this->config );
		$this->assertArrayHasKey( 'modules', $this->config['config_spec'] );

		$modules = $this->config['config_spec']['modules'];
		$this->assertNotEmpty( $modules, 'Modules should not be empty' );
		$this->assertArrayHasKey( $moduleKey, $modules, "Security module '{$moduleKey}' is not defined" );
		
		$module = $modules[$moduleKey];
		
		// Check required fields for each module
		foreach ( $requiredFields as $field ) {
			$this->assertArrayHasKey( $field, $module, "Module '{$moduleKey}' missing required field '{$field}'" );
		}
		
		// Validate slug matches the key
		$this->assertEquals( $moduleKey, $module['slug'], "Module slug should match the module key" );
		
		// Validate show_central is boolean if present
		if ( isset( $module['show_central'] ) ) {
			$this->assertIsBool( $module['show_central'], "Module '{$moduleKey}' show_central must be boolean" );
		}
	}

	/**
	 * Data provider for security modules test — built dynamically from plugin.json
	 */
	public function securityModulesProvider() :array {
		$configPath = $this->getPluginJsonPath();
		$config = \json_decode( \file_get_contents( $configPath ), true );
		$modules = $config['config_spec']['modules'] ?? [];

		$datasets = [];
		foreach ( $modules as $moduleKey => $module ) {
			$requiredFields = [ 'slug', 'name' ];
			if ( isset( $module['tagline'] ) ) {
				$requiredFields[] = 'tagline';
			}
			if ( isset( $module['show_central'] ) ) {
				$requiredFields[] = 'show_central';
			}
			$datasets[$moduleKey] = [ $moduleKey, $requiredFields ];
		}

		return $datasets;
	}

	/**
	 * Test that sections reference valid modules
	 */
	public function testSectionsReferenceValidModules() :void {
		$this->assertArrayHasKey( 'config_spec', $this->config );
		$this->assertArrayHasKey( 'sections', $this->config['config_spec'] );
		$this->assertArrayHasKey( 'modules', $this->config['config_spec'] );
		
		$sections = $this->config['config_spec']['sections'];
		$modules = $this->config['config_spec']['modules'];
		
		$this->assertIsArray( $sections, 'Sections must be an array' );
		$this->assertNotEmpty( $sections, 'Sections array cannot be empty' );
		
		foreach ( $sections as $index => $section ) {
			$this->assertArrayHasKey( 'slug', $section, "Section at index {$index} missing 'slug'" );
			$this->assertArrayHasKey( 'module', $section, "Section at index {$index} missing 'module'" );
			
			$moduleRef = $section['module'];
			$this->assertArrayHasKey( 
				$moduleRef, 
				$modules, 
				"Section '{$section['slug']}' references non-existent module '{$moduleRef}'" 
			);
			
			// If not hidden, should have title and title_short
			if ( !isset( $section['hidden'] ) || !$section['hidden'] ) {
				$this->assertArrayHasKey( 'title', $section, "Section '{$section['slug']}' missing 'title'" );
				$this->assertArrayHasKey( 'title_short', $section, "Section '{$section['slug']}' missing 'title_short'" );
			}
			
			// Validate primary flag if present
			if ( isset( $section['primary'] ) ) {
				$this->assertIsBool( $section['primary'], "Section '{$section['slug']}' primary flag must be boolean" );
			}
			
			// Validate beacon_id if present
			if ( isset( $section['beacon_id'] ) ) {
				$this->assertIsInt( $section['beacon_id'], "Section '{$section['slug']}' beacon_id must be integer" );
				$this->assertGreaterThan( 0, $section['beacon_id'], "Section '{$section['slug']}' beacon_id must be positive" );
			}
		}
	}

	/**
	 * Test the options structure within config_spec
	 */
	public function testOptionsStructure() :void {
		$this->assertArrayHasKey( 'config_spec', $this->config );
		$this->assertArrayHasKey( 'options', $this->config['config_spec'] );
		
		$options = $this->config['config_spec']['options'];
		$this->assertIsArray( $options, 'Options must be an array' );
		
		// Test a sample of options to ensure proper structure
		foreach ( $options as $optionKey => $option ) {
			$this->assertIsArray( $option, "Option '{$optionKey}' must be an array" );
			
			// Common required fields for options
			if ( !isset( $option['transferable'] ) || $option['transferable'] !== false ) {
				$this->assertArrayHasKey( 'default', $option, "Option '{$optionKey}' missing 'default' value" );
			}
			
			// If option has type, validate it
			if ( isset( $option['type'] ) ) {
				$validTypes = ['checkbox', 'select', 'text', 'multiple_select', 'integer', 'email', 'timestamp', 'password', 'array', 'boolean', 'noneditable_text'];
				$this->assertContains( 
					$option['type'], 
					$validTypes, 
					"Option '{$optionKey}' has invalid type '{$option['type']}'" 
				);
			}
			
			// If option references a section, validate it exists
			if ( isset( $option['section'] ) ) {
				$this->assertSectionExists( $option['section'], "Option '{$optionKey}' references non-existent section" );
			}
		}
	}

	/**
	 * Test meta and plugin_meta sections
	 */
	public function testMetaSections() :void {
		// Test meta section
		$this->assertArrayHasKey( 'meta', $this->config );
		$meta = $this->config['meta'];
		$this->assertIsArray( $meta, 'Meta section must be an array' );
		
		// Validate required meta URLs
		$requiredMetaUrls = [
			'url_repo_home',
			'privacy_policy_href'
		];
		
		foreach ( $requiredMetaUrls as $urlKey ) {
			$this->assertArrayHasKey( $urlKey, $meta, "Meta URL '{$urlKey}' is missing" );
			$this->assertStringStartsWith( 'https://', $meta[$urlKey], "Meta URL '{$urlKey}' should use HTTPS" );
			$this->assertNotEmpty( $meta[$urlKey], "Meta URL '{$urlKey}' cannot be empty" );
		}
		
		// Test plugin_meta section
		$this->assertArrayHasKey( 'plugin_meta', $this->config );
		$pluginMeta = $this->config['plugin_meta'];
		$this->assertIsArray( $pluginMeta, 'Plugin meta section must be an array' );
		
		// Test rate section in plugin_meta
		$this->assertArrayHasKey( 'rate', $pluginMeta, 'Plugin meta rate section is missing' );
		$rate = $pluginMeta['rate'];
		
		$this->assertArrayHasKey( 'name', $rate, 'Rate name is missing' );
		$this->assertArrayHasKey( 'href', $rate, 'Rate href is missing' );
		$this->assertNotEmpty( $rate['name'], 'Rate name cannot be empty' );
		$this->assertStringStartsWith( 'https://', $rate['href'], 'Rate href should use HTTPS' );
	}

	/**
	 * Test that database requirements are properly defined for modules that need them
	 */
	public function testModuleDatabaseRequirements() :void {
		$this->assertArrayHasKey( 'config_spec', $this->config );
		$this->assertArrayHasKey( 'modules', $this->config['config_spec'] );

		$modules = $this->config['config_spec']['modules'];
		$modulesWithDbsFound = 0;

		foreach ( $modules as $moduleKey => $module ) {
			if ( isset( $module['reqs']['dbs'] ) ) {
				$modulesWithDbsFound++;
				$this->assertIsArray( $module['reqs']['dbs'], "Module '{$moduleKey}' dbs must be an array" );
				$this->assertNotEmpty( $module['reqs']['dbs'], "Module '{$moduleKey}' dbs should not be empty" );

				foreach ( $module['reqs']['dbs'] as $db ) {
					$this->assertIsString( $db, "Module '{$moduleKey}' db entry must be a string" );
					$this->assertNotEmpty( $db, "Module '{$moduleKey}' db entry should not be empty" );
				}
			}
		}

		$this->assertGreaterThan( 0, $modulesWithDbsFound, 'At least one module should have database requirements' );
	}

	/**
	 * Test action_links section
	 */
	public function testActionLinksSection() :void {
		$this->assertArrayHasKey( 'action_links', $this->config );
		$actionLinks = $this->config['action_links'];
		
		$this->assertIsArray( $actionLinks, 'Action links must be an array' );
		
		// Check expected structure
		if ( isset( $actionLinks['remove'] ) ) {
			// remove can be null or an array
			if ( $actionLinks['remove'] !== null ) {
				$this->assertIsArray( $actionLinks['remove'], "Action links 'remove' must be null or array" );
			}
		}
		
		if ( isset( $actionLinks['add'] ) ) {
			$this->assertIsArray( $actionLinks['add'], "Action links 'add' must be an array" );
			
			foreach ( $actionLinks['add'] as $index => $link ) {
				$this->assertIsArray( $link, "Action link at index {$index} must be an array" );
				
				// Common fields for action links
				if ( isset( $link['name'] ) ) {
					$this->assertIsString( $link['name'], "Action link at index {$index} 'name' must be a string" );
				}
				
				if ( isset( $link['show'] ) ) {
					$this->assertIsString( $link['show'], "Action link at index {$index} 'show' must be a string" );
				}
				
				if ( isset( $link['href'] ) ) {
					$this->assertIsString( $link['href'], "Action link at index {$index} 'href' must be a string" );
				}
			}
		}
	}

	/**
	 * Test the events structure in config_spec for audit trail
	 */
	public function testEventsStructure() :void {
		$this->assertArrayHasKey( 'config_spec', $this->config );
		$this->assertArrayHasKey( 'events', $this->config['config_spec'] );
		
		$events = $this->config['config_spec']['events'];
		$this->assertIsArray( $events, 'Events must be an array' );
		$this->assertNotEmpty( $events, 'Events array cannot be empty' );
		
		$validLevels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
		
		foreach ( $events as $eventKey => $event ) {
			$this->assertIsArray( $event, "Event '{$eventKey}' must be an array" );
			
			// Check for required level field
			if ( isset( $event['level'] ) ) {
				$this->assertContains( 
					$event['level'], 
					$validLevels, 
					"Event '{$eventKey}' has invalid level '{$event['level']}'" 
				);
			}
			
			// Check audit_params if present
			if ( isset( $event['audit_params'] ) ) {
				$this->assertIsArray( $event['audit_params'], "Event '{$eventKey}' audit_params must be an array" );
				foreach ( $event['audit_params'] as $param ) {
					$this->assertIsString( $param, "Event '{$eventKey}' audit param must be string" );
				}
			}
			
			// Check offense flag if present
			if ( isset( $event['offense'] ) ) {
				$this->assertIsBool( $event['offense'], "Event '{$eventKey}' offense flag must be boolean" );
			}
			
			// Check recent flag if present
			if ( isset( $event['recent'] ) ) {
				$this->assertIsBool( $event['recent'], "Event '{$eventKey}' recent flag must be boolean" );
			}
		}
	}

	/**
	 * Helper method to check if a section exists
	 */
	private function assertSectionExists( string $sectionSlug, string $message = '' ) :void {
		$sections = $this->config['config_spec']['sections'] ?? [];
		
		$sectionSlugs = array_column( $sections, 'slug' );
		$this->assertContains( $sectionSlug, $sectionSlugs, $message );
	}

	/**
	 * Test that the configuration doesn't contain any unexpected top-level keys
	 */
	public function testNoUnexpectedTopLevelKeys() :void {
		$expectedKeys = [
			'properties',
			'requirements',
			'paths',
			'includes',
			'menu',
			'labels',
			'meta',
			'plugin_meta',
			'action_links',
			'config_spec',
			'translations',
		];
		
		$actualKeys = array_keys( $this->config );
		$unexpectedKeys = array_diff( $actualKeys, $expectedKeys );
		
		$this->assertEmpty( 
			$unexpectedKeys, 
			'Unexpected top-level keys found: '.implode( ', ', $unexpectedKeys ) 
		);
	}

	/**
	 * Test large file handling - ensure the parser can handle the 6000+ line file
	 */
	public function testLargeFileHandling() :void {
		// Get file size
		$fileSize = filesize( $this->configPath );
		$this->assertGreaterThan( 100000, $fileSize, 'Plugin.json should be a large file' );
		
		// Test that we can traverse deep structures
		$this->assertArrayHasKey( 'config_spec', $this->config );
		$this->assertArrayHasKey( 'events', $this->config['config_spec'] );
		
		// Count total events to ensure we're processing the full file
		$eventCount = count( $this->config['config_spec']['events'] );
		$this->assertGreaterThan( 50, $eventCount, 'Should have many events defined' );
		
		// Test file line count (expected to be around 6,673 lines)
		$content = file_get_contents( $this->configPath );
		$lineCount = substr_count( $content, "\n" ) + 1;
		
		// Sanity check — file should be substantial, but don't assert an upper bound
		$this->assertGreaterThan( 1000, $lineCount, 'plugin.json should have at least 1000 lines' );
	}
}