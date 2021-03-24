<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

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
	}

	/**
	 * @deprecated 10.3
	 */
	private function cleanCspHosts() {
	}
}