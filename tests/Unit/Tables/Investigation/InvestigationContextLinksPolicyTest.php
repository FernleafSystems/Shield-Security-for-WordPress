<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Investigation;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\InvestigationTrafficTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Utilities\IpUtils;

class InvestigationContextLinksPolicyTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_html' )->alias( static fn( $text ) :string => (string)$text );
		Functions\when( 'esc_attr' )->alias( static fn( $text ) :string => (string)$text );
		Functions\when( 'esc_url' )->alias( static fn( $text ) :string => (string)$text );

		$this->servicesSnapshot = ServicesState::snapshot();
		$this->installControllerStub();
		$this->installServices();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function testUserLinksRouteToInvestigateByUser() :void {
		$builder = new InvestigationContextLinksPolicyTableDouble( [
			77 => (object)[
				'user_login' => 'alice'
			]
		] );

		$userHref = $builder->exposeUserHref( 77 );

		$this->assertStringContainsString( '/admin/activity/by_user?user_id=77', $userHref );
		$this->assertStringContainsString( 'alice', $userHref );
		$this->assertStringNotContainsString( 'profile', $userHref );
	}

	public function testIpLinksKeepOffcanvasAndAddInvestigateDeeplink() :void {
		$builder = new InvestigationContextLinksPolicyTableDouble();

		$link = $builder->exposeIpAnalysisLink( '203.0.113.88' );

		$this->assertStringContainsString( 'offcanvas_ip_analysis', $link );
		$this->assertStringContainsString( '/admin/ip-analysis/203.0.113.88', $link );
		$this->assertStringContainsString( 'investigate-ip-deeplink', $link );
		$this->assertStringContainsString( '/admin/activity/by_ip?analyse_ip=203.0.113.88', $link );
	}

	public function testInvalidIpDoesNotRenderInvestigateDeeplink() :void {
		$builder = new InvestigationContextLinksPolicyTableDouble();

		$link = $builder->exposeIpAnalysisLink( 'not-an-ip' );

		$this->assertStringNotContainsString( 'investigate-ip-deeplink', $link );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function investigateByUser( string $uid = '' ) :string {
				return '/admin/activity/by_user?user_id='.$uid;
			}

			public function investigateByIp( string $ip = '' ) :string {
				return '/admin/activity/by_ip?analyse_ip='.$ip;
			}

			public function ipAnalysis( string $ip ) :string {
				return '/admin/ip-analysis/'.$ip;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	private function installServices() :void {
		ServicesState::installItems( [
			'service_ip' => new class extends IpUtils {
				public function isValidIp( $ip, $flags = null ) {
					return \filter_var( $ip, \FILTER_VALIDATE_IP ) !== false;
				}

				public function isValidIpRange( $ip ) :bool {
					return false;
				}

				public function version( string $ip ) :int {
					return 4;
				}
			},
		] );
	}
}

class InvestigationContextLinksPolicyTableDouble extends InvestigationTrafficTableData {

	private array $users;

	public function __construct( array $users = [] ) {
		$this->users = $users;
	}

	protected function resolveUser( int $uid ) {
		return $this->users[ $uid ] ?? null;
	}

	public function exposeUserHref( int $uid ) :string {
		return $this->getUserHref( $uid );
	}

	public function exposeIpAnalysisLink( string $ip ) :string {
		return $this->getIpAnalysisLink( $ip );
	}
}
