<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

/**
 * Class ResultItem
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 * @property string $md5_file_wp
 * @property bool   $is_checksumfail
 * @property bool   $is_missing
 */
class ResultItem extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\FileResultItem {

	public function isReady() :bool {
		return !empty( $this->path_full ) && !empty( $this->md5_file_wp ) && !empty( $this->path_fragment );
	}
}