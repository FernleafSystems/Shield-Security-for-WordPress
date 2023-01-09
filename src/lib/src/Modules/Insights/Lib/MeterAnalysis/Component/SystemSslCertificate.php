<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Ssl;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class SystemSslCertificate extends Base {

	public const SLUG = 'system_ssl_certificate';
	public const WEIGHT = 45;

	public function build() :array {
		$WP = Services::WpGeneral();
		$srvSSL = new Ssl();

		$build = parent::build();

		// SSL Expires
		$homeURL = $WP->getHomeUrl();
		$isHomeSsl = strpos( $homeURL, 'https://' ) === 0;

		if ( !$isHomeSsl ) {
			$build[ 'desc_unprotected' ] = __( "Visitors aren't protected with a valid SSL Certificate.", 'wp-simple-firewall' );
		}
		elseif ( strpos( $WP->getWpUrl(), 'https://' ) !== 0 ) {
			$build[ 'desc_unprotected' ] = __( "HTTPS setting for Home URL and WP Site URL aren't consistent.", 'wp-simple-firewall' );
			$build[ 'href' ] = $WP->getAdminUrl_Settings();
		}
		elseif ( $srvSSL->isEnvSupported() ) {
			try {
				// first verify SSL cert:
				$srvSSL->getCertDetailsForDomain( $homeURL );

				// If we didn't throw an exception, we got it.
				$expiresAt = $srvSSL->getExpiresAt( $homeURL );
				if ( $expiresAt > 0 ) {
					$timeRemaining = ( $expiresAt - Services::Request()->ts() );
					$isExpired = $timeRemaining < 0;
					$daysLeft = $isExpired ? 0 : (int)round( $timeRemaining/DAY_IN_SECONDS, 0, PHP_ROUND_HALF_DOWN );

					if ( $daysLeft < 15 ) {
						if ( $isExpired ) {
							$build[ 'desc_unprotected' ] = __( 'SSL certificate for this site has expired.', 'wp-simple-firewall' );
						}
						else {
							$build[ 'desc_unprotected' ] = sprintf( __( 'SSL certificate will expire soon (%s days)', 'wp-simple-firewall' ), $daysLeft );
						}
					}
					else {
						$build[ 'protected' ] = true;
					}
				}
			}
			catch ( \Exception $e ) {
				$build[ 'desc_unprotected' ] = sprintf( '%s: %s', __( "Couldn't automatically test and verify your site SSL certificate", 'wp-simple-firewall' ), $e->getMessage() );
			}
		}
		else {
			$build[ 'protected' ] = true;
		}

		return $build;
	}

	public function href() :string {
		return URL::Build( 'https://mxtoolbox.com/SuperTool.aspx', [
			'action' => Services::WpGeneral()->getHomeUrl(),
			'run'    => 'toolpage'
		] );
	}

	public function title() :string {
		return __( 'SSL Certificate', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "SSL Certificate remains valid for at least the next 2 weeks.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Visitors aren't protected by a valid SSL Certificate.", 'wp-simple-firewall' );
	}
}