<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Plugin;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\General;

class PluginURLsTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();

		Functions\when( 'plugin_basename' )->alias( static fn( string $file ) :string => $file );
		Functions\when( 'sanitize_key' )->alias( static fn( string $key ) :string => \strtolower( \trim( $key ) ) );
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
				$query = [];
				foreach ( $params as $key => $value ) {
					$query[] = $key.'='.$value;
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $query );
			}
		);

		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_wpgeneral' => new class extends General {
				public function getUrl_AdminPage( string $page = '', bool $networkAdmin = false ) :string {
					return '/shield-admin.php?page='.$page;
				}
			},
		] );

		PluginControllerInstaller::install( new class extends Controller {
			public function __construct() {
				parent::__construct( 'icwp-wpsf.php' );
				$this->cfg = (object)[
					'properties' => [
						'wpms_network_admin_only' => false,
						'slug_parent' => 'icwp',
						'slug_plugin' => 'wpsf',
					],
				];
			}
		} );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_plugin_vulnerability_helpers_build_expected_urls() :void {
		$urls = new PluginURLs();

		$this->assertSame(
			$urls->investigateByPlugin( 'akismet/akismet.php' ).'#tab-navlink-plugin-vulnerabilities',
			$urls->investigatePluginVulnerabilities( 'akismet/akismet.php' )
		);
		$this->assertSame(
			'https://clk.shldscrty.com/shieldvulnerabilitylookup?type=plugin&slug=akismet&version=5.0',
			$urls->vulnerabilityLookupByPlugin( 'akismet', '5.0' )
		);
	}

	public function test_theme_vulnerability_helpers_build_expected_urls() :void {
		$urls = new PluginURLs();

		$this->assertSame(
			$urls->investigateByTheme( 'twentytwentysix' ).'#tab-navlink-theme-vulnerabilities',
			$urls->investigateThemeVulnerabilities( 'twentytwentysix' )
		);
		$this->assertSame(
			'https://clk.shldscrty.com/shieldvulnerabilitylookup?type=theme&slug=twentytwentysix&version=1.2',
			$urls->vulnerabilityLookupByTheme( 'twentytwentysix', '1.2' )
		);
	}
}
