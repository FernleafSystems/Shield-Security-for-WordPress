<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * @phpstan-type FixtureState array{options_snapshot:array<string,mixed>}
 */
class MerlinWelcomeFixtureBuilder {

	private const OPTION_KEYS = [
		'license_activated_at',
		'license_data',
		'license_deactivated_at',
	];

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed() :array {
		$state = [
			'options_snapshot' => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
		];

		try {
			RuntimeTestState::disablePremiumCapabilities();
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
