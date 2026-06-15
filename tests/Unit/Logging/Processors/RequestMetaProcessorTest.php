<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Logging\Processors;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler as ReqLogsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors\RequestMetaProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	McpTestControllerFactory,
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	Comments,
	General,
	Request,
	Rest
};

class RequestMetaProcessorTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();

		Functions\when( 'sanitize_text_field' )->returnArg();

		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		$_GET = [];
		$_POST = [];
		$_SERVER = [];

		parent::tearDown();
	}

	public function test_invoke_classifies_shield_mcp_routes_as_mcp_and_other_rest_routes_as_rest() :void {
		$controller = McpTestControllerFactory::install();
		$controller->this_req = new class {
			public string $restRoute = '';

			public function getRestRoute() :string {
				return $this->restRoute;
			}
		};

		$request = new class extends Request {
			public function getPath() :string {
				return '/';
			}

			public function getID( bool $sub = false, int $length = 10 ) :string {
				unset( $sub, $length );
				return 'requestid01';
			}

			public function ip() :string {
				return '198.51.100.25';
			}

			public function getUserAgent() :string {
				return 'phpunit';
			}

			public function getMethod() :string {
				return 'post';
			}
		};

		ServicesState::installItems( [
			'service_request'    => $request,
			'service_rest'       => new class extends Rest {
				public bool $isRest = true;

				public function isRest() :bool {
					return $this->isRest;
				}
			},
			'service_wpgeneral'  => new class extends General {
				public function isWpCli() :bool {
					return false;
				}

				public function isMultisite_SubdomainInstall() :bool {
					return false;
				}

				public function isAjax() :bool {
					return false;
				}

				public function isXmlrpc() :bool {
					return false;
				}

				public function isCron() :bool {
					return false;
				}

				public function isLoginRequest() :bool {
					return false;
				}

				public function isLoginUrl() :bool {
					return false;
				}
			},
			'service_wpcomments' => new class extends Comments {
				public function isCommentSubmission() :bool {
					return false;
				}
			},
		] );

		$processor = new RequestMetaProcessor();
		$records = [ 'extra' => [] ];

		$controller->this_req->restRoute = 'wp/v2/mcp-servers/shield-security/mcp';
		$this->assertSame( ReqLogsHandler::TYPE_MCP, $processor( $records )[ 'extra' ][ 'meta_request' ][ 'type' ] );

		$controller->this_req->restRoute = 'wp/v2/users/me';
		$this->assertSame( ReqLogsHandler::TYPE_REST, $processor( $records )[ 'extra' ][ 'meta_request' ][ 'type' ] );
	}

	public function test_php_cli_cron_without_detected_ip_uses_loopback_identity_without_changing_cron_type() :void {
		$this->installRequestMetaServices(
			$this->requestService( '', '', 'cronreq001' ),
			$this->generalService( false, true )
		);

		$meta = ( new RequestMetaProcessor() )( [ 'extra' => [] ] )[ 'extra' ][ 'meta_request' ];

		$this->assertSame( ReqLogsHandler::TYPE_CRON, $meta[ 'type' ] );
		$this->assertSame( '127.0.0.1', $meta[ 'ip' ] );
		$this->assertSame( '/wp-cron.php', $meta[ 'path' ] );
		$this->assertSame( 0, $meta[ 'has_params' ] );
		$this->assertArrayNotHasKey( 'query', $meta );
	}

	public function test_php_cli_cron_without_detected_ip_preserves_non_empty_path() :void {
		$this->installRequestMetaServices(
			$this->requestService( '/server-cron.php', '', 'cronreq002' ),
			$this->generalService( false, true )
		);

		$meta = ( new RequestMetaProcessor() )( [ 'extra' => [] ] )[ 'extra' ][ 'meta_request' ];

		$this->assertSame( ReqLogsHandler::TYPE_CRON, $meta[ 'type' ] );
		$this->assertSame( '127.0.0.1', $meta[ 'ip' ] );
		$this->assertSame( '/server-cron.php', $meta[ 'path' ] );
	}

	public function test_php_cli_cron_preserves_valid_detected_identity() :void {
		$this->installRequestMetaServices(
			$this->requestService( '/custom-cron.php', '198.51.100.45', 'cronreq003' ),
			$this->generalService( false, true )
		);

		$meta = ( new RequestMetaProcessor() )( [ 'extra' => [] ] )[ 'extra' ][ 'meta_request' ];

		$this->assertSame( ReqLogsHandler::TYPE_CRON, $meta[ 'type' ] );
		$this->assertSame( '198.51.100.45', $meta[ 'ip' ] );
		$this->assertSame( '/custom-cron.php', $meta[ 'path' ] );
	}

	public function test_php_cli_cron_with_detected_ip_keeps_empty_path_unchanged() :void {
		$this->installRequestMetaServices(
			$this->requestService( '', '198.51.100.46', 'cronreq004' ),
			$this->generalService( false, true )
		);

		$meta = ( new RequestMetaProcessor() )( [ 'extra' => [] ] )[ 'extra' ][ 'meta_request' ];

		$this->assertSame( ReqLogsHandler::TYPE_CRON, $meta[ 'type' ] );
		$this->assertSame( '198.51.100.46', $meta[ 'ip' ] );
		$this->assertSame( '', $meta[ 'path' ] );
	}

	public function test_wp_cli_metadata_still_uses_existing_loopback_identity() :void {
		global $argv;
		$originalArgv = $argv ?? null;
		$argv = [ 'wp', 'shield', 'scan' ];

		try {
			$this->installRequestMetaServices(
				$this->requestService( '/ignored-http-path', '198.51.100.46', 'wpclireq01' ),
				$this->generalService( true, true )
			);

			$meta = ( new RequestMetaProcessor() )( [ 'extra' => [] ] )[ 'extra' ][ 'meta_request' ];
		}
		finally {
			if ( $originalArgv === null ) {
				unset( $argv );
			}
			else {
				$argv = $originalArgv;
			}
		}

		$this->assertSame( ReqLogsHandler::TYPE_WPCLI, $meta[ 'type' ] );
		$this->assertSame( '127.0.0.1', $meta[ 'ip' ] );
		$this->assertSame( 'wp', $meta[ 'path' ] );
		$this->assertSame( 'shield scan', $meta[ 'query' ] );
		$this->assertSame( 1, $meta[ 'has_params' ] );
		$this->assertArrayNotHasKey( 'ua', $meta );
		$this->assertArrayNotHasKey( 'code', $meta );
		$this->assertArrayNotHasKey( 'verb', $meta );
	}

	private function installRequestMetaServices( Request $request, General $general ) :void {
		ServicesState::installItems( [
			'service_request'    => $request,
			'service_rest'       => new class extends Rest {
				public function isRest() :bool {
					return false;
				}
			},
			'service_wpgeneral'  => $general,
			'service_wpcomments' => new class extends Comments {
				public function isCommentSubmission() :bool {
					return false;
				}
			},
		] );
	}

	private function requestService( string $path, string $ip, string $requestID ) :Request {
		return new class( $path, $ip, $requestID ) extends Request {
			private string $path;

			private string $ip;

			private string $requestID;

			public function __construct( string $path, string $ip, string $requestID ) {
				$this->path = $path;
				$this->ip = $ip;
				$this->requestID = $requestID;
			}

			public function getPath() :string {
				return $this->path;
			}

			public function getID( bool $sub = false, int $length = 10 ) :string {
				unset( $sub, $length );
				return $this->requestID;
			}

			public function ip() :string {
				return $this->ip;
			}

			public function getUserAgent() :string {
				return '';
			}

			public function getMethod() :string {
				return '';
			}
		};
	}

	private function generalService( bool $isWpCli, bool $isCron ) :General {
		return new class( $isWpCli, $isCron ) extends General {
			private bool $isWpCli;

			private bool $isCron;

			public function __construct( bool $isWpCli, bool $isCron ) {
				$this->isWpCli = $isWpCli;
				$this->isCron = $isCron;
			}

			public function isWpCli() :bool {
				return $this->isWpCli;
			}

			public function isMultisite_SubdomainInstall() :bool {
				return false;
			}

			public function isAjax() :bool {
				return false;
			}

			public function isXmlrpc() :bool {
				return false;
			}

			public function isCron() :bool {
				return $this->isCron;
			}

			public function isLoginRequest() :bool {
				return false;
			}

			public function isLoginUrl() :bool {
				return false;
			}
		};
	}

	public function test_2fa_request_classification_uses_query_execute_value_only() :void {
		McpTestControllerFactory::install()->this_req = new class {
			public function getRestRoute() :string {
				return '';
			}
		};

		$request = new class extends Request {
			public function getPath() :string {
				return '/wp-login.php';
			}

			public function getID( bool $sub = false, int $length = 10 ) :string {
				unset( $sub, $length );
				return 'requestid02';
			}

			public function ip() :string {
				return '198.51.100.26';
			}

			public function getUserAgent() :string {
				return 'phpunit';
			}

			public function getMethod() :string {
				return 'POST';
			}

			public function isPost() :bool {
				return true;
			}

			public function query( $key, $default = null ) {
				return $key === ActionData::FIELD_EXECUTE ? ActionData::FIELD_SHIELD.'-wp_login_2fa_verify' : $default;
			}

			public function request( $key, $includeCookies = false, $default = null ) {
				unset( $includeCookies );
				return $key === ActionData::FIELD_EXECUTE ? 'wrong-post-value' : $default;
			}
		};

		ServicesState::installItems( [
			'service_request'    => $request,
			'service_rest'       => new class extends Rest {
				public function isRest() :bool {
					return false;
				}
			},
			'service_wpgeneral'  => new class extends General {
				public function isWpCli() :bool {
					return false;
				}

				public function isMultisite_SubdomainInstall() :bool {
					return false;
				}

				public function isAjax() :bool {
					return false;
				}

				public function isXmlrpc() :bool {
					return false;
				}

				public function isCron() :bool {
					return false;
				}

				public function isLoginRequest() :bool {
					return false;
				}

				public function isLoginUrl() :bool {
					return true;
				}
			},
			'service_wpcomments' => new class extends Comments {
				public function isCommentSubmission() :bool {
					return false;
				}
			},
		] );

		$records = [ 'extra' => [] ];
		$this->assertSame(
			ReqLogsHandler::TYPE_2FA,
			( new RequestMetaProcessor() )( $records )[ 'extra' ][ 'meta_request' ][ 'type' ]
		);
	}
}
