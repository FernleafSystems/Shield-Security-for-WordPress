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
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Utilities\Data;

class DismissAdminNoticeTest extends BaseUnitTest {

	private const NOW = 1700001234;

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

	public function test_dismiss_php_future_minimum_stores_current_user_snooze_when_dismissible() :void {
		$meta = $this->installEnvironment( '8.2.20' );

		$action = new DismissAdminNoticeTestDouble( [
			'notice_id' => PhpFutureMinimum::ID,
		] );
		$action->execForTest();

		$this->assertSame( self::NOW, $meta->{PhpFutureMinimum::SNOOZE_USER_META} );
		$payload = $action->response()->payload();
		$this->assertTrue( (bool)$payload[ 'success' ] );
		$this->assertSame( PhpFutureMinimum::ID, $payload[ 'notice_id' ] );
	}

	public function test_dismiss_php_future_minimum_does_not_snooze_non_dismissible_variant() :void {
		$meta = $this->installEnvironment( '8.1.29' );

		$action = new DismissAdminNoticeTestDouble( [
			'notice_id' => PhpFutureMinimum::ID,
		] );
		$action->execForTest();

		$this->assertNull( $meta->{PhpFutureMinimum::SNOOZE_USER_META} );
		$this->assertArrayNotHasKey( 'success', $action->response()->payload() );
	}

	public function test_legacy_notice_dismissal_still_uses_admin_notice_controller() :void {
		$adminNotices = new DismissAdminNoticeAdminNoticesStub( [
			(object)[
				'id'          => 'legacy_notice',
				'can_dismiss' => true,
			],
		] );
		$this->installEnvironment( '8.4.8', $adminNotices );

		$action = new DismissAdminNoticeTestDouble( [
			'notice_id' => 'legacy_notice',
		] );
		$action->execForTest();

		$this->assertSame( [ 'legacy_notice' ], $adminNotices->dismissedNoticeIds() );
		$this->assertTrue( (bool)$action->response()->payload()[ 'success' ] );
	}

	private function installEnvironment(
		string $phpVersion,
		?DismissAdminNoticeAdminNoticesStub $adminNotices = null
	) :DismissAdminNoticeUserMetaStub {
		$meta = new DismissAdminNoticeUserMetaStub();
		ServicesState::installItems( [
			'service_data'    => new DismissAdminNoticeDataStub( $phpVersion ),
			'service_request' => new UnitTestRequest( [], '127.0.0.1', self::NOW ),
		] );
		PluginControllerInstaller::install(
			new DismissAdminNoticeControllerStub(
				$adminNotices ?? new DismissAdminNoticeAdminNoticesStub(),
				new DismissAdminNoticeUserMetasStub( $meta )
			)
		);

		return $meta;
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

class DismissAdminNoticeUserMetasStub {

	private DismissAdminNoticeUserMetaStub $meta;

	public function __construct( DismissAdminNoticeUserMetaStub $meta ) {
		$this->meta = $meta;
	}

	public function current() :DismissAdminNoticeUserMetaStub {
		return $this->meta;
	}
}

class DismissAdminNoticeUserMetaStub {

	private array $values = [];

	public function __get( string $key ) {
		return $this->values[ $key ] ?? null;
	}

	public function __set( string $key, $value ) :void {
		$this->values[ $key ] = $value;
	}
}

class DismissAdminNoticeDataStub extends Data {

	private string $phpVersion;

	public function __construct( string $phpVersion ) {
		$this->phpVersion = $phpVersion;
	}

	public function getPhpVersion() :string {
		return $this->phpVersion;
	}
}
