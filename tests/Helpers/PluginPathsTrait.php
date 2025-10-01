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
	 * Get the root path of the plugin being tested
	 * @return string
	 */
	protected function getPluginRoot() :string {
		static $pluginRoot = null;
		
		if ( $pluginRoot === null ) {
			// Check if we're testing a package
			$envPath = getenv( 'SHIELD_PACKAGE_PATH' );
			if ( $envPath !== false && !empty($envPath) ) {
				$pluginRoot = $envPath;
				$this->debugPath( 'Using SHIELD_PACKAGE_PATH', $pluginRoot );
			} else {
				// Default to source directory (2 levels up from this file)
				$pluginRoot = dirname( dirname( __DIR__ ) );
				$this->debugPath( 'Using source directory', $pluginRoot );
				
				// Fix for Docker: If empty or root, use fallback methods
				if ( empty($pluginRoot) || $pluginRoot === '/' ) {
					$pluginRoot = getcwd();
					$this->debugPath( 'Fallback to getcwd()', $pluginRoot );
				}
				
				// Final fallback: check for known plugin file in /app
				if ( !file_exists($pluginRoot . '/icwp-wpsf.php') && file_exists('/app/icwp-wpsf.php') ) {
					$pluginRoot = '/app';
					$this->debugPath( 'Fallback to /app (Docker detected)', $pluginRoot );
				}
			}
		}
		
		return $pluginRoot;
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
		return getenv( 'SHIELD_PACKAGE_PATH' ) !== false;
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
		if ( !file_exists( $path ) ) {
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
