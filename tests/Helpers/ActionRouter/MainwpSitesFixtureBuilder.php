<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions\SiteActionDeactivate;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions\SiteActionSync;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions\SiteCustomAction;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * @phpstan-type MainwpSiteRuntime array{
 *   child_key:string,
 *   child_file:string,
 *   page:string,
 *   sites:list<array<string,mixed>>,
 *   sync:array<string,array<string,mixed>>
 * }
 * @phpstan-type FixtureContract array{
 *   page_url:string,
 *   site_id:int,
 *   actions:array<string,string>
 * }
 * @phpstan-type FixtureState array{
 *   options_snapshot:array<string,mixed>,
 *   runtime:MainwpSiteRuntime
 * }
 */
class MainwpSitesFixtureBuilder {

	private const OPTION_KEYS = [
		'enable_mainwp',
		'license_activated_at',
		'license_data',
		'license_deactivated_at',
	];
	private const SITE_ID = 7901;
	private const CHILD_KEY = 'shield-browser-mainwp-child-key';
	private const PAGE = 'Extensions-Wp-Simple-Firewall';

	/**
	 * @return array{contract:FixtureContract,state:FixtureState}
	 */
	public function seed() :array {
		$con = RuntimeTestState::controller();
		$state = [
			'options_snapshot' => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
			'runtime'          => $this->buildRuntimeState(),
		];

		try {
			RuntimeTestState::applyPremiumCapabilities( [ 'mainwp_level_1' ] );
			$con->opts->optSet( 'enable_mainwp', 'Y' );
			RuntimeTestState::forcePersistOptions( [
				'enable_mainwp' => 'Y',
			] );
			RuntimeTestState::resetOptionsRuntimeCache();

			return [
				'contract' => [
					'page_url' => '/wp-admin/admin.php?page='.self::PAGE.'&tab=sites',
					'site_id'  => self::SITE_ID,
					'actions'  => $this->buildActionContract(),
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
	public function cleanup( array $state ) :void {
		RuntimeTestState::restoreOptions( $this->normalizeOptionsSnapshot( $state ) );
	}

	/**
	 * @return MainwpSiteRuntime
	 */
	private function buildRuntimeState() :array {
		$con = RuntimeTestState::controller();
		$siteID = self::SITE_ID;
		$now = \time();
		$plugins = \wp_json_encode( [
			[
				'slug'   => $con->base_file,
				'active' => true,
			],
		] );

		return [
			'child_key'  => self::CHILD_KEY,
			'child_file' => $con->getRootFile(),
			'page'       => self::PAGE,
			'sites'      => [
				[
					'id'        => $siteID,
					'userid'    => 1,
					'adminname' => 'admin',
					'name'      => 'MainWP Browser Fixture Site',
					'url'       => 'https://mainwp-browser-fixture.example.test',
					'siteurl'   => 'https://mainwp-browser-fixture.example.test',
					'plugins'   => \is_string( $plugins ) ? $plugins : '[]',
					'themes'    => '[]',
				],
			],
			'sync'       => [
				(string)$siteID => [
					'meta'     => [
						'is_pro'       => true,
						'is_mainwp_on' => true,
						'installed_at' => $now - 86400,
						'sync_at'      => $now,
						'version'      => $con->cfg->version(),
						'has_update'   => false,
					],
					'overview' => [
						'attention_summary' => [
							'total'    => 0,
							'severity' => 'good',
						],
					],
				],
			],
		];
	}

	/**
	 * @return array<string,string>
	 */
	private function buildActionContract() :array {
		return [
			'sync'       => SiteActionSync::SLUG,
			'deactivate' => SiteActionDeactivate::SLUG,
			'license'    => SiteCustomAction::SLUG,
		];
	}

	/**
	 * @param array<string,mixed> $state
	 * @return array<string,mixed>
	 */
	private function normalizeOptionsSnapshot( array $state ) :array {
		return \is_array( $state[ 'options_snapshot' ] ?? null ) ? $state[ 'options_snapshot' ] : [];
	}
}
