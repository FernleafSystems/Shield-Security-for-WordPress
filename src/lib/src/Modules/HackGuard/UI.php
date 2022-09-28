<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

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
				elseif ( !$enc->hasCipherAlgo( 'rc4' ) ) {
					$warnings[] = sprintf( __( "FileLocker can't be used because the RC4 encryption cipher isn't available.", 'wp-simple-firewall' ), 'OpenSSL' );
				}

//				if ( !Services::Encrypt()->isSupportedOpenSslDataEncryption() ) {
//					$warnings[] = sprintf( __( 'Not available because the %s extension is not available.', 'wp-simple-firewall' ), 'OpenSSL' );
//				}
//				if ( !Services::WpFs()->isFilesystemAccessDirect() ) {
//					$warnings[] = sprintf( __( "Not available because PHP/WordPress doesn't have direct filesystem access.", 'wp-simple-firewall' ), 'OpenSSL' );
//				}
				break;
		}

		return $warnings;
	}
}