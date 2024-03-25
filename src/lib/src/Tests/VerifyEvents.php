<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class VerifyEvents {

	use PluginControllerConsumer;

	public function run() {
		$NoKey = [];
		$NotEnoughSubstitutions = [];
		$SubstitutionsMismatch = [];
		$OldStyleSprintfSubstitutions = [];
		$NotEnoughParams = [];
		$NoMsgs = [];
		$srvEvents = self::con()->comps->events;
		foreach ( $srvEvents->getEvents() as $evt ) {
			$key = $evt[ 'key' ] ?? '';
			if ( empty( $key ) ) {
				$NoKey[] = $key;
			}
			else {
				$msg = \implode( '', $srvEvents->getEventAuditStrings( $key ) );

				if ( empty( $msg ) ) {
					$NoMsgs[] = $key;
				}
				else {
					$paramCount = \count( $evt[ 'audit_params' ] );

					if ( substr_count( $msg, '%s' ) > 0 ) {
						$OldStyleSprintfSubstitutions[] = $key;
					}
					else {
						preg_match_all( '#{{[a-z_]+}}#i', $msg, $matches );
						$substitutionPlaceholders = $matches[ 0 ];
						if ( $paramCount < \count( $substitutionPlaceholders ) ) {
							$NotEnoughParams[] = $key;
						}
						elseif ( \count( $substitutionPlaceholders ) > $paramCount ) {
							$NotEnoughSubstitutions[] = $key;
						}
						elseif ( !empty( $substitutionPlaceholders ) ) {
							$substitutionPlaceholders = \array_map(
								fn( $placeholder ) => \trim( $placeholder, '{}' ),
								$substitutionPlaceholders
							);

							// audit_params that aren't present in the string is ok, but not the other way around.
							if ( !empty( \array_diff( $substitutionPlaceholders, $evt[ 'audit_params' ] ) ) ) {
								$SubstitutionsMismatch[] = $key;
							}
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
		if ( !empty( $SubstitutionsMismatch ) ) {
			var_dump( 'There is a mismatch between substitutions placeholders and audit params' );
			var_dump( $SubstitutionsMismatch );
		}
	}
}