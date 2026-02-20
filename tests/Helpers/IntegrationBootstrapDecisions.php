<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

use Symfony\Component\Filesystem\Path;

class IntegrationBootstrapDecisions {

	private const DOCKER_PLUGIN_DIR = '/tmp/wordpress/wp-content/plugins/wp-simple-firewall';

	/**
	 * @param array<string,string|false|null> $env
	 * @return array{
	 *   mode:'package'|'docker_symlink'|'docker_missing_symlink'|'source',
	 *   plugin_dir:string,
	 *   main_plugin_file:string,
	 *   plugin_autoload_file:string,
	 *   wp_plugin_dir:string
	 * }
	 */
	public static function resolvePluginContext(
		string $repoRoot,
		array $env,
		bool $dockerPluginDirExists,
		bool $explicitDockerMode,
		bool $dockerModeHeuristic
	) :array {
		$packagePath = self::sanitizeEnvValue( $env[ 'SHIELD_PACKAGE_PATH' ] ?? false );
		if ( $packagePath !== '' ) {
			return [
				'mode' => 'package',
				'plugin_dir' => $packagePath,
				'main_plugin_file' => Path::join( $packagePath, 'icwp-wpsf.php' ),
				'plugin_autoload_file' => Path::join( $packagePath, 'plugin_autoload.php' ),
				'wp_plugin_dir' => \dirname( $packagePath ),
			];
		}

		if ( $dockerPluginDirExists ) {
			return [
				'mode' => 'docker_symlink',
				'plugin_dir' => self::DOCKER_PLUGIN_DIR,
				'main_plugin_file' => Path::join( self::DOCKER_PLUGIN_DIR, 'icwp-wpsf.php' ),
				'plugin_autoload_file' => Path::join( self::DOCKER_PLUGIN_DIR, 'plugin_autoload.php' ),
				'wp_plugin_dir' => \dirname( self::DOCKER_PLUGIN_DIR ),
			];
		}

		if ( $explicitDockerMode || $dockerModeHeuristic ) {
			return [
				'mode' => 'docker_missing_symlink',
				'plugin_dir' => self::DOCKER_PLUGIN_DIR,
				'main_plugin_file' => Path::join( self::DOCKER_PLUGIN_DIR, 'icwp-wpsf.php' ),
				'plugin_autoload_file' => Path::join( self::DOCKER_PLUGIN_DIR, 'plugin_autoload.php' ),
				'wp_plugin_dir' => \dirname( self::DOCKER_PLUGIN_DIR ),
			];
		}

		return [
			'mode' => 'source',
			'plugin_dir' => $repoRoot,
			'main_plugin_file' => Path::join( $repoRoot, 'icwp-wpsf.php' ),
			'plugin_autoload_file' => Path::join( $repoRoot, 'plugin_autoload.php' ),
			'wp_plugin_dir' => \dirname( $repoRoot ),
		];
	}

	/**
	 * @param array<string,string|false|null> $env
	 * @return array{
	 *   candidate:string,
	 *   fallback_paths:string[],
	 *   checked_paths:string[]
	 * }
	 */
	public static function resolveWpTestsDirContext( string $repoRoot, array $env ) :array {
		$wpTestsDir = self::sanitizeEnvValue( $env[ 'WP_TESTS_DIR' ] ?? false );
		$wpDevelopDir = self::sanitizeEnvValue( $env[ 'WP_DEVELOP_DIR' ] ?? false );

		$candidate = $wpTestsDir;
		if ( $candidate === '' ) {
			$candidate = $wpDevelopDir !== ''
				? Path::join( $wpDevelopDir, 'tests', 'phpunit' )
				: '/tmp/wordpress-tests-lib';
		}

		return [
			'candidate' => $candidate,
			'fallback_paths' => [
				'/tmp/wordpress-develop/tests/phpunit',
				Path::join( \dirname( $repoRoot ), 'wordpress-tests-lib' ),
			],
			'checked_paths' => [
				$wpTestsDir !== '' ? $wpTestsDir : 'WP_TESTS_DIR not set',
				$wpDevelopDir !== '' ? Path::join( $wpDevelopDir, 'tests', 'phpunit' ) : 'WP_DEVELOP_DIR not set',
				'/tmp/wordpress-tests-lib',
			],
		];
	}

	/**
	 * @param string|false|null $value
	 */
	private static function sanitizeEnvValue( $value ) :string {
		return \is_string( $value ) ? $value : '';
	}
}
