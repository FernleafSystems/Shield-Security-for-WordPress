<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\SitesListTableColumn;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\{
	MWPSiteVO,
	SyncVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\ClientPluginStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestGeneral,
	UnitTestPluginUrls
};

class MainWPSitesListTableColumnTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				if ( \is_array( $value ) ) {
					return \array_map(
						static fn( $item ) :string => \rawurlencode( (string)$item ),
						$value
					);
				}
				return \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params, string $url ) :string {
				if ( empty( $params ) ) {
					return $url;
				}
				$pieces = [];
				foreach ( $params as $key => $value ) {
					$pieces[] = $key.'='.$value;
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $pieces );
			}
		);
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_wpgeneral' => new class extends UnitTestGeneral {
				public function getUrl_AdminPage( string $slug, bool $wpmsOnly = false ) :string {
					unset( $wpmsOnly );
					return '/mainwp/admin?page='.$slug;
				}
			},
		] );

		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			(object)[
				'cfg'    => new class {
					public function version() :string {
						return '18.2.1';
					}
				},
				'labels' => (object)[
					'Name' => 'Shield',
				],
				'mwpVO'  => (object)[
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

	public function test_active_warning_site_uses_attention_summary_for_count_and_button_color() :void {
		$page = new class( [
			'raw_mainwp_site_data' => [
				'id'      => 99,
				'plugins' => \wp_json_encode( [
					[ 'slug' => 'shield.php', 'active' => true ],
				] ),
			],
		] ) extends SitesListTableColumn {
			protected function loadSyncData( MWPSiteVO $site ) {
				unset( $site );
				return ( new SyncVO() )->applyFromArray( [
					'overview' => [
						'attention_summary' => [
							'total'        => 4,
							'severity'     => 'warning',
							'is_all_clear' => false,
						],
					],
				] );
			}

			protected function detectClientPluginStatus( MWPSiteVO $site ) :array {
				unset( $site );
				return [ ClientPluginStatus::ACTIVE => 'Active' ];
			}
		};

		$data = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertSame( 4, $data[ 'vars' ][ 'issues_count' ] );
		$this->assertSame( 'orange', $data[ 'vars' ][ 'issues_button_class' ] );
		$this->assertArrayNotHasKey( 'issues_severity', $data[ 'vars' ] );
		$this->assertSame( '/mainwp/admin', \parse_url( $data[ 'hrefs' ][ 'issues' ], \PHP_URL_PATH ) );

		\parse_str( (string)\parse_url( $data[ 'hrefs' ][ 'issues' ], \PHP_URL_QUERY ), $query );
		$this->assertSame( 'SiteOpen', $query[ 'page' ] ?? '' );
		$this->assertSame( '99', $query[ 'websiteid' ] ?? '' );
		$this->assertSame( 'nonce-mainwp-admin-nonce', $query[ '_opennonce' ] ?? '' );
		$this->assertSame( '/admin/scans/overview', \base64_decode( (string)( $query[ 'location' ] ?? '' ), true ) );
	}

	public function test_active_good_site_renders_zero_with_green_button() :void {
		$page = new class( [
			'raw_mainwp_site_data' => [
				'id'      => 100,
				'plugins' => \wp_json_encode( [
					[ 'slug' => 'shield.php', 'active' => true ],
				] ),
			],
		] ) extends SitesListTableColumn {
			protected function loadSyncData( MWPSiteVO $site ) {
				unset( $site );
				return ( new SyncVO() )->applyFromArray( [
					'overview' => [
						'attention_summary' => [
							'total'        => 0,
							'severity'     => 'good',
							'is_all_clear' => true,
						],
					],
				] );
			}

			protected function detectClientPluginStatus( MWPSiteVO $site ) :array {
				unset( $site );
				return [ ClientPluginStatus::ACTIVE => 'Active' ];
			}
		};

		$data = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertSame( 0, $data[ 'vars' ][ 'issues_count' ] );
		$this->assertSame( 'green', $data[ 'vars' ][ 'issues_button_class' ] );
	}
}
