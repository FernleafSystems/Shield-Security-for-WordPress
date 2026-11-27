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

	public function guidance() :array {
		return [
			'checks'   => __( "Shield checks whether an email address has been chosen for its reports and alerts. It asks only during the first day after it's installed.", 'wp-simple-firewall' ),
			'why'      => [
				__( "Shield emails you when it finds something that needs you, such as a changed file or a plugin with a known security hole. For that first day it holds these emails back, so they don't go to the site admin email, which often belongs to someone other than the person who installed Shield.", 'wp-simple-firewall' ),
			],
			'steps'    => [
				__( "Use this item's link in the queue to open the setup guide's report email step.", 'wp-simple-firewall' ),
				__( 'Enter the address that should get the emails, and save. After that, Shield sends its emails to that address, including the ones it held.', 'wp-simple-firewall' ),
			],
			'who'      => __( "Any administrator can do this. If you do nothing, the emails go to the site admin email once Shield has been installed for a day.", 'wp-simple-firewall' ),
			'practice' => [
				__( 'Choose an address that is read every day, by whoever looks after the site.', 'wp-simple-firewall' ),
			],
		];
	}
}
