<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use WP_CLI;

class License extends BaseCmd {

	/**
	 * License checking WP-CLI cmds may be run if you're not premium,
	 * or you're premium and you haven't switched it off (parent).
	 */
	protected function canRun() :bool {
		return self::con()->caps->canWpcliLevel1();
	}

	protected function cmdParts() :array {
		return [ 'pro-license' ];
	}

	protected function cmdShortDescription() :string {
		return 'Manage the ShieldPRO license.';
	}

	protected function cmdSynopsis() :array {
		return [
			[
				'type'        => 'assoc',
				'name'        => 'action',
				'options'     => [
					'status',
					'verify',
					'remove',
				],
				'default'     => 'status',
				'optional'    => false,
				'description' => 'Action to perform on the ShieldPRO license.',
			],
			[
				'type'        => 'flag',
				'name'        => 'force',
				'optional'    => true,
				'description' => 'Bypass confirmation prompt.',
			],
		];
	}

	public function runCmd() :void {
		switch ( $this->execCmdArgs[ 'action' ] ) {
			case 'verify':
				$this->runVerify();
				break;
			case 'remove':
				$this->runRemove( $this->isForceFlag() );
				break;
			case 'status':
			default:
				$this->runStatus();
				break;
		}
	}

	private function runRemove( $confirm ) {
		if ( self::con()->isPremiumActive() ) {
			if ( !$confirm ) {
				WP_CLI::confirm( __( 'Are you sure you want to remove the ShieldPRO license?', 'wp-simple-firewall' ) );
			}
			self::con()->comps->license->clearLicense();
			WP_CLI::success( __( 'License removed successfully.', 'wp-simple-firewall' ) );
		}
		else {
			WP_CLI::success( __( 'No license to remove.', 'wp-simple-firewall' ) );
		}
	}

	private function runStatus() {
		self::con()->comps->license->isActive() ?
			WP_CLI::success( __( 'Active license found.', 'wp-simple-firewall' ) )
			: WP_CLI::error( __( 'No active license present.', 'wp-simple-firewall' ) );
	}

	private function runVerify() {
		try {
			if ( self::con()->isPremiumActive() ) {
				WP_CLI::log( 'Premium license is already active. Re-checking...' );
			}
			$success = self::con()->comps->license->verify( true )->hasValidWorkingLicense();
			$msg = $success ? __( 'Valid license found and installed.', 'wp-simple-firewall' )
				: __( "Valid license couldn't be found.", 'wp-simple-firewall' );
		}
		catch ( \Exception $e ) {
			$success = false;
			$msg = $e->getMessage();
		}

		$success ? WP_CLI::success( $msg ) : WP_CLI::error( $msg );
	}
}