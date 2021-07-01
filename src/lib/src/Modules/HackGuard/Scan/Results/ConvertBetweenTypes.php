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
	 * @param Scans\Base\BaseResultsSet $oResultsSet
	 * @return Databases\Scanner\EntryVO[]|mixed
	 */
	public function fromResultsToVOs( $oResultsSet ) {
		$vos = [];
		foreach ( $oResultsSet->getAllItems() as $item ) {
			/** @var Scans\Base\BaseResultItem $item */
			$vos[ $item->generateHash() ] = $this->convertResultItemToVO( $item );
		}
		return $vos;
	}

	/**
	 * @param Databases\Scanner\EntryVO[] $aVOs
	 * @return Scans\Base\BaseResultsSet|mixed
	 */
	public function fromVOsToResultsSet( $aVOs ) {
		$oRes = $this->getScanController()->getNewResultsSet();
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
		return $this->getScanController()
					->getNewResultItem()
					->applyFromArray( $oVo->meta );
	}

	/**
	 * @param Scans\Base\BaseResultItem $oIt
	 * @return Databases\Scanner\EntryVO
	 */
	private function convertResultItemToVO( $oIt ) {
		$oVo = new Databases\Scanner\EntryVO();
		$oVo->hash = $oIt->hash;
		$oVo->meta = $oIt->getData();
		$oVo->scan = $this->getScanController()->getSlug();
		return $oVo;
	}
}
