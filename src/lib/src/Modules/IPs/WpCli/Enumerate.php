<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;
use WP_CLI;

class Enumerate extends Base {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'print' ] ),
			[ $this, 'cmdPrint' ]
		);
	}

	public function cmdPrint( $args, $aNamed ) {

		if ( $this->validateArg_List( $aNamed ) ) {
			/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
			$oMod = $this->getMod();

			$oRtr = ( new Ops\RetrieveIpsForLists() )
				->setDbHandler( $oMod->getDbHandler_IPs() );
			$aIPs = $aNamed[ 'list' ] === 'white' ? $oRtr->white() : $oRtr->black();
			$aIPs = array_map(
				function ( $sIP ) {
					return [ 'IP' => $sIP, ];
				},
				$aIPs
			);
			WP_CLI\Utils\format_items(
				'table',
				$aIPs,
				[ 'IP' ]
			);
		}
	}

	public function cmdIpRemove( $args, $aNamed ) {

		if ( $this->validateArg_List( $aNamed ) ) {
			$sIP = $aNamed[ 'ip' ];
			$sList = $aNamed[ 'list' ];

			/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
			$oMod = $this->getMod();
			$oDel = ( new Ops\DeleteIp() )
				->setDbHandler( $oMod->getDbHandler_IPs() )
				->setIP( $sIP );
			if ( $sList === 'white' ) {
				$bSuccess = $oDel->fromWhiteList();
			}
			else {
				$bSuccess = $oDel->fromBlacklist();
			}

			$bSuccess ?
				WP_CLI::success( __( 'IP address remove successfully.', 'wp-simple-firewall' ) )
				: WP_CLI::error( __( 'IP address could not be removed. (It may not be on this list)', 'wp-simple-firewall' ) );
		}
	}
}