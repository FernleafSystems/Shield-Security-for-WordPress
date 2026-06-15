<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DismissAdminNotice;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices\PhpFutureMinimum;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};

class DismissAdminNoticeTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_url' )->alias( static fn( string $url ) :string => $url );
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_legacy_notice_dismissal_still_uses_admin_notice_controller() :void {
		$adminNotices = new DismissAdminNoticeAdminNoticesStub( [
			(object)[
				'id'          => 'legacy_notice',
				'can_dismiss' => true,
			],
		] );
		$this->installEnvironment( $adminNotices );

		$action = new DismissAdminNoticeTestDouble( [
			'notice_id' => 'legacy_notice',
		] );
		$action->execForTest();

		$this->assertSame( [ 'legacy_notice' ], $adminNotices->dismissedNoticeIds() );
		$this->assertTrue( (bool)$action->response()->payload()[ 'success' ] );
	}

	public function test_dormant_php_future_minimum_notice_is_not_dismissed() :void {
		$this->installEnvironment();

		$action = new DismissAdminNoticeTestDouble( [
			'notice_id' => PhpFutureMinimum::ID,
		] );
		$action->execForTest();

		$this->assertArrayNotHasKey( 'success', $action->response()->payload() );
	}

	private function installEnvironment( ?DismissAdminNoticeAdminNoticesStub $adminNotices = null ) :void {
		PluginControllerInstaller::install(
			new DismissAdminNoticeControllerStub(
				$adminNotices ?? new DismissAdminNoticeAdminNoticesStub(),
				new \stdClass()
			)
		);
	}
}

class DismissAdminNoticeTestDouble extends DismissAdminNotice {

	public function execForTest() :void {
		$this->exec();
	}
}

class DismissAdminNoticeControllerStub extends Controller {

	public function __construct( object $adminNotices, object $userMetas ) {
		$this->admin_notices = $adminNotices;
		$this->user_metas = $userMetas;
	}
}

class DismissAdminNoticeAdminNoticesStub {

	private array $notices;

	private array $dismissedNoticeIds = [];

	public function __construct( array $notices = [] ) {
		$this->notices = $notices;
	}

	public function getAdminNotices() :array {
		return $this->notices;
	}

	public function setNoticeDismissed( object $notice ) :void {
		$this->dismissedNoticeIds[] = $notice->id;
	}

	public function dismissedNoticeIds() :array {
		return $this->dismissedNoticeIds;
	}
}
