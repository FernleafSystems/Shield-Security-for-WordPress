<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	/**
	 * @return array
	 */
	public function buildInsightsVars() {
		/** @var \ICWP_WPSF_FeatureHandler_License $mod */
		$mod = $this->getMod();
		$con = $this->getCon();
		$opts = $this->getOptions();
		$WP = Services::WpGeneral();
		$oCarbon = Services::Request()->carbon();

		$oCurrent = $mod->getLicenseHandler()->getLicense();

		$nExpiresAt = $oCurrent->getExpiresAt();
		if ( $nExpiresAt > 0 && $nExpiresAt != PHP_INT_MAX ) {
			$sExpiresAt = $oCarbon->setTimestamp( $nExpiresAt )->diffForHumans()
						  .sprintf( '<br/><small>%s</small>', $WP->getTimeStampForDisplay( $nExpiresAt ) );
		}
		else {
			$sExpiresAt = 'n/a';
		}

		$nLastReqAt = $oCurrent->last_request_at;
		if ( empty( $nLastReqAt ) ) {
			$sChecked = __( 'Never', 'wp-simple-firewall' );
		}
		else {
			$sChecked = $oCarbon->setTimestamp( $nLastReqAt )->diffForHumans()
						.sprintf( '<br/><small>%s</small>', $WP->getTimeStampForDisplay( $nLastReqAt ) );
		}
		$aLicenseTableVars = [
			'product_name'    => $oCurrent->is_central ?
				$opts->getDef( 'license_item_name_sc' ) :
				$opts->getDef( 'license_item_name' ),
			'license_active'  => $mod->getLicenseHandler()->hasValidWorkingLicense() ?
				__( '&#10004;', 'wp-simple-firewall' ) : __( '&#10006;', 'wp-simple-firewall' ),
			'license_expires' => $sExpiresAt,
			'license_email'   => $oCurrent->customer_email,
			'last_checked'    => $sChecked,
			'last_errors'     => $mod->hasLastErrors() ? $mod->getLastErrors( true ) : '',
			'wphashes_token'  => $mod->getWpHashesTokenManager()->hasToken() ?
				__( '&#10004;', 'wp-simple-firewall' ) : __( '&#10006;', 'wp-simple-firewall' ),
			'installation_id' => $con->getSiteInstallationId(),
		];
		return [
			'vars'    => [
				'license_table'  => $aLicenseTableVars,
				'activation_url' => $WP->getHomeUrl(),
				'error'          => $mod->getLastErrors( true )
			],
			'inputs'  => [
				'license_key' => [
					'name'      => $con->prefixOption( 'license_key' ),
					'maxlength' => $opts->getDef( 'license_key_length' ),
				]
			],
			'ajax'    => [
				'license_handling' => $mod->getAjaxActionData( 'license_handling' ),
				'connection_debug' => $mod->getAjaxActionData( 'connection_debug' )
			],
			'aHrefs'  => [
				'shield_pro_url'           => 'https://shsec.io/shieldpro',
				'shield_pro_more_info_url' => 'https://shsec.io/shld1',
				'iframe_url'               => $opts->getDef( 'landing_page_url' ),
				'keyless_cp'               => $opts->getDef( 'keyless_cp' ),
			],
			'flags'   => [
				'show_ads'              => false,
				'button_enabled_check'  => true,
				'show_standard_options' => false,
				'show_alt_content'      => true,
				'is_pro'                => $con->isPremiumActive(),
				'has_error'             => $mod->hasLastErrors()
			],
			'strings' => $mod->getStrings()->getDisplayStrings(),
		];
	}

	/**
	 * @return bool
	 */
	public function isEnabledForUiSummary() :bool {
		/** @var \ICWP_WPSF_FeatureHandler_License $mod */
		$mod = $this->getMod();
		return $mod->getLicenseHandler()->hasValidWorkingLicense();
	}
}