<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;
use WP_CLI;

class AddRemove extends Base {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'ip', 'add' ] ),
			[ $this, 'cmdIpAdd' ]
		);
		WP_CLI::add_command(
			$this->buildCmd( [ 'ip', 'remove' ] ),
			[ $this, 'cmdIpRemove' ]
		);
	}

	public function cmdIpAdd( $args, $aNamed ) {

		if ( $this->commonIpCmdChecking( $aNamed ) ) {
			$sIP = $aNamed[ 'ip' ];
			$sList = $aNamed[ 'list' ];
			$sLabel = isset( $aNamed[ 'label' ] ) ? $aNamed[ 'label' ] : 'none';

			$oAdder = ( new Ops\AddIp() )
				->setMod( $this->getMod() )
				->setIP( $sIP );
			try {
				if ( $sList === 'white' ) {
					$oAdder->toManualWhitelist( $sLabel );
				}
				else {
					$oAdder->toManualBlacklist( $sLabel );
				}
			}
			catch ( \Exception $oE ) {
				WP_CLI::error( $oE->getMessage() );
			}
		}
		WP_CLI::success( __( 'IP address added successfully.', 'wp-simple-firewall' ) );
	}

	private function commonIpCmdChecking( $aArgs ) {
		$sIP = isset( $aArgs[ 'ip' ] ) ? $aArgs[ 'ip' ] : '';
		if ( empty( $sIP ) ) {
			WP_CLI::error_multi_line( [
					__( 'Please provide an IP.', 'wp-simple-firewall' ),
					__( 'Use the `--ip=` option.' )
				]
			);
			WP_CLI::halt( 1 );
		}

		$sList = strtolower( isset( $aArgs[ 'list' ] ) ? $aArgs[ 'list' ] : '' );
		if ( empty( $sList ) ) {
			WP_CLI::error_multi_line( [
					__( 'Please specify either the white or black list.', 'wp-simple-firewall' ),
					__( 'Use the `--list=` option.' )
				]
			);
			WP_CLI::halt( 1 );
		}
		elseif ( !in_array( $sList, [ 'white', 'black' ] ) ) {
			WP_CLI::error( __( 'The only option for `list` is either `white` or `black`.', 'wp-simple-firewall' ) );
		}

		return true;
	}

	public function cmdIpRemove( $args, $aNamed ) {

		if ( $this->commonIpCmdChecking( $aNamed ) ) {
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