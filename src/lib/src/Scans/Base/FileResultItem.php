<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * @property string $path_full
 * @property string $path_fragment - relative to ABSPATH
 */
class FileResultItem extends ResultItem {

	public function getDescriptionForAudit() :string {
		return $this->path_fragment;
	}
}