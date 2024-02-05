<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties;

class PasskeyCompatibilityCheck {

	public function run() :bool {
		$can = false;
		if ( \function_exists( '\extension_loaded' ) ) {
			foreach ( $this->requiredExtensions() as $requiredExtension ) {
				if ( \extension_loaded( $requiredExtension ) ) {
					$can = true;
					break;
				}
			}
		}
		return $can;
	}

	public function requiredExtensions() :array {
		return [
			'bcmath',
			'gmp',
		];
	}
}