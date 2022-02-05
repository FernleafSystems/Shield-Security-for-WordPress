<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class UI extends BaseShield\UI {

	/**
	 * @return array
	 */
	public function buildInsightsVars() {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$opts = $this->getOptions();
		$WP = Services::WpGeneral();
		$carb = Services::Request()->carbon();

		$lic = $mod->getLicenseHandler()->getLicense();

		$expiresAt = $lic->getExpiresAt();
		if ( $expiresAt > 0 && $expiresAt != PHP_INT_MAX ) {
			// Expires At has a random addition added to disperse future license lookups
			// So we bring the license expiration back down to normal for user display.
			$endOfExpireDay = Services::Request()
										->carbon()
										->setTimestamp( $expiresAt )
										->startOfDay()->timestamp - 1;
			$expiresAtHuman = sprintf( '%s<br/><small>%s</small>',
				$carb->setTimestamp( $endOfExpireDay )->diffForHumans(),
				$WP->getTimeStampForDisplay( $endOfExpireDay )
			);
		}
		else {
			$expiresAtHuman = 'n/a';
		}

		$lastReqAt = $lic->last_request_at;
		if ( empty( $lastReqAt ) ) {
			$checked = __( 'Never', 'wp-simple-firewall' );
		}
		else {
			$checked = sprintf( '%s<br/><small>%s</small>',
				$carb->setTimestamp( $lastReqAt )->diffForHumans(),
				$WP->getTimeStampForDisplay( $lastReqAt )
			);
		}

		return [
			'vars'    => [
				'license_table'  => [
					'product_name'    => $lic->is_central ?
						$opts->getDef( 'license_item_name_sc' ) :
						$opts->getDef( 'license_item_name' ),
					'license_active'  => $mod->getLicenseHandler()->hasValidWorkingLicense() ?
						__( '&#10004;', 'wp-simple-firewall' ) : __( '&#10006;', 'wp-simple-firewall' ),
					'license_expires' => $expiresAtHuman,
					'license_email'   => $lic->customer_email,
					'last_checked'    => $checked,
					'last_errors'     => $mod->hasLastErrors() ? $mod->getLastErrors( true ) : '',
					'wphashes_token'  => $mod->getWpHashesTokenManager()->hasToken() ?
						__( '&#10004;', 'wp-simple-firewall' ) : __( '&#10006;', 'wp-simple-firewall' ),
					'installation_id' => $con->getSiteInstallationId(),
				],
				'activation_url' => $WP->getHomeUrl(),
				'error'          => $mod->getLastErrors( true ),
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
				'shield_pro_url' => 'https://shsec.io/shieldpro',
				'iframe_url'     => $opts->getDef( 'landing_page_url' ),
				'keyless_cp'     => $opts->getDef( 'keyless_cp' ),
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

	public function isEnabledForUiSummary() :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getLicenseHandler()->hasValidWorkingLicense();
	}
}