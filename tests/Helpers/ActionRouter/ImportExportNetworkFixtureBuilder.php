<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * @phpstan-type FixtureState array{options_snapshot:array<string,mixed>}
 */
class ImportExportNetworkFixtureBuilder {

	private const OPTION_KEYS = [
		'license_activated_at',
		'license_data',
		'license_deactivated_at',
		'importexport_enable',
		'importexport_masterurl',
		NetworkInviteRepository::OPTION_KEY,
	];

	/**
	 * @param list<string> $args
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed( array $args = [] ) :array {
		$state = [
			'options_snapshot' => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
		];

		try {
			RuntimeTestState::applyPremiumCapabilities( [ 'import_export_level_1', 'import_export_level_2' ] );
			$con = RuntimeTestState::controller();
			$masterUrl = \in_array( 'connected-master', $args, true ) ? 'https://master.example.com/import' : '';
			$con->opts
				->optSet( 'importexport_enable', 'Y' )
				->optSet( 'importexport_masterurl', $masterUrl )
				->optSet( NetworkInviteRepository::OPTION_KEY, [] )
				->store();

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
