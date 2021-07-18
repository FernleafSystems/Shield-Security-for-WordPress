<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

use FernleafSystems\Wordpress\Services\Services;

class AssessPhpFile {

	/**
	 * @param string $file
	 * @return bool
	 * @throws \Exception
	 */
	public function isEmptyOfCode( string $file ) :bool {
		$ext = strtolower( Services::Data()->getExtension( $file ) );
		if ( !in_array( $ext, [ 'php', 'php5', 'php7', 'phtml' ] ) ) {
			throw new \Exception( 'Not a standard PHP file.' );
		}
		if ( !Services::WpFs()->isFile( $file ) ) {
			throw new \Exception( 'File does not exist on disk.' );
		}

		$Ts = token_get_all( $this->getRelevantContent( $file ) );

		if ( !is_array( $Ts ) ) {
			throw new \Exception( 'Could not get tokens.' );
		}

		$Ts = array_values( array_filter( $Ts, function ( $token ) {
			return is_array( $token ) &&
				   !in_array( $token[ 0 ], [ T_WHITESPACE, T_DOC_COMMENT, T_COMMENT, T_INLINE_HTML ] );
		} ) );

		// If there is at least 1 token we assess it
		if ( !empty( $Ts ) ) {

			// If the 1st token isn't <?php
			if ( $Ts[ 0 ][ 0 ] !== T_OPEN_TAG ) {
				throw new \Exception( 'Irregular start to PHP file.' );
			}
			unset( $Ts[ 0 ] );

			$Ts = array_values( $Ts );

			if ( count( $Ts ) >= 3 ) {
				if ( $Ts[ 0 ][ 0 ] == T_DECLARE &&
					 $Ts[ 1 ][ 0 ] == T_STRING && $Ts[ 2 ][ 0 ] == T_LNUMBER ) {
					unset( $Ts[ 0 ], $Ts[ 1 ], $Ts[ 2 ] );

					$Ts = array_values( $Ts );
				}
			}
		}

		return empty( $Ts );
	}

	private function printTokens( $Ts ) {
		foreach ( $Ts as $t ) {
			if ( is_array( $t ) ) {
				echo "Line {$t[2]}: ", token_name( $t[ 0 ] ), " ('{$t[1]}')", PHP_EOL;
			}
		}
	}

	private function getRelevantContent( $file ) :string {
		return implode( "\n", array_filter( array_map( 'trim',
			explode( "\n", Services::DataManipulation()->convertLineEndingsDosToLinux( $file ) )
		) ) );
	}
}