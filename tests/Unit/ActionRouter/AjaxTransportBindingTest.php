<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionNonce,
	Actions\BaseAction,
	CaptureAjaxAction
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\InvalidActionNonceException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestUsers
};
use FernleafSystems\Wordpress\Services\Core\Request;

class AjaxTransportBindingTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = 'auth' ) :string => \hash( 'sha256', $scheme.'|'.$data )
		);

		$this->servicesSnapshot = ServicesState::snapshot();
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_ajax_capture_runnable_check_uses_post_transport_only() :void {
		$transport = [
			ActionData::FIELD_ACTION  => ActionData::FIELD_SHIELD,
			ActionData::FIELD_EXECUTE => AjaxTransportBindingNonceActionTestDouble::SLUG,
		];

		$this->installRequest( $transport, [] );
		$this->assertFalse( ( new AjaxTransportBindingCaptureAjaxActionTestDouble() )->canRunForTest() );

		$this->installRequest( [], $transport );
		$this->assertTrue( ( new AjaxTransportBindingCaptureAjaxActionTestDouble() )->canRunForTest() );
	}

	public function test_ajax_action_uses_action_data_nonce_instead_of_query_nonce() :void {
		$this->installRequest( [], [] );
		$validNonce = ActionNonce::Create( AjaxTransportBindingNonceActionTestDouble::class );
		$this->installRequest(
			[
				ActionData::FIELD_ACTION  => ActionData::FIELD_SHIELD,
				ActionData::FIELD_EXECUTE => AjaxTransportBindingNonceActionTestDouble::SLUG,
				ActionData::FIELD_NONCE   => $validNonce,
			],
			[
				ActionData::FIELD_ACTION  => ActionData::FIELD_SHIELD,
				ActionData::FIELD_EXECUTE => AjaxTransportBindingNonceActionTestDouble::SLUG,
			]
		);

		$this->expectException( InvalidActionNonceException::class );
		( new AjaxTransportBindingNonceActionTestDouble() )->process();
	}

	public function test_ajax_action_accepts_payload_nonce_even_when_query_nonce_is_invalid() :void {
		$this->installRequest( [], [] );
		$validNonce = ActionNonce::Create( AjaxTransportBindingNonceActionTestDouble::class );
		$this->installRequest(
			[
				ActionData::FIELD_ACTION  => ActionData::FIELD_SHIELD,
				ActionData::FIELD_EXECUTE => AjaxTransportBindingNonceActionTestDouble::SLUG,
				ActionData::FIELD_NONCE   => 'invalid_nonce',
			],
			[
				ActionData::FIELD_ACTION  => ActionData::FIELD_SHIELD,
				ActionData::FIELD_EXECUTE => AjaxTransportBindingNonceActionTestDouble::SLUG,
				ActionData::FIELD_NONCE   => $validNonce,
			]
		);

		$action = new AjaxTransportBindingNonceActionTestDouble( [
			ActionData::FIELD_NONCE => $validNonce,
		] );
		$action->process();

		$this->assertTrue( (bool)( $action->response()->payload()[ 'success' ] ?? false ) );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->this_req = (object)[
			'request_bypasses_all_restrictions' => false,
			'is_ip_blocked'                     => false,
			'wp_is_ajax'                        => true,
			'is_security_admin'                 => false,
		];
		PluginControllerInstaller::install( $controller );
	}

	private function installRequest( array $query, array $post ) :void {
		ServicesState::installItems( [
			'service_request' => new AjaxTransportBindingRequestStub( $query, $post ),
			'service_wpusers' => new UnitTestUsers( 1 ),
		] );
	}
}

class AjaxTransportBindingRequestStub extends Request {

	public function __construct( array $query, array $post ) {
		$this->query = $query;
		$this->post = $post;
	}

	public function ip() :string {
		return '127.0.0.1';
	}

	public function ts( bool $update = true ) :int {
		return 1700000000;
	}
}

class AjaxTransportBindingCaptureAjaxActionTestDouble extends CaptureAjaxAction {

	public function canRunForTest() :bool {
		return $this->canRun();
	}
}

class AjaxTransportBindingNonceActionTestDouble extends BaseAction {

	public const SLUG = 'ajax_transport_binding_test_action';

	protected function getMinimumUserAuthCapability() :string {
		return '';
	}

	protected function exec() {
		$this->response()
			->setPayload( [ 'success' => true ] )
			->setPayloadSuccess( true );
	}
}
