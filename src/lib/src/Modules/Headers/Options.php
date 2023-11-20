<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\Options {

	public function preSave() :void {
		if ( $this->isOptChanged( 'xcsp_custom' ) ) {
			$this->setOpt( 'xcsp_custom', \array_unique( \array_filter( \array_map(
				function ( $rule ) {
					$rule = \trim( \preg_replace( '#;|\s{2,}#', '', \html_entity_decode( $rule, \ENT_QUOTES ) ) );
					if ( !empty( $rule ) ) {
						$rule .= ';';
					}
					return $rule;
				},
				$this->getOpt( 'xcsp_custom', [] )
			) ) ) );
		}

		if ( empty( $this->getOpt( 'xcsp_custom', [] ) ) ) {
			$this->setOpt( 'enable_x_content_security_policy', 'N' );
		}
	}

	public function getCspCustomRules() :array {
		$csp = \is_array( $this->getOpt( 'xcsp_custom' ) ) ? $this->getOpt( 'xcsp_custom' ) : [];
		$this->setOpt( 'xcsp_custom', \array_filter( \array_map( '\trim', $csp ) ) );
		return self::con()->isPremiumActive() ? $this->getOpt( 'xcsp_custom' ) : [];
	}

	/**
	 * Using this function without first checking isReferrerPolicyEnabled() will result in empty
	 * referrer policy header in the case of "disabled"
	 */
	public function getReferrerPolicyValue() :string {
		$value = $this->getOpt( 'x_referrer_policy' );
		return \in_array( $value, [ 'empty', 'disabled' ] ) ? '' : $value;
	}

	public function isEnabledContentSecurityPolicy() :bool {
		return $this->isOpt( 'enable_x_content_security_policy', 'Y' )
			   && !empty( $this->getCspCustomRules() );
	}

	public function isEnabledContentTypeHeader() :bool {
		return $this->isOpt( 'x_content_type', 'Y' );
	}

	public function isEnabledXssProtection() :bool {
		return $this->isOpt( 'x_xss_protect', 'Y' );
	}

	public function isEnabledXFrame() :bool {
		return \in_array( $this->getOpt( 'x_frame' ), [ 'on_sameorigin', 'on_deny' ] );
	}

	public function isReferrerPolicyEnabled() :bool {
		return !$this->isOpt( 'x_referrer_policy', 'disabled' );
	}
}