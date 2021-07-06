<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\OptsConsumer;

class VerifyConfig {

	use OptsConsumer;

	public function run() {
		$opts = $this->getOpts();
		foreach ( $opts->getOptionsKeys() as $sKey ) {
			$optType = $opts->getOptionType( $sKey );
			if ( empty( $optType ) ) {
				var_dump( $sKey.': no type' );
				continue;
			}

			$mDefault = $opts->getOptDefault( $sKey );
			if ( is_null( $mDefault ) ) {
				var_dump( sprintf( '%s: opt has no default.', $sKey ) );
				continue;
			}

			$mVal = $opts->getOpt( $sKey );
			$valType = gettype( $mVal );

			$isBroken = false;
			switch ( $optType ) {

				case 'integer':
					if ( $valType != 'integer' ) {
						$isBroken = true;
					}
					break;

				case 'text':
					if ( $valType != 'string' ) {
						$isBroken = true;
					}
					break;

				default:
					break;
			}

			if ( $isBroken ) {
				var_dump( sprintf( '%s: opt type is %s, value is %s at "%s". Default is: %s',
					$sKey, $optType, $valType, var_export( $mVal, true ), $opts->getOptDefault( $sKey ) ) );
//				$opts->resetOptToDefault( $sKey );
			}
		}
	}
}