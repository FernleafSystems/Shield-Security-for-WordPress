<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\ModConsumer;

class VerifyPinRequest {

	use ModConsumer;

	public function run( string $pin ) :bool {
		$valid = false;

		if ( !empty( $pin ) ) {
			if ( wp_check_password( $pin, $this->opts()->getSecurityPIN() ) ) {
				$valid = true;
			}
			elseif ( \hash_equals( $this->opts()->getSecurityPIN(), \md5( $pin ) ) ) {
				$this->opts()->setOpt( 'admin_access_key', wp_hash_password( $pin ) );
				$valid = true;
			}
			self::con()->fireEvent( $valid ? 'key_success' : 'key_fail' );
		}

		return $valid;
	}
}