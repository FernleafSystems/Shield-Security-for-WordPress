<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\LicenseClear;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * @phpstan-type FixtureState array{selected_options_snapshot:array<string,mixed>}
 */
class LicenseClearFixtureBuilder {

	private const OPTION_KEYS = [
		'license_data',
		'license_activated_at',
		'license_deactivated_at',
	];

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed() :array {
		RuntimeTestState::loginAsSecurityAdmin();
		$state = [
			'selected_options_snapshot' => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
		];

		try {
			RuntimeTestState::applyPremiumCapabilities( [
				'reports_local',
				'site_blockdown',
				'whitelabel',
			] );

			return [
				'contract' => $this->baseContract(),
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
		unset( $state );
		RuntimeTestState::loginAsSecurityAdmin();
		return \array_merge( $this->baseContract(), [
			'state' => $this->currentLicenseState(),
		] );
	}

	/**
	 * @param array<string,mixed> $state
	 */
	public function cleanup( array $state ) :void {
		$normalized = $this->normalizePersistedState( $state );
		if ( $normalized[ 'selected_options_snapshot' ] === [] ) {
			return;
		}

		RuntimeTestState::loginAsSecurityAdmin();
		RuntimeTestState::restoreOptions( $normalized[ 'selected_options_snapshot' ] );
		RuntimeTestState::resetOptionsRuntimeCache();
	}

	/**
	 * @return array<string,mixed>
	 */
	private function baseContract() :array {
		return [
			'route'       => [
				PluginNavs::FIELD_NAV    => PluginNavs::NAV_LICENSE,
				PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_LICENSE_CHECK,
			],
			'action_slug' => LicenseClear::SLUG,
			'selectors'   => [
				'page'  => '.license-page',
				'clear' => '.license-action[data-action="clear"]',
			],
			'state'       => $this->currentLicenseState(),
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function currentLicenseState() :array {
		$con = RuntimeTestState::controller();
		return [
			'license_data_empty'       => $con->opts->optGet( 'license_data' ) === [],
			'license_activated_at'     => (int)$con->opts->optGet( 'license_activated_at' ),
			'license_deactivated_at'   => (int)$con->opts->optGet( 'license_deactivated_at' ),
			'license_active'           => $con->comps->license->isActive(),
			'has_valid_working_license' => $con->comps->license->hasValidWorkingLicense(),
			'is_premium_active'        => $con->isPremiumActive(),
			'can_reports_local'        => $con->caps->canReportsLocal(),
			'can_site_blockdown'       => $con->caps->canSiteBlockdown(),
			'can_whitelabel'           => $con->caps->canWhitelabel(),
		];
	}

	/**
	 * @param array<string,mixed> $state
	 * @return FixtureState
	 */
	private function normalizePersistedState( array $state ) :array {
		$snapshot = [];
		foreach ( \is_array( $state[ 'selected_options_snapshot' ] ?? null ) ? $state[ 'selected_options_snapshot' ] : [] as $key => $value ) {
			if ( \is_string( $key ) && \in_array( $key, self::OPTION_KEYS, true ) ) {
				$snapshot[ $key ] = $value;
			}
		}

		return [
			'selected_options_snapshot' => $snapshot,
		];
	}
}
