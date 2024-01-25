<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities;

class ItemActionHandler extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities\ItemActionHandler {

	/**
	 * @throws \Exception
	 */
	public function repairDelete() :bool {
		return $this->repair( true );
	}
}
