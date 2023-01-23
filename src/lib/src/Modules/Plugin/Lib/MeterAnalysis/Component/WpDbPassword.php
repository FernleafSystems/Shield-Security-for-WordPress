<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;
use ZxcvbnPhp\Zxcvbn;

class WpDbPassword extends Base {

	public const SLUG = 'wp_db_password';
	public const WEIGHT = 25;

	public function href() :string {
		return URL::Build( 'https://mxtoolbox.com/SuperTool.aspx', [
			'action' => Services::WpGeneral()->getHomeUrl(),
			'run'    => 'toolpage'
		] );
	}

	protected function isProtected() :bool {
		return ( ( new Zxcvbn() )->passwordStrength( DB_PASSWORD )[ 'score' ] ?? 0 ) >= 4;
	}

	public function title() :string {
		return __( 'MySQL DB Password', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "WP Database password is very strong.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "WP Database password appears to be weak.", 'wp-simple-firewall' );
	}
}