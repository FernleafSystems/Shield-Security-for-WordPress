<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Utilities\AdminNotices;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\Controller as AdminNoticesController;

class ControllerFlashMessageTest extends BaseUnitTest {

	private const NOW = 1700000000;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( 'sanitize_text_field' )->returnArg();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function testMalformedFlashMessageIsClearedAndNotRendered() :void {
		$meta = (object)[
			'flash_msg' => [ 'error' => true ],
		];
		$this->installEnvironment( $meta );

		$this->assertNull( $this->invokeGetFlashNotice() );
		$this->assertNull( $meta->flash_msg );
	}

	public function testFlashMessageDefaultsMissingBooleanFlags() :void {
		$meta = (object)[
			'flash_msg' => [ 'message' => 'Saved.' ],
		];
		$this->installEnvironment( $meta );

		$notice = $this->invokeGetFlashNotice();

		$this->assertNotNull( $notice );
		$this->assertSame( 'updated', $notice->type );
		$this->assertArrayHasKey( 'message', $notice->render_data );
		$this->assertSame( 'Saved.', $notice->render_data[ 'message' ] );
		$this->assertNull( $meta->flash_msg );
	}

	public function testExpiredFlashMessageIsClearedAndNotRendered() :void {
		$meta = (object)[
			'flash_msg' => [
				'message'    => 'Old message.',
				'expires_at' => self::NOW - 1,
			],
		];
		$this->installEnvironment( $meta );

		$this->assertNull( $this->invokeGetFlashNotice() );
		$this->assertNull( $meta->flash_msg );
	}

	/**
	 * @dataProvider loginMessageProvider
	 */
	public function testLoginMessageAdapterNormalizesMixedFilterValues( $value, string $expected ) :void {
		$this->installEnvironment( (object)[ 'flash_msg' => null ] );

		$this->assertSame( $expected, ( new AdminNoticesController() )->onLoginMessageFilter( $value ) );
	}

	public static function loginMessageProvider() :array {
		return [
			'string' => [ 'message', 'message' ],
			'integer' => [ 12, '12' ],
			'boolean' => [ true, '1' ],
			'array' => [ [ 'message' ], '' ],
			'nested array' => [ [ [ 'message' ] ], '' ],
			'object' => [ new \stdClass(), '' ],
			'stringable' => [ new ControllerFlashMessageStringable(), 'stringable' ],
			'throwing stringable' => [ new ControllerFlashMessageThrowingStringable(), '' ],
			'null' => [ null, '' ],
		];
	}

	public function testLoginMessageAdapterRejectsResource() :void {
		$this->installEnvironment( (object)[ 'flash_msg' => null ] );
		$resource = \fopen( 'php://memory', 'r' );
		try {
			$this->assertSame( '', ( new AdminNoticesController() )->onLoginMessageFilter( $resource ) );
		}
		finally {
			\fclose( $resource );
		}
	}

	public function testLoginMessageFilterConsumesShownFlashExactlyOnce() :void {
		$meta = (object)[
			'flash_msg' => [
				'message'    => 'Saved.',
				'expires_at' => self::NOW + 1,
				'error'      => false,
				'show_login' => true,
			],
		];
		$this->installEnvironment( $meta );
		$controller = new AdminNoticesController();

		$first = $controller->onLoginMessageFilter( 'existing' );

		$this->assertStringStartsWith( 'existing', $first );
		$this->assertNotSame( 'existing', $first );
		$this->assertNull( $meta->flash_msg );
		$this->assertSame( 'existing', $controller->onLoginMessageFilter( 'existing' ) );
	}

	private function installEnvironment( object $meta ) :void {
		UnitTestControllerFactory::install( null, null, (object)[
			'user_metas' => new ControllerFlashMessageUserMetasStub( $meta ),
		] );
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', self::NOW ),
		] );
	}

	private function invokeGetFlashNotice() {
		$method = new \ReflectionMethod( AdminNoticesController::class, 'getFlashNotice' );
		$method->setAccessible( true );
		return $method->invoke( new AdminNoticesController() );
	}
}

class ControllerFlashMessageUserMetasStub {

	private object $meta;

	public function __construct( object $meta ) {
		$this->meta = $meta;
	}

	public function current() :object {
		return $this->meta;
	}
}

class ControllerFlashMessageStringable {
	public function __toString() :string {
		return 'stringable';
	}
}

class ControllerFlashMessageThrowingStringable {
	public function __toString() :string {
		throw new \Error( 'conversion failed' );
	}
}
