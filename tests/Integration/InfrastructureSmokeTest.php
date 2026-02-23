<?php declare( strict_types=1 );

/**
 * Infrastructure Smoke Tests
 *
 * These tests validate assumptions about the WordPress test framework, Shield plugin
 * environment, and PHP/MySQL capabilities. They run FIRST (before all other integration
 * tests) to provide immediate, actionable failure messages when any infrastructure
 * component changes.
 *
 * Run independently: vendor/bin/phpunit -c phpunit-integration.xml --group smoke
 *
 * @group smoke
 */
class InfrastructureSmokeTest extends \WP_UnitTestCase {

	/**
	 * @group smoke
	 */
	public function test_wp_unit_test_case_class_hierarchy() :void {
		$this->assertTrue(
			\class_exists( 'WP_UnitTestCase' ),
			'WP_UnitTestCase class must exist. Check that the WordPress test library is installed correctly.'
		);
		$this->assertTrue(
			\class_exists( 'WP_UnitTestCase_Base' ),
			'WP_UnitTestCase_Base class must exist. This class was introduced in WordPress 5.9. '
			.'Ensure the WordPress test library version matches WordPress 5.9+.'
		);
		$this->assertTrue(
			\is_subclass_of( 'WP_UnitTestCase', 'WP_UnitTestCase_Base' ),
			'WP_UnitTestCase must extend WP_UnitTestCase_Base. '
			.'The test framework class hierarchy has changed unexpectedly.'
		);
	}

	/**
	 * @group smoke
	 */
	public function test_wp_lifecycle_methods_are_snake_case() :void {
		$ref = new \ReflectionClass( 'WP_UnitTestCase' );
		$this->assertTrue(
			$ref->hasMethod( 'set_up' ),
			'WP_UnitTestCase must have set_up() method (snake_case). '
			.'This was introduced in WordPress 5.9. If missing, the WP test lib may be outdated.'
		);
		$this->assertTrue(
			$ref->hasMethod( 'tear_down' ),
			'WP_UnitTestCase must have tear_down() method (snake_case). '
			.'This was introduced in WordPress 5.9. If missing, the WP test lib may be outdated.'
		);
	}

	/**
	 * Validates that temporary table filter functions are instance methods on
	 * WP_UnitTestCase_Base, NOT global functions. This is the exact check that
	 * would have caught the bootstrap bug where global function callbacks were
	 * registered for these non-existent global functions.
	 *
	 * @group smoke
	 */
	public function test_wp_temporary_table_methods_are_instance_methods() :void {
		$ref = new \ReflectionClass( 'WP_UnitTestCase_Base' );

		$this->assertTrue(
			$ref->hasMethod( '_create_temporary_tables' ),
			'WP_UnitTestCase_Base must have _create_temporary_tables() as an instance method.'
		);
		$this->assertTrue(
			$ref->hasMethod( '_drop_temporary_tables' ),
			'WP_UnitTestCase_Base must have _drop_temporary_tables() as an instance method.'
		);
		$this->assertTrue(
			$ref->hasMethod( 'start_transaction' ),
			'WP_UnitTestCase_Base must have start_transaction() method.'
		);

		$this->assertFalse(
			\function_exists( '_create_temporary_tables' ),
			'_create_temporary_tables must NOT exist as a global function. '
			.'If it does, it may have been erroneously defined. In modern WordPress, '
			.'this is an instance method on WP_UnitTestCase_Base.'
		);
		$this->assertFalse(
			\function_exists( '_drop_temporary_tables' ),
			'_drop_temporary_tables must NOT exist as a global function. '
			.'If it does, it may have been erroneously defined. In modern WordPress, '
			.'this is an instance method on WP_UnitTestCase_Base.'
		);
	}

