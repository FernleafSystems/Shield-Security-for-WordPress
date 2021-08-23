<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class VerifyEvents {

	use PluginControllerConsumer;

	public function run() {
		$con = $this->getCon();

		$NoKey = [];
		$NotEnoughSubstitutions = [];
		$NotEnoughParams = [];
		$NoMsgs = [];
		foreach ( $con->loadEventsService()->getEvents() as $evt ) {
			$key = $evt[ 'key' ] ?? '';
			if ( empty( $key ) ) {
				$NoKey[] = $key;
			}
			else {
				$msg = implode( '', $con->getModule( $evt[ 'context' ] )
										->getStrings()
										->getAuditMessage( $key ) );

				if ( empty( $msg ) ) {
					$NoMsgs[] = $key;
				}
				else {
					$paramCount = count( $evt[ 'audit_params' ] );

					$subCount = substr_count( $msg, '%s' );
					if ( $subCount > 0 ) {
						if ( $paramCount > $subCount ) {
							$NotEnoughSubstitutions[] = $key;
						}
						elseif ( $paramCount < $subCount ) {
							$NotEnoughParams[] = $key;
						}
					}
					else {
						preg_match_all( '#{{[a-z]+}}#i', $msg, $matches );
						if ( $paramCount < max( 0, count( $matches[ 0 ] ) ) ) {
							$NotEnoughParams[] = $key;
						}
					}
				}
			}
		}

		if ( !empty( $NoKey ) ) {
			var_dump( 'No Key' );
			var_dump( $NoKey );
		}
		if ( !empty( $NoMsgs ) ) {
			var_dump( 'No Msg' );
			var_dump( $NoMsgs );
		}
		if ( !empty( $NotEnoughSubstitutions ) ) {
			var_dump( 'Not enough substitutions in the audit message' );
			var_dump( $NotEnoughSubstitutions );
		}
		if ( !empty( $NotEnoughParams ) ) {
			var_dump( 'Not enough parameters in the event def' );
			var_dump( $NotEnoughParams );
		}
	}
}