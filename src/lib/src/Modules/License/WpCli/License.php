<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModCon;
use WP_CLI;

class License extends Base\WpCli\BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'pro' ] ),
			[ $this, 'cmdAction' ], $this->mergeCommonCmdArgs( [
				'shortdesc' => 'Manage the ShieldPRO license.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'action',
						'options'     => [
							'status',
							'verify',
							'remove',
						],
						'optional'    => false,
						'description' => 'Action to perform on the ShieldPRO license.',
					],
					[
						'type'        => 'flag',
						'name'        => 'force',
						'optional'    => true,
						'description' => 'Bypass confirmation prompt.',
					],
				],
			]
		) );
	}

	/**
	 * @param array $null
	 * @param array $aA
	 * @throws WP_CLI\ExitException
	 */
	public function cmdAction( array $null, array $aA ) {

		switch ( $aA[ 'action' ] ) {
			case 'status':
				$this->runStatus();
				break;

			case 'verify':
				$this->runVerify();
				break;

			case 'remove':
				$this->runRemove( $this->isForceFlag( $aA ) );
				break;
		}
	}

	private function runRemove( $bConfirm ) {
		if ( !self::con()->isPremiumActive() ) {
			WP_CLI::success( __( 'No license to remove.', 'wp-simple-firewall' ) );
		}
		else {
			if ( !$bConfirm ) {
				WP_CLI::confirm( __( 'Are you sure you want to remove the ShieldPRO license?', 'wp-simple-firewall' ) );
			}
			self::con()->getModule_License()->getLicenseHandler()->clearLicense();
			WP_CLI::success( __( 'License removed successfully.', 'wp-simple-firewall' ) );
		}
	}

	/**
	 * @throws WP_CLI\ExitException
	 */
	private function runStatus() {
		self::con()->getModule_License()->getLicenseHandler()->isActive() ?
			WP_CLI::success( __( 'Active license found.', 'wp-simple-firewall' ) )
			: WP_CLI::error( __( 'No active license present.', 'wp-simple-firewall' ) );
	}

	/**
	 * @throws WP_CLI\ExitException
	 */
	private function runVerify() {
		try {
			if ( self::con()->isPremiumActive() ) {
				WP_CLI::log( 'Premium license is already active. Re-checking...' );
			}
			$success = self::con()
						   ->getModule_License()
						   ->getLicenseHandler()
						   ->verify( true )
						   ->hasValidWorkingLicense();
			$msg = $success ? __( 'Valid license found and installed.', 'wp-simple-firewall' )
				: __( "Valid license couldn't be found.", 'wp-simple-firewall' );
		}
		catch ( \Exception $e ) {
			$success = false;
			$msg = $e->getMessage();
		}

		$success ? WP_CLI::success( $msg ) : WP_CLI::error( $msg );
	}

	/**
	 * License checking WP-CLI cmds may be run if you're not premium,
	 * or you're premium and you haven't switched it off (parent).
	 */
	protected function canRun() :bool {
		return !self::con()->isPremiumActive() || parent::canRun();
	}
}