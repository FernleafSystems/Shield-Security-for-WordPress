<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultsSet
 * @property ResultItem[] $items
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class ResultsSet extends Base\ResultsSet {

	/**
	 * @param ResultItem[] $aItems
	 * @return string[]
	 */
	public function filterItemsForPaths( $aItems ) {
		return array_map(
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->path_fragment;
			},
			$aItems
		);
	}
}