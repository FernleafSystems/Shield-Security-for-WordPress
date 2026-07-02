<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use Symfony\Component\Filesystem\Path;

class LocalWpTestsConfigGuard {

	/** @var string[] */
	private const DB_CONSTANT_NAMES = [ 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST' ];

	/**
	 * @param array{DB_NAME:string,DB_USER:string,DB_PASSWORD:string,DB_HOST:string} $expected
	 */
	public function removeIfStale( string $wpTestsDir, array $expected ) :void {
		$configPath = $this->configPath( $wpTestsDir );
		if ( !\is_file( $configPath ) ) {
			return;
		}
		if ( $this->matchesExpectedConstants( $configPath, $expected ) ) {
			return;
		}
		if ( !@\unlink( $configPath ) && \is_file( $configPath ) ) {
			throw new \RuntimeException( 'Failed to remove stale WordPress test DB config: '.$configPath );
		}
	}

	/**
	 * @param array{DB_NAME:string,DB_USER:string,DB_PASSWORD:string,DB_HOST:string} $expected
	 */
	public function assertMatches( string $wpTestsDir, array $expected ) :void {
		$configPath = $this->configPath( $wpTestsDir );
		if ( !\is_file( $configPath ) ) {
			throw new \RuntimeException( 'WordPress test DB config was not created: '.$configPath );
		}
		if ( $this->matchesExpectedConstants( $configPath, $expected ) ) {
			return;
		}

		$found = $this->readDbConstants( $configPath );
		throw new \RuntimeException(
			'WordPress test DB config does not match integration-local database. '
			.'Expected '.$this->formatConstants( $expected ).'; found '.$this->formatConstants( $found ).'. '
			.'Remove or regenerate '.$configPath.'.'
		);
	}

	private function configPath( string $wpTestsDir ) :string {
		return Path::join( $wpTestsDir, 'wp-tests-config.php' );
	}

	/**
	 * @param array{DB_NAME:string,DB_USER:string,DB_PASSWORD:string,DB_HOST:string} $expected
	 */
	private function matchesExpectedConstants( string $configPath, array $expected ) :bool {
		$found = $this->readDbConstants( $configPath );
		foreach ( $expected as $name => $value ) {
			if ( ( $found[ $name ] ?? null ) !== $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return array<string,string>
	 */
	private function readDbConstants( string $configPath ) :array {
		$content = (string)\file_get_contents( $configPath );
		$constants = [];
		foreach ( self::DB_CONSTANT_NAMES as $name ) {
			$pattern = '/define\s*\(\s*[\'"]'.\preg_quote( $name, '/' ).'[\'"]\s*,\s*([\'"])(.*?)\1\s*\)\s*;/s';
			if ( \preg_match( $pattern, $content, $matches ) === 1 ) {
				$constants[ $name ] = (string)( $matches[ 2 ] ?? '' );
			}
		}

		return $constants;
	}

	/**
	 * @param array<string,string> $constants
	 */
	private function formatConstants( array $constants ) :string {
		$parts = [];
		foreach ( self::DB_CONSTANT_NAMES as $name ) {
			$parts[] = $name.'='.( $constants[ $name ] ?? '<missing>' );
		}

		return \implode( ', ', $parts );
	}
}
