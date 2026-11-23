<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SetupGuideState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * The setup guide dismissed, as on a site whose admin chose not to see it again. The site's setup guide state is
 * kept aside and put back afterwards.
 * @phpstan-type FixtureState array{options_snapshot:array<string,mixed>}
 */
class SetupGuideDismissedFixtureBuilder {

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed() :array {
		$state = [
			'options_snapshot' => RuntimeTestState::snapshotOptions( [ SetupGuideState::OPT_KEY ] ),
		];

		try {
			( new SetupGuideState() )->dismiss();
			RuntimeTestState::controller()->opts->store();
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
