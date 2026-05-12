<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PackagerConfigResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class PackagerConfigResolverTest extends TestCase {

	use TempDirLifecycleTrait;

	/**
	 * @var array<string,string|false>
	 */
	private array $originalEnv = [];

	protected function setUp() :void {
		parent::setUp();
		foreach ( $this->envNames() as $envName ) {
			$this->originalEnv[ $envName ] = \getenv( $envName );
			\putenv( $envName );
		}
	}

	protected function tearDown() :void {
		foreach ( $this->originalEnv as $envName => $value ) {
			if ( \is_string( $value ) ) {
				\putenv( $envName.'='.$value );
			}
			else {
				\putenv( $envName );
			}
		}
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testResolveReturnsNullValuesWhenConfigMissing() :void {
		$this->assertSame(
			[
				'strauss_version' => null,
				'strauss_fork_repo' => null,
				'strauss_fork_branch' => null,
			],
			( new PackagerConfigResolver() )->resolve( $this->createTrackedTempDir( 'shield-packager-config-' ) )
		);
	}

	public function testResolveReadsConfigFile() :void {
		$rootDir = $this->createRootWithPackagerConfig( [
			'STRAUSS_VERSION="v0.19.4"',
			'STRAUSS_FORK_REPO="https://example.com/strauss.git"',
			'STRAUSS_FORK_BRANCH="feature/branch"',
		] );

		$this->assertSame(
			[
				'strauss_version' => '0.19.4',
				'strauss_fork_repo' => 'https://example.com/strauss.git',
				'strauss_fork_branch' => 'feature/branch',
			],
			( new PackagerConfigResolver() )->resolve( $rootDir )
		);
	}

	public function testResolveEnvironmentOverridesConfigFile() :void {
		$rootDir = $this->createRootWithPackagerConfig( [
			'STRAUSS_VERSION="0.19.4"',
			'STRAUSS_FORK_REPO="https://example.com/strauss.git"',
			'STRAUSS_FORK_BRANCH="feature/branch"',
		] );
		\putenv( 'SHIELD_STRAUSS_VERSION=v0.20.0' );
		\putenv( 'SHIELD_STRAUSS_FORK_REPO=https://env.example/strauss.git' );
		\putenv( 'SHIELD_STRAUSS_FORK_BRANCH=env/branch' );

		$this->assertSame(
			[
				'strauss_version' => '0.20.0',
				'strauss_fork_repo' => 'https://env.example/strauss.git',
				'strauss_fork_branch' => 'env/branch',
			],
			( new PackagerConfigResolver() )->resolve( $rootDir )
		);
	}

	public function testResolveDefaultsForkBranchWhenForkRepoExists() :void {
		$rootDir = $this->createRootWithPackagerConfig( [
			'STRAUSS_VERSION="0.19.4"',
			'STRAUSS_FORK_REPO="https://example.com/strauss.git"',
		] );

		$this->assertSame( 'develop', ( new PackagerConfigResolver() )->resolve( $rootDir )[ 'strauss_fork_branch' ] );
	}

	public function testResolveIgnoresForkBranchWithoutForkRepo() :void {
		$rootDir = $this->createRootWithPackagerConfig( [
			'STRAUSS_VERSION="0.19.4"',
			'STRAUSS_FORK_BRANCH="feature/branch"',
		] );

		$this->assertSame(
			[
				'strauss_version' => '0.19.4',
				'strauss_fork_repo' => null,
				'strauss_fork_branch' => null,
			],
			( new PackagerConfigResolver() )->resolve( $rootDir )
		);
	}

	/**
	 * @return string[]
	 */
	private function envNames() :array {
		return [
			'SHIELD_STRAUSS_VERSION',
			'SHIELD_STRAUSS_FORK_REPO',
			'SHIELD_STRAUSS_FORK_BRANCH',
		];
	}

	/**
	 * @param string[] $lines
	 */
	private function createRootWithPackagerConfig( array $lines ) :string {
		$rootDir = $this->createTrackedTempDir( 'shield-packager-config-' );
		$configDir = Path::join( $rootDir, '.github', 'config' );
		$this->assertTrue( \mkdir( $configDir, 0777, true ) );
		$this->assertNotFalse( \file_put_contents(
			Path::join( $configDir, 'packager.conf' ),
			\implode( \PHP_EOL, $lines ).\PHP_EOL
		) );

		return $rootDir;
	}
}
