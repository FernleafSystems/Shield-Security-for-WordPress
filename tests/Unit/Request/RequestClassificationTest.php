<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Request;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\{
	RequestProfile,
	RequestProfileBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Request\RequestTypeResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory
};
use FernleafSystems\Wordpress\Services\Core\{
	Comments,
	General,
	Request,
	Rest
};

class RequestClassificationTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();

		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	/**
	 * @dataProvider provideRequestTypeScenarios
	 */
	public function test_request_type_resolver_classifies_known_request_types( array $scenario ) :void {
		$this->installScenario( $scenario );

		$this->assertSame( $scenario[ 'expected_type' ], ( new RequestTypeResolver() )->resolve() );
	}

	public function provideRequestTypeScenarios() :array {
		return [
			'wp cli'          => [ $this->scenario( [ 'general' => [ 'wpCli' => true ], 'expected_type' => Handler::TYPE_WPCLI ] ) ],
			'ajax'            => [ $this->scenario( [ 'general' => [ 'ajax' => true ], 'expected_type' => Handler::TYPE_AJAX ] ) ],
			'rest'            => [ $this->scenario( [ 'rest' => true, 'rest_route' => 'wp/v2/users/me', 'expected_type' => Handler::TYPE_REST ] ) ],
			'shield mcp rest' => [ $this->scenario( [ 'rest' => true, 'rest_route' => 'wp/v2/mcp-servers/shield-security/mcp', 'expected_type' => Handler::TYPE_MCP ] ) ],
			'xmlrpc'          => [ $this->scenario( [ 'general' => [ 'xmlrpc' => true ], 'expected_type' => Handler::TYPE_XMLRPC ] ) ],
			'cron'            => [ $this->scenario( [ 'general' => [ 'cron' => true ], 'expected_type' => Handler::TYPE_CRON ] ) ],
			'login'           => [ $this->scenario( [ 'general' => [ 'loginRequest' => true ], 'expected_type' => Handler::TYPE_LOGIN ] ) ],
			'2fa query'       => [ $this->scenario( [
				'method'        => 'POST',
				'general'       => [ 'loginUrl' => true ],
				'query'         => [ ActionData::FIELD_EXECUTE => ActionData::FIELD_SHIELD.'-wp_login_2fa_verify' ],
				'request'       => [ ActionData::FIELD_EXECUTE => 'wrong-post-value' ],
				'expected_type' => Handler::TYPE_2FA,
			] ) ],
			'comment'         => [ $this->scenario( [ 'comment' => true, 'expected_type' => Handler::TYPE_COMMENT ] ) ],
			'http'            => [ $this->scenario( [ 'expected_type' => Handler::TYPE_HTTP ] ) ],
		];
	}

	/**
	 * @dataProvider provideRequestProfileScenarios
	 */
	public function test_request_profile_builder_derives_fixed_surfaces( array $scenario ) :void {
		$this->installScenario( $scenario );

		$profile = ( new RequestProfileBuilder() )->build();

		$this->assertSame( $scenario[ 'expected_surface' ], $profile->surface );
		$this->assertSame( \strtoupper( $scenario[ 'method' ] ), $profile->method );
	}

	public function provideRequestProfileScenarios() :array {
		return [
			'public read'      => [ $this->scenario( [ 'method' => 'GET', 'path' => '/blog/post/', 'expected_surface' => RequestProfile::SURFACE_PUBLIC_READ ] ) ],
			'api read'         => [ $this->scenario( [ 'method' => 'GET', 'rest' => true, 'rest_route' => 'wp/v2/posts', 'expected_surface' => RequestProfile::SURFACE_API_READ ] ) ],
			'ajax read'        => [ $this->scenario( [ 'method' => 'GET', 'general' => [ 'ajax' => true ], 'expected_surface' => RequestProfile::SURFACE_API_READ ] ) ],
			'auth attempt'     => [ $this->scenario( [ 'method' => 'POST', 'general' => [ 'loginRequest' => true ], 'expected_surface' => RequestProfile::SURFACE_AUTH_ATTEMPT ] ) ],
			'content mutation' => [ $this->scenario( [ 'method' => 'POST', 'path' => '/submit/', 'expected_surface' => RequestProfile::SURFACE_CONTENT_MUTATION ] ) ],
			'comment mutation' => [ $this->scenario( [ 'method' => 'POST', 'comment' => true, 'expected_surface' => RequestProfile::SURFACE_CONTENT_MUTATION ] ) ],
			'admin mutation'   => [ $this->scenario( [ 'method' => 'POST', 'wp_admin' => true, 'expected_surface' => RequestProfile::SURFACE_ADMIN_MUTATION ] ) ],
			'ajax mutation'    => [ $this->scenario( [ 'method' => 'POST', 'general' => [ 'ajax' => true ], 'expected_surface' => RequestProfile::SURFACE_ADMIN_MUTATION ] ) ],
			'api mutation'     => [ $this->scenario( [ 'method' => 'PUT', 'rest' => true, 'rest_route' => 'wp/v2/posts/1', 'expected_surface' => RequestProfile::SURFACE_API_MUTATION ] ) ],
			'xmlrpc'           => [ $this->scenario( [ 'method' => 'POST', 'general' => [ 'xmlrpc' => true ], 'expected_surface' => RequestProfile::SURFACE_XMLRPC ] ) ],
			'shield action'    => [ $this->scenario( [
				'method'           => 'POST',
				'request'          => [
					ActionData::FIELD_ACTION  => ActionData::FIELD_SHIELD,
					ActionData::FIELD_EXECUTE => 'render_test_action',
				],
				'expected_surface' => RequestProfile::SURFACE_SHIELD_ACTION,
			] ) ],
			'probe'            => [ $this->scenario( [ 'method' => 'GET', 'path' => '/.env', 'expected_surface' => RequestProfile::SURFACE_PROBE ] ) ],
		];
	}

	private function installScenario( array $scenario ) :void {
		UnitTestControllerFactory::install( null, null, (object)[
			'this_req' => new RequestClassificationThisRequestStub(
				$scenario[ 'path' ],
				$scenario[ 'rest_route' ],
				$scenario[ 'wp_admin' ]
			),
		] );

		ServicesState::installItems( [
			'service_request'    => new RequestClassificationRequestStub(
				$scenario[ 'method' ],
				$scenario[ 'path' ],
				$scenario[ 'query' ],
				$scenario[ 'request' ]
			),
			'service_rest'       => new RequestClassificationRestStub( $scenario[ 'rest' ] ),
			'service_wpgeneral'  => new RequestClassificationGeneralStub( $scenario[ 'general' ] ),
			'service_wpcomments' => new RequestClassificationCommentsStub( $scenario[ 'comment' ] ),
		] );
	}

	private function scenario( array $overrides = [] ) :array {
		return \array_replace_recursive( [
			'method'           => 'GET',
			'path'             => '/',
			'rest_route'       => '',
			'wp_admin'         => false,
			'rest'             => false,
			'comment'          => false,
			'query'            => [],
			'request'          => [],
			'general'          => [
				'wpCli'        => false,
				'ajax'         => false,
				'xmlrpc'       => false,
				'cron'         => false,
				'loginRequest' => false,
				'loginUrl'     => false,
			],
			'expected_type'    => Handler::TYPE_HTTP,
			'expected_surface' => RequestProfile::SURFACE_PUBLIC_READ,
		], $overrides );
	}
}

