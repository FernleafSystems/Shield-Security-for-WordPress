<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;

class VerifyPinRequest {

	use ModConsumer;

	public const MOD = ModCon::SLUG;

	public function run( string $pin ) :bool {
		$valid = false;

		if ( !empty( $pin ) ) {
			/** @var Options $opts */
			$opts = $this->getOptions();
			$valid = hash_equals( $opts->getSecurityPIN(), md5( $pin ) );
			$this->getCon()->fireEvent( $valid ? 'key_success' : 'key_fail' );
		}

		return $valid;
	}
}