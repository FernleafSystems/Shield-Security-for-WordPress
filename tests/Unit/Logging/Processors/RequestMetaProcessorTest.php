<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Logging\Processors;

use Brain\Monkey\Functions;
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
use FernleafSystems\Wordpress\Services\Utilities\{
	IpUtils,
	ServiceProviders
};
use FernleafSystems\Wordpress\Services\Utilities\Net\{
	BaseIP,
	RequestIpDetect
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

	/**
	 * @dataProvider provider_real_request_detector_uses_canonical_ip
	 */
	public function test_real_request_detector_uses_canonical_ip_without_transport_attribution(
		string $preferredSource,
		array $server
	) :void {
		$this->prepareServer( $server );
		$request = ( new Request() )->setIpDetector(
			( new RequestIpDetect() )->setPreferredSource( $preferredSource )
		);
		$this->installRealRequestMetaServices( $request, $this->cloudflareProviders(), $this->ipUtils() );

		$this->assertSame( '8.8.8.8', $request->ip() );

		$meta = ( new RequestMetaProcessor() )( [ 'extra' => [] ] )[ 'extra' ][ 'meta_request' ];
		$this->assertSame( '8.8.8.8', $meta[ 'ip' ] );
		$this->assertArrayNotHasKey( 'ip_attribution', $meta );
		$this->assertArrayNotHasKey( 'ip_provider', $meta );
		$this->assertArrayNotHasKey( 'ip_source', $meta );
	}

	public function provider_real_request_detector_uses_canonical_ip() :array {
		return [
			'canonical Cloudflare header beats Cloudflare transport' => [
				'',
				[
					'REMOTE_ADDR'          => '173.245.48.5',
					'HTTP_CF_CONNECTING_IP' => '8.8.8.8',
				],
			],
			'preferred Cloudflare header is a normal browser request' => [
				'HTTP_CF_CONNECTING_IP',
				[
					'REMOTE_ADDR'          => '173.245.48.5',
					'HTTP_CF_CONNECTING_IP' => '8.8.8.8',
				],
			],
			'preferred remote address is a normal browser request' => [
				'REMOTE_ADDR',
				[
					'REMOTE_ADDR'          => '173.245.48.5',
					'HTTP_CF_CONNECTING_IP' => '8.8.8.8',
				],
			],
		];
	}

	/**
	 * @dataProvider provider_cloudflare_transport_addresses
	 */
	public function test_cloudflare_transport_is_logged_when_real_detector_has_no_canonical_ip( string $transportIp ) :void {
		$this->prepareServer( [
			'REMOTE_ADDR'          => $transportIp,
			'HTTP_CF_CONNECTING_IP' => $transportIp,
		] );
		$request = ( new Request() )->setIpDetector(
			( new RequestIpDetect() )->setPreferredSource( 'REMOTE_ADDR' )
		);
		$this->installRealRequestMetaServices( $request, $this->cloudflareProviders(), $this->ipUtils() );

		$this->assertSame( '', $request->ip() );

		$meta = ( new RequestMetaProcessor() )( [ 'extra' => [] ] )[ 'extra' ][ 'meta_request' ];
		$this->assertSame( $transportIp, $meta[ 'ip' ] );
		$this->assertSame( [
			'ip_attribution' => 'transport',
			'ip_provider'    => 'cloudflare',
			'ip_source'      => 'REMOTE_ADDR',
		], array_intersect_key( $meta, [
			'ip_attribution' => true,
			'ip_provider'    => true,
			'ip_source'      => true,
		] ) );
		$this->assertSame( '', $request->ip() );
	}

	public function provider_cloudflare_transport_addresses() :array {
		return [
			'IPv4' => [ '173.245.48.5' ],
			'IPv6' => [ '2400:cb00::1' ],
		];
	}

	/**
	 * @dataProvider provider_transport_attribution_rejections
	 */
	public function test_transport_attribution_rejections_leave_empty_canonical_ip( $remoteAddr, ServiceProviders $providers ) :void {
		$this->prepareServer( [ 'REMOTE_ADDR' => $remoteAddr ] );
		$this->installRealRequestMetaServices(
			$this->requestService( '/', '', 'transportreject' ),
			$providers,
			$this->ipUtils()
		);

		$meta = ( new RequestMetaProcessor() )( [ 'extra' => [] ] )[ 'extra' ][ 'meta_request' ];
		$this->assertSame( '', $meta[ 'ip' ] );
		$this->assertArrayNotHasKey( 'ip_attribution', $meta );
		$this->assertArrayNotHasKey( 'ip_provider', $meta );
		$this->assertArrayNotHasKey( 'ip_source', $meta );
	}

	public function provider_transport_attribution_rejections() :array {
		return [
			'not an IP address'      => [ 'not-an-ip', $this->cloudflareProviders() ],
			'private IP address'      => [ '10.0.0.1', $this->cloudflareProviders() ],
			'reserved IP address'     => [ '192.0.2.1', $this->cloudflareProviders() ],
			'public non-Cloudflare IP'=> [ '8.8.8.8', $this->cloudflareProviders() ],
			'non-string transport data'=> [ 123, $this->cloudflareProviders() ],
			'empty provider data'     => [ '173.245.48.5', $this->emptyProviders() ],
			'provider runtime failure'=> [ '173.245.48.5', $this->failingProviders() ],
		];
	}

	public function test_php_cli_cron_ignores_qualifying_cloudflare_transport_attribution() :void {
		$this->prepareServer( [ 'REMOTE_ADDR' => '173.245.48.5' ] );
		$this->installRealRequestMetaServices(
			$this->requestService( '', '', 'cloudflarecron' ),
			$this->cloudflareProviders(),
			$this->ipUtils(),
			$this->generalService( false, true )
		);

		$meta = ( new RequestMetaProcessor() )( [ 'extra' => [] ] )[ 'extra' ][ 'meta_request' ];
		$this->assertSame( ReqLogsHandler::TYPE_CRON, $meta[ 'type' ] );
		$this->assertSame( '127.0.0.1', $meta[ 'ip' ] );
		$this->assertSame( '/wp-cron.php', $meta[ 'path' ] );
		$this->assertArrayNotHasKey( 'ip_attribution', $meta );
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

	private function installRealRequestMetaServices(
		Request $request,
		ServiceProviders $providers,
		IpUtils $ipUtils,
		?General $general = null
	) :void {
		ServicesState::installItems( [
			'service_request'          => $request,
			'service_ip'               => $ipUtils,
			'service_serviceproviders' => $providers,
			'service_rest'             => new class extends Rest {
				public function isRest() :bool {
					return false;
				}
			},
			'service_wpgeneral'        => $general ?? $this->generalService( false, false ),
			'service_wpcomments'       => new class extends Comments {
				public function isCommentSubmission() :bool {
					return false;
				}
			},
		] );
	}

	private function prepareServer( array $server ) :void {
		$_SERVER = array_fill_keys( ( new BaseIP() )->getSources(), '' );
		$_SERVER = array_merge( $_SERVER, [
			'HTTP_HOST'       => 'example.test',
			'HTTP_USER_AGENT' => 'phpunit',
			'REQUEST_METHOD'  => 'GET',
			'REQUEST_URI'     => '/',
		], $server );
		$_GET = [];
		$_POST = [];
	}

	private function ipUtils() :IpUtils {
		return new class extends IpUtils {
			public function getServerPublicIPs( $forceRefresh = false ) :array {
				unset( $forceRefresh );
				return [];
			}
		};
	}

	private function cloudflareProviders() :ServiceProviders {
		return new class extends ServiceProviders {
			public function getProviders() :array {
				return [
					'services' => [
						'cloudflare' => [
							'name' => 'Cloudflare',
							'ips'  => [
								4 => [ '173.245.48.0/20' ],
								6 => [ '2400:cb00::/32' ],
							],
						],
					],
					'crawlers' => [],
				];
			}
		};
	}

	private function emptyProviders() :ServiceProviders {
		return new class extends ServiceProviders {
			public function getProviders() :array {
				return [];
			}
		};
	}

	private function failingProviders() :ServiceProviders {
		return new class extends ServiceProviders {
			public function getProviders() :array {
				throw new \RuntimeException( 'Provider data unavailable.' );
			}
		};
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

			public function server( $key, $default = null ) {
				return $_SERVER[ $key ] ?? $default;
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
}
