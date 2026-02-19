<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\IntegrationBootstrapDecisions;
use Symfony\Component\Filesystem\Path;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class IntegrationBootstrapDecisionsTest extends TestCase {

	private string $repoRoot;

	protected function set_up() :void {
		parent::set_up();
		$this->repoRoot = \dirname( \dirname( \dirname( __DIR__ ) ) );
	}

	public function testResolvePluginContextPrefersPackagePath() :void {
		$context = IntegrationBootstrapDecisions::resolvePluginContext(
			$this->repoRoot,
			[
				'SHIELD_PACKAGE_PATH' => '/tmp/shield-package',
			],
			false,
			false,
			false
		);

		$this->assertSame( 'package', $context[ 'mode' ] );
		$this->assertSame( '/tmp/shield-package', $context[ 'plugin_dir' ] );
		$this->assertSame( '/tmp/shield-package/icwp-wpsf.php', $context[ 'main_plugin_file' ] );
		$this->assertSame( '/tmp', $context[ 'wp_plugin_dir' ] );
	}

	public function testResolvePluginContextUsesDockerSymlinkWhenPresent() :void {
		$context = IntegrationBootstrapDecisions::resolvePluginContext(
			$this->repoRoot,
			[
				'SHIELD_PACKAGE_PATH' => false,
			],
			true,
			true,
			true
		);

		$this->assertSame( 'docker_symlink', $context[ 'mode' ] );
		$this->assertSame( '/tmp/wordpress/wp-content/plugins/wp-simple-firewall', $context[ 'plugin_dir' ] );
	}

	public function testResolvePluginContextUsesDockerMissingSymlinkWhenSignalsPresent() :void {
		$context = IntegrationBootstrapDecisions::resolvePluginContext(
			$this->repoRoot,
			[
				'SHIELD_PACKAGE_PATH' => false,
			],
			false,
			true,
			false
		);

		$this->assertSame( 'docker_missing_symlink', $context[ 'mode' ] );
		$this->assertSame(
			'/tmp/wordpress/wp-content/plugins/wp-simple-firewall/icwp-wpsf.php',
			$context[ 'main_plugin_file' ]
		);
	}

	public function testResolvePluginContextFallsBackToSourceMode() :void {
		$context = IntegrationBootstrapDecisions::resolvePluginContext(
			$this->repoRoot,
			[
				'SHIELD_PACKAGE_PATH' => false,
			],
			false,
			false,
			false
		);

		$this->assertSame( 'source', $context[ 'mode' ] );
		$this->assertSame( $this->repoRoot, $context[ 'plugin_dir' ] );
		$this->assertSame( Path::join( $this->repoRoot, 'icwp-wpsf.php' ), $context[ 'main_plugin_file' ] );
	}

	public function testResolveWpTestsDirContextPrefersWpTestsDir() :void {
		$context = IntegrationBootstrapDecisions::resolveWpTestsDirContext(
			$this->repoRoot,
			[
				'WP_TESTS_DIR' => '/tmp/custom-tests-lib',
				'WP_DEVELOP_DIR' => '/tmp/wordpress-develop',
			]
		);

		$this->assertSame( '/tmp/custom-tests-lib', $context[ 'candidate' ] );
		$this->assertSame( '/tmp/custom-tests-lib', $context[ 'checked_paths' ][ 0 ] );
		$this->assertSame( '/tmp/wordpress-develop/tests/phpunit', $context[ 'checked_paths' ][ 1 ] );
		$this->assertSame( '/tmp/wordpress-develop/tests/phpunit', $context[ 'fallback_paths' ][ 0 ] );
		$this->assertSame( Path::join( \dirname( $this->repoRoot ), 'wordpress-tests-lib' ), $context[ 'fallback_paths' ][ 1 ] );
	}

	public function testResolveWpTestsDirContextUsesWpDevelopAndDefaultsWhenUnset() :void {
		$wpDevelopContext = IntegrationBootstrapDecisions::resolveWpTestsDirContext(
			$this->repoRoot,
			[
				'WP_TESTS_DIR' => false,
				'WP_DEVELOP_DIR' => '/tmp/wordpress-develop',
			]
		);
		$this->assertSame( '/tmp/wordpress-develop/tests/phpunit', $wpDevelopContext[ 'candidate' ] );
		$this->assertSame( 'WP_TESTS_DIR not set', $wpDevelopContext[ 'checked_paths' ][ 0 ] );

		$defaultContext = IntegrationBootstrapDecisions::resolveWpTestsDirContext(
			$this->repoRoot,
			[
				'WP_TESTS_DIR' => false,
				'WP_DEVELOP_DIR' => false,
			]
		);
		$this->assertSame( '/tmp/wordpress-tests-lib', $defaultContext[ 'candidate' ] );
		$this->assertSame( 'WP_DEVELOP_DIR not set', $defaultContext[ 'checked_paths' ][ 1 ] );
	}
}
