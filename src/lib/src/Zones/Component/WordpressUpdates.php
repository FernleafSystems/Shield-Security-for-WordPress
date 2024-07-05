<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Services\Services;

class WordpressUpdates extends Base {

	public function title() :string {
		return __( 'WordPress Updates', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'A high-level overview of your WordPress updates status.', 'wp-simple-firewall' );
	}

	protected function hasConfigAction() :bool {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();
		if ( Services::WpGeneral()->hasCoreUpdate() ) {
			$level = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "WP Core update is available.", 'wp-simple-firewall' );
		}
		if ( \count( Services::WpPlugins()->getUpdates() ) ) {
			$status[ 'exp' ][] = __( "WP plugin update(s) available.", 'wp-simple-firewall' );
		}
		if ( \count( Services::WpThemes()->getUpdates() ) ) {
			$status[ 'exp' ][] = __( "WP theme update(s) available.", 'wp-simple-firewall' );
		}
		if ( empty( $level ) ) {
			$level = \count( $status[ 'exp' ] ) === 0 ? EnumEnabledStatus::GOOD : EnumEnabledStatus::OKAY;
		}
		$status[ 'level' ] = $level;
		return $status;
	}
}