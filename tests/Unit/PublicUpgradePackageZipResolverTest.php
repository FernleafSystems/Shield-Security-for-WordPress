<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\PublicUpgradeArtifacts;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PublicUpgradePackageZipMetadata;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PublicUpgradePackageZipMetadataReader;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PublicUpgradePackageZipResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class PublicUpgradePackageZipResolverTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testBuildsPackageZipWhenPathOmitted() :void {
		$root = $this->createTrackedTempDir( 'shield-upgrade-resolver-root-' );
		$artifacts = PublicUpgradeArtifacts::resolve( $root, Path::join( $root, 'artifacts' ) );
		$artifacts->resetForRun();
		$runner = new RecordingProcessRunner( [ 0 ] );
		$reader = $this->createMock( PublicUpgradePackageZipMetadataReader::class );
		$reader->expects( $this->once() )
			   ->method( 'read' )
			   ->with( $artifacts->path( 'wp-simple-firewall-current.zip' ) )
			   ->willReturn( new PublicUpgradePackageZipMetadata( $artifacts->path( 'wp-simple-firewall-current.zip' ), '3.4.5', 'wp-simple-firewall/icwp-wpsf.php' ) );

		$metadata = ( new PublicUpgradePackageZipResolver( $runner, $reader ) )->resolve( $root, null, $artifacts );

		$this->assertSame( '3.4.5', $metadata->version() );
		$this->assertSame( \PHP_BINARY, $runner->calls[ 0 ][ 'command' ][ 0 ] ?? null );
		$this->assertContains( './bin/build-zip.php', $runner->calls[ 0 ][ 'command' ] );
		$this->assertContains( '--zip-root-folder=wp-simple-firewall', $runner->calls[ 0 ][ 'command' ] );
	}

	public function testReadsProvidedRelativePackageZipWithoutBuilding() :void {
		$root = $this->createTrackedTempDir( 'shield-upgrade-resolver-root-' );
		$artifacts = PublicUpgradeArtifacts::resolve( $root, Path::join( $root, 'artifacts' ) );
		$artifacts->resetForRun();
		$runner = new RecordingProcessRunner();
		$reader = $this->createMock( PublicUpgradePackageZipMetadataReader::class );
		$expectedPath = Path::join( $root, 'builds', 'shield.zip' );
		$reader->expects( $this->once() )
			   ->method( 'read' )
			   ->with( $expectedPath )
			   ->willReturn( new PublicUpgradePackageZipMetadata( $expectedPath, '3.4.5', 'wp-simple-firewall/icwp-wpsf.php' ) );

		( new PublicUpgradePackageZipResolver( $runner, $reader ) )->resolve( $root, 'builds/shield.zip', $artifacts );

		$this->assertSame( [], $runner->calls );
	}
}
