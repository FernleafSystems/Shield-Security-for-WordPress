<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\PublicUpgradeArtifacts;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class PublicUpgradeArtifactsTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		\putenv( 'SHIELD_UPGRADE_TEST_ARTIFACT_DIR' );
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testExplicitArtifactDirWinsAndWritesSummaryJson() :void {
		$root = $this->createTrackedTempDir( 'shield-upgrade-artifacts-root-' );
		$dir = Path::join( $root, 'custom-artifacts' );

		$artifacts = PublicUpgradeArtifacts::resolve( $root, $dir );
		$artifacts->resetForRun();
		$artifacts->writeJson( PublicUpgradeArtifacts::SUMMARY_FILE, [ 'status' => 'pass' ] );

		$this->assertSame( $dir, $artifacts->dir() );
		$this->assertFileExists( Path::join( $dir, PublicUpgradeArtifacts::SUMMARY_FILE ) );
	}

	public function testEnvironmentArtifactDirWinsOverDefault() :void {
		$root = $this->createTrackedTempDir( 'shield-upgrade-artifacts-root-' );
		$dir = Path::join( $root, 'env-artifacts' );
		\putenv( 'SHIELD_UPGRADE_TEST_ARTIFACT_DIR='.$dir );

		$artifacts = PublicUpgradeArtifacts::resolve( $root, null );

		$this->assertSame( $dir, $artifacts->dir() );
	}

	public function testResetRemovesKnownFilesOnly() :void {
		$root = $this->createTrackedTempDir( 'shield-upgrade-artifacts-root-' );
		$artifacts = PublicUpgradeArtifacts::resolve( $root, null );
		$artifacts->resetForRun();

		\file_put_contents( $artifacts->path( PublicUpgradeArtifacts::WP_CLI_LOG_FILE ), 'old' );
		\file_put_contents( Path::join( $artifacts->dir(), 'keep.txt' ), 'keep' );
		$artifacts->resetForRun();

		$this->assertFileDoesNotExist( $artifacts->path( PublicUpgradeArtifacts::WP_CLI_LOG_FILE ) );
		$this->assertFileExists( Path::join( $artifacts->dir(), 'keep.txt' ) );
	}

	public function testEnsureFileExistsCreatesEmptyArtifactFile() :void {
		$root = $this->createTrackedTempDir( 'shield-upgrade-artifacts-root-' );
		$artifacts = PublicUpgradeArtifacts::resolve( $root, null );
		$artifacts->resetForRun();

		$artifacts->ensureFileExists( PublicUpgradeArtifacts::ERROR_EVENTS_FILE );

		$this->assertFileExists( $artifacts->path( PublicUpgradeArtifacts::ERROR_EVENTS_FILE ) );
		$this->assertSame( '', (string)\file_get_contents( $artifacts->path( PublicUpgradeArtifacts::ERROR_EVENTS_FILE ) ) );
	}
}
