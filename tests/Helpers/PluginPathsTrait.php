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
	 * Debug path information (only outputs if SHIELD_DEBUG_PATHS is set)
	 * @param string $label
	 * @param string $path
	 */
	private function debugPath( string $label, string $path ) :void {
		if ( getenv( 'SHIELD_DEBUG_PATHS' ) ) {
			echo "[PATH DEBUG] $label: $path\n";
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
		if ( !file_exists( $path ) && getenv( 'SHIELD_DEBUG_PATHS' ) ) {
			echo "\n[PATH DEBUG] === File Not Found Debug Info ===\n";
			echo "[PATH DEBUG] Looking for: $path\n";
			echo "[PATH DEBUG] File exists check: " . ( file_exists( $path ) ? 'YES' : 'NO' ) . "\n";
			echo "[PATH DEBUG] Is readable: " . ( is_readable( $path ) ? 'YES' : 'NO' ) . "\n";
			echo "[PATH DEBUG] Current working directory: " . getcwd() . "\n";
			echo "[PATH DEBUG] Plugin root: " . $this->getPluginRoot() . "\n";
			echo "[PATH DEBUG] SHIELD_PACKAGE_PATH env: " . ( getenv( 'SHIELD_PACKAGE_PATH' ) ?: 'NOT SET' ) . "\n";
			
			// Try to show parent directory contents
			$dir = dirname( $path );
			if ( is_dir( $dir ) ) {
				echo "[PATH DEBUG] Parent directory ($dir) contents:\n";
				$files = scandir( $dir );
				foreach ( $files as $file ) {
					if ( $file !== '.' && $file !== '..' ) {
						echo "[PATH DEBUG]   - $file\n";
					}
				}
			} else {
				echo "[PATH DEBUG] Parent directory does not exist: $dir\n";
			}
			echo "[PATH DEBUG] ===================================\n\n";
		}
		$this->assertFileExists( $path, $message );
	}
}
