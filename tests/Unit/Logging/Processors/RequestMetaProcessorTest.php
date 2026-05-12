<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Logging\Processors;

use Brain\Monkey\Functions;
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
		$this->assertSame( 'P', $processor( $records )[ 'extra' ][ 'meta_request' ][ 'type' ] );

		$controller->this_req->restRoute = 'wp/v2/users/me';
		$this->assertSame( 'R', $processor( $records )[ 'extra' ][ 'meta_request' ][ 'type' ] );
	}
}
