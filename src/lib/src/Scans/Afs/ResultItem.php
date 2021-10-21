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
 * @property string $mal_sig
 * @property int[]  $file_lines
 * @property int    $fp_confidence - false positive confidence level
 */
class ResultItem extends FileResultItem {

	/**
	 * @inheritDoc
	 */
	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'is_mal':
				$value = true;
				break;
			case 'file_lines':
				if ( !is_array( $value ) ) {
					$value = [];
				}
				break;
			default:
				break;
		}
		return $value;
	}
}