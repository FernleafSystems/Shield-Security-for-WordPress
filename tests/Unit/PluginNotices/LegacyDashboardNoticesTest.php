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
		Functions\when( 'esc_html' )->returnArg();
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

	/**
	 * @dataProvider providerNoticeTypeMappings
	 */
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

	/**
	 * @dataProvider providerEmailVerificationNoticeStates
	 */
	public function testEmailVerificationNoticeExposesStableStatusAndActions(
		int $verifiedAt,
		int $sentAt,
		string $expectedStatus
	) :void {
		$this->installControllerForEmailVerificationNotice( $verifiedAt, $sentAt );

		$payload = ( new LegacyDashboardNotices() )->check();

		$this->assertIsArray( $payload );
		$this->assertArrayHasKey( 'id', $payload );
		$this->assertArrayHasKey( 'email_verification_status', $payload );
		$this->assertArrayHasKey( 'text', $payload );
		$this->assertSame( 'email-verification-sent', $payload[ 'id' ] );
		$this->assertSame( $expectedStatus, $payload[ 'email_verification_status' ] );
		$this->assertIsArray( $payload[ 'text' ] );
		$text = \implode( "\n", \array_map( 'strval', $payload[ 'text' ] ) );
		$this->assertStringContainsString( 'data-notice_action="resend_verification_email"', $text );
		$this->assertStringContainsString( 'data-notice_action="mfa_email_disable"', $text );
	}

	public static function providerEmailVerificationNoticeStates() :array {
		return [
			'unsent' => [ 0, 0, 'unsent' ],
			'pending' => [ 0, 1699999900, 'pending' ],
			'stale' => [ 0, 1699913599, 'stale' ],
		];
	}

	private function installControllerForNoticeType( string $type ) :void {
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
			new class( $notice ) extends Controller {

				private NoticeVO $notice;

				public function __construct( NoticeVO $notice ) {
					$this->notice = $notice;
					$this->labels = (object)[ 'Name' => 'Shield' ];
					$this->plugin_urls = new UnitTestPluginUrls();
					$this->opts = new class {
						public function optGet( string $key ) {
							return $key === 'tracking_permission_set_at' ? 0 : null;
						}

						public function optIs( string $key, $value ) :bool {
							return $key === 'enable_upgrade_admin_notice' && $value === 'Y';
						}
					};
					$this->admin_notices = new class( $notice ) {
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
						'opts_lookup' => new class {
							public function getInstalledAt() :int {
								return 1699990000;
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

	private function installControllerForEmailVerificationNotice( int $verifiedAt, int $sentAt ) :void {
		$notice = ( new NoticeVO() )->applyFromArray( [
			'id'               => 'email-verification-sent',
			'type'             => 'warning',
			'plugin_page_only' => false,
			'valid_admin'      => false,
			'plugin_admin'     => 'ignore',
			'min_install_days' => 0,
			'can_dismiss'      => false,
		] );

		PluginControllerInstaller::install(
			new class( $notice, $verifiedAt, $sentAt ) extends Controller {

				private NoticeVO $notice;

				private int $verifiedAt;

				private int $sentAt;

				public function __construct( NoticeVO $notice, int $verifiedAt, int $sentAt ) {
					$this->notice = $notice;
					$this->verifiedAt = $verifiedAt;
					$this->sentAt = $sentAt;
					$this->labels = (object)[ 'Name' => 'Shield' ];
					$this->plugin_urls = new UnitTestPluginUrls();
					$this->opts = new class( $verifiedAt, $sentAt ) {
						private int $verifiedAt;

						private int $sentAt;

						public function __construct( int $verifiedAt, int $sentAt ) {
							$this->verifiedAt = $verifiedAt;
							$this->sentAt = $sentAt;
						}

						public function optGet( string $key ) {
							return [
								'email_can_send_verified_at' => $this->verifiedAt,
								'email_can_send_verification_sent_at' => $this->sentAt,
							][ $key ];
						}

						public function optIs( string $key, $value ) :bool {
							return $key === 'enable_email_authentication' && $value === 'Y';
						}
					};
					$this->admin_notices = new class( $notice ) {
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
						'opts_lookup' => new class {
							public function getInstalledAt() :int {
								return 1699990000;
							}

							public function getReportEmail() :string {
								return 'reports@example.test';
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
