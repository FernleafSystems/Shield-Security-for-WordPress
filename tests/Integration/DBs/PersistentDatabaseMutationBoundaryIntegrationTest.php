<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/** @group database-transaction-exception */
class PersistentDatabaseMutationBoundaryIntegrationTest extends ShieldIntegrationTestCase {

	private static ?string $fixtureSuffix = null;

	public function test_successful_exercise_runs_exact_restoration() :void {
		$table = $this->tableName( 'success' );
		$restored = false;

		$this->runWithPersistentDatabaseMutation(
			function () use ( $table ) :void {
				$this->requiredQuery( "CREATE TABLE `{$table}` (`id` bigint unsigned NOT NULL PRIMARY KEY) ENGINE=InnoDB" );
				$this->requiredQuery( "INSERT INTO `{$table}` (`id`) VALUES (1)" );
			},
			function () use ( $table, &$restored ) :void {
				$this->requiredQuery( "DROP TABLE IF EXISTS `{$table}`" );
				$restored = true;
			}
		);

		$this->assertTrue( $restored );
		$this->assertTableDoesNotExist( $table );
	}

	public function test_initial_and_pre_restoration_rollbacks_discard_transactional_dml() :void {
		$beforeKey = $this->fixtureKey( 'before_initial_rollback' );
		$exerciseKey = $this->fixtureKey( 'before_restoration_rollback' );
		\update_option( $beforeKey, 'must roll back', false );

		$this->runWithPersistentDatabaseMutation(
			function () use ( $beforeKey, $exerciseKey ) :void {
				$this->assertNull( $this->storedOptionValue( $beforeKey ) );
				\update_option( $exerciseKey, 'must also roll back', false );
				$this->assertSame( 'must also roll back', $this->storedOptionValue( $exerciseKey ) );
			},
			function () use ( $exerciseKey ) :void {
				$this->assertNull( $this->storedOptionValue( $exerciseKey ) );
			}
		);
	}

	public function test_exercise_failure_still_runs_restoration() :void {
		$table = $this->tableName( 'exercise_failure' );

		try {
			$this->runWithPersistentDatabaseMutation(
				function () use ( $table ) :void {
					$this->requiredQuery( "CREATE TABLE `{$table}` (`id` bigint unsigned NOT NULL PRIMARY KEY) ENGINE=InnoDB" );
					throw new \RuntimeException( 'exercise failure marker' );
				},
				function () use ( $table ) :void {
					$this->requiredQuery( "DROP TABLE IF EXISTS `{$table}`" );
				}
			);
			$this->fail( 'The exercise failure must be rethrown after restoration.' );
		}
		catch ( \RuntimeException $e ) {
			$this->assertSame( 'exercise failure marker', $e->getMessage() );
		}

		$this->assertTableDoesNotExist( $table );
	}

	public function test_restoration_failure_is_visible_after_transaction_reentry() :void {
		$table = $this->tableName( 'restoration_failure' );

		try {
			$this->runWithPersistentDatabaseMutation(
				function () use ( $table ) :void {
					$this->requiredQuery( "CREATE TABLE `{$table}` (`id` bigint unsigned NOT NULL PRIMARY KEY) ENGINE=InnoDB" );
				},
				function () use ( $table ) :void {
					$this->requiredQuery( "DROP TABLE IF EXISTS `{$table}`" );
					throw new \RuntimeException( 'restoration failure marker' );
				}
			);
			$this->fail( 'The restoration failure must be surfaced.' );
		}
		catch ( \RuntimeException $e ) {
			$this->assertSame( 'restoration failure marker', $e->getMessage() );
		}

		$this->assertTableDoesNotExist( $table );
	}

