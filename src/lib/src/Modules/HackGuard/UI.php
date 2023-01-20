<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\CipherTests;

class UI extends BaseShield\UI {

	public function getSectionWarnings( string $section ) :array {
		$con = $this->getCon();
		$warnings = [];

		switch ( $section ) {

			case 'section_file_guard':
				if ( !$this->getCon()->cache_dir_handler->exists() ) {
					$warnings[] = __( "Plugin/Theme file scanners are unavailable because we couldn't create a temporary directory to store files.", 'wp-simple-firewall' );
				}

				if ( $con->isPremiumActive() ) {
					$canHandshake = $con->getModule_Plugin()
										->getShieldNetApiController()
										->canHandshake();
					if ( !$canHandshake ) {
						$warnings[] = sprintf( __( 'Not available as your site cannot handshake with ShieldNET API.', 'wp-simple-firewall' ), 'OpenSSL' );
					}
				}

				$enc = Services::Encrypt();
				if ( !$enc->isSupportedOpenSslDataEncryption() ) {
					$warnings[] = sprintf( __( "FileLocker can't be used because the PHP %s extension isn't available.", 'wp-simple-firewall' ), 'OpenSSL' );
				}
				elseif ( count( ( new CipherTests() )->findAvailableCiphers() ) === 0 ) {
					$warnings[] = sprintf( __( "FileLocker can't be used because there is no encryption cipher isn't available.", 'wp-simple-firewall' ), 'OpenSSL' );
				}

				break;
		}

		return $warnings;
	}
}