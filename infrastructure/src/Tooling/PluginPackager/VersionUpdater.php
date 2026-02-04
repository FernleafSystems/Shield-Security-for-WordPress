<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Path;

/**
 * Updates version metadata in plugin files during packaging.
 *
 * Updates version, release_timestamp, and build values in:
 * - plugin-spec/01_properties.json (source spec file, updated BEFORE build)
 * - plugin.json (properties.version, properties.release_timestamp, properties.build)
 * - readme.txt (Stable tag:)
 * - icwp-wpsf.php (Version: header)
 */
class VersionUpdater {

	private string $projectRoot;

	/** @var callable */
	private $logger;

	public function __construct( string $projectRoot, ?callable $logger = null ) {
		$this->projectRoot = $projectRoot;
		$this->logger = $logger ?? static function ( string $message ) :void {
			echo $message.PHP_EOL;
		};
	}

	/**
	 * Update version metadata in target package files.
	 *
	 * @param string               $targetDir The package target directory
	 * @param array<string, mixed> $options   Values to update: version, release_timestamp, build
	 * @return array<string, mixed> The values that were applied
	 * @throws \InvalidArgumentException if validation fails
	 */
	public function update( string $targetDir, array $options ) :array {
		if ( empty( $options ) ) {
			return [];
		}

		$this->log( 'Updating version metadata...' );

		// Validate all values first
		$applied = $this->validateAndFilterOptions( $options );

		// Update files
		$this->updatePluginJson( $targetDir, $applied );

		if ( isset( $applied[ 'version' ] ) ) {
			$this->updateReadmeTxt( $targetDir, $applied[ 'version' ] );
			$this->updatePluginHeader( $targetDir, $applied[ 'version' ] );
		}

		$this->log( '  ✓ Version metadata updated' );

		return $applied;
	}

	/**
	 * Generate a build number in YYYYMM.DDBB format.
	 *
	 * If the source build is from the same day, increments BB.
	 * Otherwise, starts fresh with BB=01.
	 */
	public function generateBuild() :string {
		$sourceBuild = $this->readSourceBuild();
		$now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$todayPrefix = $now->format( 'Ym' ).'.'.$now->format( 'd' );

		if ( $sourceBuild !== null && \preg_match( '/^(\d{6}\.\d{2})(\d{2})$/', $sourceBuild, $m ) ) {
			$sourcePrefix = $m[ 1 ];
			$sourceIteration = (int)$m[ 2 ];

			if ( $sourcePrefix === $todayPrefix ) {
				// Same day - increment iteration
				return $todayPrefix.\sprintf( '%02d', $sourceIteration + 1 );
			}
		}

		// New day or couldn't parse source - start at 01
		return $todayPrefix.'01';
	}

	/**
	 * Validate version format: numeric segments separated by dots (e.g., "1.0", "21.0.102", "1.2.3.4")
	 *
	 * @throws \InvalidArgumentException if version format is invalid
	 */
	private function validateVersion( string $version ) :void {
		if ( $version === '' ) {
			throw new \InvalidArgumentException( 'Version cannot be empty' );
		}

		// Must be numeric segments separated by dots: 1.0, 21.0.102, 1.2.3.4
		if ( !\preg_match( '/^\d+(\.\d+)+$/', $version ) ) {
			throw new \InvalidArgumentException(
				\sprintf(
					'Invalid version format: "%s". Expected numeric segments separated by dots (e.g., "1.0", "21.0.102")',
					$version
				)
			);
		}
	}

	/**
	 * Validate timestamp: must be a positive integer after year 2000 (946684800)
	 *
	 * @throws \InvalidArgumentException if timestamp is invalid
	 */
	private function validateTimestamp( int $timestamp ) :void {
		// Must be positive and after year 2000 (946684800)
		$minTimestamp = 946684800; // 2000-01-01 00:00:00 UTC

		if ( $timestamp <= 0 ) {
			throw new \InvalidArgumentException(
				\sprintf( 'Invalid timestamp: %d. Must be a positive integer', $timestamp )
			);
		}

		if ( $timestamp < $minTimestamp ) {
			throw new \InvalidArgumentException(
				\sprintf(
					'Invalid timestamp: %d. Must be after year 2000 (>= %d)',
					$timestamp,
					$minTimestamp
				)
			);
		}
	}

