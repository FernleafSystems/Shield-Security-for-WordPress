<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginIpDetect;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * @phpstan-type FixtureState array{options_snapshot:array<string,mixed>}
 * @phpstan-type FixtureContract array{
 *     routes:array{pages_admin:string},
 *     detected_ip:string,
 *     action_slug:string,
 *     selectors:array{overlay:string,spinner:string,toast:string}
 * }
 */
class IpDetectBackgroundFixtureBuilder {

	private const OPTION_KEYS = [
		'ipdetect_at',
		'visitor_address_source',
	];

	private const DETECTED_IP = '203.0.113.44';

	/**
	 * @return array{contract:FixtureContract,state:FixtureState}
	 */
	public function seed() :array {
		RuntimeTestState::loginAsSecurityAdmin();
		$state = [
			'options_snapshot' => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
		];

		try {
			$this->forceIpDetectDue();
			return [
				'contract' => $this->contract(),
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
	public function cleanup( array $state ) :void {
		$options = $this->normalizeOptionsSnapshot( $state[ 'options_snapshot' ] ?? [] );
		if ( $options !== [] ) {
			RuntimeTestState::loginAsSecurityAdmin();
			RuntimeTestState::restoreOptions( $options );
		}
	}

	private function forceIpDetectDue() :void {
		RuntimeTestState::controller()->opts
			->optSet( 'ipdetect_at', 1 )
			->optSet( 'visitor_address_source', 'AUTO_DETECT_IP' )
			->store();

		RuntimeTestState::forcePersistOptions( [
			'ipdetect_at'            => 1,
			'visitor_address_source' => 'AUTO_DETECT_IP',
		] );
		RuntimeTestState::resetOptionsRuntimeCache();
	}

	/**
	 * @return FixtureContract
	 */
	private function contract() :array {
		return [
			'routes'      => [
				'pages_admin' => '/wp-admin/edit.php?post_type=page',
			],
			'detected_ip' => self::DETECTED_IP,
			'action_slug' => PluginIpDetect::SLUG,
			'selectors'   => [
				'overlay' => '#ShieldOverlay',
				'spinner' => '#ShieldOverlaySpinner',
				'toast'   => '.shield-wpadmin-toast',
			],
		];
	}

	/**
	 * @param mixed $snapshot
	 * @return array<string,mixed>
	 */
	private function normalizeOptionsSnapshot( $snapshot ) :array {
		$options = [];
		foreach ( \is_array( $snapshot ) ? $snapshot : [] as $key => $value ) {
			if ( \is_string( $key ) && \in_array( $key, self::OPTION_KEYS, true ) ) {
				$options[ $key ] = $value;
			}
		}
		return $options;
	}
}
