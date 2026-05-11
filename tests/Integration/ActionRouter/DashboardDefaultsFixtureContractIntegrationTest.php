<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\DashboardDefaultsFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class DashboardDefaultsFixtureContractIntegrationTest extends ShieldIntegrationTestCase {

	public function test_fixture_contract_owns_routes_options_and_cleanup_restores_raw_option_stores() :void {
		$this->ensureDefaultAdminUser();
		$originalStores = $this->snapshotRawOptionStores();
		$this->primeRawOptionStoreRows();
		$beforeStores = $this->snapshotRawOptionStores();

		try {
			$builder = new DashboardDefaultsFixtureBuilder();
			$result = $builder->seed();

			try {
				$contract = $result[ 'contract' ];

				$this->assertSame( [
					'display_plugin_badge',
					'visitor_address_source',
					'autoupdate_plugin_self',
					'enable_tracking',
					'enable_upgrade_admin_notice',
					'language_override',
				], $contract[ 'option_keys' ] );
				$this->assertSame( 'dashboard', $contract[ 'routes' ][ 'dashboard' ][ 'nav' ] );
				$this->assertSame( 'zones', $contract[ 'routes' ][ 'configure' ][ 'nav' ] );
				$this->assertSame( '/wp-admin/index.php', $contract[ 'routes' ][ 'wp_dashboard' ][ 'path' ] );
				$this->assertSame( [
					'zone_key'    => 'general',
					'row_key'     => 'plugin_general',
					'config_item' => 'display_plugin_badge',
				], $contract[ 'configure_focus' ] );
				$this->assertSame( 'mod_options_save', $contract[ 'action_slugs' ][ 'module_options_save' ] );
				$this->assertSame( 'dashboard_live_monitor_set_state', $contract[ 'action_slugs' ][ 'dashboard_live_monitor_state' ] );
				$this->assertSame( 'ajax_batch_requests', $contract[ 'action_slugs' ][ 'ajax_batch_requests' ] );
				$this->assertSame( 'render_options_form_for', $contract[ 'render_slugs' ][ 'options_form' ] );
				$this->assertSame( 'render_dashboard_widget', $contract[ 'render_slugs' ][ 'dashboard_widget' ] );

				$this->assertSame( 'disabled', $contract[ 'options' ][ 'display_plugin_badge' ][ 'default' ] );
				$this->assertSame( 'light', $contract[ 'options' ][ 'display_plugin_badge' ][ 'expected' ] );
				$this->assertSame( 'AUTO_DETECT_IP', $contract[ 'options' ][ 'visitor_address_source' ][ 'default' ] );
				$this->assertSame( 'REMOTE_ADDR', $contract[ 'options' ][ 'visitor_address_source' ][ 'expected' ] );
				$this->assertSame( 'en', $contract[ 'options' ][ 'language_override' ][ 'expected' ] );
				$this->assertSame( 'Opt-language_override', $contract[ 'options' ][ 'language_override' ][ 'control_id' ] );

				$this->assertSame( [
					'display_plugin_badge'        => 'light',
					'visitor_address_source'      => 'REMOTE_ADDR',
					'autoupdate_plugin_self'      => 'disabled',
					'enable_tracking'             => 'Y',
					'enable_upgrade_admin_notice' => 'N',
					'language_override'           => 'en',
				], $contract[ 'mutated_options' ] );
				$this->assertNotSame( $contract[ 'original_options' ], $contract[ 'mutated_options' ] );
			}
			finally {
				$builder->cleanup( $result[ 'state' ] );
			}

			$this->assertSame( $beforeStores, $this->snapshotRawOptionStores() );
			$this->assertSame(
				$result[ 'contract' ][ 'original_options' ],
				RuntimeTestState::snapshotOptions( $result[ 'contract' ][ 'option_keys' ] )
			);
		}
		finally {
			$this->restoreRawOptionStores( $originalStores );
		}
	}

	public function test_reset_defaults_uses_defaults_owner_and_cleanup_restores_raw_option_stores() :void {
		$this->ensureDefaultAdminUser();
		$originalStores = $this->snapshotRawOptionStores();
		$this->primeRawOptionStoreRows();
		$beforeStores = $this->snapshotRawOptionStores();

		try {
			$builder = new DashboardDefaultsFixtureBuilder();
			$result = $builder->seed();

			try {
				$reset = $builder->resetDefaults( $result[ 'state' ] );

				$this->assertSame( $result[ 'contract' ][ 'mutated_options' ], $reset[ 'before_reset_options' ] );
				$this->assertSame( [
					'display_plugin_badge'        => 'disabled',
					'visitor_address_source'      => 'AUTO_DETECT_IP',
					'autoupdate_plugin_self'      => 'auto',
					'enable_tracking'             => 'N',
					'enable_upgrade_admin_notice' => 'Y',
					'language_override'           => '',
				], $reset[ 'defaults' ] );
				$this->assertSame( $reset[ 'defaults' ], $reset[ 'after_reset_options' ] );
				$this->assertSame( $reset[ 'defaults' ], RuntimeTestState::snapshotOptions( $reset[ 'option_keys' ] ) );
			}
			finally {
				$builder->cleanup( $result[ 'state' ] );
			}

			$this->assertSame( $beforeStores, $this->snapshotRawOptionStores() );
		}
		finally {
			$this->restoreRawOptionStores( $originalStores );
		}
	}

	private function ensureDefaultAdminUser() :void {
		if ( \get_user_by( 'login', 'admin' ) instanceof \WP_User ) {
			return;
		}

		self::factory()->user->create( [
			'user_login' => 'admin',
			'user_pass'  => 'password',
			'role'       => 'administrator',
		] );
	}

	/**
	 * @return array<string,array{option_name:string,exists:bool,row:array{option_id:int,option_name:string,option_value:string,autoload:string}|null}>
	 */
	private function snapshotRawOptionStores() :array {
		$this->requireController();
		$snapshot = [];
		foreach ( $this->optionStoreNames() as $key => $optionName ) {
			$row = $this->fetchRawOptionRow( $optionName );
			$snapshot[ $key ] = [
				'option_name' => $optionName,
				'exists'      => $row !== null,
				'row'         => $row,
			];
		}
		return $snapshot;
	}

	private function primeRawOptionStoreRows() :void {
		foreach ( $this->optionStoreNames() as $key => $optionName ) {
			\delete_option( $optionName );
			\add_option(
				$optionName,
				[
					'version'       => self::con()->cfg->version(),
					'values'        => [
						'free' => [
							'display_plugin_badge' => 'disabled',
						],
						'pro'  => [],
					],
					'xfer_excluded' => [ 'fixture-prime-'.$key ],
				],
				'',
				$key !== 'opts_free'
			);
		}
		RuntimeTestState::resetOptionsRuntimeCache();
	}

	/**
	 * @return array{opts_all:string,opts_free:string,opts_pro:string}
	 */
	private function optionStoreNames() :array {
		$this->requireController();
		return [
			'opts_all'  => self::con()->prefix( 'opts_all', '_' ),
			'opts_free' => self::con()->prefix( 'opts_free', '_' ),
			'opts_pro'  => self::con()->prefix( 'opts_pro', '_' ),
		];
	}

	/**
	 * @return array{option_id:int,option_name:string,option_value:string,autoload:string}|null
	 */
	private function fetchRawOptionRow( string $optionName ) :?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$optionName
			),
			\ARRAY_A
		);

		return \is_array( $row ) ? [
			'option_id'    => (int)$row[ 'option_id' ],
			'option_name'  => (string)$row[ 'option_name' ],
			'option_value' => (string)$row[ 'option_value' ],
			'autoload'     => (string)$row[ 'autoload' ],
		] : null;
	}

	/**
	 * @param array<string,array{option_name:string,exists:bool,row:array{option_id:int,option_name:string,option_value:string,autoload:string}|null}> $snapshot
	 */
	private function restoreRawOptionStores( array $snapshot ) :void {
		global $wpdb;

		foreach ( $snapshot as $store ) {
			$optionName = (string)$store[ 'option_name' ];
			if ( !(bool)$store[ 'exists' ] ) {
				$wpdb->delete( $wpdb->options, [ 'option_name' => $optionName ], [ '%s' ] );
				$this->clearRawOptionCaches( $optionName );
				continue;
			}

			$row = $store[ 'row' ];
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name = %s OR option_id = %d",
					$row[ 'option_name' ],
					$row[ 'option_id' ]
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->options} (option_id, option_name, option_value, autoload) VALUES (%d, %s, %s, %s)",
					$row[ 'option_id' ],
					$row[ 'option_name' ],
					$row[ 'option_value' ],
					$row[ 'autoload' ]
				)
			);
			$this->clearRawOptionCaches( $row[ 'option_name' ] );
		}
		RuntimeTestState::resetOptionsRuntimeCache();
	}

	private function clearRawOptionCaches( string $optionName ) :void {
		\wp_cache_delete( $optionName, 'options' );
		\wp_cache_delete( 'alloptions', 'options' );
		\wp_cache_delete( 'notoptions', 'options' );
	}
}
