<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class ItemActionHandler extends Base\Utilities\ItemActionHandler {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function ignore() {
		parent::ignore();

		( new FalsePositiveReporter() )
			->setMod( $this->getMod() )
			->reportResultItem( $this->getScanItem(), true );

		return true;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function repairDelete() :bool {
		return $this->repair( true );
	}

	/**
	 * @return Repair
	 */
	public function getRepairer() {
		return ( new Repair() )
			->setScanItem( $this->getScanItem() )
			->setMod( $this->getMod() );
	}
}