	/**
	 * @group smoke
	 */
	public function test_wp_test_helper_functions_available() :void {
		$this->assertTrue(
			\function_exists( 'tests_add_filter' ),
			'tests_add_filter() must exist. Ensure WordPress test functions.php is loaded in bootstrap.php.'
		);

		global $wpdb;
		$this->assertInstanceOf(
			\wpdb::class,
			$wpdb,
			'Global $wpdb must be a wpdb instance. WordPress may not have bootstrapped correctly.'
		);

		$result = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl' LIMIT 1" );
		$this->assertNotEmpty(
			$result,
			'The WordPress options table must be queryable. Database connection may be broken.'
		);
	}

	/**
	 * Inspects the global $wp_filter to ensure no string callback for
	 * '_create_temporary_tables' is registered on the 'query' hook.
	 * This directly catches the bootstrap bug.
	 *
	 * @group smoke
	 */
	public function test_no_stale_temporary_table_string_callbacks() :void {
		global $wp_filter;

		$hasQueryFilter = \is_array( $wp_filter ) && isset( $wp_filter['query'] );
		if ( !$hasQueryFilter ) {
			$this->assertFalse( $hasQueryFilter, 'No query filters are registered, so there are no stale callbacks to validate.' );
			return;
		}

		$hook = $wp_filter['query'];
		$callbacks = ( $hook instanceof \WP_Hook ) ? $hook->callbacks : (array) $hook;
		$this->assertIsArray( $callbacks, 'Query hook callbacks should be represented as an array for stale callback inspection.' );

		foreach ( $callbacks as $priority => $funcs ) {
			foreach ( $funcs as $key => $entry ) {
				$fn = $entry['function'] ?? null;
				if ( \is_string( $fn ) ) {
					$this->assertNotEquals(
						'_create_temporary_tables',
						$fn,
						"Found string callback '_create_temporary_tables' on 'query' filter (priority {$priority}). "
						.'This global function does not exist in modern WordPress — it is an instance method on '
						.'WP_UnitTestCase_Base. Remove the add_filter() call from bootstrap.php.'
					);
					$this->assertNotEquals(
						'_drop_temporary_tables',
						$fn,
						"Found string callback '_drop_temporary_tables' on 'query' filter (priority {$priority}). "
						.'This global function does not exist in modern WordPress — it is an instance method on '
						.'WP_UnitTestCase_Base. Remove the add_filter() call from bootstrap.php.'
					);
				}
			}
		}

	}

	/**
	 * @group smoke
	 */
	public function test_shield_plugin_loaded() :void {
		$this->assertTrue(
			\function_exists( 'shield_security_get_plugin' ),
			'shield_security_get_plugin() must exist. The Shield plugin did not load. '
			.'Check bootstrap.php plugin loading and the plugins_loaded hook.'
		);
		$this->assertTrue(
			\class_exists( 'ICWP_WPSF_Shield_Security' ),
			'ICWP_WPSF_Shield_Security class must exist. The Shield plugin main class was not loaded.'
		);
		$this->assertTrue(
			\class_exists( \FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller::class ),
			'Shield Controller class must exist. The Shield autoloader may not be working.'
		);

		$plugin = shield_security_get_plugin();
		$this->assertNotNull( $plugin, 'shield_security_get_plugin() must return a non-null value.' );
		$this->assertNotNull(
			$plugin->getController(),
			'Shield Controller must be accessible via getController().'
		);
	}

	/**
	 * @group smoke
	 */
	public function test_shield_db_infrastructure() :void {
		$dbConClass = \FernleafSystems\Wordpress\Plugin\Shield\Controller\Database\DbCon::class;
		$this->assertTrue( \class_exists( $dbConClass ), "DbCon class must exist at {$dbConClass}." );

		$ref = new \ReflectionClass( $dbConClass );
		$this->assertTrue(
			$ref->hasConstant( 'MAP' ),
			'DbCon::MAP constant must exist. It defines the database table handlers.'
		);

		$map = $ref->getConstant( 'MAP' );
		$this->assertIsArray( $map, 'DbCon::MAP must be an array.' );
		$this->assertNotEmpty( $map, 'DbCon::MAP must not be empty.' );

		foreach ( $map as $slug => $entry ) {
			$this->assertArrayHasKey(
				'slug',
				$entry,
				"DbCon::MAP entry '{$slug}' must have a 'slug' key."
			);
			$this->assertArrayHasKey(
				'handler_class',
				$entry,
				"DbCon::MAP entry '{$slug}' must have a 'handler_class' key."
			);
		}

		$con = shield_security_get_plugin()->getController();
		$this->assertTrue(
			isset( $con->db_con ),
			'Controller db_con property must be accessible.'
		);
		$this->assertTrue(
			\method_exists( $con->db_con, 'loadAll' ),
			'DbCon must have a loadAll() method.'
		);
		$this->assertTrue(
			\method_exists( $con->db_con, 'reset' ),
			'DbCon must have a reset() method.'
		);
	}

