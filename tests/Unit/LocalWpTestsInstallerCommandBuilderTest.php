<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Process\BashCommandResolver;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalWpTestsInstallerCommandBuilder;
use PHPUnit\Framework\TestCase;

class LocalWpTestsInstallerCommandBuilderTest extends TestCase {

	public function testBuildWindowsContainsExpectedParameters() :void {
		$builder = new LocalWpTestsInstallerCommandBuilder();

		$command = $builder->buildWindows(
			'wordpress_test_local',
			'root',
			'testpass',
			'127.0.0.1:3311',
			'latest'
		);

		$this->assertSame( 'powershell', $command[ 0 ] );
		$this->assertContains( '-DB_NAME', $command );
		$this->assertContains( 'wordpress_test_local', $command );
		$this->assertContains( '-DB_USER', $command );
		$this->assertContains( 'root', $command );
		$this->assertContains( '-DB_PASS', $command );
		$this->assertContains( 'testpass', $command );
		$this->assertContains( '-DB_HOST', $command );
		$this->assertContains( '127.0.0.1:3311', $command );
		$this->assertContains( '-WP_VERSION', $command );
		$this->assertContains( 'latest', $command );
	}

	public function testBuildNonWindowsUsesResolvedBashAndSkipDbFlag() :void {
		$builder = new LocalWpTestsInstallerCommandBuilder(
			new class() extends BashCommandResolver {
				public function resolve() :string {
					return '/resolved/bash';
				}
			}
		);

		$command = $builder->buildNonWindows(
			'wordpress_test_local',
			'root',
			'testpass',
			'127.0.0.1:3311',
			'latest',
			true
		);

		$this->assertSame(
			[
				'/resolved/bash',
				'./bin/install-wp-tests.sh',
				'wordpress_test_local',
				'root',
				'testpass',
				'127.0.0.1:3311',
				'latest',
				'true',
			],
			$command
		);
	}
}