	public function test_exercise_and_restoration_failures_are_both_visible() :void {
		try {
			$this->runWithPersistentDatabaseMutation(
				static function () :void {
					throw new \RuntimeException( 'primary exercise marker' );
				},
				static function () :void {
					throw new \RuntimeException( 'secondary restoration marker' );
				}
			);
			$this->fail( 'Both failures must be surfaced.' );
		}
		catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'primary exercise marker', $e->getMessage() );
			$this->assertStringContainsString( 'secondary restoration marker', $e->getMessage() );
			$this->assertInstanceOf( \RuntimeException::class, $e->getPrevious() );
			$this->assertSame( 'primary exercise marker', $e->getPrevious()->getMessage() );
		}
	}

	public function test_exact_query_hook_state_is_restored() :void {
		global $wp_filter;
		$before = $wp_filter[ 'query' ]->callbacks;
		$createHook = [ $this, '_create_temporary_tables' ];
		$dropHook = [ $this, '_drop_temporary_tables' ];

		$this->runWithPersistentDatabaseMutation(
			function () use ( $createHook, $dropHook ) :void {
				$this->assertFalse( \has_filter( 'query', $createHook ) );
				$this->assertFalse( \has_filter( 'query', $dropHook ) );
			},
			static function () :void {
			}
		);

		$this->assertSame( $before, $wp_filter[ 'query' ]->callbacks );
	}

	public function test_restoration_is_committed_before_a_new_transaction_starts() :void {
		$key = $this->fixtureKey( 'committed_restoration' );

		$this->runWithPersistentDatabaseMutation(
			static function () :void {
			},
			function () use ( $key ) :void {
				global $wpdb;
				$this->requiredQuery( $wpdb->prepare(
					"INSERT INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'no')",
					$key,
					'committed restoration'
				) );
			}
		);

		$this->runWithPersistentDatabaseMutation(
			function () use ( $key ) :void {
				$this->assertSame( 'committed restoration', $this->storedOptionValue( $key ) );
			},
			function () use ( $key ) :void {
				global $wpdb;
				$this->requiredQuery( $wpdb->prepare(
					"DELETE FROM `{$wpdb->options}` WHERE `option_name`=%s",
					$key
				) );
			}
		);
		$this->assertNull( $this->storedOptionValue( $key ) );
	}

	public function test_producer_writes_inside_the_reopened_transaction() :string {
		$key = $this->fixtureKey( 'reopened_transaction' );
		$this->runWithPersistentDatabaseMutation(
			static function () :void {
			},
			static function () :void {
			}
		);

		\update_option( $key, 'transactional value', false );
		$this->assertSame( 'transactional value', \get_option( $key ) );
		return $key;
	}

	/** @depends test_producer_writes_inside_the_reopened_transaction */
	public function test_parent_teardown_rolls_back_the_reopened_transaction( string $key ) :void {
		global $wpdb;
		$this->assertNull( $wpdb->get_var( $wpdb->prepare(
			"SELECT `option_value` FROM `{$wpdb->options}` WHERE `option_name`=%s",
			$key
		) ) );
	}

	private function requiredQuery( string $sql ) :void {
		global $wpdb;
		$wpdb->last_error = '';
		$result = $wpdb->query( $sql );
		$this->assertNotFalse( $result, 'Database statement failed: '.$sql.'; '.$wpdb->last_error );
		$this->assertSame( '', $wpdb->last_error, 'Database statement failed: '.$sql );
	}

	private function assertTableDoesNotExist( string $table ) :void {
		global $wpdb;
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
	}

	private function storedOptionValue( string $key ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare(
			"SELECT `option_value` FROM `{$wpdb->options}` WHERE `option_name`=%s",
			$key
		) );
	}

	private function tableName( string $type ) :string {
		global $wpdb;
		return $wpdb->prefix.$this->fixtureKey( $type );
	}

	private function fixtureKey( string $type ) :string {
		if ( self::$fixtureSuffix === null ) {
			self::$fixtureSuffix = \substr( \sha1( __CLASS__.\microtime( true ).\getmypid() ), 0, 10 );
		}
		return 'shield_tx_boundary_'.$type.'_'.self::$fixtureSuffix;
	}
}