	/**
	 * @group smoke
	 */
	public function test_shield_static_cache_properties_exist() :void {
		$checks = [
			[
				'class' => \FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus::class,
				'props' => [ 'cache', 'ranges', 'bypass' ],
			],
			[
				'class' => \FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords::class,
				'props' => [ 'ips' ],
			],
			[
				'class' => \FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors\ProcessConditions::class,
				'props' => [ 'ConditionsCache' ],
			],
			[
				'class' => \FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\FirewallPatternFoundInRequest::class,
				'props' => [ 'ParamsToAssess' ],
			],
			[
				'class' => \FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\ExtractSubConditions::class,
				'props' => [ 'ConditionDeps', 'AllConditions' ],
			],
		];

		foreach ( $checks as $check ) {
			$class = $check['class'];
			$this->assertTrue( \class_exists( $class ), "Class {$class} must exist." );
			$ref = new \ReflectionClass( $class );
			foreach ( $check['props'] as $prop ) {
				$this->assertTrue(
					$ref->hasProperty( $prop ),
					"Class {$class} must have static property \${$prop}. "
					.'If this property was renamed or removed, update ShieldIntegrationTestCase::resetIpCaches().'
				);
				$refProp = $ref->getProperty( $prop );
				$this->assertTrue(
					$refProp->isStatic(),
					"Property {$class}::\${$prop} must be static."
				);
			}
		}

		$cacheClass = \FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRulesCache::class;
		$this->assertTrue( \class_exists( $cacheClass ), "Class {$cacheClass} must exist." );
		$this->assertTrue(
			\method_exists( $cacheClass, 'ResetAll' ),
			"{$cacheClass}::ResetAll() must exist. "
			.'If renamed or removed, update ShieldIntegrationTestCase::resetIpCaches().'
		);
	}

	/**
	 * @group smoke
	 */
	public function test_php_reflection_capabilities() :void {
		$testClass = \FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords::class;
		$ref = new \ReflectionClass( $testClass );
		$prop = $ref->getProperty( 'ips' );
		$prop->setAccessible( true );

		$original = $prop->getValue( null );
		$prop->setValue( null, [ 'test' => 'value' ] );
		$modified = $prop->getValue( null );
		$prop->setValue( null, $original );

		$this->assertSame(
			[ 'test' => 'value' ],
			$modified,
			'ReflectionProperty::setAccessible() + setValue() must work on private static properties. '
			.'This is required for resetting caches between integration tests.'
		);
	}

	/**
	 * @group smoke
	 */
	public function test_mysql_required_features() :void {
		global $wpdb;

		$wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' );
		$this->assertEmpty(
			$wpdb->last_error,
			'SET FOREIGN_KEY_CHECKS must execute without error. Got: '.$wpdb->last_error
		);
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );

		$wpdb->query( 'START TRANSACTION' );
		$this->assertEmpty(
			$wpdb->last_error,
			'START TRANSACTION must execute without error. Got: '.$wpdb->last_error
		);
		$wpdb->query( 'ROLLBACK' );
		$this->assertEmpty(
			$wpdb->last_error,
			'ROLLBACK must execute without error. Got: '.$wpdb->last_error
		);

		$wpdb->query( 'SET autocommit=1' );
		$this->assertEmpty(
			$wpdb->last_error,
			'SET autocommit must execute without error. Got: '.$wpdb->last_error
		);
	}
}
