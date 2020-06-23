<?php

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ICWP_WPSF_Wizard_BaseWpsf
 */
abstract class ICWP_WPSF_Wizard_BaseWpsf extends ICWP_WPSF_Wizard_Base {

	/**
	 * @param string $sSlide
	 * @return bool
	 */
	protected function getUserCanSlide( $sSlide ) {
		$aSlide = $this->getStepsDefinition()[ $sSlide ];
		$bRestricted = !isset( $aSlide[ 'security_admin' ] ) || $aSlide[ 'security_admin' ];
		return !$bRestricted || $this->getCon()->isPluginAdmin();
	}

	/**
	 * @param array $aStepsInThisInstance
	 * @param int   $nCurrentPos
	 * @return array
	 */
	protected function getNextStep( $aStepsInThisInstance, $nCurrentPos ) {
		$aNext = parent::getNextStep( $aStepsInThisInstance, $nCurrentPos );
		if ( !$this->getUserCanSlide( $aNext[ 'slug' ] ) ) {
			$aNext = $this->getStepsDefinition()[ 'security_admin_verify' ];
		}
		return $aNext;
	}

	/**
	 * @param string $sStep
	 * @return array
	 */
	protected function getRenderData_SlideExtra( $sStep ) {

		switch ( $sStep ) {
			case 'security_admin_verify':
				$aAdditional = array( 'current_index' => Services::Request()->post( 'current_index' ) );
				break;
			default:
				$aAdditional = parent::getRenderData_SlideExtra( $sStep );
				break;
		}

		return $aAdditional;
	}

	/**
	 * @return array[]
	 */
	protected function getStepsDefinition() {
		return array_merge(
			parent::getStepsDefinition(),
			array(
				'security_admin_verify' => array(
					'content'        => '',
					'slug'           => 'security_admin_verify',
					'title'          => __( 'Security Admin', 'wp-simple-firewall' ),
					'security_admin' => false
				)
			)
		);
	}

	/**
	 * @param string $sSlideSlug
	 * @return bool
	 */
	protected function isSlideCommon( $sSlideSlug ) {
		return parent::isSlideCommon( $sSlideSlug ) || in_array( $sSlideSlug, [ 'security_admin_verify' ] );
	}

	/**
	 * @param string $sStep
	 * @return \FernleafSystems\Utilities\Response|null
	 */
	protected function processWizardStep( $sStep ) {
		switch ( $sStep ) {
			case 'security_admin_verify':
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
		$sKey = Services::Request()->post( 'AccessKey' );

		$oResponse = new \FernleafSystems\Utilities\Response();

		$bSuccess = false;
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oModule */
		$oModule = $this->getCon()->getModule( 'admin_access_restriction' );

		$sMessage = '';
		if ( empty( $sKey ) ) {
			$sMessage = 'Security Admin PIN was empty.';
		}
		elseif ( !$oModule->verifyAccessKey( $sKey ) ) {
			$sMessage = __( 'Security Admin PIN was not correct.', 'wp-simple-firewall' );
		}
		else {
			$bSuccess = $oModule->setSecurityAdminStatusOnOff( true );
			$aData = array(
				'rerender' => true
			);
			$oResponse->setData( $aData );
		}

		return $oResponse->setSuccessful( $bSuccess )
						 ->setMessageText( $sMessage );
	}
}