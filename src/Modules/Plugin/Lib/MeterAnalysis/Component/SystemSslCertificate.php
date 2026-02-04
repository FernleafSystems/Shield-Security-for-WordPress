<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\SslStatus;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class SystemSslCertificate extends Base {

	public const SLUG = 'system_ssl_certificate';
	public const WEIGHT = 5;

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
		return SslStatus::Check();
	}
}