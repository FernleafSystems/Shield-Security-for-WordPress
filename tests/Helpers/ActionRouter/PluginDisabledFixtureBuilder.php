<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * All protection switched off with the global setting, so the notice offers to turn it back on.
 * @phpstan-type FixtureState array{options_snapshot:array<string,mixed>}
 */
class PluginDisabledFixtureBuilder {

	private const OPTION_KEYS = [
		'global_enable_plugin_features',
	];

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed() :array {
		$state = [
			'options_snapshot' => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
		];
		try {
			RuntimeTestState::controller()->opts->optSet( 'global_enable_plugin_features', 'N' )->store();
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

	public function inspect() :array {
		return [
			'state' => [
				'plugin_enabled' => RuntimeTestState::controller()->comps->opts_lookup->isPluginEnabled(),
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
