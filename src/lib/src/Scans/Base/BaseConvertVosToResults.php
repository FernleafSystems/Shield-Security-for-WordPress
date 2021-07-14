<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO;

/**
 * Class BaseConvertVosToResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 */
abstract class BaseConvertVosToResults {

	/**
	 * @param EntryVO[] $VOs
	 * @return ResultsSet|mixed
	 */
	public function convert( array $VOs ) {
		$result = $this->getNewResultSet();
		foreach ( $VOs as $vo ) {
			$item = $this->convertItem( $vo );
			$item->record_id = (int)$vo->id;
			$result->addItem( $item );
		}
		return $result;
	}

	/**
	 * @param EntryVO $VO
	 * @return ResultItem|mixed
	 */
	public function convertItem( EntryVO $VO ) {
		return $this->getNewResultItem()->applyFromArray( $VO->meta );
	}

	/**
	 * @return ResultItem|mixed
	 */
	protected function getNewResultItem() {
		return new ResultItem();
	}

	/**
	 * @return ResultsSet|mixed
	 */
	protected function getNewResultSet() {
		return new ResultsSet();
	}
}