<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\Utilities;

class ItemActionHandler extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities\ItemActionHandler {

	/**
	 * A known vulnerability stays in view until it's dealt with, so it can't be ignored.
	 * @throws \Exception
	 */
	public function ignore() :bool {
		throw new \Exception( 'Known vulnerabilities cannot be ignored.' );
	}
}
