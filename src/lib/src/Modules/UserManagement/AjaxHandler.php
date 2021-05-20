<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

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
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();

		( new UserManagement\Lib\CleanExpired() )
			->setMod( $mod )
			->run();

		/** @var Shield\Modules\SecurityAdmin\Options $optsSecAdmin */
		$optsSecAdmin = $con->getModule_SecAdmin()->getOptions();

		$oTableBuilder = ( new Shield\Tables\Build\Sessions() )
			->setMod( $mod )
			->setDbHandler( $con->getModule_Sessions()->getDbHandler_Sessions() )
			->setSecAdminUsers( $optsSecAdmin->getSecurityAdminUsers() );

		return [
			'success' => true,
			'html'    => $oTableBuilder->render()
		];
	}

	private function ajaxExec_BulkItemAction() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		$bSuccess = false;

		$aIds = $req->post( 'ids' );
		if ( empty( $aIds ) || !is_array( $aIds ) ) {
			$bSuccess = false;
			$sMessage = __( 'No items selected.', 'wp-simple-firewall' );
		}
		elseif ( !in_array( $req->post( 'bulk_action' ), [ 'delete' ] ) ) {
			$sMessage = __( 'Not a supported action.', 'wp-simple-firewall' );
		}
		else {
			$yourId = $mod->getSession()->id;
			$bIncludesYourSession = in_array( $yourId, $aIds );

			if ( $bIncludesYourSession && ( count( $aIds ) == 1 ) ) {
				$sMessage = __( 'Please logout if you want to delete your own session.', 'wp-simple-firewall' );
			}
			else {
				$bSuccess = true;

				$terminator = ( new Sessions\Lib\Ops\Terminate() )
					->setMod( $this->getCon()->getModule_Sessions() );
				foreach ( $aIds as $id ) {
					if ( is_numeric( $id ) && ( $id != $yourId ) ) {
						$terminator->byRecordId( (int)$id );
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
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$bSuccess = false;
		$nId = Services::Request()->post( 'rid', -1 );
		if ( !is_numeric( $nId ) || $nId < 0 ) {
			$sMessage = __( 'Invalid session selected', 'wp-simple-firewall' );
		}
		elseif ( $mod->getSession()->id === $nId ) {
			$sMessage = __( 'Please logout if you want to delete your own session.', 'wp-simple-firewall' );
		}
		elseif ( $con->getModule_Sessions()->getDbHandler_Sessions()->getQueryDeleter()->deleteById( $nId ) ) {
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