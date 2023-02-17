<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	public const SLUG = 'headers';

	protected function preProcessOptions() {
		$this->cleanCustomRules();
	}

	private function cleanCustomRules() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$opts->setOpt( 'xcsp_custom', array_unique( array_filter( array_map(
			function ( $rule ) {
				$rule = trim( preg_replace( '#;|\s{2,}#', '', html_entity_decode( $rule, ENT_QUOTES ) ) );
				if ( !empty( $rule ) ) {
					$rule .= ';';
				}
				return $rule;
			},
			$opts->getOpt( 'xcsp_custom', [] )
		) ) ) );

		if ( empty( $opts->getOpt( 'xcsp_custom', [] ) ) ) {
			$opts->setOpt( 'enable_x_content_security_policy', 'N' );
		}
	}
}