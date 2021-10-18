<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\ConvertLineEndings;

/**
 * @property string $path_full
 * @property string $path_fragment - relative to ABSPATH
 */
class FileResultItem extends ResultItem {

	public function generateHash() :string {
		$FS = Services::WpFs();
		$toHash = $this->path_fragment;
		if ( $FS->isFile( $this->path_full ) ) {
			$toHash .= $FS->getModifiedTime( $this->path_full )
					   .( new ConvertLineEndings() )->fileDosToLinux( $this->path_full );
		}
		return md5( $toHash );
	}

	public function getDescriptionForAudit() :string {
		return $this->path_fragment;
	}
}