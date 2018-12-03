<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class BaseRepair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 */
abstract class BaseRepair {

	/**
	 * @param BaseResultsSet $oResults
	 */
	public function repairResultsSet( $oResults ) {
		foreach ( $oResults->getItems() as $oItem ) {
			try {
				/** @var BaseResultItem $oItem */
				$this->repairItem( $oItem );
			}
			catch ( \Exception $oE ) {
			}
		}
	}

	/**
	 * @param BaseResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	abstract public function repairItem( $oItem );
}