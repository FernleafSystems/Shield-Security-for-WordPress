<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use WP_CLI;

class License extends Base\WpCli\BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'check' ] ),
			[ $this, 'cmdCheck' ]
		);
		WP_CLI::add_command(
			$this->buildCmd( [ 'remove' ] ),
			[ $this, 'cmdRemove' ]
		);
	}

	public function cmdRemove( $args, $aNamed ) {
		if ( !$this->getCon()->isPremiumActive() ) {
			WP_CLI::success( __( 'No license to remove.', 'wp-simple-firewall' ) );
		}
		else {
			if ( !array_key_exists( 'force', $aNamed ) ) {
				WP_CLI::confirm( __( 'Are you sure you want to remove the ShieldPRO license?', 'wp-simple-firewall' ) );
			}
			/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
			$oMod = $this->getMod();
			$oMod->getLicenseHandler()->clearLicense();
			WP_CLI::success( __( 'License removed successfully.', 'wp-simple-firewall' ) );
		}
	}

	public function cmdCheck( $args, $aNamed ) {
		/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
		$oMod = $this->getMod();

		try {
			$bSuccess = $oMod
				->getLicenseHandler()
				->verify()
				->hasValidWorkingLicense();
			$sMessage = $bSuccess ? __( 'Valid license found and installed.', 'wp-simple-firewall' ) : __( "Valid license couldn't be found.", 'wp-simple-firewall' );
		}
		catch ( \Exception $oE ) {
			$bSuccess = false;
			$sMessage = $oE->getMessage();
		}

		$bSuccess ? WP_CLI::success( $sMessage ) : WP_CLI::error( $sMessage );
	}

	protected function canRun() {
		return true;
	}
}