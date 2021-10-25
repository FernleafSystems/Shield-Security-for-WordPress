<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class ItemActionHandler extends Base\Utilities\ItemActionHandler {

	/**
	 * @throws \Exception
	 */
	public function ignore() :bool {
		parent::ignore();

		( new MalFalsePositiveReporter() )
			->setMod( $this->getMod() )
			->reportResultItem( $this->getScanItem(), true );

		return true;
	}

	/**
	 * @throws \Exception
	 */
	public function repairDelete() :bool {
		return $this->repair( true );
	}
}
