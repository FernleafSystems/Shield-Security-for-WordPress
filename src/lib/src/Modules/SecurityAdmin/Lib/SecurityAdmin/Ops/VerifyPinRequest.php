<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;
use FernleafSystems\Wordpress\Services\Services;

class VerifyPinRequest {

	use ModConsumer;

	private $valid;

	public function run( string $pin = null ) :bool {
		if ( !isset( $this->valid ) ) {
			$valid = false;

			if ( empty( $pin ) ) {
				$pin = Services::Request()->post( 'sec_admin_key' );
			}

			if ( !empty( $pin ) ) {
				/** @var Options $opts */
				$opts = $this->getOptions();
				$valid = hash_equals( $opts->getSecurityPIN(), md5( $pin ) );
				$this->getCon()->fireEvent( $valid ? 'key_success' : 'key_fail' );

				$toggler = ( new ToggleSecAdminStatus() )->setMod( $this->getMod() );
				$valid = $valid ? $toggler->turnOn() : $toggler->turnOff();
			}

			$this->valid = $valid;
		}
		return $this->valid;
	}
}