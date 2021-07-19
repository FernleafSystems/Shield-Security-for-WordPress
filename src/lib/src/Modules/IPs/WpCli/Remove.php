<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use WP_CLI;

class Remove extends BaseAddRemove {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'ip-remove' ] ),
			[ $this, 'cmdIpRemove' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Remove an IP address from one of your lists, white or black.',
			'synopsis'  => $this->getCommonIpCmdArgs(),
		] ) );
	}

	/**
	 * @param array $null
	 * @param array $args
	 * @throws WP_CLI\ExitException
	 */
	public function cmdIpRemove( array $null, array $args ) {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();

		$deleter = ( new IPs\Lib\Ops\DeleteIp() )
			->setMod( $mod )
			->setIP( $args[ 'ip' ] );
		if ( $args[ 'list' ] === 'white' ) {
			$success = $deleter->fromWhiteList();
		}
		else {
			$success = $deleter->fromBlacklist();
		}

		$success ?
			WP_CLI::success( __( 'IP address removed successfully.', 'wp-simple-firewall' ) )
			: WP_CLI::error( __( "IP address couldn't be removed. (It may not be on this list)", 'wp-simple-firewall' ) );
	}
}