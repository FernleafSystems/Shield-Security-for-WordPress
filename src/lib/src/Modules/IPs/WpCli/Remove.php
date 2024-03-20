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
	 * @throws WP_CLI\ExitException
	 */
	public function cmdIpRemove( array $null, array $args ) {
		$this->showDeprecatedWarning();

		try {
			$this->checkList( $args[ 'list' ] );

			$ruleStatus = new IPs\Lib\IpRules\IpRuleStatus( $args[ 'ip' ] );
			$records = \in_array( $args[ 'list' ], [ 'white', 'bypass' ] ) ?
				$ruleStatus->getRulesForBypass() : $ruleStatus->getRulesForBlock();

			$success = false;
			foreach ( $records as $record ) {
				$success = ( new IPs\Lib\IpRules\DeleteRule() )->byRecord( $record );
			}

			$success ?
				WP_CLI::success( __( 'IP address removed successfully.', 'wp-simple-firewall' ) )
				: WP_CLI::error( __( "IP address couldn't be removed. (It may not be on this list)", 'wp-simple-firewall' ) );
		}
		catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}
}