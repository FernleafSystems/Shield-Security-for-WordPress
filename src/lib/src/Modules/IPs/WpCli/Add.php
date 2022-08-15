<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\AddRule;
use WP_CLI;

class Add extends BaseAddRemove {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'ip-add' ] ),
			[ $this, 'cmdIpAdd' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Add an IP address to one of your lists, white or black.',
			'synopsis'  => array_merge(
				$this->getCommonIpCmdArgs(),
				[
					'type'        => 'assoc',
					'name'        => 'label',
					'optional'    => true,
					'description' => 'The label to assign to this IP entry.',
				]
			),
		] ) );
	}

	/**
	 * @throws WP_CLI\ExitException
	 */
	public function cmdIpAdd( array $null, array $args ) {
		try {
			$this->checkList( $args[ 'list' ] );

			$adder = ( new AddRule() )
				->setMod( $this->getMod() )
				->setIP( $args[ 'ip' ] );

			in_array( $args[ 'list' ], [ 'white', 'bypass' ] ) ?
				$adder->toManualWhitelist( $args[ 'label' ] ?? '' )
				: $adder->toManualBlacklist( $args[ 'label' ] ?? '' );

			WP_CLI::success( __( 'IP address added successfully.', 'wp-simple-firewall' ) );
		}
		catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}
}