<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class PluginReportEmail extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'report_email';
	public const WEIGHT = 1;

	protected function testIfProtected() :bool {
		return Services::Data()->validEmail( self::con()->opts->optGet( 'block_send_email_address' ) );
	}

	protected function getOptConfigKey() :string {
		return 'block_send_email_address';
	}

	public function title() :string {
		return __( 'Report Email', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'An email address has been provided for reporting important security notices.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "An email address hasn't been provided for reporting important security notices.", 'wp-simple-firewall' )
			   .' '.__( 'A default will be used.', 'wp-simple-firewall' );
	}
}