	/**
	 * Validate build format: YYYYMM.DDBB (e.g., "202602.0301")
	 *
	 * @throws \InvalidArgumentException if build format is invalid
	 */
	private function validateBuild( string $build ) :void {
		if ( $build === '' ) {
			throw new \InvalidArgumentException( 'Build cannot be empty' );
		}

		// Format: YYYYMM.DDBB where YYYY=year, MM=month, DD=day, BB=iteration
		if ( !\preg_match( '/^\d{6}\.\d{4}$/', $build ) ) {
			throw new \InvalidArgumentException(
				\sprintf(
					'Invalid build format: "%s". Expected YYYYMM.DDBB format (e.g., "202602.0301")',
					$build
				)
			);
		}
	}

	/**
	 * Validate and filter options array, returning only valid fields.
	 *
	 * @param array<string, mixed> $options Options to validate
	 * @return array<string, mixed> Validated options (version, release_timestamp, build)
	 * @throws \InvalidArgumentException if any value fails validation
	 */
	private function validateAndFilterOptions( array $options ) :array {
		$validated = [];

		if ( isset( $options[ 'version' ] ) ) {
			$this->validateVersion( $options[ 'version' ] );
			$validated[ 'version' ] = $options[ 'version' ];
		}

		if ( isset( $options[ 'release_timestamp' ] ) ) {
			$this->validateTimestamp( $options[ 'release_timestamp' ] );
			$validated[ 'release_timestamp' ] = $options[ 'release_timestamp' ];
		}

		if ( isset( $options[ 'build' ] ) ) {
			$this->validateBuild( $options[ 'build' ] );
			$validated[ 'build' ] = $options[ 'build' ];
		}

		return $validated;
	}

	/**
	 * Update plugin.json with version metadata.
	 *
	 * @param array<string, mixed> $values Values to update
	 */
	private function updatePluginJson( string $targetDir, array $values ) :void {
		$path = Path::join( $targetDir, 'plugin.json' );

		if ( !\file_exists( $path ) ) {
			throw new \RuntimeException(
				\sprintf( 'plugin.json not found at: %s', $path )
			);
		}

		$content = \file_get_contents( $path );
		if ( $content === false ) {
			throw new \RuntimeException(
				\sprintf( 'Failed to read plugin.json: %s', $path )
			);
		}

		$config = \json_decode( $content, true );
		if ( !\is_array( $config ) ) {
			throw new \RuntimeException(
				\sprintf( 'Failed to parse plugin.json: %s', \json_last_error_msg() )
			);
		}

		// Ensure properties key exists
		if ( !isset( $config[ 'properties' ] ) || !\is_array( $config[ 'properties' ] ) ) {
			$config[ 'properties' ] = [];
		}

		// Update values
		if ( isset( $values[ 'version' ] ) ) {
			$config[ 'properties' ][ 'version' ] = $values[ 'version' ];
			$this->log( \sprintf( '  - plugin.json: version = %s', $values[ 'version' ] ) );
		}

		if ( isset( $values[ 'release_timestamp' ] ) ) {
			$config[ 'properties' ][ 'release_timestamp' ] = $values[ 'release_timestamp' ];
			$this->log( \sprintf( '  - plugin.json: release_timestamp = %d', $values[ 'release_timestamp' ] ) );
		}

		if ( isset( $values[ 'build' ] ) ) {
			$config[ 'properties' ][ 'build' ] = $values[ 'build' ];
			$this->log( \sprintf( '  - plugin.json: build = %s', $values[ 'build' ] ) );
		}

		$json = \json_encode( $config, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE );
		if ( $json === false ) {
			throw new \RuntimeException(
				\sprintf( 'Failed to encode plugin.json: %s', \json_last_error_msg() )
			);
		}

		$bytesWritten = \file_put_contents( $path, $json."\n" );
		if ( $bytesWritten === false ) {
			throw new \RuntimeException(
				\sprintf( 'Failed to write plugin.json: %s', $path )
			);
		}
	}

	/**
	 * Update readme.txt Stable tag.
	 */
	public function updateReadmeTxt( string $targetDir, string $version ) :void {
		$path = Path::join( $targetDir, 'readme.txt' );

		if ( !\file_exists( $path ) ) {
			$this->log( '  - Warning: readme.txt not found, skipping' );
			return;
		}

		$content = \file_get_contents( $path );
		if ( $content === false ) {
			$this->log( '  - Warning: Failed to read readme.txt, skipping' );
			return;
		}

		// Preserve line endings
		$lineEnding = \strpos( $content, "\r\n" ) !== false ? "\r\n" : "\n";

		// Replace Stable tag line
		$pattern = '/^Stable tag:\s*\S+\s*$/mi';
		$replacement = 'Stable tag: '.$version;
		$newContent = \preg_replace( $pattern, $replacement, $content, 1, $count );

		if ( $count === 0 ) {
			$this->log( '  - Warning: Could not find "Stable tag:" in readme.txt, skipping' );
			return;
		}

		$bytesWritten = \file_put_contents( $path, $newContent );
		if ( $bytesWritten === false ) {
			$this->log( '  - Warning: Failed to write readme.txt' );
			return;
		}

		$this->log( \sprintf( '  - readme.txt: Stable tag = %s', $version ) );
	}

