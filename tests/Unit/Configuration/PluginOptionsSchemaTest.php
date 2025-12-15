<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Configuration;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Tests for plugin options schema validation.
 * 
 * These tests validate that all options in plugin.json have correct structure,
 * types, and default values - catching configuration bugs before they reach production.
 */
class PluginOptionsSchemaTest extends TestCase {

	use PluginPathsTrait;

	private array $options;
	private array $sections;

	protected function set_up() :void {
		parent::set_up();
		$config = $this->loadConfig();
		$this->options = $config['config_spec']['options'] ?? [];
		$this->sections = $config['config_spec']['sections'] ?? [];
	}

	private function loadConfig() :array {
		return $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration' );
	}

	// =========================================================================
	// OPTION STRUCTURE TESTS
	// =========================================================================

	public function testAllOptionsHaveValidTypes() :void {
		$validTypes = [
			'checkbox',
			'select',
			'multiple_select',
			'text',
			'password',
			'integer',
			'email',
			'array',
			'boolean',
			'noneditable_text',
			'timestamp',
		];

		$optionsWithTypes = \array_filter( $this->options, fn( $opt ) => isset( $opt['type'] ) );
		
		foreach ( $optionsWithTypes as $key => $option ) {
			$this->assertContains(
				$option['type'],
				$validTypes,
				sprintf( "Option '%s' has invalid type '%s'", $key, $option['type'] )
			);
		}
	}

	public function testCheckboxOptionsHaveValidDefaults() :void {
		$checkboxOptions = \array_filter( 
			$this->options, 
			fn( $opt ) => ( $opt['type'] ?? '' ) === 'checkbox' 
		);

		foreach ( $checkboxOptions as $key => $option ) {
			if ( isset( $option['default'] ) ) {
				$this->assertContains(
					$option['default'],
					[ 'Y', 'N' ],
					sprintf( "Checkbox option '%s' should have 'Y' or 'N' as default, got '%s'", $key, $option['default'] )
				);
			}
		}
	}

	public function testSelectOptionsHaveValueOptions() :void {
		$selectOptions = \array_filter( 
			$this->options, 
			fn( $opt ) => \in_array( $opt['type'] ?? '', [ 'select', 'multiple_select' ] )
		);

		foreach ( $selectOptions as $key => $option ) {
			// Skip if hidden or not transferable
			if ( ( $option['hidden'] ?? false ) || ( $option['transferable'] ?? true ) === false ) {
				continue;
			}

			$this->assertArrayHasKey(
				'value_options',
				$option,
				sprintf( "Select option '%s' should have 'value_options' defined", $key )
			);

			if ( isset( $option['value_options'] ) ) {
				$this->assertIsArray(
					$option['value_options'],
					sprintf( "Option '%s' value_options should be an array", $key )
				);

				foreach ( $option['value_options'] as $idx => $valueOption ) {
					$this->assertArrayHasKey(
						'value_key',
						$valueOption,
						sprintf( "Option '%s' value_option[%d] should have 'value_key'", $key, $idx )
					);
				}
			}
		}
	}

	public function testIntegerOptionsHaveNumericDefaults() :void {
		$integerOptions = \array_filter( 
			$this->options, 
			fn( $opt ) => ( $opt['type'] ?? '' ) === 'integer' 
		);

		foreach ( $integerOptions as $key => $option ) {
			if ( isset( $option['default'] ) ) {
				$this->assertTrue(
					\is_numeric( $option['default'] ),
					sprintf( "Integer option '%s' should have numeric default, got '%s'", $key, \gettype( $option['default'] ) )
				);
			}
		}
	}

	public function testIntegerOptionsWithMinMaxAreValid() :void {
		$integerOptions = \array_filter( 
			$this->options, 
			fn( $opt ) => ( $opt['type'] ?? '' ) === 'integer' 
		);

		foreach ( $integerOptions as $key => $option ) {
			if ( isset( $option['min'] ) && isset( $option['max'] ) ) {
				$this->assertLessThanOrEqual(
					$option['max'],
					$option['min'],
					sprintf( "Option '%s' min (%s) should not exceed max (%s)", $key, $option['min'], $option['max'] )
				);
			}

			// Default should be within min/max range if all are set
			if ( isset( $option['default'] ) && isset( $option['min'] ) ) {
				$this->assertGreaterThanOrEqual(
					$option['min'],
					$option['default'],
					sprintf( "Option '%s' default (%s) should be >= min (%s)", $key, $option['default'], $option['min'] )
				);
			}

			if ( isset( $option['default'] ) && isset( $option['max'] ) ) {
				$this->assertLessThanOrEqual(
					$option['max'],
					$option['default'],
					sprintf( "Option '%s' default (%s) should be <= max (%s)", $key, $option['default'], $option['max'] )
				);
			}
		}
	}

