<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\PluginNotices;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumDashboardDisplay;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices\LegacyDashboardNotices;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestPluginUrls,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;

class LegacyDashboardNoticesTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
		] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'providerNoticeTypeMappings' )]
	public function testCheckNormalisesLegacyNoticeTypeInPayload( string $input, string $expected ) :void {
		$this->installControllerForNoticeType( $input );

		$payload = ( new LegacyDashboardNotices() )->check();

		$this->assertIsArray( $payload );
		$this->assertArrayHasKey( 'id', $payload );
		$this->assertArrayHasKey( 'type', $payload );
		$this->assertSame( 'allow-tracking', $payload[ 'id' ] );
		$this->assertSame( $expected, $payload[ 'type' ] );
	}

	public static function providerNoticeTypeMappings() :array {
		return [
			'promo maps to info' => [ 'promo', 'info' ],
			'error maps to danger' => [ 'error', 'danger' ],
			'warning unchanged' => [ 'warning', 'warning' ],
			'info unchanged' => [ 'info', 'info' ],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'providerNoticeTypesWhenNonEssentialNoticesAreHidden' )]
	public function testHidingNonEssentialNoticesOnlyHidesPromoNotices( string $type, bool $expectShown ) :void {
		$this->installControllerForNoticeType( $type, false );

		$payload = ( new LegacyDashboardNotices() )->check();

		$this->assertSame( $expectShown, \is_array( $payload ) );
	}

	public static function providerNoticeTypesWhenNonEssentialNoticesAreHidden() :array {
		return [
			'promo is hidden' => [ 'promo', false ],
			'error still shows' => [ 'error', true ],
			'warning still shows' => [ 'warning', true ],
			'info still shows' => [ 'info', true ],
		];
	}

	private function installControllerForNoticeType( string $type, bool $showNonEssentialNotices = true ) :void {
		$notice = ( new NoticeVO() )->applyFromArray( [
			'id'               => 'allow-tracking',
			'type'             => $type,
			'plugin_page_only' => false,
			'valid_admin'      => false,
			'plugin_admin'     => 'ignore',
			'min_install_days' => 0,
			'can_dismiss'      => false,
		] );

		PluginControllerInstaller::install(
			new class( $notice, $showNonEssentialNotices ) extends Controller {

				private NoticeVO $notice;

				public function __construct( NoticeVO $notice, bool $showNonEssentialNotices ) {
					$this->notice = $notice;
					$this->labels = (object)[ 'Name' => 'Shield' ];
					$this->plugin_urls = new UnitTestPluginUrls();
					$this->opts = new class {
						public function optGet( string $key ) {
							return $key === 'tracking_permission_set_at' ? 0 : null;
						}
					};
					// The real notices controller decides who a notice is for, given this fixture's notice and plugin.
					$this->admin_notices = new class( $notice ) extends \FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\Controller {
						private NoticeVO $notice;

						public function __construct( NoticeVO $notice ) {
							$this->notice = $notice;
						}

						public function getDismissed() :array {
							return [];
						}

						public function getAdminNotices() :array {
							return [ $this->notice ];
						}
					};
					$this->comps = (object)[
						'opts_lookup' => new class( $showNonEssentialNotices ) {
							private bool $showNonEssentialNotices;

							public function __construct( bool $showNonEssentialNotices ) {
								$this->showNonEssentialNotices = $showNonEssentialNotices;
							}

							public function getInstalledAt() :int {
								return 1699990000;
							}

							public function showsInDashboard( string $element ) :bool {
								return $element !== EnumDashboardDisplay::NOTICES || $this->showNonEssentialNotices;
							}
						},
					];
				}

				public function isPluginAdminPageRequest() :bool {
					return true;
				}

				public function isValidAdminArea( bool $checkUserPerms = false ) :bool {
					return true;
				}

				public function isPluginAdmin() :bool {
					return true;
				}
			}
		);
	}
}
