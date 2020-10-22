<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() :array {
		return [
			'lic_check_success'   => [
				__( 'Pro License check succeeded.', 'wp-simple-firewall' )
			],
			'lic_fail_email'      => [
				__( 'License check failed. Sending Warning Email.', 'wp-simple-firewall' )
			],
			'lic_fail_deactivate' => [
				__( 'License check failed. Deactivating Pro.', 'wp-simple-firewall' )
			],
		];
	}

	/**
	 * @param string $key
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( string $key ) :array {
		$oCon = $this->getCon();

		switch ( $key ) {

			case 'frequency_alert' :
				$sName = __( 'Alert Frequency', 'wp-simple-firewall' );
				$sSummary = __( 'How Often Important Alerts Will Be Sent To You', 'wp-simple-firewall' );
				$sDescription = [
					__( 'Choose when you should be sent important and critical alerts about your site security.', 'wp-simple-firewall' ),
					__( 'Critical alerts are typically results from your most recent site scans.', 'wp-simple-firewall' )
				];
				if ( !$oCon->isPremiumActive() ) {
					$sDescription[] = __( 'If you wish to receive alerts more quickly, please consider upgrading to ShieldPRO.', 'wp-simple-firewall' );
					$sDescription[] = sprintf( '<a href="%s" target="_blank">%s</a>', 'https://shsec.io/shieldgoprofeature', __( 'Upgrade to ShieldPRO', 'wp-simple-firewall' ) );
				}
				break;

			case 'frequency_info' :
				$sName = __( 'Info Frequency', 'wp-simple-firewall' );
				$sSummary = __( 'How Often Informational Reports Will Be Sent To You', 'wp-simple-firewall' );
				$sDescription = [
					__( 'Choose when you should be sent non-critical information and reports about your site security.', 'wp-simple-firewall' ),
					__( 'Information and reports are typically statistics.', 'wp-simple-firewall' )
				];
				if ( !$oCon->isPremiumActive() ) {
					$sDescription[] = __( 'If you wish to receive reports more often, please consider upgrading to ShieldPRO.', 'wp-simple-firewall' );
					$sDescription[] = sprintf( '<a href="%s" target="_blank">%s</a>', 'https://shsec.io/shieldgoprofeature', __( 'Upgrade to ShieldPRO', 'wp-simple-firewall' ) );
				}
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $sName,
			'summary'     => $sSummary,
			'description' => $sDescription,
		];
	}

	/**
	 * @param string $section
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {

		switch ( $section ) {

			case 'section_timings' :
				$sTitle = __( 'Report Frequencies', 'wp-simple-firewall' );
				$sTitleShort = __( 'Report Frequencies', 'wp-simple-firewall' );
				$aSummary = [
					__( 'Receive regular reports from the plugin summarising important events.', 'wp-simple-firewall' ),
					sprintf( 'Your reporting email address is: %s', '<code>'.$this->getMod()
																				  ->getPluginReportEmail().'</code>' )
					.' '.
					sprintf( '<br/><a href="%s" class="font-weight-bolder">%s</a>',
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
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}
}