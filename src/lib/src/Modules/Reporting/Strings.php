<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @inheritDoc
	 */
	public function getEventStrings() :array {
		return [
			'report_generated' => [
				'name'  => __( 'Report Generated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Report Generated.', 'wp-simple-firewall' ),
					__( 'Type: {{type}}; Interval: {{interval}};', 'wp-simple-firewall' ),
				],
			],
			'report_sent'      => [
				'name'  => __( 'Report Sent', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Report Sent (via {{medium}}).', 'wp-simple-firewall' ),
				],
			],
		];
	}

	public function getOptionStrings( string $key ) :array {
		$con = $this->getCon();

		switch ( $key ) {

			case 'frequency_alert' :
				$name = __( 'Alert Frequency', 'wp-simple-firewall' );
				$summary = __( 'How Often Important Alerts Will Be Sent To You', 'wp-simple-firewall' );
				$description = [
					__( 'Choose when you should be sent important and critical alerts about your site security.', 'wp-simple-firewall' ),
					__( 'Critical alerts are typically results from your most recent site scans.', 'wp-simple-firewall' )
				];
				if ( !$con->isPremiumActive() ) {
					$description[] = __( 'If you wish to receive alerts more quickly, please consider upgrading to ShieldPRO.', 'wp-simple-firewall' );
					$description[] = sprintf( '<a href="%s" target="_blank">%s</a>', 'https://shsec.io/shieldgoprofeature', __( 'Upgrade to ShieldPRO', 'wp-simple-firewall' ) );
				}
				break;

			case 'frequency_info' :
				$name = __( 'Info Frequency', 'wp-simple-firewall' );
				$summary = __( 'How Often Informational Reports Will Be Sent To You', 'wp-simple-firewall' );
				$description = [
					__( 'Choose when you should be sent non-critical information and reports about your site security.', 'wp-simple-firewall' ),
					__( 'Information and reports are typically statistics.', 'wp-simple-firewall' )
				];
				if ( !$con->isPremiumActive() ) {
					$description[] = __( 'If you wish to receive reports more often, please consider upgrading to ShieldPRO.', 'wp-simple-firewall' );
					$description[] = sprintf( '<a href="%s" target="_blank">%s</a>', 'https://shsec.io/shieldgoprofeature', __( 'Upgrade to ShieldPRO', 'wp-simple-firewall' ) );
				}
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $name,
			'summary'     => $summary,
			'description' => $description,
		];
	}

	public function getSectionStrings( string $section ) :array {

		switch ( $section ) {

			case 'section_timings' :
				$title = __( 'Report Frequencies', 'wp-simple-firewall' );
				$titleShort = __( 'Report Frequencies', 'wp-simple-firewall' );
				$summary = [
					__( 'Receive regular reports from the plugin summarising important events.', 'wp-simple-firewall' ),
					sprintf( 'Your reporting email address is: %s', '<code>'.$this->getMod()
																				  ->getPluginReportEmail().'</code>' )
					.' '.
					sprintf( '<br/><a href="%s" class="fw-bolder">%s</a>',
						$this->getCon()->getModule_Plugin()
							 ->getUrl_DirectLinkToOption( 'block_send_email_address' ),
						__( 'Update reporting email address', 'wp-simple-firewall' )
					),
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Choose the most appropriate frequency to receive alerts from Shield according to your schedule.', 'wp-simple-firewall' ) ),
				];
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $title,
			'title_short' => $titleShort,
			'summary'     => ( isset( $summary ) && is_array( $summary ) ) ? $summary : [],
		];
	}
}