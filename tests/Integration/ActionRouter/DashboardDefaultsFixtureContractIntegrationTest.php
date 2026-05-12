<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\DashboardDefaultsFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\RawOptionStoreSnapshot;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * @phpstan-import-type RawOptionStoreState from RawOptionStoreSnapshot
 */
class DashboardDefaultsFixtureContractIntegrationTest extends ShieldIntegrationTestCase {

	public function test_fixture_contract_owns_routes_options_and_cleanup_restores_raw_option_stores() :void {
		$this->ensureDefaultAdminUser();
		$rawStores = new RawOptionStoreSnapshot();
		$originalStores = $rawStores->snapshot();
		$this->primeRawOptionStoreRows( $originalStores );
		$beforeStores = $rawStores->snapshot();

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

			$this->assertSame( $beforeStores, $rawStores->snapshot() );
			$this->assertSame(
				$result[ 'contract' ][ 'original_options' ],
				RuntimeTestState::snapshotOptions( $result[ 'contract' ][ 'option_keys' ] )
			);
		}
		finally {
			$rawStores->restore( $originalStores, 'Dashboard Defaults integration original stores' );
		}
	}

	public function test_reset_defaults_uses_defaults_owner_and_cleanup_restores_raw_option_stores() :void {
		$this->ensureDefaultAdminUser();
		$rawStores = new RawOptionStoreSnapshot();
		$originalStores = $rawStores->snapshot();
		$this->primeRawOptionStoreRows( $originalStores );
		$beforeStores = $rawStores->snapshot();

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

			$this->assertSame( $beforeStores, $rawStores->snapshot() );
		}
		finally {
			$rawStores->restore( $originalStores, 'Dashboard Defaults integration original stores' );
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
	 * @param array<string,RawOptionStoreState> $rawStores
	 */
	private function primeRawOptionStoreRows( array $rawStores ) :void {
		foreach ( $rawStores as $key => $store ) {
			$optionName = $store[ 'option_name' ];
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
}
