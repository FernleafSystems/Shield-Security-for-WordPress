<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Symfony\Component\Filesystem\Path;

/**
 * Validates the source-controlled WordPress.org preview blueprint.
 */
class WordpressOrgBlueprintTest extends BaseUnitTest {

	use PluginPathsTrait;

	private function skipWhenTestingPackage() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'WordPress.org blueprint source checks run only in source mode.' );
		}
	}

	private function getBlueprintPath() :string {
		return Path::join( $this->getPluginRoot(), 'infrastructure/wordpress-org/blueprints/blueprint.json' );
	}

	private function getBlueprint() :array {
		$path = $this->getBlueprintPath();
		$this->assertFileExistsWithDebug( $path, 'WordPress.org blueprint source file is missing' );

		$content = file_get_contents( $path );
		$this->assertNotFalse( $content, 'Unable to read WordPress.org blueprint source file' );

		$data = json_decode( $content, true );
		$this->assertSame( JSON_ERROR_NONE, json_last_error(), 'Blueprint JSON must be valid: '.json_last_error_msg() );
		$this->assertIsArray( $data, 'Blueprint JSON should decode to an array' );
		return $data;
	}

	public function testBlueprintFileExistsAndIsValidJson() :void {
		$this->skipWhenTestingPackage();
		$this->getBlueprint();
	}

	public function testBlueprintDefinesAdminSmokeFlow() :void {
		$this->skipWhenTestingPackage();
		$blueprint = $this->getBlueprint();

		$this->assertArrayHasKey( '$schema', $blueprint, 'Blueprint should declare schema URL' );
		$this->assertArrayHasKey( 'landingPage', $blueprint, 'Blueprint should declare a landing page' );
		$this->assertStringContainsString( '/wp-admin/admin.php?page=icwp-wpsf-plugin', (string)$blueprint['landingPage'] );

		$this->assertArrayHasKey( 'steps', $blueprint, 'Blueprint should declare steps' );
		$this->assertIsArray( $blueprint['steps'] );
		$this->assertNotEmpty( $blueprint['steps'], 'Blueprint steps should not be empty' );

		$stepNames = array_map(
			static function ( array $step ) :string {
				return (string)( $step['step'] ?? '' );
			},
			array_filter( $blueprint['steps'], static fn( $step ) :bool => is_array( $step ) )
		);

		$this->assertContains( 'installPlugin', $stepNames, 'Blueprint should install the plugin for preview' );
		$this->assertContains( 'login', $stepNames, 'Blueprint should include an admin login step' );

		$installStep = null;
		foreach ( $blueprint['steps'] as $step ) {
			if ( is_array( $step ) && ( $step['step'] ?? '' ) === 'installPlugin' ) {
				$installStep = $step;
				break;
			}
		}

		$this->assertIsArray( $installStep, 'Blueprint installPlugin step is required' );
		$this->assertArrayHasKey( 'pluginData', $installStep );
		$this->assertArrayHasKey( 'url', $installStep['pluginData'] );
		$this->assertStringContainsString(
			'downloads.wordpress.org/plugin/wp-simple-firewall.latest-stable.zip',
			(string)$installStep['pluginData']['url'],
			'Blueprint should install Shield from WordPress.org stable zip'
		);
	}
}
