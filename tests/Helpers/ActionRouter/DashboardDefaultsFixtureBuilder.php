<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	AjaxBatchRequests,
	DashboardLiveMonitorSetState,
	ModuleOptionsSave
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Traffic\TrafficLiveLogs;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\{
	DashboardLiveMonitorTicker,
	WpDashboardSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	PageConfigureLanding,
	PageDashboardOverview,
	PageOperatorModeLanding
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\DashboardLiveMonitorPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * @phpstan-import-type RawOptionStoreState from RawOptionStoreSnapshot
 * @phpstan-type FixtureState array{
 *   option_store_snapshot:array<string,RawOptionStoreState>,
 *   selected_options_snapshot:array<string,mixed>,
 *   live_monitor_flags_snapshot:array<string,mixed>,
 *   user_id:int
 * }
 * @phpstan-type OptionContract array{
 *   key:string,
 *   type:string,
 *   default:mixed,
 *   save:mixed,
 *   expected:mixed,
 *   control_id:string
 * }
 */
class DashboardDefaultsFixtureBuilder {

	private const CONFIGURE_ROW_KEY = 'plugin_general';

	private const CONFIGURE_ZONE_KEY = 'general';

	private const OPTION_CASES = [
		'display_plugin_badge' => [
			'type'     => 'select',
			'default'  => 'disabled',
			'save'     => 'light',
			'expected' => 'light',
		],
		'visitor_address_source' => [
			'type'     => 'select',
			'default'  => 'AUTO_DETECT_IP',
			'save'     => 'REMOTE_ADDR',
			'expected' => 'REMOTE_ADDR',
		],
		'autoupdate_plugin_self' => [
			'type'     => 'select',
			'default'  => 'auto',
			'save'     => 'disabled',
			'expected' => 'disabled',
		],
		'enable_tracking' => [
			'type'     => 'checkbox',
			'default'  => 'N',
			'save'     => 'Y',
			'expected' => 'Y',
		],
		'enable_upgrade_admin_notice' => [
			'type'     => 'checkbox',
			'default'  => 'Y',
			'save'     => 'N',
			'expected' => 'N',
		],
		'language_override' => [
			'type'     => 'text',
			'default'  => '',
			'save'     => 'EN',
			'expected' => 'en',
		],
	];

	private const DEFAULT_SECTION_REMAINDER = [
		'enable_mu',
		'delete_on_deactivate',
		'preferred_temp_dir',
		'enable_beta',
	];

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed() :array {
		RuntimeTestState::loginAsSecurityAdmin();
		$this->assertConfiguredOptionCorpus();

		$state = $this->newFixtureState();

		try {
			$this->applyRepresentativeMutations();
			( new DashboardLiveMonitorPreference() )->setCollapsed( true );

			return [
				'contract' => \array_merge( $this->baseContract(), [
					'original_options' => $state[ 'selected_options_snapshot' ],
					'mutated_options'  => $this->currentOptions(),
				] ),
				'state'    => $state,
			];
		}
		catch ( \Throwable $throwable ) {
			$this->cleanup( $state );
			throw $throwable;
		}
	}

	/**
	 * @param array<string,mixed> $state
	 * @return array<string,mixed>
	 */
	public function inspect( array $state = [] ) :array {
		RuntimeTestState::loginAsSecurityAdmin();
		$state = $this->normalizePersistedState( $state );

		return \array_merge( $this->baseContract(), [
			'fixture_state_present' => $state !== $this->emptyFixtureState(),
			'current_options'       => $this->currentOptions(),
			'original_options'      => $state[ 'selected_options_snapshot' ],
			'live_monitor'          => [
				'is_collapsed' => ( new DashboardLiveMonitorPreference() )->isCollapsed(),
			],
		] );
	}

	/**
	 * @param array<string,mixed> $state
	 * @return array<string,mixed>
	 */
	public function resetDefaults( array $state ) :array {
		RuntimeTestState::loginAsSecurityAdmin();
		$state = $this->normalizePersistedState( $state );
		if ( $state === $this->emptyFixtureState() ) {
			throw new \RuntimeException( 'Dashboard/defaults fixture must be seeded before reset-defaults.' );
		}

		$this->applyRepresentativeMutations();
		$beforeReset = $this->currentOptions();
		RuntimeTestState::controller()->opts->resetToDefaults();
		RuntimeTestState::resetOptionsRuntimeCache();
		$afterReset = $this->currentOptions();

		return \array_merge( $this->baseContract(), [
			'before_reset_options' => $beforeReset,
			'after_reset_options'  => $afterReset,
			'defaults'             => $this->defaultOptions(),
		] );
	}

	/**
	 * @param array<string,mixed> $state
	 */
	public function cleanup( array $state ) :void {
		$state = $this->normalizePersistedState( $state );
		if ( $state === $this->emptyFixtureState() ) {
			return;
		}

		RuntimeTestState::loginAsSecurityAdmin();
		$this->rawOptionStores()->restore( $state[ 'option_store_snapshot' ], 'Dashboard/defaults fixture' );
		$this->restoreLiveMonitorFlags( $state );
		RuntimeTestState::resetOptionsRuntimeCache();
	}

	/**
	 * @return FixtureState
	 */
	private function newFixtureState() :array {
		return [
			'option_store_snapshot'       => $this->rawOptionStores()->snapshot(),
			'selected_options_snapshot'  => $this->currentOptions(),
			'live_monitor_flags_snapshot' => $this->currentUserFlags(),
			'user_id'                     => \get_current_user_id(),
		];
	}

	/**
	 * @return FixtureState
	 */
	private function emptyFixtureState() :array {
		return [
			'option_store_snapshot'       => [],
			'selected_options_snapshot'  => [],
			'live_monitor_flags_snapshot' => [],
			'user_id'                     => 0,
		];
	}

	/**
	 * @param array<string,mixed> $state
	 * @return FixtureState
	 */
	private function normalizePersistedState( array $state ) :array {
		$rawStores = $state === []
			? []
			: $this->rawOptionStores()->normalize(
				\is_array( $state[ 'option_store_snapshot' ] ?? null ) ? $state[ 'option_store_snapshot' ] : [],
				'Dashboard/defaults fixture'
			);

		$selectedOptions = [];
		foreach ( \is_array( $state[ 'selected_options_snapshot' ] ?? null ) ? $state[ 'selected_options_snapshot' ] : [] as $key => $value ) {
			if ( \is_string( $key ) && \array_key_exists( $key, self::OPTION_CASES ) ) {
				$selectedOptions[ $key ] = $value;
			}
		}

		$flags = \is_array( $state[ 'live_monitor_flags_snapshot' ] ?? null )
			? $state[ 'live_monitor_flags_snapshot' ]
			: [];

		return [
			'option_store_snapshot'       => $rawStores,
			'selected_options_snapshot'  => $selectedOptions,
			'live_monitor_flags_snapshot' => $flags,
			'user_id'                     => (int)( $state[ 'user_id' ] ?? 0 ),
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function baseContract() :array {
		return [
			'routes'                    => [
				'dashboard'    => [
					PluginNavs::FIELD_NAV    => PluginNavs::NAV_DASHBOARD,
					PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_DASHBOARD_OVERVIEW,
				],
				'configure'    => [
					PluginNavs::FIELD_NAV    => PluginNavs::NAV_ZONES,
					PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_ZONES_OVERVIEW,
				],
				'wp_dashboard' => [
					'path' => '/wp-admin/index.php',
				],
			],
			'configure_focus'           => [
				'zone_key'    => self::CONFIGURE_ZONE_KEY,
				'row_key'     => self::CONFIGURE_ROW_KEY,
				'config_item' => 'display_plugin_badge',
			],
			'action_slugs'              => [
				'module_options_save'          => ModuleOptionsSave::SLUG,
				'dashboard_live_monitor_state' => DashboardLiveMonitorSetState::SLUG,
				'ajax_batch_requests'          => AjaxBatchRequests::SLUG,
			],
			'render_slugs'              => [
				'dashboard_page'        => PageDashboardOverview::SLUG,
				'operator_mode_landing' => PageOperatorModeLanding::SLUG,
				'configure_page'        => PageConfigureLanding::SLUG,
				'options_form'          => OptionsFormFor::SLUG,
				'live_monitor_ticker'   => DashboardLiveMonitorTicker::SLUG,
				'traffic_live_logs'     => TrafficLiveLogs::SLUG,
				'dashboard_widget'      => WpDashboardSummary::SLUG,
			],
			'operator_modes'            => [
				PluginNavs::MODE_ACTIONS,
				PluginNavs::MODE_INVESTIGATE,
				PluginNavs::MODE_CONFIGURE,
				PluginNavs::MODE_REPORTS,
			],
			'selectors'                 => [
				'dashboard_live_monitor' => '[data-dashboard-live-monitor="1"]',
				'status_region'          => '[data-shield-status-region="1"]',
				'configure_landing'      => '[data-configure-landing="1"]',
				'dashboard_widget'       => '#ShieldDashboardWidget',
			],
			'options'                   => $this->optionContracts(),
			'option_keys'               => \array_keys( self::OPTION_CASES ),
			'default_section_remainder' => self::DEFAULT_SECTION_REMAINDER,
			'manual_remainder'          => 'Uncovered option-family remainder for SHI-269: integer, multiple-select, array, email/password/sensitive, premium, hidden, feature-module options, environment-dependent paths/IP/webserver settings, and external side effects remain manual/external or later-slice scope.',
		];
	}

	/**
	 * @return array<string,OptionContract>
	 */
	private function optionContracts() :array {
		$contracts = [];
		foreach ( self::OPTION_CASES as $key => $case ) {
			$contracts[ $key ] = [
				'key'        => $key,
				'type'       => $case[ 'type' ],
				'default'    => $case[ 'default' ],
				'save'       => $case[ 'save' ],
				'expected'   => $case[ 'expected' ],
				'control_id' => 'Opt-'.$key,
			];
		}
		return $contracts;
	}

	private function assertConfiguredOptionCorpus() :void {
		$opts = RuntimeTestState::controller()->opts;
		foreach ( self::OPTION_CASES as $key => $case ) {
			if ( $opts->optType( $key ) !== $case[ 'type' ] ) {
				throw new \RuntimeException( \sprintf( 'Unexpected option type for %s.', $key ) );
			}
			if ( $opts->optDefault( $key ) !== $case[ 'default' ] ) {
				throw new \RuntimeException( \sprintf( 'Unexpected option default for %s.', $key ) );
			}
		}
	}

	private function applyRepresentativeMutations() :void {
		$opts = RuntimeTestState::controller()->opts;
		foreach ( self::OPTION_CASES as $key => $case ) {
			$opts->optSet( $key, $case[ 'save' ] );
		}
		$opts->store();
		RuntimeTestState::resetOptionsRuntimeCache();
	}

	/**
	 * @return array<string,mixed>
	 */
	private function currentOptions() :array {
		$values = [];
		$opts = RuntimeTestState::controller()->opts;
		foreach ( \array_keys( self::OPTION_CASES ) as $key ) {
			$values[ $key ] = $opts->optGet( $key );
		}
		return $values;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function defaultOptions() :array {
		return \array_map(
			static fn( array $case ) => $case[ 'default' ],
			self::OPTION_CASES
		);
	}

	private function rawOptionStores() :RawOptionStoreSnapshot {
		return new RawOptionStoreSnapshot();
	}

	/**
	 * @return array<string,mixed>
	 */
	private function currentUserFlags() :array {
		$meta = RuntimeTestState::controller()->user_metas->current();
		return !empty( $meta ) && \is_array( $meta->flags ) ? $meta->flags : [];
	}

	/**
	 * @phpstan-param FixtureState $state
	 */
	private function restoreLiveMonitorFlags( array $state ) :void {
		$user = \get_user_by( 'id', $state[ 'user_id' ] );
		$meta = $user instanceof \WP_User
			? RuntimeTestState::controller()->user_metas->for( $user )
			: RuntimeTestState::controller()->user_metas->current();
		if ( !empty( $meta ) ) {
			$meta->flags = $state[ 'live_monitor_flags_snapshot' ];
		}
	}
}
