<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$map = parent::getAjaxActionCallbackMap( $isAuth );
		if ( $isAuth ) {
			$map = array_merge( $map, [
				'render_table_sessions' => [ $this, 'ajaxExec_BuildTableSessions' ],
				'bulk_action'           => [ $this, 'ajaxExec_BulkItemAction' ],
				'session_delete'        => [ $this, 'ajaxExec_SessionDelete' ],
			] );
		}
		return $map;
	}

	public function ajaxExec_BuildTableSessions() :array {
		/** @var Shield\Modules\SecurityAdmin\Options $optsSecAdmin */
		$optsSecAdmin = $this->getCon()->getModule_SecAdmin()->getOptions();
		return [
			'success' => true,
			'html'    => ( new Shield\Tables\Build\Sessions() )
				->setMod( $this->getMod() )
				->setSecAdminUsers( $optsSecAdmin->getSecurityAdminUsers() )
				->render()
		];
	}

	public function ajaxExec_BulkItemAction() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		$success = false;

		$IDs = $req->post( 'ids' );
		if ( empty( $IDs ) || !is_array( $IDs ) ) {
			$msg = __( 'No items selected.', 'wp-simple-firewall' );
		}
		elseif ( $req->post( 'bulk_action' ) != 'delete' ) {
			$msg = __( 'Not a supported action.', 'wp-simple-firewall' );
		}
		else {
			$sessionCon = $this->getCon()->getModule_Sessions()->getSessionCon();
			$yourId = $mod->getSessionWP()->shield[ 'unique' ] ?? '';
			$includesYourSession = false;

			foreach ( $IDs as $IDunique ) {
				list( $userID, $uniqueID ) = explode( '-', $IDunique );
				if ( $yourId === $uniqueID ) {
					$includesYourSession = true;
					continue;
				}

				$sessionCon->removeSessionBasedOnUniqueID( $userID, $uniqueID );
			}

			$msg = __( 'Selected items were deleted.', 'wp-simple-firewall' );
			if ( $includesYourSession ) {
				$msg .= ' *'.__( 'Your session was retained', 'wp-simple-firewall' );
			}

			$success = true;
		}

		return [
			'success' => $success,
			'message' => $msg,
		];
	}

	public function ajaxExec_SessionDelete() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$success = false;

		list( $userID, $uniqueID ) = explode( '-', Services::Request()->post( 'rid', '' ) );

		if ( empty( $userID ) || !is_numeric( $userID ) || $userID < 0 || empty( $uniqueID ) ) {
			$msg = __( 'Invalid session selected', 'wp-simple-firewall' );
		}
		elseif ( $mod->getSessionWP()->shield[ 'unique' ] === $uniqueID ) {
			$msg = __( 'Please logout if you want to delete your own session.', 'wp-simple-firewall' );
		}
		else {
			$con->getModule_Sessions()
				->getSessionCon()
				->removeSessionBasedOnUniqueID( $userID, $uniqueID );
			$msg = __( 'User session deleted', 'wp-simple-firewall' );
			$success = true;
		}

		return [
			'success' => $success,
			'message' => $msg,
		];
	}
}