<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

class WildCardOptions {

	public const FILE_PATH_REL = 0;
	public const URL_PATH = 1;

	public function clean( array $optValues, array $checks, int $dataType ) :array {

		$optValues = $this->basicCleanValues( $optValues, $dataType );
		$checks = $this->preProcessChecks( $checks, $dataType );

		$cleanedValues = [];
		foreach ( $optValues as $value ) {

			$cleanedValues[ $value ] = $value;

			$valueRegEx = $this->buildFullRegexValue( $value, $dataType );
			foreach ( $checks as $check ) {
				if ( \preg_match( $valueRegEx, $check ) ) {
					$cleanedValues[ $value ] = false;
					break;
				}
			}
		}

		return \array_values( \array_filter( $cleanedValues ) );
	}

	protected function preProcessChecks( array $checks, int $type ) :array {

		switch ( $type ) {

			case self::FILE_PATH_REL:
				$checks = \array_merge( $checks, \array_map( 'untrailingslashit', $checks ) );
				break;

			case self::URL_PATH:
				$checks = \array_map( function ( $path ) {
					$path = '/'.\ltrim( $path, '/' );
					return '/'.\trim( $path, '/' );
				}, $checks );
				$checks = \array_merge( $checks, \array_map( 'trailingslashit', $checks ) );
				break;

			default:
				break;
		}

		return \array_unique( $checks );
	}

	protected function basicCleanValues( array $optValues, int $type ) :array {

		$optValues = \array_filter( \array_map( function ( $value ) {
			return \strtolower( \trim( $value ) );
		}, $optValues ) );

		switch ( $type ) {

			case self::FILE_PATH_REL:
				$optValues = \array_map( function ( string $relPath ) {
					$relPath = wp_normalize_path( $relPath );
					if ( \strpos( $relPath, wp_normalize_path( ABSPATH ) ) === 0 ) {
						$relPath = \str_replace( wp_normalize_path( ABSPATH ), '', $relPath );
					}
					return \ltrim( $relPath, '/' );
				}, $optValues );
				break;

			case self::URL_PATH:
				$optValues = \array_map( function ( $path ) {
					$path = \preg_replace( '#^https?://[^/]+/#i', '', $path );
					if ( \strpos( $path, '*' ) !== 0 ) {
						$path = '/'.\ltrim( $path, '/' );
					}
					return $path;
				}, $optValues );
				break;

			default:
				break;
		}

		return \array_unique( $optValues );
	}

	public function buildFullRegexValue( string $value, int $type, bool $regexWrap = true ) :string {
		$valueRegEx = $this->convertValueToRegEx( $value, $type );

		switch ( $type ) {
			case self::FILE_PATH_REL:
				$fullValue = \path_join( ABSPATH, $valueRegEx );
				break;

			case self::URL_PATH:
			default:
				$fullValue = $valueRegEx;
				break;
		}

		return $regexWrap ? \sprintf( '#^%s$#i', $fullValue ) : $fullValue;
	}

	protected function convertValueToRegEx( string $value, int $type ) :string {

		switch ( $type ) {
			case self::FILE_PATH_REL:
				if ( \preg_match( '#/$#', $value ) ) {
					$value .= '*';
				}
				break;

			case self::URL_PATH:
			default:
				break;
		}
		return \str_replace( 'WILDCARDSTAR', '.*', \preg_quote( \str_replace( '*', 'WILDCARDSTAR', $value ), '#' ) );
	}
}