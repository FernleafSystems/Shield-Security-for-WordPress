<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\TestingEnvironmentResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class TestingEnvironmentResolverTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testBuildDockerProcessEnvOverridesWithoutPackageUnset() :void {
		$resolver = new TestingEnvironmentResolver();

		$envOverrides = $resolver->buildDockerProcessEnvOverrides( 'shield-tests' );

		$this->assertSame(
			[
				'DOCKER_BUILDKIT' => '1',
				'MSYS_NO_PATHCONV' => '1',
				'COMPOSE_PROJECT_NAME' => 'shield-tests',
			],
			$envOverrides
		);
	}

	public function testBuildDockerProcessEnvOverridesWithPackageUnset() :void {
		$resolver = new TestingEnvironmentResolver();

		$envOverrides = $resolver->buildDockerProcessEnvOverrides( 'shield-local-db', true );

		$this->assertSame(
			[
				'DOCKER_BUILDKIT' => '1',
				'MSYS_NO_PATHCONV' => '1',
				'COMPOSE_PROJECT_NAME' => 'shield-local-db',
				'SHIELD_PACKAGE_PATH' => false,
			],
			$envOverrides
		);
	}

	public function testDetectWordpressVersionsParsesMachineOutput() :void {
		$processRunner = new RecordingProcessRunner( [ [
			'exit_code' => 0,
			'stdout' => "Detector startup noise\nLATEST_VERSION=9.4.2\nPREVIOUS_VERSION=9.3.7\nDetector complete\n",
		] ] );
		$resolver = new TestingEnvironmentResolver( $processRunner );

		$this->assertSame( [ '9.4.2', '9.3.7' ], $this->detectWordpressVersionsSilenced( $resolver ) );
		$this->assertSame( './.github/scripts/detect-wp-versions.sh', $processRunner->calls[ 0 ][ 'command' ][ 1 ] );
		$this->assertSame( '/project-root', $processRunner->calls[ 0 ][ 'working_dir' ] );
	}

	public function testDetectWordpressVersionsFailsWhenDetectorFails() :void {
		$resolver = new TestingEnvironmentResolver( new RecordingProcessRunner( [ [
			'exit_code' => 1,
		] ] ) );

		$this->expectExceptionMessage( 'WordPress version detection failed.' );
		$this->detectWordpressVersionsSilenced( $resolver );
	}

	/**
	 * @dataProvider providerMalformedWordpressVersionOutput
	 */
	public function testDetectWordpressVersionsFailsForMalformedMachineOutput( string $output ) :void {
		$resolver = new TestingEnvironmentResolver( new RecordingProcessRunner( [ [
			'exit_code' => 0,
			'stdout' => $output,
		] ] ) );

		$this->expectExceptionMessage( 'Could not parse LATEST_VERSION/PREVIOUS_VERSION from detect-wp-versions.sh output.' );
		$this->detectWordpressVersionsSilenced( $resolver );
	}

	/**
	 * @return array<string,array{string}>
	 */
	public function providerMalformedWordpressVersionOutput() :array {
		return [
			'missing-previous' => [ "LATEST_VERSION=9.4.2\n" ],
			'empty-latest' => [ "LATEST_VERSION=\nPREVIOUS_VERSION=9.3.7\n" ],
		];
	}

	/**
	 * @return array{string,string}
	 */
	private function detectWordpressVersionsSilenced( TestingEnvironmentResolver $resolver ) :array {
		\ob_start();
		try {
			return $resolver->detectWordpressVersions( '/project-root' );
		}
		finally {
			\ob_end_clean();
		}
	}

	public function testResolvePackagerConfigReadsForkBranch() :void {
		$rootDir = $this->createRootWithPackagerConfig( [
			'STRAUSS_VERSION="0.19.4"',
			'STRAUSS_FORK_REPO="https://example.com/strauss.git"',
			'STRAUSS_FORK_BRANCH="feature/branch"',
		] );

		$resolver = new TestingEnvironmentResolver();

		$this->assertSame(
			[
				'strauss_version' => '0.19.4',
				'strauss_fork_repo' => 'https://example.com/strauss.git',
				'strauss_fork_branch' => 'feature/branch',
			],
			$resolver->resolvePackagerConfig( $rootDir )
		);
	}

	public function testResolvePackagerConfigDefaultsForkBranchWhenForkRepoExists() :void {
		$rootDir = $this->createRootWithPackagerConfig( [
			'STRAUSS_VERSION="0.19.4"',
			'STRAUSS_FORK_REPO="https://example.com/strauss.git"',
		] );

		$resolver = new TestingEnvironmentResolver();

		$this->assertSame( 'develop', $resolver->resolvePackagerConfig( $rootDir )[ 'strauss_fork_branch' ] );
	}

	public function testResolvePackagerConfigIgnoresForkBranchWithoutForkRepo() :void {
		$rootDir = $this->createRootWithPackagerConfig( [
			'STRAUSS_VERSION="0.19.4"',
			'STRAUSS_FORK_BRANCH="feature/branch"',
		] );

		$resolver = new TestingEnvironmentResolver();

		$this->assertNull( $resolver->resolvePackagerConfig( $rootDir )[ 'strauss_fork_branch' ] );
	}

	/**
	 * @param string[] $lines
	 */
	private function createRootWithPackagerConfig( array $lines ) :string {
		$rootDir = $this->createTrackedTempDir( 'shield-testing-env-' );
		$configDir = Path::join( $rootDir, '.github', 'config' );
		$this->assertTrue( \mkdir( $configDir, 0777, true ) );
		$this->assertNotFalse( \file_put_contents(
			Path::join( $configDir, 'packager.conf' ),
			\implode( \PHP_EOL, $lines ).\PHP_EOL
		) );

		return $rootDir;
	}
}
