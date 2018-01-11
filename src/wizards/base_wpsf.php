<?php

if ( class_exists( 'ICWP_WPSF_Wizard_Base', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

/**
 * Class ICWP_WPSF_Wizard_BaseWpsf
 */
abstract class ICWP_WPSF_Wizard_BaseWpsf extends ICWP_WPSF_Wizard_Base {

	/**
	 * @param string $sStep
	 * @return \FernleafSystems\Utilities\Response|null
	 */
	protected function processWizardStep( $sStep ) {
		switch ( $sStep ) {
			case 'admin_access_restriction_verify':
				$oResponse = $this->wizardSecurityAdminVerify();
				break;
			default:
				$oResponse = parent::processWizardStep( $sStep );
				break;
		}
		return $oResponse;
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardSecurityAdminVerify() {
		$sKey = $this->loadDP()->post( 'AccessKey' );

		$oResponse = new \FernleafSystems\Utilities\Response();

		$bSuccess = false;
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oModule */
		$oModule = $this->getPluginCon()->getModule( 'admin_access_restriction' );

		$sMessage = '';
		if ( empty( $sKey ) ) {
			$sMessage = 'Security access key was empty.';
		}
		else if ( !$oModule->verifyAccessKey( $sKey ) ) {
			$sMessage = _wpsf__( 'Security Admin Key was not correct.' );
		}
		else {
			$bSuccess = true;
			$oModule->setPermissionToSubmit( true );
			$aData = array(
				'rerender' => true
			);
			$oResponse->setData( $aData );
		}

		return $oResponse->setSuccessful( $bSuccess )
						 ->setMessageText( $sMessage );
	}
}