<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps\ReportEmail;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportEmailHold;

/**
 * An Actions Queue item for as long as the report email hold lasts, leading to the setup guide's report email step.
 */
class ReportEmailChoice extends Base {

	public const SLUG = 'report_email';
	public const WEIGHT = 1;

	public function channel() :string {
		return self::CHANNEL_ACTION;
	}

	protected function isApplicable() :bool {
		return ( new ReportEmailHold() )->isActive();
	}

	protected function hrefFull() :string {
		return self::con()->plugin_urls->setupGuide( ReportEmail::SLUG );
	}

	protected function hrefFullTargetBlank() :bool {
		return false;
	}

	protected function text() :array {
		return \array_merge(
			parent::text(),
			[
				'fix' => __( 'Choose report email', 'wp-simple-firewall' ),
			]
		);
	}

	public function title() :string {
		return __( 'Choose where Shield sends reports', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'A report email has been chosen.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return ( new ReportEmailHold() )->explanation();
	}
}
