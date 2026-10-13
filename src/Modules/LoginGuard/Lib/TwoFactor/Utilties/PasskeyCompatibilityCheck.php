<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties;

class PasskeyCompatibilityCheck {

	public function run() :bool {
		foreach ( $this->requiredExtensions() as $requiredExtension ) {
			if ( !$this->isExtensionLoaded( $requiredExtension ) ) {
				return false;
			}
		}

		foreach ( $this->requiredFunctions() as $requiredFunction ) {
			if ( !$this->isFunctionAvailable( $requiredFunction ) ) {
				return false;
			}
		}

		return true;
	}

	public function requiredExtensions() :array {
		return [
			'json',
			'openssl',
		];
	}

	public function requiredFunctions() :array {
		return [
			'mb_strlen',
		];
	}

	protected function isExtensionLoaded( string $extension ) :bool {
		return \extension_loaded( $extension );
	}

	protected function isFunctionAvailable( string $function ) :bool {
		return \function_exists( $function );
	}
}
