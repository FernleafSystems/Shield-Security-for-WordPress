<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers;

/**
 * @deprecated 19.1
 */
class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	/**
	 * @deprecated 19.1
	 */
	public function isEnabledContentTypeHeader() :bool {
		return $this->isOpt( 'x_content_type', 'Y' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isEnabledXssProtection() :bool {
		return $this->isOpt( 'x_xss_protect', 'Y' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isReferrerPolicyEnabled() :bool {
		return !$this->isOpt( 'x_referrer_policy', 'disabled' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isEnabledContentSecurityPolicy() :bool {
		return false;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getCspCustomRules() :array {
		return self::con()->isPremiumActive() ? \array_filter( \array_map( '\trim', $this->getOpt( 'xcsp_custom' ) ) ) : [];
	}

	/**
	 * Using this function without first checking isReferrerPolicyEnabled() will result in empty
	 * referrer policy header in the case of "disabled"
	 * @deprecated 19.1
	 */
	public function getReferrerPolicyValue() :string {
		$value = $this->getOpt( 'x_referrer_policy' );
		return \in_array( $value, [ 'empty', 'disabled' ] ) ? '' : $value;
	}
}