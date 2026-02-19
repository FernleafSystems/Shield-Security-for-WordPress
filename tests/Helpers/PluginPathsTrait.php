<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

/**
 * Trait to handle plugin paths consistently across all tests.
 *
 * @note Every change here affects both source and packaged test runs. Tread carefully—
 *       bootstrap logic, Docker runners, and package validation all depend on these helpers
 *       resolving identical paths across environments.
 */
trait PluginPathsTrait {

	/**
	 * Get the root path of the plugin being tested.
	 *
	 * Computed fresh on every call—no caching. The work is trivial (dirname + getenv)
	 * and caching caused stale-state bugs when tests manipulate SHIELD_PACKAGE_PATH.
	 *
	 * @return string
	 */
	protected function getPluginRoot() :string {
		// Check if we're testing a package
		$envPath = getenv( 'SHIELD_PACKAGE_PATH' );
		if ( $envPath !== false && !empty( $envPath ) ) {
			$this->debugPath( 'Using SHIELD_PACKAGE_PATH', $envPath );
			return $envPath;
		}

		// Default to source directory (2 levels up from this file)
		$root = \dirname( \dirname( __DIR__ ) );
		$this->debugPath( 'Using source directory', $root );

		// Fix for Docker: If empty or root, use fallback methods
		if ( empty( $root ) || $root === '/' ) {
			$root = \getcwd();
			$this->debugPath( 'Fallback to getcwd()', $root );
		}

		// Final fallback: check for known plugin file in /app
		if ( !\file_exists( $root.'/icwp-wpsf.php' ) && \file_exists( '/app/icwp-wpsf.php' ) ) {
			$root = '/app';
			$this->debugPath( 'Fallback to /app (Docker detected)', $root );
		}

		return $root;
	}
	
	/**
	 * Get path to a file relative to plugin root
	 * @param string $relativePath Path relative to plugin root (e.g., 'icwp-wpsf.php')
	 * @return string
	 */
	protected function getPluginFilePath( string $relativePath ) :string {
		$path = $this->getPluginRoot() . '/' . ltrim( $relativePath, '/' );
		$this->debugPath( "File path for '$relativePath'", $path );
		return $path;
	}
	
	/**
	 * Check if we're testing a package or source
	 * @return bool
	 */
	protected function isTestingPackage() :bool {
		$envPath = getenv( 'SHIELD_PACKAGE_PATH' );
		return $envPath !== false && !empty( $envPath );
	}
	
	/**
	 * Write debug output to stderr so it never triggers PHPUnit's output detection.
	 */
	private function debugWrite( string $message ) :void {
		\fwrite( \STDERR, $message );
	}

	/**
	 * Debug path information (only outputs in verbose mode).
	 * @param string $label
	 * @param string $path
	 */
	private function debugPath( string $label, string $path ) :void {
		if ( TestEnv::isPathDebug() ) {
			$this->debugWrite( "[PATH DEBUG] $label: ".TestEnv::normalizePathForLog( $path )."\n" );
		}
	}

	protected function getPluginJsonPath() :string {
		return $this->getPluginFilePath( 'plugin.json' );
	}

	protected function getPluginFileContents( string $relativePath, string $context = 'file' ) :string {
		$path = $this->getPluginFilePath( $relativePath );
		$this->assertFileExistsWithDebug( $path, sprintf( '%s is missing: %s', $context, $relativePath ) );
		$content = file_get_contents( $path );
		$this->assertNotFalse( $content, sprintf( 'Unable to read %s at %s', $context, $path ) );
		return $content;
	}

	protected function decodePluginJsonFile( string $relativePath, string $context = 'JSON file' ) :array {
		$content = $this->getPluginFileContents( $relativePath, $context );
		$decoded = json_decode( $content, true );
		$this->assertSame( JSON_ERROR_NONE, json_last_error(), sprintf( '%s should contain valid JSON: %s', $context, json_last_error_msg() ) );
		$this->assertIsArray( $decoded, sprintf( '%s should decode to an array structure', $context ) );
		return $decoded;
	}

	/**
	 * Assert file exists with helpful debug info
	 * @param string $path
	 * @param string $message
	 */
	protected function assertFileExistsWithDebug( string $path, string $message = '' ) :void {
		if ( !\file_exists( $path ) && TestEnv::isPathDebug() ) {
			$this->debugWrite( "\n[PATH DEBUG] === File Not Found Debug Info ===\n" );
			$this->debugWrite( "[PATH DEBUG] Looking for: ".TestEnv::normalizePathForLog( $path )."\n" );
			$this->debugWrite( "[PATH DEBUG] File exists check: ".( \file_exists( $path ) ? 'YES' : 'NO' )."\n" );
			$this->debugWrite( "[PATH DEBUG] Is readable: ".( \is_readable( $path ) ? 'YES' : 'NO' )."\n" );
			$this->debugWrite( "[PATH DEBUG] Current working directory: ".TestEnv::normalizePathForLog( (string)\getcwd() )."\n" );
			$this->debugWrite( "[PATH DEBUG] Plugin root: ".TestEnv::normalizePathForLog( $this->getPluginRoot() )."\n" );
			$this->debugWrite( "[PATH DEBUG] SHIELD_PACKAGE_PATH env: ".TestEnv::normalizePathForLog( (string)( getenv( 'SHIELD_PACKAGE_PATH' ) ?: 'NOT SET' ) )."\n" );

			// Try to show parent directory contents
			$dir = \dirname( $path );
			if ( \is_dir( $dir ) ) {
				$this->debugWrite( "[PATH DEBUG] Parent directory (".TestEnv::normalizePathForLog( $dir ).") contents:\n" );
				$files = \scandir( $dir );
				foreach ( $files as $file ) {
					if ( $file !== '.' && $file !== '..' ) {
						$this->debugWrite( "[PATH DEBUG]   - $file\n" );
					}
				}
			}
			else {
				$this->debugWrite( "[PATH DEBUG] Parent directory does not exist: ".TestEnv::normalizePathForLog( $dir )."\n" );
			}
			$this->debugWrite( "[PATH DEBUG] ===================================\n\n" );
		}
		$this->assertFileExists( $path, $message );
	}
}
