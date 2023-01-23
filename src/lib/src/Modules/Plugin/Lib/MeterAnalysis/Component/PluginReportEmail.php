<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class PluginReportEmail extends Base {

	public const SLUG = 'report_email';
	public const WEIGHT = 10;

	protected function isProtected() :bool {
		return Services::Data()->validEmail(
			$this->getCon()->getModule_Plugin()->getOptions()->getOpt( 'block_send_email_address' )
		);
	}

	public function href() :string {
		return $this->link( 'block_send_email_address' );
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