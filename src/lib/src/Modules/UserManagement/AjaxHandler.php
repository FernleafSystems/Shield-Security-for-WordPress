<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\Base\AjaxHandlerShield {

	/**
	 * @param string $sAction
	 * @return array
	 */
	protected function processAjaxAction( $sAction ) {

		switch ( $sAction ) {
			case 'render_table_sessions':
				$aResponse = $this->ajaxExec_BuildTableTraffic();
				break;

			case 'bulk_action':
				$aResponse = $this->ajaxExec_BulkItemAction();
				break;

			case 'session_delete':
				$aResponse = $this->ajaxExec_SessionDelete();
				break;

			default:
				$aResponse = parent::processAjaxAction( $sAction );
		}

		return $aResponse;
	}

	/**
	 * @return array
	 */
	private function ajaxExec_BuildTableTraffic() {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $oMod */
		$oMod = $this->getMod();
		/** @var \ICWP_WPSF_Processor_UserManagement $oPro */
		$oPro = $oMod->getProcessor();

		// first clean out the expired sessions before display
		$oPro->getProcessorSessions()->cleanExpiredSessions();

		$oSecAdminMod = $this->getCon()->getModule_SecAdmin();

		$oTableBuilder = ( new Shield\Tables\Build\Sessions() )
			->setMod( $oMod )
			->setDbHandler( $oMod->getDbHandler_Sessions() )
			->setSecAdminUsers( $oSecAdminMod->getSecurityAdminUsers() );

		return [
			'success' => true,
			'html'    => $oTableBuilder->buildTable()
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_BulkItemAction() {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $oMod */
		$oMod = $this->getMod();
		$oReq = Services::Request();

		$bSuccess = false;

		$aIds = $oReq->post( 'ids' );
		if ( empty( $aIds ) || !is_array( $aIds ) ) {
			$bSuccess = false;
			$sMessage = __( 'No items selected.', 'wp-simple-firewall' );
		}
		else if ( !in_array( $oReq->post( 'bulk_action' ), [ 'delete' ] ) ) {
			$sMessage = __( 'Not a supported action.', 'wp-simple-firewall' );
		}
		else {
			$nYourId = $oMod->getSession()->id;
			$bIncludesYourSession = in_array( $nYourId, $aIds );

			if ( $bIncludesYourSession && ( count( $aIds ) == 1 ) ) {
				$sMessage = __( 'Please logout if you want to delete your own session.', 'wp-simple-firewall' );
			}
			else {
				$bSuccess = true;

				/** @var Shield\Databases\Session\Delete $oDel */
				$oDel = $oMod->getDbHandler_Sessions()->getQueryDeleter();
				foreach ( $aIds as $nId ) {
					if ( is_numeric( $nId ) && ( $nId != $nYourId ) ) {
						$oDel->deleteById( $nId );
						$this->getCon()->fireEvent( 'terminate_session' );
					}
				}
				$sMessage = __( 'Selected items were deleted.', 'wp-simple-firewall' );
				if ( $bIncludesYourSession ) {
					$sMessage .= ' *'.__( 'Your session was retained', 'wp-simple-firewall' );
				}
			}
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_SessionDelete() {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $oMod */
		$oMod = $this->getMod();
		$bSuccess = false;
		$nId = Services::Request()->post( 'rid', -1 );
		if ( !is_numeric( $nId ) || $nId < 0 ) {
			$sMessage = __( 'Invalid session selected', 'wp-simple-firewall' );
		}
		else if ( $oMod->getSession()->id === $nId ) {
			$sMessage = __( 'Please logout if you want to delete your own session.', 'wp-simple-firewall' );
		}
		else if ( $oMod->getDbHandler_Sessions()->getQueryDeleter()->deleteById( $nId ) ) {
			$sMessage = __( 'User session deleted', 'wp-simple-firewall' );
			$bSuccess = true;
		}
		else {
			$sMessage = __( "User session wasn't deleted", 'wp-simple-firewall' );
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
	}
}