class RequestClassificationThisRequestStub {

	public string $path;

	public bool $wp_is_admin;

	private string $restRoute;

	public function __construct( string $path, string $restRoute, bool $wpAdmin ) {
		$this->path = $path;
		$this->restRoute = $restRoute;
		$this->wp_is_admin = $wpAdmin;
	}

	public function getRestRoute() :string {
		return $this->restRoute;
	}
}

class RequestClassificationRequestStub extends Request {

	private string $method;

	private string $path;

	private array $queryData;

	private array $requestData;

	public function __construct( string $method, string $path, array $queryData, array $requestData ) {
		$this->method = $method;
		$this->path = $path;
		$this->queryData = $queryData;
		$this->requestData = $requestData;
	}

	public function getMethod() :string {
		return $this->method;
	}

	public function getPath() :string {
		return $this->path;
	}

	public function isPost() :bool {
		return \strtoupper( $this->method ) === 'POST';
	}

	public function query( $key, $default = null ) {
		return $this->queryData[ $key ] ?? $default;
	}

	public function request( $key, $includeCookies = false, $default = null ) {
		unset( $includeCookies );
		return $this->requestData[ $key ] ?? $default;
	}
}

class RequestClassificationRestStub extends Rest {

	private bool $isRest;

	public function __construct( bool $isRest ) {
		$this->isRest = $isRest;
	}

	public function isRest() :bool {
		return $this->isRest;
	}
}

class RequestClassificationGeneralStub extends General {

	private array $flags;

	public function __construct( array $flags ) {
		$this->flags = $flags;
	}

	public function isWpCli() :bool {
		return (bool)$this->flags[ 'wpCli' ];
	}

	public function isAjax() :bool {
		return (bool)$this->flags[ 'ajax' ];
	}

	public function isXmlrpc() :bool {
		return (bool)$this->flags[ 'xmlrpc' ];
	}

	public function isCron() :bool {
		return (bool)$this->flags[ 'cron' ];
	}

	public function isLoginRequest() :bool {
		return (bool)$this->flags[ 'loginRequest' ];
	}

	public function isLoginUrl() :bool {
		return (bool)$this->flags[ 'loginUrl' ];
	}
}

class RequestClassificationCommentsStub extends Comments {

	private bool $isCommentSubmission;

	public function __construct( bool $isCommentSubmission ) {
		$this->isCommentSubmission = $isCommentSubmission;
	}

	public function isCommentSubmission() :bool {
		return $this->isCommentSubmission;
	}
}
