<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;
use FernleafSystems\Wordpress\Services\Utilities\Ssl;

class SslStatus {

	public static function Check() :string {
		$key = 'apto_site_ssl_certificate_check_result';
		$status = Transient::Get( $key );
		if ( empty( $status ) ) {
			$srvSSL = new Ssl();
			$homeURL = Services::WpGeneral()->getHomeUrl();

			if ( \strpos( $homeURL, 'https://' ) !== 0 ) {
				$status = 'visitor_unprotected';
			}
			elseif ( \strpos( Services::WpGeneral()->getWpUrl(), 'https://' ) !== 0 ) {
				$status = 'settings_inconsistent';
			}
			elseif ( $srvSSL->isEnvSupported() ) {
				try {
					// first verify SSL cert:
					$srvSSL->getCertDetailsForDomain( $homeURL );

					// If we didn't throw an exception, we got it.
					$expiresAt = $srvSSL->getExpiresAt( $homeURL );
					if ( $expiresAt < 0 ) {
						throw new \Exception( 'Failed to get expiry.' );
					}

					$timeRemaining = $expiresAt - Services::Request()->ts();
					$isExpired = $timeRemaining < 0;
					$daysLeft = $isExpired ? 0 : (int)\round( $timeRemaining/\DAY_IN_SECONDS, 0, \PHP_ROUND_HALF_DOWN );
					$status = $isExpired ? 'ssl_expired' : ( $daysLeft < 15 ? 'ssl_expires_soon' : 'ssl_valid' );
				}
				catch ( \Exception $e ) {
					$status = 'ssl_test_fail';
				}
			}
			else {
				$status = 'ssl_test_unavailable';
			}
			Transient::Set( $key, $status, 60 );
		}
		return $status;
	}
}