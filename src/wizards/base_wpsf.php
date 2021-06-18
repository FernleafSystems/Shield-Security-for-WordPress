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
	 * @param string $step
	 * @return array
	 */
	protected function getRenderData_SlideExtra( $step ) {

		switch ( $step ) {
			case 'security_admin_verify':
				$aAdditional = [ 'current_index' => Services::Request()->post( 'current_index' ) ];
				break;
			default:
				$aAdditional = parent::getRenderData_SlideExtra( $step );
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
			[
				'security_admin_verify' => [
					'content'        => '',
					'slug'           => 'security_admin_verify',
					'title'          => __( 'Security Admin', 'wp-simple-firewall' ),
					'security_admin' => false
				]
			]
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
	 * @param string $step
	 * @return \FernleafSystems\Utilities\Response|null
	 */
	protected function processWizardStep( string $step ) {
		switch ( $step ) {
			case 'security_admin_verify':
				$oResponse = $this->wizardSecurityAdminVerify();
				break;
			default:
				$oResponse = parent::processWizardStep( $step );
				break;
		}
		return $oResponse;
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardSecurityAdminVerify() {
		$pin = Services::Request()->post( 'sec_admin_key' );

		$response = new \FernleafSystems\Utilities\Response();
		$success = false;
		$msg = '';

		if ( empty( $pin ) ) {
			$msg = 'Security Admin PIN was empty.';
		}
		elseif ( !$this->getCon()->getModule_SecAdmin()->getSecurityAdminController()->verifyPinRequest() ) {
			$msg = __( 'Security Admin PIN was not correct.', 'wp-simple-firewall' );
		}
		else {
			$success = true;
			$response->setData( [
				'rerender' => true
			] );
		}

		return $response->setSuccessful( $success )
						->setMessageText( $msg );
	}
}