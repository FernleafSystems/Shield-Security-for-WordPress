<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

/**
 * @property bool   $is_mal
 * @property string $mal_sig
 * @property int[]  $file_lines
 * @property int    $fp_confidence - false positive confidence level
 */
class ResultItem extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\FileResultItem {

}