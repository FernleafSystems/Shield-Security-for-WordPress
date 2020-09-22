<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\Base\AjaxHandlerShield {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'render_table_sessions':
				$response = $this->ajaxExec_BuildTableSessions();
				break;

			case 'bulk_action':
				$response = $this->ajaxExec_BulkItemAction();
				break;

			case 'session_delete':
				$response = $this->ajaxExec_SessionDelete();
				break;

			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	private function ajaxExec_BuildTableSessions() :array {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $mod */
		$mod = $this->getMod();

		( new UserManagement\Lib\CleanExpired() )
			->setMod( $mod )
			->run();

		/** @var Shield\Modules\SecurityAdmin\Options $optsSecAdmin */
		$optsSecAdmin = $this->getCon()
							 ->getModule_SecAdmin()
							 ->getOptions();

		$oTableBuilder = ( new Shield\Tables\Build\Sessions() )
			->setMod( $mod )
			->setDbHandler( $mod->getDbHandler_Sessions() )
			->setSecAdminUsers( $optsSecAdmin->getSecurityAdminUsers() );

		return [
			'success' => true,
			'html'    => $oTableBuilder->render()
		];
	}

	private function ajaxExec_BulkItemAction() :array {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $mod */
		$mod = $this->getMod();
		$oReq = Services::Request();

		$bSuccess = false;

		$aIds = $oReq->post( 'ids' );
		if ( empty( $aIds ) || !is_array( $aIds ) ) {
			$bSuccess = false;
			$sMessage = __( 'No items selected.', 'wp-simple-firewall' );
		}
		elseif ( !in_array( $oReq->post( 'bulk_action' ), [ 'delete' ] ) ) {
			$sMessage = __( 'Not a supported action.', 'wp-simple-firewall' );
		}
		else {
			$nYourId = $mod->getSession()->id;
			$bIncludesYourSession = in_array( $nYourId, $aIds );

			if ( $bIncludesYourSession && ( count( $aIds ) == 1 ) ) {
				$sMessage = __( 'Please logout if you want to delete your own session.', 'wp-simple-firewall' );
			}
			else {
				$bSuccess = true;

				$oTerminator = ( new Sessions\Lib\Ops\Terminate() )
					->setMod( $this->getCon()->getModule_Sessions() );
				foreach ( $aIds as $nId ) {
					if ( is_numeric( $nId ) && ( $nId != $nYourId ) ) {
						$oTerminator->byRecordId( $nId );
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

	private function ajaxExec_SessionDelete() :array {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $mod */
		$mod = $this->getMod();
		$bSuccess = false;
		$nId = Services::Request()->post( 'rid', -1 );
		if ( !is_numeric( $nId ) || $nId < 0 ) {
			$sMessage = __( 'Invalid session selected', 'wp-simple-firewall' );
		}
		elseif ( $mod->getSession()->id === $nId ) {
			$sMessage = __( 'Please logout if you want to delete your own session.', 'wp-simple-firewall' );
		}
		elseif ( $mod->getDbHandler_Sessions()->getQueryDeleter()->deleteById( $nId ) ) {
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