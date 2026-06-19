<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * @property string $path_full
 * @property string $path_fragment - filesystem-service canonical file item ID
 */
class FileResultItem extends ResultItem {

	public function getDescriptionForAudit() :string {
		return $this->path_fragment;
	}
}
