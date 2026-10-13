<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\MinimumRequirements;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Validates consistency for release metadata across active artifacts.
 */
class MetadataConsistencyTest extends TestCase {

	use PluginPathsTrait;

	public function testVersionMetadataIsConsistentAcrossActiveArtifacts() :void {
		$config = $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration' );
		$properties = $this->assertArrayValue( $config, 'properties', 'plugin.json should define properties' );

		$version = $this->assertStringValue( $properties, 'version', 'Version should be defined in plugin.json properties.version' );
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
			$sourceVersion = $this->assertStringValue(
				$sourceProperties,
				'version',
				'Version should be defined in plugin-spec/01_properties.json'
			);
			$this->assertNotSame( '', $sourceVersion, 'Version should be defined in plugin-spec/01_properties.json' );
			$this->assertSame( $version, $sourceVersion, 'Version in plugin-spec/01_properties.json should match plugin.json' );
		}
	}

	public function testTextDomainIsConsistentAcrossConfigurationAndHeader() :void {
		$config = $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration' );
		$properties = $this->assertArrayValue( $config, 'properties', 'plugin.json should define properties' );
		$textDomain = $this->assertStringValue(
			$properties,
			'text_domain',
			'Text domain should be defined in plugin.json properties.text_domain'
		);
		$this->assertNotSame( '', $textDomain, 'Text domain should be defined in plugin.json properties.text_domain' );

		$pluginHeader = $this->getPluginFileContents( 'icwp-wpsf.php', 'Main plugin file' );
		$headerTextDomain = $this->extractPluginHeaderValue( $pluginHeader, 'Text Domain' );
		$this->assertSame( $textDomain, $headerTextDomain, 'Text domain in plugin.json should match icwp-wpsf.php header text domain' );
	}

	public function testWordpressMinimumRequirementIsConsistentAcrossActiveArtifacts() :void {
		$config = $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration' );
		$requirements = $this->assertArrayValue( $config, 'requirements', 'plugin.json should define requirements' );
		$wpMinimum = $this->assertStringValue( $requirements, 'wordpress', 'plugin.json should define requirements.wordpress' );
		$this->assertSame( '5.7', $wpMinimum, 'Plugin-wide WordPress minimum should remain 5.7' );

		if ( !$this->isTestingPackage() ) {
			$sourceRequirements = $this->decodePluginJsonFile( 'plugin-spec/04_requirements.json', 'Source requirements spec' );
			$this->assertSame(
				$wpMinimum,
				$this->assertStringValue(
					$sourceRequirements,
					'wordpress',
					'plugin-spec/04_requirements.json should define wordpress'
				),
				'WordPress minimum in plugin-spec/04_requirements.json should match plugin.json'
			);
		}

		$pluginHeader = $this->getPluginFileContents( 'icwp-wpsf.php', 'Main plugin file' );
		$this->assertSame(
			$wpMinimum,
			$this->extractPluginHeaderValue( $pluginHeader, 'Requires at least' ),
			'WordPress minimum in plugin.json should match icwp-wpsf.php header'
		);

		$readme = $this->getPluginFileContents( 'readme.txt', 'Plugin readme file' );
		$this->assertSame(
			$wpMinimum,
			$this->extractReadmeHeaderValue( $readme, 'Requires at least' ),
			'WordPress minimum in plugin.json should match readme.txt'
		);
	}

	public function testPhpMinimumRequirementIsConsistentAcrossActiveArtifacts() :void {
		$config = $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration' );
		$requirements = $this->assertArrayValue( $config, 'requirements', 'plugin.json should define requirements' );
		$phpMinimum = $this->assertStringValue( $requirements, 'php', 'plugin.json should define requirements.php' );
		$this->assertSame( MinimumRequirements::PHP, $phpMinimum, 'Plugin-wide PHP minimum should match the runtime constant' );

		if ( !$this->isTestingPackage() ) {
			$sourceRequirements = $this->decodePluginJsonFile( 'plugin-spec/04_requirements.json', 'Source requirements spec' );
			$this->assertSame(
				$phpMinimum,
				$this->assertStringValue(
					$sourceRequirements,
					'php',
					'plugin-spec/04_requirements.json should define php'
				),
				'PHP minimum in plugin-spec/04_requirements.json should match plugin.json'
			);
			$this->assertComposerPhpMinimum( $phpMinimum );
			$this->assertMatrixPhpMinimum( $phpMinimum );
		}

		$pluginHeader = $this->getPluginFileContents( 'icwp-wpsf.php', 'Main plugin file' );
		$this->assertSame(
			$phpMinimum,
			$this->extractPluginHeaderValue( $pluginHeader, 'Requires PHP' ),
			'PHP minimum in plugin.json should match icwp-wpsf.php header'
		);
		$this->assertPluginBootstrapMinimumPhp( $pluginHeader, $phpMinimum );

		$readme = $this->getPluginFileContents( 'readme.txt', 'Plugin readme file' );
		$this->assertSame(
			$phpMinimum,
			$this->extractReadmeHeaderValue( $readme, 'Requires PHP' ),
			'PHP minimum in plugin.json should match readme.txt'
		);
		$this->assertUnsupportedPhpMinimum( $phpMinimum );
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

	private function extractReadmeHeaderValue( string $readmeContent, string $headerName ) :string {
		$pattern = sprintf(
			'/^%s:\s*(\S+)\s*$/mi',
			preg_quote( $headerName, '/' )
		);

		if ( !preg_match( $pattern, $readmeContent, $matches ) ) {
			$this->fail( sprintf( 'Failed to parse "%s" from readme.txt', $headerName ) );
		}

		return trim( (string)$matches[ 1 ] );
	}

	private function assertPluginBootstrapMinimumPhp( string $pluginContent, string $phpMinimum ) :void {
		$pattern = '/version_compare\(\s*PHP_VERSION,\s*[\'"]([^\'"]+)[\'"],\s*[\'"]<[\'"]\s*\)/';

		if ( !preg_match( $pattern, $pluginContent, $matches ) ) {
			$this->fail( 'Failed to parse PHP minimum from icwp-wpsf.php bootstrap version check' );
		}

		$this->assertSame(
			$phpMinimum,
			(string)$matches[ 1 ],
			'PHP minimum in plugin.json should match icwp-wpsf.php bootstrap version check'
		);
	}

	private function assertComposerPhpMinimum( string $phpMinimum ) :void {
		$composer = $this->decodePluginJsonFile( 'composer.json', 'composer.json' );
		$config = $this->assertArrayValue( $composer, 'config', 'composer.json should define config' );
		$platform = $this->assertArrayValue( $config, 'platform', 'composer.json should define config.platform' );
		$require = $this->assertArrayValue( $composer, 'require', 'composer.json should define require' );

		$this->assertSame(
			$phpMinimum,
			$this->assertStringValue( $platform, 'php', 'composer.json should define config.platform.php' ),
			'Composer platform PHP should match the plugin PHP minimum'
		);
		$this->assertSame(
			'>='.$phpMinimum,
			$this->assertStringValue( $require, 'php', 'composer.json should define require.php' ),
			'Composer PHP requirement should match the plugin PHP minimum'
		);
	}

	private function assertMatrixPhpMinimum( string $phpMinimum ) :void {
		$matrix = $this->getPluginFileContents( '.github/config/matrix.conf', 'PHP matrix config' );
		if ( !preg_match( '/^DEFAULT_PHP="?([^"\r\n]+)"?/m', $matrix, $defaultMatch ) ) {
			$this->fail( 'Failed to parse DEFAULT_PHP from .github/config/matrix.conf' );
		}
		if ( !preg_match( '/^PHP_VERSIONS="?([^"\r\n]+)"?/m', $matrix, $versionsMatch ) ) {
			$this->fail( 'Failed to parse PHP_VERSIONS from .github/config/matrix.conf' );
		}

		$versions = \preg_split( '/\s+/', \trim( (string)$versionsMatch[ 1 ] ) );
		$this->assertIsArray( $versions );
		$this->assertSame( $phpMinimum, \trim( (string)$defaultMatch[ 1 ] ), 'Matrix default PHP should match the plugin PHP minimum' );
		$this->assertNotEmpty( $versions, 'Matrix PHP versions should not be empty' );
		$this->assertSame( $phpMinimum, $versions[ 0 ], 'Matrix PHP versions should start at the plugin PHP minimum' );
	}

	private function assertUnsupportedPhpMinimum( string $phpMinimum ) :void {
		$unsupported = $this->getPluginFileContents( 'unsupported.php', 'Unsupported PHP notice' );
		$this->assertStringContainsString(
			sprintf( 'requires PHP %s or newer', $phpMinimum ),
			$unsupported,
			'Unsupported PHP notice should mention the plugin PHP minimum'
		);
		$this->assertStringContainsString(
			sprintf( 'at least PHP %s', $phpMinimum ),
			$unsupported,
			'Unsupported PHP upgrade guidance should mention the plugin PHP minimum'
		);
	}

	private function assertArrayValue( array $source, string $key, string $message ) :array {
		$this->assertArrayHasKey( $key, $source, $message );
		$this->assertIsArray( $source[ $key ], $message );
		return $source[ $key ];
	}

	private function assertStringValue( array $source, string $key, string $message ) :string {
		$this->assertArrayHasKey( $key, $source, $message );
		$this->assertIsString( $source[ $key ], $message );
		return $source[ $key ];
	}
}
