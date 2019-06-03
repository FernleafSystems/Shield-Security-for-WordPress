<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		/** @var \ICWP_WPSF_FeatureHandler_Firewall $oMod */
		$oMod = $this->getMod();

		$aMsgs = [
			'check_skip'                 => [
				sprintf( __( 'Skipping firewall checking for this visit: %s.', 'wp-simple-firewall' ), __( 'Parsing the URI failed', 'wp-simple-firewall' ) )
			],
			'blockparam_dirtraversal'    => [
				sprintf( __( 'Firewall Trigger: %s.', 'wp-simple-firewall' ), __( 'Directory Traversal', 'wp-simple-firewall' ) )
			],
			'blockparam_wpterms'         => [
				sprintf( __( 'Firewall Trigger: %s.', 'wp-simple-firewall' ), __( 'WordPress Terms', 'wp-simple-firewall' ) )
			],
			'blockparam_fieldtruncation' => [
				sprintf( __( 'Firewall Trigger: %s.', 'wp-simple-firewall' ), __( 'Field Truncation', 'wp-simple-firewall' ) )
			],
			'blockparam_sqlqueries'      => [
				sprintf( __( 'Firewall Trigger: %s.', 'wp-simple-firewall' ), __( 'SQL Queries', 'wp-simple-firewall' ) )
			],
			'blockparam_schema'          => [
				sprintf( __( 'Firewall Trigger: %s.', 'wp-simple-firewall' ), __( 'Leading Schema', 'wp-simple-firewall' ) )
			],
			'blockparam_aggressive'      => [
				sprintf( __( 'Firewall Trigger: %s.', 'wp-simple-firewall' ), __( 'Aggressive Rules', 'wp-simple-firewall' ) )
			],
			'blockparam_phpcode'         => [
				sprintf( __( 'Firewall Trigger: %s.', 'wp-simple-firewall' ), __( 'PHP Code', 'wp-simple-firewall' ) )
			],
			'block_exeupload'            => [
				sprintf( __( 'Firewall Trigger: %s.', 'wp-simple-firewall' ), __( 'EXE File Uploads', 'wp-simple-firewall' ) )
			],
		];

		foreach ( $aMsgs as $sKey => &$aMsg ) {

			if ( strpos( $sKey, 'blockparam_' ) === 0 ) {
				$aMsg[] = __( 'Page parameter failed firewall check.', 'wp-simple-firewall' );
				$aMsg[] = __( 'The offending parameter was "%s" with a value of "%s".', 'wp-simple-firewall' );
			}

			if ( strpos( $sKey, 'block' ) === 0 ) {

				switch ( $oMod->getBlockResponse() ) {
					case 'redirect_die':
						$sBlkResp = __( 'Visitor connection was killed with wp_die()', 'wp-simple-firewall' );
						break;
					case 'redirect_die_message':
						$sBlkResp = __( 'Visitor connection was killed with wp_die() and a message', 'wp-simple-firewall' );
						break;
					case 'redirect_home':
						$sBlkResp = __( 'Visitor was sent HOME', 'wp-simple-firewall' );
						break;
					case 'redirect_404':
						$sBlkResp = __( 'Visitor was sent 404', 'wp-simple-firewall' );
						break;
					default:
						$sBlkResp = __( 'Unknown', 'wp-simple-firewall' );
						break;
				}
				$aMsg[] = sprintf( __( 'Firewall Block Response: %s.', 'wp-simple-firewall' ), $sBlkResp );
			}
		}

		return $aMsgs;
	}
}