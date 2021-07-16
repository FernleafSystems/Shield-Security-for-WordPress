<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class ConvertVosToResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv
 */
class ConvertVosToResults extends Shield\Scans\Base\BaseConvertVosToResults {

	/**
	 * @param Shield\Databases\Scanner\EntryVO[] $VOs
	 * @return ResultsSet
	 */
	public function convert( $VOs ) {
		$results = new ResultsSet();
		foreach ( $VOs as $vo ) {
			$results->addItem( $this->convertItem( $vo ) );
		}
		return $results;
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO $VO
	 * @return ResultItem
	 */
	public function convertItem( $VO ) {
		return ( new ResultItem() )->applyFromArray( $VO->meta );
	}
}