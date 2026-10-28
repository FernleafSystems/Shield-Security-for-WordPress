<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Request;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
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

class RequestTypeResolverTest extends BaseUnitTest {

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

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideRequestTypeScenarios' )]
	public function test_request_type_resolver_classifies_known_request_types( array $scenario ) :void {
		$this->installScenario( $scenario );

		$this->assertSame( $scenario[ 'expected_type' ], ( new RequestTypeResolver() )->resolve() );
	}

	public static function provideRequestTypeScenarios() :array {
		return [
			'wp cli'          => [ self::scenario( [ 'general' => [ 'wpCli' => true ], 'expected_type' => Handler::TYPE_WPCLI ] ) ],
			'ajax'            => [ self::scenario( [ 'general' => [ 'ajax' => true ], 'expected_type' => Handler::TYPE_AJAX ] ) ],
			'rest'            => [ self::scenario( [ 'rest' => true, 'rest_route' => 'wp/v2/users/me', 'expected_type' => Handler::TYPE_REST ] ) ],
			'shield mcp rest' => [ self::scenario( [ 'rest' => true, 'rest_route' => 'wp/v2/mcp-servers/shield-security/mcp', 'expected_type' => Handler::TYPE_MCP ] ) ],
			'xmlrpc'          => [ self::scenario( [ 'general' => [ 'xmlrpc' => true ], 'expected_type' => Handler::TYPE_XMLRPC ] ) ],
			'cron'            => [ self::scenario( [ 'general' => [ 'cron' => true ], 'expected_type' => Handler::TYPE_CRON ] ) ],
			'login'           => [ self::scenario( [ 'general' => [ 'loginRequest' => true ], 'expected_type' => Handler::TYPE_LOGIN ] ) ],
			'2fa query'       => [ self::scenario( [
				'method'        => 'POST',
				'general'       => [ 'loginUrl' => true ],
				'query'         => [ ActionData::FIELD_EXECUTE => ActionData::FIELD_SHIELD.'-wp_login_2fa_verify' ],
				'request'       => [ ActionData::FIELD_EXECUTE => 'wrong-post-value' ],
				'expected_type' => Handler::TYPE_2FA,
			] ) ],
			'comment'         => [ self::scenario( [ 'comment' => true, 'expected_type' => Handler::TYPE_COMMENT ] ) ],
			'http'            => [ self::scenario( [ 'expected_type' => Handler::TYPE_HTTP ] ) ],
		];
	}

	private function installScenario( array $scenario ) :void {
		UnitTestControllerFactory::install( null, null, (object)[
			'this_req' => new RequestTypeResolverThisRequestStub( $scenario[ 'rest_route' ] ),
		] );

		ServicesState::installItems( [
			'service_request'    => new RequestTypeResolverRequestStub(
				$scenario[ 'method' ],
				$scenario[ 'query' ],
				$scenario[ 'request' ]
			),
			'service_rest'       => new RequestTypeResolverRestStub( $scenario[ 'rest' ] ),
			'service_wpgeneral'  => new RequestTypeResolverGeneralStub( $scenario[ 'general' ] ),
			'service_wpcomments' => new RequestTypeResolverCommentsStub( $scenario[ 'comment' ] ),
		] );
	}

	private static function scenario( array $overrides = [] ) :array {
		return \array_replace_recursive( [
			'method'        => 'GET',
			'rest_route'    => '',
			'rest'          => false,
			'comment'       => false,
			'query'         => [],
			'request'       => [],
			'general'       => [
				'wpCli'        => false,
				'ajax'         => false,
				'xmlrpc'       => false,
				'cron'         => false,
				'loginRequest' => false,
				'loginUrl'     => false,
			],
			'expected_type' => Handler::TYPE_HTTP,
		], $overrides );
	}
}

class RequestTypeResolverThisRequestStub {

	private string $restRoute;

	public function __construct( string $restRoute ) {
		$this->restRoute = $restRoute;
	}

	public function getRestRoute() :string {
		return $this->restRoute;
	}
}

class RequestTypeResolverRequestStub extends Request {

	private string $method;

	private array $queryData;

	private array $requestData;

	public function __construct( string $method, array $queryData, array $requestData ) {
		$this->method = $method;
		$this->queryData = $queryData;
		$this->requestData = $requestData;
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

class RequestTypeResolverRestStub extends Rest {

	private bool $isRest;

	public function __construct( bool $isRest ) {
		$this->isRest = $isRest;
	}

	public function isRest() :bool {
		return $this->isRest;
	}
}

class RequestTypeResolverGeneralStub extends General {

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

class RequestTypeResolverCommentsStub extends Comments {

	private bool $isCommentSubmission;

	public function __construct( bool $isCommentSubmission ) {
		$this->isCommentSubmission = $isCommentSubmission;
	}

	public function isCommentSubmission() :bool {
		return $this->isCommentSubmission;
	}
}
