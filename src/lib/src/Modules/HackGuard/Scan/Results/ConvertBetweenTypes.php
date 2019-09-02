<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ConvertBetweenTypes
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
class ConvertBetweenTypes {

	use Scans\Common\ScanActionConsumer;

	/**
	 * @param Scans\Base\BaseResultsSet $oResultsSet
	 * @return Databases\Scanner\EntryVO[]|mixed
	 */
	public function fromResultsToVOs( $oResultsSet ) {
		$aVos = [];
		foreach ( $oResultsSet->getAllItems() as $oIt ) {
			/** @var Scans\Base\BaseResultItem $oIt */
			$aVos[ $oIt->generateHash() ] = $this->convertResultItemToVO( $oIt );
		}
		return $aVos;
	}

	/**
	 * @param Databases\Scanner\EntryVO[] $aVOs
	 * @return Scans\Base\BaseResultsSet|mixed
	 */
	public function fromVOsToResultsSet( $aVOs ) {
		$oRes = $this->getScanActionVO()->getNewResultsSet();
		foreach ( $aVOs as $oVo ) {
			$oRes->addItem( $this->convertVoToResultItem( $oVo ) );
		}
		return $oRes;
	}

	/**
	 * @param Databases\Scanner\EntryVO $oVo
	 * @return Scans\Base\BaseResultItem
	 */
	public function convertVoToResultItem( $oVo ) {
		$oAction = $this->getScanActionVO();
		$oItem = $oAction->getNewResultItem()->applyFromArray( $oVo->meta );
		return $oItem;
	}

	/**
	 * @param Scans\Base\BaseResultItem $oIt
	 * @return Databases\Scanner\EntryVO
	 */
	private function convertResultItemToVO( $oIt ) {
		$oVo = new Databases\Scanner\EntryVO();
		$oVo->hash = $oIt->hash;
		$oVo->meta = $oIt->getData();
		$oVo->scan = $this->getScanActionVO()->scan;
		return $oVo;
	}
}
