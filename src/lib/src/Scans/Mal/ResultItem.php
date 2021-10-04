<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\FileResultItem;

/**
 * @property bool   $is_mal
 * @property string $mal_sig
 * @property int[]  $file_lines
 * @property int    $fp_confidence - false positive confidence level
 */
class ResultItem extends FileResultItem {

}