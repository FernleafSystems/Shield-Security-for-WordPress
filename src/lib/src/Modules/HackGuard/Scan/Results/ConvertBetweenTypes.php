<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ConvertBetweenTypes
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results
 */
class ConvertBetweenTypes {

	use ScanControllerConsumer;

	/**
	 * @param Scans\Base\ResultsSet $resultsSet
	 * @return Databases\Scanner\EntryVO[]|mixed
	 */
	public function fromResultsToVOs( $resultsSet ) {
		$vos = [];
		foreach ( $resultsSet->getAllItems() as $item ) {
			/** @var Scans\Base\ResultItem $item */
			$vos[ $item->generateHash() ] = $this->convertResultItemToVO( $item );
		}
		return $vos;
	}

	/**
	 * @param Databases\Scanner\EntryVO[] $VOs
	 * @return Scans\Base\ResultsSet|mixed
	 */
	public function fromVOsToResultsSet( $VOs ) {
		$results = $this->getScanController()->getNewResultsSet();
		foreach ( $VOs as $VO ) {
			$results->addItem( $this->convertVoToResultItem( $VO ) );
		}
		return $results;
	}

	/**
	 * @param Databases\Scanner\EntryVO $VO
	 * @return Scans\Base\ResultItem
	 */
	public function convertVoToResultItem( $VO ) {
		$item = $this->getScanController()
					 ->getNewResultItem()
					 ->applyFromArray( $VO->meta );
		$item->record_id = $VO->id;
		$item->scan = $VO->scan;
		return $item;
	}

	/**
	 * @param Scans\Base\ResultItem $item
	 * @return Databases\Scanner\EntryVO
	 */
	private function convertResultItemToVO( $item ) {
		$vo = new Databases\Scanner\EntryVO();
		$vo->hash = $item->hash;
		$vo->meta = $item->getData();
		$vo->scan = $this->getScanController()->getSlug();
		if ( isset( $item->record_id ) ) {
			$vo->id = $item->record_id;
		}
		return $vo;
	}
}