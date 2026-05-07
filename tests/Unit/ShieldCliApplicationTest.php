<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Cli\ShieldCliApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ShieldCliApplicationTest extends TestCase {

	public function testRequestedCommandFactoryLoadsWithoutInstantiatingUnrelatedCommands() :void {
		$invocations = [
			'pass' => 0,
			'explode' => 0,
		];

		$application = new ShieldCliApplication( __DIR__, [
			'pass' => static function () use ( &$invocations ) :Command {
				$invocations[ 'pass' ]++;
				return new class() extends Command {

					public function __construct() {
						parent::__construct( 'pass' );
					}

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

		$hadPhpSelf = \array_key_exists( 'PHP_SELF', $_SERVER );
		$originalPhpSelf = $_SERVER[ 'PHP_SELF' ] ?? null;
		$_SERVER[ 'PHP_SELF' ] ??= 'bin/shield';

		try {
			$output = new BufferedOutput();
			$this->assertSame( 0, $application->run( new ArrayInput( [ 'command' => 'pass' ] ), $output ), $output->fetch() );
		}
		finally {
			if ( $hadPhpSelf ) {
				$_SERVER[ 'PHP_SELF' ] = $originalPhpSelf;
			}
			else {
				unset( $_SERVER[ 'PHP_SELF' ] );
			}
		}

		$this->assertSame( 1, $invocations[ 'pass' ] );
		$this->assertSame( 0, $invocations[ 'explode' ] );
	}
}
