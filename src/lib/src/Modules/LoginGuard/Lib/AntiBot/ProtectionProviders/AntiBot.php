<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

/**
 * @deprecated 19.2
 */
class AntiBot extends BaseProtectionProvider {

	public function performCheck( $formProvider ) {
	}

	public function buildFormInsert( $formProvider ) :string {
		return '';
	}
}