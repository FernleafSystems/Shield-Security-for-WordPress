<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\OptsConsumer;

class VerifyConfig {

	use OptsConsumer;

	public function run() {
		$oOpts = $this->getOpts();
		foreach ( $oOpts->getOptionsKeys() as $sKey ) {
			$sOptType = $oOpts->getOptionType( $sKey );
			if ( empty( $sOptType ) ) {
				var_dump( $sKey.': no type' );
				continue;
			}

			$mDefault = $oOpts->getOptDefault( $sKey );
			if ( is_null( $mDefault ) ) {
				var_dump( sprintf( '%s: opt has no default.', $sKey ) );
				continue;
			}

			$mVal = $oOpts->getOpt( $sKey );
			$sValType = gettype( $mVal );

			$bBroken = false;
			switch ( $sOptType ) {

				case 'integer':
					if ( $sValType != 'integer' ) {
						$bBroken = true;
					}
					break;

				case 'text':
					if ( $sValType != 'string' ) {
						$bBroken = true;
					}
					break;

				default:
					break;
			}

			if ( $bBroken ) {
				var_dump( sprintf( '%s: opt type is %s, value is %s at "%s". Default is: %s',
					$sKey, $sOptType, $sValType, var_export( $mVal, true ), $oOpts->getOptDefault( $sKey ) ) );
//				$oOpts->resetOptToDefault( $sKey );
			}
		}
	}
}