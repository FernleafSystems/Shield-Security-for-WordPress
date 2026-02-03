<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\AddRule;

class IpRuleAdd extends IpRuleAddRemoveBase {

	protected function cmdParts() :array {
		return [ 'add' ];
	}

	protected function cmdShortDescription() :string {
		return 'Add an IP address to one of your lists, white or black.';
	}

	protected function cmdSynopsis() :array {
		return \array_merge(
			$this->getCommonIpCmdArgs(),
			[
				'type'        => 'assoc',
				'name'        => 'label',
				'optional'    => true,
				'description' => 'The label to assign to this IP entry.',
			]
		);
	}

	/**
	 * @throws \WP_CLI\ExitException
	 */
	public function runCmd() :void {
		try {
			$this->checkList( $this->execCmdArgs[ 'list' ] ?? '' );

			$adder = ( new AddRule() )->setIP( $this->execCmdArgs[ 'ip' ] );

			\in_array( $this->execCmdArgs[ 'list' ], [ 'white', 'bypass' ] ) ?
				$adder->toManualWhitelist( $this->execCmdArgs[ 'label' ] ?? '' )
				: $adder->toManualBlacklist( $this->execCmdArgs[ 'label' ] ?? '' );

			\WP_CLI::success( __( 'IP address added successfully.', 'wp-simple-firewall' ) );
		}
		catch ( \Exception $e ) {
			\WP_CLI::error( $e->getMessage() );
		}
	}
}