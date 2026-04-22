<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage\TabSitesListing;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\{
	MWPSiteVO,
	SyncVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\ClientPluginStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestGeneral,
	UnitTestPluginUrls,
	UnitTestRequest
};

class MainWPTabSitesListingTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest(),
			'service_wpgeneral' => new UnitTestGeneral(),
		] );

		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			(object)[
				'mwpVO' => (object)[
					'official_extension_data' => [
						'page' => 'Extensions-Wp-Simple-Firewall',
					],
				],
			]
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_build_entire_site_data_uses_issue_summary_and_removes_grade_contract() :void {
		$page = new class extends TabSitesListing {
			protected function getSiteByID( int $id ) :MWPSiteVO {
				unset( $id );
				return ( new MWPSiteVO() )->applyFromArray( [
					'id'      => '42',
					'plugins' => \wp_json_encode( [
						[ 'slug' => 'shield.php', 'active' => true ],
					] ),
				] );
			}

			protected function loadSyncData( $site ) {
				unset( $site );
				return ( new SyncVO() )->applyFromArray( [
					'meta'     => [
						'sync_at'    => 1700000000,
						'has_update' => false,
					],
					'overview' => [
						'attention_summary' => [
							'total'        => 4,
							'severity'     => 'warning',
							'is_all_clear' => false,
						],
					],
				] );
			}

			protected function detectClientPluginStatus( $site ) :array {
				unset( $site );
				return [ ClientPluginStatus::ACTIVE => 'Active' ];
			}

			protected function getJumpUrlFor( string $siteID, string $page ) :string {
				unset( $siteID );
				return '/jump'.$page;
			}

			protected function createInternalExtensionHref( array $params ) :string {
				return '/internal?tab='.( $params[ 'tab' ] ?? '' );
			}

			public function buildSiteDataForTest( array $site ) :array {
				return $this->buildEntireSiteData( $site );
			}
		};

		$site = $page->buildSiteDataForTest( [
			'id'   => 42,
			'name' => 'Example',
			'url'  => 'https://example.com',
		] );

		$this->assertSame( 4, $site[ 'shield' ][ 'issues' ] );
		$this->assertTrue( $site[ 'shield' ][ 'has_issues' ] );
		$this->assertSame( 'orange', $site[ 'shield' ][ 'issues_button_class' ] );
		$this->assertArrayNotHasKey( 'issues_severity', $site[ 'shield' ] );
		$this->assertSame( '/jump/admin/scans/overview', $site[ 'shield' ][ 'href_issues' ] );
		$this->assertArrayNotHasKey( 'grades', $site[ 'shield' ] );
	}

	public function test_build_entire_site_data_maps_critical_attention_to_red_button() :void {
		$page = new class extends TabSitesListing {
			protected function getSiteByID( int $id ) :MWPSiteVO {
				unset( $id );
				return ( new MWPSiteVO() )->applyFromArray( [
					'id'      => '43',
					'plugins' => \wp_json_encode( [
						[ 'slug' => 'shield.php', 'active' => true ],
					] ),
				] );
			}

			protected function loadSyncData( $site ) {
				unset( $site );
				return ( new SyncVO() )->applyFromArray( [
					'meta'     => [
						'sync_at'    => 1700000000,
						'has_update' => false,
					],
					'overview' => [
						'attention_summary' => [
							'total'        => 2,
							'severity'     => 'critical',
							'is_all_clear' => false,
						],
					],
				] );
			}

			protected function detectClientPluginStatus( $site ) :array {
				unset( $site );
				return [ ClientPluginStatus::ACTIVE => 'Active' ];
			}

			protected function getJumpUrlFor( string $siteID, string $page ) :string {
				unset( $siteID );
				return '/jump'.$page;
			}

			public function buildSiteDataForTest( array $site ) :array {
				return $this->buildEntireSiteData( $site );
			}
		};

		$site = $page->buildSiteDataForTest( [
			'id'   => 43,
			'name' => 'Critical Example',
			'url'  => 'https://critical.example.com',
		] );

		$this->assertSame( 2, $site[ 'shield' ][ 'issues' ] );
		$this->assertSame( 'red', $site[ 'shield' ][ 'issues_button_class' ] );
		$this->assertTrue( $site[ 'shield' ][ 'has_issues' ] );
	}

	public function test_build_entire_site_data_treats_sync_required_site_as_non_active_even_with_stale_payload() :void {
		$page = new class extends TabSitesListing {
			protected function getSiteByID( int $id ) :MWPSiteVO {
				unset( $id );
				return ( new MWPSiteVO() )->applyFromArray( [
					'id'      => '44',
					'plugins' => \wp_json_encode( [
						[ 'slug' => 'shield.php', 'active' => true ],
					] ),
				] );
			}

			protected function loadSyncData( $site ) {
				unset( $site );
				return ( new SyncVO() )->applyFromArray( [
					'integrity'   => [
						'status' => 'ok',
					],
					'scan_issues' => [
						'malware' => 7,
					],
				] );
			}

			protected function detectClientPluginStatus( $site ) :array {
				unset( $site );
				return [ ClientPluginStatus::NEED_SYNC => 'Sync Required' ];
			}

			public function buildSiteDataForTest( array $site ) :array {
				return $this->buildEntireSiteData( $site );
			}
		};

		$site = $page->buildSiteDataForTest( [
			'id'   => 44,
			'name' => 'Stale Example',
			'url'  => 'https://stale.example.com',
		] );

		$this->assertFalse( $site[ 'shield' ][ 'is_active' ] );
		$this->assertTrue( $site[ 'shield' ][ 'is_sync_rqd' ] );
		$this->assertFalse( $site[ 'shield' ][ 'has_issues' ] );
		$this->assertSame( 0, $site[ 'shield' ][ 'issues' ] ?? 0 );
	}
}
