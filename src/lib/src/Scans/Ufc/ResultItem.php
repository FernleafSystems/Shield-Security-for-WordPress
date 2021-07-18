<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

class ResultItem extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\FileResultItem {

	public function generateHash() :string {
		return md5( $this->path_full );
	}
}