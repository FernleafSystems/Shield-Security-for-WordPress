<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\FileResultItem;

/**
 * @property bool   $is_in_core
 * @property bool   $is_in_plugin
 * @property bool   $is_in_theme
 * @property bool   $is_checksumfail
 * @property bool   $is_unrecognised
 * @property bool   $is_missing
 * @property bool   $is_mal
 * @property int    $mal_fp_confidence
 * @property array  $mal_fp_lines
 * @property array  $mal_file_lines
 * @property string $mal_sig
 */
class ResultItem extends FileResultItem {

	/**
	 * @inheritDoc
	 */
	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'mal_fp_lines':
				if ( !is_array( $value ) ) {
					$value = json_decode( $value, true );
				}
				break;
			case 'mal_file_lines':
				if ( !is_array( $value ) ) {
					$value = json_decode( base64_decode( $value ), true );
				}
				break;
			case 'mal_sig':
				$value = base64_decode( $value );
				break;
			case 'mal_fp_confidence':
				$value = (int)( $value );
				break;
			default:
				break;
		}

		if ( preg_match( '/^is_/i', $key ) ) {
			$value = (bool)$value;
		}

		return $value;
	}

	/**
	 * @inheritDoc
	 */
	public function __set( string $key, $value ) {
		switch ( $key ) {
			case 'mal_fp_lines':
				$value = json_encode( $value );
				break;
			case 'mal_file_lines':
				$value = base64_encode( json_encode( $value ) );
				break;
			case 'mal_sig':
				$value = base64_encode( $value );
				break;
			case 'mal_fp_confidence':
				$value = (int)( $value );
				break;
			default:
				break;
		}

		if ( preg_match( '/^is_/i', $key ) ) {
			$value = (bool)$value;
		}

		parent::__set( $key, $value );
	}
}