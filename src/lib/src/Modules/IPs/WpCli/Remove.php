<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;
use WP_CLI;

class Remove extends BaseAddRemove {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'ip', 'remove' ] ),
			[ $this, 'cmdIpRemove' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Remove an IP address from one of your lists, white or black.',
			'synopsis'  => $this->getCommonIpCmdArgs(),
		] ) );
	}

	/**
	 * @param array $null
	 * @param array $aA
	 * @throws WP_CLI\ExitException
	 */
	public function cmdIpRemove( array $null, array $aA ) {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();

		$oDel = ( new Ops\DeleteIp() )
			->setDbHandler( $oMod->getDbHandler_IPs() )
			->setIP( $aA[ 'ip' ] );
		if ( $aA[ 'list' ] === 'white' ) {
			$bSuccess = $oDel->fromWhiteList();
		}
		else {
			$bSuccess = $oDel->fromBlacklist();
		}

		$bSuccess ?
			WP_CLI::success( __( 'IP address removed successfully.', 'wp-simple-firewall' ) )
			: WP_CLI::error( __( "IP address couldn't be removed. (It may not be on this list)", 'wp-simple-firewall' ) );
	}
}