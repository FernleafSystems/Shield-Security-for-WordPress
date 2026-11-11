<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Services\Services;

/**
 * A fresh install with no report email chosen, so the report email hold is active. The browser admin's setup guide
 * progress is kept aside too, since a scenario may skip a step.
 * @phpstan-type FixtureState array{options_snapshot:array<string,mixed>,setup_guide:array<string,mixed>}
 */
class ReportEmailHoldFixtureBuilder {

	private const OPTION_KEYS = [
		'installation_time',
		'block_send_email_address',
	];

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed() :array {
		$meta = $this->browserAdminMeta();
		$state = [
			'options_snapshot' => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
			'setup_guide'      => \is_array( $meta->setup_guide ) ? $meta->setup_guide : [],
		];

		try {
			RuntimeTestState::controller()->opts
				->optSet( 'installation_time', Services::Request()->ts() - 60 )
				->optSet( 'block_send_email_address', '' )
				->store();
			$meta->setup_guide = [];

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
		if ( \is_array( $state[ 'setup_guide' ] ?? null ) ) {
			$this->browserAdminMeta()->setup_guide = $state[ 'setup_guide' ];
		}
	}

	private function browserAdminMeta() :object {
		RuntimeTestState::loginAsSecurityAdmin();
		$meta = RuntimeTestState::controller()->user_metas->current();
		if ( empty( $meta ) ) {
			throw new \RuntimeException( 'Shield user meta is not available for the browser admin user.' );
		}
		return $meta;
	}
}
