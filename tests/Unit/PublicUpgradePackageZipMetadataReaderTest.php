<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\PublicUpgradePackageZipMetadataReader;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class PublicUpgradePackageZipMetadataReaderTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function setUp() :void {
		parent::setUp();
		if ( !\class_exists( \ZipArchive::class ) ) {
			$this->markTestSkipped( 'ZipArchive extension is required.' );
		}
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testReadsVersionFromPluginJson() :void {
		$zip = $this->buildZip( [
			'wp-simple-firewall/icwp-wpsf.php' => "<?php\n/*\nVersion: 1.2.3\n*/",
			'wp-simple-firewall/plugin.json' => '{"properties":{"version":"9.8.7"}}',
		] );

		$metadata = ( new PublicUpgradePackageZipMetadataReader() )->read( $zip );

		$this->assertSame( $zip, $metadata->zipPath() );
		$this->assertSame( '9.8.7', $metadata->version() );
		$this->assertSame( 'wp-simple-firewall/icwp-wpsf.php', $metadata->pluginFile() );
	}

	public function testReadsVersionFromPluginHeaderWhenPluginJsonMissing() :void {
		$zip = $this->buildZip( [
			'wp-simple-firewall/icwp-wpsf.php' => "<?php\n/*\nVersion: 2.3.4\n*/",
		] );

		$metadata = ( new PublicUpgradePackageZipMetadataReader() )->read( $zip );

		$this->assertSame( '2.3.4', $metadata->version() );
	}

	public function testRequiresWordPressOrgRootFolderShape() :void {
		$zip = $this->buildZip( [
			'icwp-wpsf.php' => "<?php\n/*\nVersion: 2.3.4\n*/",
		] );

		$this->expectExceptionMessage( 'Package zip is missing wp-simple-firewall/icwp-wpsf.php.' );

		( new PublicUpgradePackageZipMetadataReader() )->read( $zip );
	}

	/**
	 * @param array<string,string> $entries
	 */
	private function buildZip( array $entries ) :string {
		$dir = $this->createTrackedTempDir( 'shield-upgrade-zip-' );
		$path = Path::join( $dir, 'package.zip' );
		$zip = new \ZipArchive();
		$this->assertTrue( $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) );
		foreach ( $entries as $name => $contents ) {
			$zip->addFromString( $name, $contents );
		}
		$zip->close();
		return $path;
	}
}
