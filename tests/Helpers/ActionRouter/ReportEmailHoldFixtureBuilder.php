<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Services\Services;

/**
 * A fresh install with no report email chosen, so the report email hold is active. The site's setup guide progress
 * is reset and kept aside too, since a scenario may skip a step. The guide starts minimised: on a fresh install
 * it would otherwise open over the dashboard by itself, which is the setup guide's own scenario to cover.
 * @phpstan-type FixtureState array{options_snapshot:array<string,mixed>}
 */
class ReportEmailHoldFixtureBuilder {

	private const OPTION_KEYS = [
		'installation_time',
		'block_send_email_address',
		'setup_guide_state',
	];

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed() :array {
		$state = [
			'options_snapshot' => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
		];

		try {
			RuntimeTestState::controller()->opts
				->optSet( 'installation_time', Services::Request()->ts() - 60 )
				->optSet( 'block_send_email_address', '' )
				->optSet( 'setup_guide_state', [ 'minimised' => true ] )
				->store();

			return [
				'contract' => [
					'site_admin_email' => Services::WpGeneral()->getSiteAdminEmail(),
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
		return [
			'state' => [
				'report_email' => (string)RuntimeTestState::controller()->opts->optGet( 'block_send_email_address' ),
			],
		];
	}

	/**
	 * @param array<string,mixed> $state
	 */
	public function cleanup( array $state ) :void {
		RuntimeTestState::restoreOptions(
			\is_array( $state[ 'options_snapshot' ] ?? null ) ? $state[ 'options_snapshot' ] : []
		);
	}
}
