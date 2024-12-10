<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class VerifyPinRequest {

	use PluginControllerConsumer;

	public function run( string $pin ) :bool {
		$valid = false;

		if ( !empty( $pin ) ) {
			$hashedPIN = self::con()->comps->opts_lookup->getSecAdminPIN();
			if ( wp_check_password( $pin, $hashedPIN ) ) {
				$valid = true;
			}
			elseif ( \hash_equals( $hashedPIN, \hash( 'md5', $pin ) ) ) {
				self::con()->opts->optSet( 'admin_access_key', wp_hash_password( $pin ) );
				$valid = true;
			}
			self::con()->fireEvent( $valid ? 'key_success' : 'key_fail' );
		}

		return $valid;
	}
}