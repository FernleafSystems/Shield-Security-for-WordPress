<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Cli\ShieldCliApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ShieldCliApplicationTest extends TestCase {

	/** @var string[] */
	private array $originalArgv;

	protected function setUp() :void {
		parent::setUp();
		$this->originalArgv = $_SERVER[ 'argv' ] ?? [];
	}

	protected function tearDown() :void {
		$_SERVER[ 'argv' ] = $this->originalArgv;
		parent::tearDown();
	}

	public function testRequestedCommandFactoryLoadsWithoutInstantiatingUnrelatedCommands() :void {
		$invocations = [
			'pass' => 0,
			'explode' => 0,
		];

		$application = new ShieldCliApplication( __DIR__, [
			'pass' => static function () use ( &$invocations ) :Command {
				$invocations[ 'pass' ]++;
				return new class() extends Command {

					protected static $defaultName = 'pass';

					protected function execute( InputInterface $input, OutputInterface $output ) :int {
						return Command::SUCCESS;
					}
				};
			},
			'explode' => static function () use ( &$invocations ) :Command {
				$invocations[ 'explode' ]++;
				throw new \RuntimeException( 'This factory should not be invoked.' );
			},
		] );

		$_SERVER[ 'argv' ] = [ 'bin/shield', 'pass' ];

		$this->assertSame( 0, $application->run() );
		$this->assertSame( 1, $invocations[ 'pass' ] );
		$this->assertSame( 0, $invocations[ 'explode' ] );
	}
}
