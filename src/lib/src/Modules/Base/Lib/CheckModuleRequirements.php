<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib;

use FernleafSystems\Wordpress\Plugin\Shield;

class CheckModuleRequirements {

	use Shield\Modules\ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() :bool {
		$preChecks = $this->con()->prechecks;
		$modReqs = $this->mod()->cfg->reqs;

		$modChecks = [
			// all DBs that this mod requires but that aren't ready.
			'dbs' => array_filter(
				array_intersect_key( $preChecks[ 'dbs' ], array_flip( $modReqs[ 'dbs' ] ) ),
				function ( $dbState ) {
					return $dbState === false;
				}
			)
		];

		// We're ready to run if all our DBs are ready.
		return empty( $modChecks[ 'dbs' ] );
	}
}