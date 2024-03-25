<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class IpRuleRemove extends IpRuleAddRemoveBase {

	protected function cmdParts() :array {
		return [ 'remove' ];
	}

	protected function cmdShortDescription() :string {
		return 'Remove an IP address from one of your lists, white or black.';
	}

	protected function cmdSynopsis() :array {
		return $this->getCommonIpCmdArgs();
	}

	/**
	 * @throws \WP_CLI\ExitException
	 */
	public function runCmd() :void {
		try {
			$this->checkList( $this->execCmdArgs[ 'list' ] );

			$ruleStatus = new IPs\Lib\IpRules\IpRuleStatus( $this->execCmdArgs[ 'ip' ] );
			$records = \in_array( $this->execCmdArgs[ 'list' ], [ 'white', 'bypass' ] ) ?
				$ruleStatus->getRulesForBypass() : $ruleStatus->getRulesForBlock();

			$success = false;
			foreach ( $records as $record ) {
				$success = ( new IPs\Lib\IpRules\DeleteRule() )->byRecord( $record );
			}

			$success ?
				\WP_CLI::success( __( 'IP address removed successfully.', 'wp-simple-firewall' ) )
				: \WP_CLI::error( __( "IP address couldn't be removed. (It may not be on this list)", 'wp-simple-firewall' ) );
		}
		catch ( \Exception $e ) {
			\WP_CLI::error( $e->getMessage() );
		}
	}
}