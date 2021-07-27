<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

/**
 * Class ResultItem
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 * @property bool   $is_mal
 * @property string $mal_sig
 * @property int[]  $file_lines
 * @property int    $fp_confidence - false positive confidence level
 */
class ResultItem extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\FileResultItem {

	public function generateHash() :string {
		return md5( $this->path_full );
	}

	public function isReady() :bool {
		return !empty( $this->path_full ) && !empty( $this->md5_file_wp ) && !empty( $this->path_fragment );
	}
}