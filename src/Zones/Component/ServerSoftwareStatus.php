<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\SslStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Services\Services;
use ZxcvbnPhp\Zxcvbn;

class ServerSoftwareStatus extends Base {

	public function title() :string {
		return __( 'Server Software Status', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'A high-level overview of your WordPress hosting server software.', 'wp-simple-firewall' );
	}

	protected function hasConfigAction() :bool {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();
		$status[ 'level' ] = EnumEnabledStatus::GOOD;

		if ( !Services::Data()->getPhpVersionIsAtLeast( '7.4' ) ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = sprintf( __( "PHP version should ideally be at least %s.", 'wp-simple-firewall' ), '7.4' );
		}
		if ( SslStatus::Check() !== 'ssl_valid' ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = $this->sslStatus();
		}
		if ( ( ( new Zxcvbn() )->passwordStrength( DB_PASSWORD )[ 'score' ] ?? 0 ) < 4 ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = __( "WP Database password appears to be weak.", 'wp-simple-firewall' );
		}

		if ( $status[ 'level' ] === EnumEnabledStatus::GOOD ) {
			$status[ 'exp' ][] = __( "PHP, MySQL password, and your SSL Certificate all appear to be in good order.", 'wp-simple-firewall' );
		}

		return $status;
	}

	private function sslStatus() :string {
		switch ( SslStatus::Check() ) {
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
}