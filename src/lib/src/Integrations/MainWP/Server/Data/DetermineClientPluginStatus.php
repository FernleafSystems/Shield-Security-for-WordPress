<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common\SyncVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class DetermineClientPluginStatus {

	use PluginControllerConsumer;

	const INSTALLED = 'inst';
	const NOT_INSTALLED = 'ninst';
	const VERSION_NEWER_THAN_SERVER = 'vnts';
	const VERSION_OLDER_THAN_SERVER = 'vots';

	/**
	 * TODO: Consider things like global disabled / forceoff
	 * @param SyncVO $sync
	 * @return array
	 */
	public function run( SyncVO $sync ) :array {
		$m = $sync->meta;
		if ( $m->installed_at > 0 ) {
			$versionStatus = version_compare( $this->getCon()->getVersion(), $m->version );
			if ( $versionStatus === -1 ) {
				$status = self::VERSION_NEWER_THAN_SERVER;
			}
			elseif ( $versionStatus === 1 ) {
				$status = self::VERSION_OLDER_THAN_SERVER;
			}
			else {
				$status = self::INSTALLED;
			}
		}
		else {
			$status = self::NOT_INSTALLED;
		}
		return [ $status => $this->getStatusText()[ $status ] ];
	}

	protected function getStatusText() {
		return [
			self::INSTALLED                 => __( 'Installed' ),
			self::NOT_INSTALLED             => __( 'Not Installed' ),
			self::VERSION_OLDER_THAN_SERVER => __( 'Update Required' ),
			self::VERSION_NEWER_THAN_SERVER => __( 'Ahead Of Server' ),
		];
	}
}