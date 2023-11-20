<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Ssl;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class SystemSslCertificate extends Base {

	public const SLUG = 'system_ssl_certificate';
	public const WEIGHT = 5;

	/**
	 * @var ?string
	 */
	private $status = null;

	public function build() :array {
		$this->getSslStatus(); // Ensure we've run the test and set the status before building begins
		return parent::build();
	}

	protected function testIfProtected() :bool {
		return $this->getSslStatus() === 'ssl_valid';
	}

	protected function hrefFullTargetBlank() :bool {
		return true;
	}

	protected function hrefFull() :string {
		switch ( $this->getSslStatus() ) {
			case 'visitor_unprotected':
			case 'settings_inconsistent':
				$href = Services::WpGeneral()->getAdminUrl_Settings();
				break;
			default:
				$href = URL::Build( 'https://mxtoolbox.com/SuperTool.aspx', [
					'action' => Services::WpGeneral()->getHomeUrl(),
					'run'    => 'toolpage'
				] );
				break;
		}
		return $href;
	}

	public function score() :int {
		switch ( $this->getSslStatus() ) {
			case 'ssl_valid':
				$score = static::WEIGHT;
				break;
			case 'ssl_expires_soon':
			case 'settings_inconsistent':
				$score = static::WEIGHT/2;
				break;
			default:
				$score = 0;
				break;
		}
		return (int)$score;
	}

	public function title() :string {
		return __( 'SSL Certificate', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "SSL Certificate remains valid for at least the next 2 weeks.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		switch ( $this->getSslStatus() ) {
			case 'visitor_unprotected':
				$desc = __( "Visitors aren't protected with a valid SSL Certificate.", 'wp-simple-firewall' );
				break;
			case 'settings_inconsistent':
				$desc = __( "HTTPS setting for Home URL and WP Site URL aren't consistent.", 'wp-simple-firewall' );
				break;
			case 'ssl_expired':
				$desc = __( "SSL certificate for this site has expired.", 'wp-simple-firewall' );
				break;
			case 'ssl_expires_soon':
				$desc = __( 'SSL certificate will expire in less than 2 weeks.', 'wp-simple-firewall' );
				break;
			case 'ssl_test_unavailable':
				$desc = __( "Automated testing of your SSL Certificate isn't available on this site.", 'wp-simple-firewall' );
				break;
			case 'ssl_test_fail':
				$desc = __( "Tests failed and we couldn't automatically determine the state of your SSL Certificate.", 'wp-simple-firewall' );
				break;
			default:
				$desc = __( "Visitors aren't protected by a valid SSL Certificate.", 'wp-simple-firewall' );
				break;
		}
		return $desc;
	}

	private function getSslStatus() :string {
		if ( empty( $this->status ) ) {

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

			$this->status = $status;
		}

		return $this->status;
	}
}