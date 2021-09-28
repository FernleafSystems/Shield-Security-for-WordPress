<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $path_full
 * @property string $path_fragment - relative to ABSPATH
 */
class FileResultItem extends ResultItem {

	public function generateHash() :string {
		$toHash = $this->path_fragment;
		if ( Services::WpFs()->isFile( $this->path_full ) ) {
			$toHash .= Services::DataManipulation()->convertLineEndingsDosToLinux( $this->path_full );
		}
		return md5( $toHash );
	}

	public function getDescriptionForAudit() :string {
		return $this->path_fragment;
	}
}