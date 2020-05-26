<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\Actions;
use WP_CLI;

class Pin extends Base {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'pin', 'set' ] ),
			[ $this, 'cmdSet' ]
		);
		WP_CLI::add_command(
			$this->buildCmd( [ 'pin', 'remove' ] ),
			[ $this, 'cmdRemove' ]
		);
	}

	public function cmdSet( $args, $aNamed ) {
		$sPin = isset( $aNamed[ 'pin' ] ) ? $aNamed[ 'pin' ] : '';
		try {
			( new Actions\SetSecAdminPin() )
				->setMod( $this->getMod() )
				->run( $sPin );
		}
		catch ( \Exception $oE ) {
			WP_CLI::error_multi_line(
				[
					__( 'Setting Security admin pin failed.', 'wp-simple-firewall' ),
					__( 'Use the `--pin=` option to set the PIN.', 'wp-simple-firewall' ),
					__( 'To remove the pin, use the command `pin remove`.', 'wp-simple-firewall' ),
					$oE->getMessage()
				]
			);
			WP_CLI::halt( 1 );
		}
		WP_CLI::success(
			sprintf( __( 'Security admin pin set to: "%s"', 'wp-simple-firewall' ), $sPin )
		);
	}

	public function cmdRemove( $args, $assoc_args ) {
		( new Actions\RemoveSecAdmin() )
			->setMod( $this->getMod() )
			->remove();
		WP_CLI::success( __( 'Security admin pin removed.', 'wp-simple-firewall' ) );
	}
}