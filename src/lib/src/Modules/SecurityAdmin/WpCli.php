<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\Actions\RemoveSecAdmin;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\Actions\SetSecAdminPin;
use WP_CLI;

class WpCli extends Base\WpCli {

	const MOD_COMMAND_KEY = 'secadmin';

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'pin', 'set' ] ),
			[ $this, 'cmdPinSet' ]
		);
		WP_CLI::add_command(
			$this->buildCmd( [ 'pin', 'remove' ] ),
			[ $this, 'cmdPinRemove' ]
		);
	}

	public function cmdPinSet( $args, $aNamed ) {
		$sPin = isset( $aNamed[ 'pin' ] ) ? $aNamed[ 'pin' ] : '';
		try {
			( new SetSecAdminPin() )
				->setMod( $this->getMod() )
				->run( $sPin );
		}
		catch ( \Exception $oE ) {
			WP_CLI::error_multi_line(
				[
					__( 'Setting Security admin pin failed.', 'wp-simple-firewall' ),
					__( 'To remove the pin, use `pin remove`.', 'wp-simple-firewall' ),
					$oE->getMessage()
				]
			);
			WP_CLI::halt( 1 );
		}
		WP_CLI::success(
			sprintf( __( 'Security admin pin set to: "%s"', 'wp-simple-firewall' ), $sPin )
		);
	}

	public function cmdPinRemove( $args, $assoc_args ) {
		( new RemoveSecAdmin() )
			->setMod( $this->getMod() )
			->remove();
		WP_CLI::success( __( 'Security admin pin removed.', 'wp-simple-firewall' ) );
	}
}