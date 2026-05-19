<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class MixedVersionMemberCompatibilityTest extends TestCase {

	public function testBotTrack404PriorityDoesNotRequireV22HookTimingsConstant() :void {
		$output = $this->runPhp( <<<'PHP'
namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin {
	class HookTimings {
		public const INIT_DEFAULT_RULES_HOOK = -2000;
	}
}

namespace {
	require 'vendor/autoload.php';

	$rule = new \FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core\BotTrack404();
	$method = new \ReflectionMethod( $rule, 'getWpHookPriority' );
	$method->setAccessible( true );
	echo $method->invoke( $rule );
}
PHP
		);

		$this->assertSame( '1001', \trim( $output ) );
	}

	public function testRestApiRuleConditionsDoNotRequireV22RequestRestRouteMethod() :void {
		$output = $this->runPhp( <<<'PHP'
namespace {
	function is_ssl() {
		return false;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Request {
	class ThisRequest {
		public object $request;
		public string $path = 'wp-json/shield/v1/probe';
		public bool $wp_is_permalinks_enabled = true;
		public string $rest_api_root = 'http://example.test/wp-json/';
	}
}

namespace {
	require 'vendor/autoload.php';

	$request = new \FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequest();
	$request->request = (object)[
		'server' => [
			'HTTP_HOST' => 'example.test',
		],
		'query'  => [],
	];

	$condition = new class() extends \FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\BaseRequestToRestAPI {
		public function route() :string {
			return $this->getRestRoute();
		}
	};

	echo $condition->setThisRequest( $request )->route();
}
PHP
		);

		$this->assertSame( 'shield/v1/probe', \trim( $output ) );
	}

	private function runPhp( string $code ) :string {
		$process = new Process( [ \PHP_BINARY, '-r', $code ], $this->projectRoot() );
		$process->run();

		$this->assertSame(
			0,
			$process->getExitCode(),
			"Subprocess failed.\nSTDOUT:\n".$process->getOutput()."\nSTDERR:\n".$process->getErrorOutput()
		);

		return $process->getOutput();
	}

	private function projectRoot() :string {
		return \str_replace( '\\', '/', \dirname( __DIR__, 2 ) );
	}
}
