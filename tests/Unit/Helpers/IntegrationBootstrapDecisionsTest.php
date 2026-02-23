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

	/**
	 * @dataProvider providerResolvePluginContextMatrix
	 *
	 * @param array<string,string|false> $env
	 */
	public function testResolvePluginContextMatrix(
		array $env,
		bool $dockerPluginDirExists,
		bool $explicitDockerMode,
		string $expectedMode
	) :void {
		$context = IntegrationBootstrapDecisions::resolvePluginContext(
			$this->repoRoot,
			$env,
			$dockerPluginDirExists,
			$explicitDockerMode
		);

		$expectedPluginDir = $expectedMode === 'package'
			? (string)$env[ 'SHIELD_PACKAGE_PATH' ]
			: ( \in_array( $expectedMode, [ 'docker_symlink', 'docker_missing_symlink' ], true )
				? '/tmp/wordpress/wp-content/plugins/wp-simple-firewall'
				: $this->repoRoot );

		$this->assertSame( $expectedMode, $context[ 'mode' ] );
		$this->assertSame( $expectedPluginDir, $context[ 'plugin_dir' ] );
		$this->assertSame( Path::join( $expectedPluginDir, 'icwp-wpsf.php' ), $context[ 'main_plugin_file' ] );
		$this->assertSame( Path::join( $expectedPluginDir, 'plugin_autoload.php' ), $context[ 'plugin_autoload_file' ] );
		$this->assertSame( \dirname( $expectedPluginDir ), $context[ 'wp_plugin_dir' ] );
	}

	/**
	 * @return array<string,array{
	 *   0:array<string,string|false>,
	 *   1:bool,
	 *   2:bool,
	 *   3:'package'|'docker_symlink'|'docker_missing_symlink'|'source'
	 * }>
	 */
	public static function providerResolvePluginContextMatrix() :array {
		return [
			'package mode takes precedence' => [
				[
					'SHIELD_PACKAGE_PATH' => '/tmp/shield-package',
				],
				true,
				true,
				'package',
			],
			'docker symlink mode' => [
				[
					'SHIELD_PACKAGE_PATH' => false,
				],
				true,
				true,
				'docker_symlink',
			],
			'explicit docker missing symlink mode' => [
				[
					'SHIELD_PACKAGE_PATH' => false,
				],
				false,
				true,
				'docker_missing_symlink',
			],
			'source fallback mode' => [
				[
					'SHIELD_PACKAGE_PATH' => false,
				],
				false,
				false,
				'source',
			],
		];
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
