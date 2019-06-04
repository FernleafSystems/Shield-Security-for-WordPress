<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Email;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @param string $sSectionSlug
	 * @return array
	 * @throws \Exception
	 */
	public function loadStrings_SectionTitles( $sSectionSlug ) {
		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $sSectionSlug ) {

			case 'section_email_options' :
				$sTitle = __( 'Email Options', 'wp-simple-firewall' );
				$sTitleShort = __( 'Email Options', 'wp-simple-firewall' );
				$aSummary = [];
				break;

			default:
				return parent::loadStrings_SectionTitles( $sSectionSlug );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $sOptKey
	 * @return array
	 * @throws \Exception
	 */
	public function loadStrings_Options( $sOptKey ) {
		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $sOptKey ) {

			case 'send_email_throttle_limit' :
				$sName = __( 'Email Throttle Limit', 'wp-simple-firewall' );
				$sSummary = __( 'Limit Emails Per Second', 'wp-simple-firewall' );
				$sDescription = __( 'You throttle emails sent by this plugin by limiting the number of emails sent every second. This is useful in case you get hit by a bot attack. Zero (0) turns this off. Suggested: 10', 'wp-simple-firewall' );
				break;

			default:
				return parent::loadStrings_Options( $sOptKey );
		}

		return [
			'name'        => $sName,
			'summary'     => $sSummary,
			'description' => $sDescription,
		];
	}
}