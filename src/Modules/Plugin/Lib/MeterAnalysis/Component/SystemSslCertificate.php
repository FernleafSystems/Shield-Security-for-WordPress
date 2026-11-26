<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\SslStatus;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class SystemSslCertificate extends Base {

	public const SLUG = 'system_ssl_certificate';
	public const WEIGHT = 5;

	public function build( ?string $meterChannel = null ) :array {
		$this->getSslStatus(); // Ensure we've run the test and set the status before building begins
		return parent::build( $meterChannel );
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

	public function guidance() :array {
		return [
			'checks'   => __( "Shield checks that your site's address uses HTTPS, that the WordPress Address and Site Address settings both do, and that your SSL certificate is valid with at least 2 weeks left before it expires.", 'wp-simple-firewall' ),
			'why'      => [
				__( "Without a valid certificate, passwords and anything else your visitors send can be read on the way, and browsers warn visitors that your site isn't secure.", 'wp-simple-firewall' ),
			],
			'steps'    => $this->guidanceSteps(),
			'who'      => __( 'Your web host looks after the certificate. Any administrator can change the address settings.', 'wp-simple-firewall' ),
			'practice' => [
				__( 'Use a certificate that renews automatically.', 'wp-simple-firewall' ),
				__( 'Have every http:// visit redirected to https://.', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return list<string>
	 */
	private function guidanceSteps() :array {
		switch ( $this->getSslStatus() ) {
			case 'ssl_valid':
				$steps = [];
				break;
			case 'visitor_unprotected':
				$steps = [
					__( "Ask your web host to install an SSL certificate for your domain. Most hosts provide a free one from their control panel.", 'wp-simple-firewall' ),
					__( 'Check that your site and its login page both load at their https:// addresses.', 'wp-simple-firewall' ),
					__( "In Settings -> General, change both WordPress Address and Site Address to start with https://, then save. You'll be asked to log in again.", 'wp-simple-firewall' ),
					__( 'Ask your host to redirect every http:// visit to https://.', 'wp-simple-firewall' ),
				];
				break;
			case 'settings_inconsistent':
				$steps = [
					__( "Go to Settings -> General and see which of WordPress Address and Site Address doesn't start with https://.", 'wp-simple-firewall' ),
					__( "Check that address loads in your browser with https:// in front of it. If it doesn't, stop there and ask your web host, as saving it would lock you out of your admin area.", 'wp-simple-firewall' ),
					__( 'Change it to start with https://, then save.', 'wp-simple-firewall' ),
					__( "If either box is greyed out, that address is set in wp-config.php and has to be changed there.", 'wp-simple-firewall' ),
				];
				break;
			case 'ssl_expired':
				$steps = [
					__( "Renew the certificate now, with your web host or whoever issued it. Until it's renewed, browsers warn visitors away from your site.", 'wp-simple-firewall' ),
					__( "Ask why it wasn't renewed automatically. Most certificates are, so an expired one usually means the renewal is failing and will fail again.", 'wp-simple-firewall' ),
				];
				break;
			case 'ssl_expires_soon':
				$steps = [
					__( 'Ask your web host whether the certificate renews automatically. Most do, in their last few weeks, so this item may clear by itself.', 'wp-simple-firewall' ),
					__( "If it doesn't renew automatically, renew it now.", 'wp-simple-firewall' ),
				];
				break;
			default:
				// The check couldn't be made from this server, so the certificate may well be fine.
				$steps = [
					__( "Shield couldn't check the certificate from this server, so there may be nothing wrong. Open your site in a browser and select the icon at the start of the address bar to see the certificate and when it expires.", 'wp-simple-firewall' ),
					__( "If the browser warns that the site isn't secure, ask your web host to install or renew the certificate.", 'wp-simple-firewall' ),
				];
				break;
		}
		return $steps;
	}

	private function getSslStatus() :string {
		return SslStatus::Check();
	}
}