	/**
	 * Update plugin header Version.
	 */
	public function updatePluginHeader( string $targetDir, string $version ) :void {
		$path = Path::join( $targetDir, 'icwp-wpsf.php' );

		if ( !\file_exists( $path ) ) {
			$this->log( '  - Warning: icwp-wpsf.php not found, skipping' );
			return;
		}

		$content = \file_get_contents( $path );
		if ( $content === false ) {
			$this->log( '  - Warning: Failed to read icwp-wpsf.php, skipping' );
			return;
		}

		// Replace Version: line in plugin header
		$pattern = '/^(\s*\*\s*Version:\s*)\S+(\s*)$/mi';
		$replacement = '${1}'.$version.'${2}';
		$newContent = \preg_replace( $pattern, $replacement, $content, 1, $count );

		if ( $count === 0 ) {
			$this->log( '  - Warning: Could not find "Version:" header in icwp-wpsf.php, skipping' );
			return;
		}

		$bytesWritten = \file_put_contents( $path, $newContent );
		if ( $bytesWritten === false ) {
			$this->log( '  - Warning: Failed to write icwp-wpsf.php' );
			return;
		}

		$this->log( \sprintf( '  - icwp-wpsf.php: Version = %s', $version ) );
	}

	/**
	 * Read the current build number from source plugin-spec/01_properties.json.
	 */
	private function readSourceBuild() :?string {
		$path = Path::join( $this->projectRoot, 'plugin-spec', '01_properties.json' );

		if ( !\file_exists( $path ) ) {
			return null;
		}

		$content = \file_get_contents( $path );
		if ( $content === false ) {
			return null;
		}

		$data = \json_decode( $content, true );
		if ( !\is_array( $data ) ) {
			return null;
		}

		return $data[ 'build' ] ?? null;
	}

	/**
	 * Update source plugin-spec/01_properties.json with version metadata.
	 * Must be called BEFORE buildPluginJson() so merged config has correct values.
	 *
	 * @param array<string, mixed> $values Values to update: version, release_timestamp, build
	 * @throws \InvalidArgumentException if validation fails
	 */
	public function updateSourceProperties( array $values ) :void {
		if ( empty( $values ) ) {
			return;
		}

		// Validate all values first (same validation as update())
		$validated = $this->validateAndFilterOptions( $values );

		$path = Path::join( $this->projectRoot, 'plugin-spec', '01_properties.json' );

		if ( !\file_exists( $path ) ) {
			throw new \RuntimeException(
				\sprintf( 'Source properties file not found: %s', $path )
			);
		}

		$content = \file_get_contents( $path );
		if ( $content === false ) {
			throw new \RuntimeException(
				\sprintf( 'Failed to read source properties: %s', $path )
			);
		}

		$data = \json_decode( $content, true );
		if ( !\is_array( $data ) ) {
			throw new \RuntimeException(
				\sprintf( 'Failed to parse source properties: %s', \json_last_error_msg() )
			);
		}

		// Update values
		if ( isset( $validated[ 'version' ] ) ) {
			$data[ 'version' ] = $validated[ 'version' ];
			$this->log( \sprintf( '  - plugin-spec/01_properties.json: version = %s', $validated[ 'version' ] ) );
		}

		if ( isset( $validated[ 'release_timestamp' ] ) ) {
			$data[ 'release_timestamp' ] = $validated[ 'release_timestamp' ];
			$this->log( \sprintf( '  - plugin-spec/01_properties.json: release_timestamp = %d', $validated[ 'release_timestamp' ] ) );
		}

		if ( isset( $validated[ 'build' ] ) ) {
			$data[ 'build' ] = $validated[ 'build' ];
			$this->log( \sprintf( '  - plugin-spec/01_properties.json: build = %s', $validated[ 'build' ] ) );
		}

		$json = \json_encode( $data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE );
		if ( $json === false ) {
			throw new \RuntimeException(
				\sprintf( 'Failed to encode source properties: %s', \json_last_error_msg() )
			);
		}

		$bytesWritten = \file_put_contents( $path, $json."\n" );
		if ( $bytesWritten === false ) {
			throw new \RuntimeException(
				\sprintf( 'Failed to write source properties: %s', $path )
			);
		}
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}
}
