<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	public function getCspCustomRules() :array {
		$csp = $this->getOpt( 'xcsp_custom' );
		return $this->isPremium() && is_array( $csp ) ? $csp : [];
	}

	/**
	 * Using this function without first checking isReferrerPolicyEnabled() will result in empty
	 * referrer policy header in the case of "disabled"
	 * @return string
	 */
	public function getReferrerPolicyValue() {
		$sValue = $this->getOpt( 'x_referrer_policy' );
		return in_array( $sValue, [ 'empty', 'disabled' ] ) ? '' : $sValue;
	}

	/**
	 * @return bool
	 */
	public function isEnabledContentSecurityPolicy() {
		return $this->isOpt( 'enable_x_content_security_policy', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledContentTypeHeader() {
		return $this->isOpt( 'x_content_type', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledXssProtection() {
		return $this->isOpt( 'x_xss_protect', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledXFrame() {
		return in_array( $this->getOpt( 'x_frame' ), [ 'on_sameorigin', 'on_deny' ] );
	}

	/**
	 * @return bool
	 */
	public function isReferrerPolicyEnabled() {
		return !$this->isOpt( 'x_referrer_policy', 'disabled' );
	}
}