<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;
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
	 * @param array $null
	 * @param array $args
	 * @throws WP_CLI\ExitException
	 */
	public function cmdIpAdd( array $null, array $args ) {

		$label = $args[ 'label' ] ?? 'none';

		$adder = ( new Ops\AddIp() )
			->setMod( $this->getMod() )
			->setIP( $args[ 'ip' ] );
		try {
			if ( $args[ 'list' ] === 'white' ) {
				$adder->toManualWhitelist( $label );
			}
			else {
				$adder->toManualBlacklist( $label );
			}
		}
		catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
		WP_CLI::success( __( 'IP address added successfully.', 'wp-simple-firewall' ) );
	}
}