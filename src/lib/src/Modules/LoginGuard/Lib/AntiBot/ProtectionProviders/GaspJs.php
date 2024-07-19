<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

/**
 * @deprecated 19.2
 */
class GaspJs extends BaseProtectionProvider {

	public function enqueueJS() {
		add_filter( 'shield/custom_enqueue_assets', function ( array $assets ) {
			return $assets;
		} );
	}

	public function performCheck( $formProvider ) {
	}

	public function buildFormInsert( $formProvider ) :string {
		return '';
	}
}