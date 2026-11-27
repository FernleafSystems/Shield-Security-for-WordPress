<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SetupGuideState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * The setup guide as a site would have it: closed for good, as on a site whose admin chose not to see it again,
 * or untouched on a site installed just now with no report email chosen, where it opens by itself on its first
 * step. The settings changed are kept aside and put back afterwards.
 * @phpstan-type FixtureState array{options_snapshot:array<string,mixed>}
 */
class SetupGuideStateFixtureBuilder {

	public const SCENARIO_DISMISSED = 'dismissed';
	public const SCENARIO_FIRST_RUN = 'first_run';

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed( string $scenario ) :array {
		$state = [
			'options_snapshot' => RuntimeTestState::snapshotOptions(
				[ SetupGuideState::OPT_KEY, 'installation_time', 'block_send_email_address' ]
			),
		];

		try {
			$opts = RuntimeTestState::controller()->opts;
			switch ( $scenario ) {
				case self::SCENARIO_DISMISSED:
					( new SetupGuideState() )->dismiss();
					break;
				case self::SCENARIO_FIRST_RUN:
					$opts->optSet( SetupGuideState::OPT_KEY, [] )
						 ->optSet( 'installation_time', \time() )
						 ->optSet( 'block_send_email_address', '' );
					break;
				default:
					throw new \RuntimeException( 'Unknown setup guide scenario: '.$scenario );
			}
			$opts->store();
			return [
				'contract' => [],
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
		RuntimeTestState::restoreOptions(
			\is_array( $state[ 'options_snapshot' ] ?? null ) ? $state[ 'options_snapshot' ] : []
		);
	}
}
