<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\TrafficLiveLog_SetEnabled;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * @phpstan-type FixtureState array{options_snapshot:array<string,mixed>}
 */
class LiveTrafficToggleFixtureBuilder {

	private const OPTION_KEYS = [
		'license_activated_at',
		'license_data',
		'license_deactivated_at',
		'enable_live_log',
		'live_log_started_at',
	];

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed() :array {
		$state = [
			'options_snapshot' => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
		];

		try {
			RuntimeTestState::applyPremiumCapabilities( [ 'traffic_live_log' ] );
			RuntimeTestState::controller()->opts
				->optSet( 'enable_live_log', 'N' )
				->optSet( 'live_log_started_at', 0 )
				->store();

			return [
				'contract' => [
					'action_slug' => TrafficLiveLog_SetEnabled::SLUG,
					'route'       => [
						'nav'     => PluginNavs::NAV_TRAFFIC,
						'nav_sub' => PluginNavs::SUBNAV_LIVE,
					],
					'selectors'   => [
						'toggle' => '[data-traffic-live-log-toggle]',
					],
				],
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
	 */
	public function inspect( array $state ) :array {
		$opts = RuntimeTestState::controller()->opts;

		return [
			'state' => [
				'enable_live_log'    => (string)$opts->optGet( 'enable_live_log' ),
				'live_log_started_at' => (int)$opts->optGet( 'live_log_started_at' ),
				'can_traffic_live_log' => RuntimeTestState::controller()->caps->canTrafficLiveLog(),
			],
		];
	}

	/**
	 * @param array<string,mixed> $state
	 */
	public function cleanup( array $state ) :void {
		RuntimeTestState::restoreOptions( $this->normalizePersistedState( $state )[ 'options_snapshot' ] );
	}

	/**
	 * @param array<string,mixed> $state
	 * @phpstan-return FixtureState
	 */
	private function normalizePersistedState( array $state ) :array {
		return [
			'options_snapshot' => \is_array( $state[ 'options_snapshot' ] ?? null ) ? $state[ 'options_snapshot' ] : [],
		];
	}
}
