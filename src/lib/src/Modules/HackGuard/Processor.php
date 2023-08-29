<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	use ModConsumer;

	protected function run() {
		$this->mod()->getScansCon()->execute();
		if ( \count( $this->opts()->getFilesToLock() ) > 0 ) {
			$this->mod()->getFileLocker()->execute();
		}
	}

	public function addAdminBarMenuGroup( array $groups ) :array {
		if ( self::con()->isValidAdminArea() ) {
			$urls = self::con()->plugin_urls;

			$thisGroup = [
				'href'  => $urls->adminTopNav( $urls::NAV_SCANS_RESULTS ),
				'items' => [],
			];
			foreach ( $this->mod()->getScansCon()->getAllScanCons() as $scanCon ) {
				if ( $scanCon->isEnabled() ) {
					$thisGroup[ 'items' ] = \array_merge( $thisGroup[ 'items' ], $scanCon->getAdminMenuItems() );
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
		}

		return $groups;
	}
}