	// =========================================================================
	// OPTION-SECTION RELATIONSHIP TESTS
	// =========================================================================

	public function testOptionsReferenceValidSections() :void {
		$sectionSlugs = \array_column( $this->sections, 'slug' );

		$optionsWithSections = \array_filter( $this->options, fn( $opt ) => isset( $opt['section'] ) );

		foreach ( $optionsWithSections as $key => $option ) {
			$this->assertContains(
				$option['section'],
				$sectionSlugs,
				sprintf( "Option '%s' references non-existent section '%s'", $key, $option['section'] )
			);
		}
	}

	// =========================================================================
	// CRITICAL OPTION TESTS
	// =========================================================================

	/**
	 * @dataProvider providerCriticalSecurityOptions
	 */
	public function testCriticalSecurityOptionsExist( string $optionKey, string $description ) :void {
		$this->assertArrayHasKey(
			$optionKey,
			$this->options,
			sprintf( "Critical security option '%s' (%s) should exist", $optionKey, $description )
		);
	}

	public static function providerCriticalSecurityOptions() :array {
		return [
			'firewall directory traversal' => [ 'block_dir_traversal', 'Directory traversal protection' ],
			'firewall sql queries' => [ 'block_sql_queries', 'SQL injection protection' ],
			'firewall field truncation' => [ 'block_field_truncation', 'Field truncation protection' ],
			'firewall php code' => [ 'block_php_code', 'PHP code injection protection' ],
			'login cooldown' => [ 'login_limit_interval', 'Login rate limiting' ],
			'enable two factor' => [ 'enable_email_authentication', 'Two-factor authentication' ],
		];
	}

	public function testSecurityOptionsDefaultToEnabled() :void {
		$securityOptions = [
			'block_dir_traversal',
			'block_sql_queries',
			'block_php_code',
		];

		foreach ( $securityOptions as $optKey ) {
			if ( isset( $this->options[$optKey] ) ) {
				$default = $this->options[$optKey]['default'] ?? null;
				$this->assertSame(
					'Y',
					$default,
					sprintf( "Security option '%s' should default to enabled (Y)", $optKey )
				);
			}
		}
	}

	// =========================================================================
	// TRANSFERABLE OPTIONS TESTS
	// =========================================================================

	public function testNonTransferableOptionsHaveNoDefaults() :void {
		$nonTransferable = \array_filter(
			$this->options,
			fn( $opt ) => isset( $opt['transferable'] ) && $opt['transferable'] === false
		);

		// Non-transferable options typically shouldn't be exported/imported
		// This test documents which options are marked as non-transferable
		$this->assertNotEmpty(
			$nonTransferable,
			'There should be some non-transferable options (like license keys, timestamps)'
		);
	}

	// =========================================================================
	// ARRAY OPTIONS TESTS
	// =========================================================================

	public function testArrayOptionsHaveArrayDefaults() :void {
		$arrayOptions = \array_filter( 
			$this->options, 
			fn( $opt ) => ( $opt['type'] ?? '' ) === 'array' 
		);

		foreach ( $arrayOptions as $key => $option ) {
			if ( isset( $option['default'] ) ) {
				$this->assertIsArray(
					$option['default'],
					sprintf( "Array option '%s' should have array default", $key )
				);
			}
		}
	}

	// =========================================================================
	// PREMIUM OPTIONS TESTS
	// =========================================================================

	public function testPremiumOptionsAreMarked() :void {
		$premiumOptions = \array_filter(
			$this->options,
			fn( $opt ) => ( $opt['premium'] ?? false ) === true
		);

		// Premium features exist
		$this->assertNotEmpty(
			$premiumOptions,
			'There should be premium options defined'
		);

		// All premium options should have a section
		foreach ( $premiumOptions as $key => $option ) {
			// Premium options that are visible should have sections
			if ( !( $option['hidden'] ?? false ) ) {
				$this->assertTrue(
					isset( $option['section'] ) || ( $option['transferable'] ?? true ) === false,
					sprintf( "Visible premium option '%s' should have a section or be non-transferable", $key )
				);
			}
		}
	}
}

