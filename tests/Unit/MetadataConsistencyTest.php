<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Validates consistency for release metadata across active artifacts.
 */
class MetadataConsistencyTest extends TestCase {

	use PluginPathsTrait;

	public function testVersionMetadataIsConsistentAcrossActiveArtifacts() :void {
		$config = $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration' );

		$version = (string)( $config['properties']['version'] ?? '' );
		$this->assertNotSame( '', $version, 'Version should be defined in plugin.json properties.version' );
		$this->assertMatchesRegularExpression(
			'/^\d+(\.\d+)+$/',
			$version,
			'Version should use numeric dot-separated segments (e.g. 21.1.9)'
		);

		$pluginHeader = $this->getPluginFileContents( 'icwp-wpsf.php', 'Main plugin file' );
		$headerVersion = $this->extractPluginHeaderValue( $pluginHeader, 'Version' );
		$this->assertSame( $version, $headerVersion, 'Version in plugin.json should match icwp-wpsf.php header version' );

		if ( $this->isTestingPackage() ) {
			$readme = $this->getPluginFileContents( 'readme.txt', 'Plugin readme file' );
			$stableTag = $this->extractReadmeStableTag( $readme );
			$this->assertSame( $version, $stableTag, 'Version in plugin.json should match readme.txt Stable tag' );
		}
		else {
			$sourceProperties = $this->decodePluginJsonFile( 'plugin-spec/01_properties.json', 'Source properties spec' );
			$sourceVersion = (string)( $sourceProperties['version'] ?? '' );
			$this->assertNotSame( '', $sourceVersion, 'Version should be defined in plugin-spec/01_properties.json' );
			$this->assertSame( $version, $sourceVersion, 'Version in plugin-spec/01_properties.json should match plugin.json' );
		}
	}

	public function testTextDomainIsConsistentAcrossConfigurationAndHeader() :void {
		$config = $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration' );
		$textDomain = (string)( $config['properties']['text_domain'] ?? '' );
		$this->assertNotSame( '', $textDomain, 'Text domain should be defined in plugin.json properties.text_domain' );

		$pluginHeader = $this->getPluginFileContents( 'icwp-wpsf.php', 'Main plugin file' );
		$headerTextDomain = $this->extractPluginHeaderValue( $pluginHeader, 'Text Domain' );
		$this->assertSame( $textDomain, $headerTextDomain, 'Text domain in plugin.json should match icwp-wpsf.php header text domain' );
	}

	private function extractPluginHeaderValue( string $pluginContent, string $headerName ) :string {
		$pattern = sprintf(
			'/^\s*\*\s*%s:\s*(\S+)\s*$/mi',
			preg_quote( $headerName, '/' )
		);

		if ( !preg_match( $pattern, $pluginContent, $matches ) ) {
			$this->fail( sprintf( 'Failed to parse "%s" from icwp-wpsf.php plugin header', $headerName ) );
		}

		return trim( (string)$matches[ 1 ] );
	}

	private function extractReadmeStableTag( string $readmeContent ) :string {
		if ( !preg_match( '/^Stable tag:\s*(\S+)\s*$/mi', $readmeContent, $matches ) ) {
			$this->fail( 'Failed to parse "Stable tag" from readme.txt' );
		}

		return trim( (string)$matches[ 1 ] );
	}
}
