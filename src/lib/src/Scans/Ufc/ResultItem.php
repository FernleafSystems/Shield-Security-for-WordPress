<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

/**
 * Class ResultItem
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc
 * @property string $path_full
 * @property string $path_fragment
 */
class ResultItem extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseResultItem {

	public function generateHash() :string {
		return md5( $this->path_full );
	}
}