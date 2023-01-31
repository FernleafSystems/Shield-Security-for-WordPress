<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$mod->getScansCon()->execute();

		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( count( $opts->getFilesToLock() ) > 0 ) {
			$mod->getFileLocker()->execute();
		}
	}

	public function addAdminBarMenuGroup( array $groups ) :array {
		$con = $this->getCon();
		$urls = $con->plugin_urls;

		/** @var ModCon $mod */
		$mod = $this->getMod();
		$thisGroup = [
			'href'  => $urls ? $urls->adminTopNav( $urls::NAV_SCANS_RESULTS ) :
				$con->getModule_Insights()->getUrl_ScansResults(),
			'items' => [],
		];
		foreach ( $mod->getScansCon()->getAllScanCons() as $scanCon ) {
			if ( $scanCon->isEnabled() ) {
				$thisGroup[ 'items' ] = array_merge( $thisGroup[ 'items' ], $scanCon->getAdminMenuItems() );
			}
		}

		if ( !empty( $thisGroup[ 'items' ] ) ) {
			$totalWarnings = 0;
			foreach ( $thisGroup[ 'items' ] as $item ) {
				$totalWarnings += $item[ 'warnings' ];
			}
			$thisGroup[ 'title' ] = sprintf( '%s %s', __( 'Scan Results', 'wp-simple-firewall' ),
				sprintf( '<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>', $totalWarnings ) );
			$groups[] = $thisGroup;
		}

		return $groups;
	}
}