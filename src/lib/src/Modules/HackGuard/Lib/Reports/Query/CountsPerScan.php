<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports\Query;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class CountsPerScan {

	use ModConsumer;

	/**
	 * @param int|null $from
	 * @param int|null $to
	 * @return int[] - key is scan slug
	 */
	public function run( $from = null, $to = null ) :array {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $mod */
		$mod = $this->getMod();
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		/** @var Scanner\Select $select */
		$select = $mod->getDbHandler_ScanResults()->getQuerySelector();

		$counts = [];

		foreach ( $opts->getScanSlugs() as $slug ) {
			$select->filterByScan( $slug )
				   ->filterByNotNotified()
				   ->filterByNotIgnored();
			if ( !is_int( $from ) ) {
				$select->filterByCreatedAt( $from, '>' );
			}
			if ( !is_int( $to ) ) {
				$select->filterByCreatedAt( $to, '<' );
			}
			$counts[ $slug ] = $select->count();
		}
		return $counts;
	}
}
