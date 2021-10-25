<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * @property string $path_full
 * @property string $path_fragment - relative to ABSPATH
 */
class FileResultItem extends ResultItem {

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'path_full':
				if ( empty( $value ) ) {
					$value = path_join( wp_normalize_path( ABSPATH ), $this->path_fragment );
				}
				break;
			default:
				break;
		}
		return $value;
	}

	public function getDescriptionForAudit() :string {
		return $this->path_fragment;
	}
}