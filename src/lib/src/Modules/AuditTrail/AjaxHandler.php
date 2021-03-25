<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'render_table_audittrail':
				$aResponse = $this->ajaxExec_BuildTableAuditTrail();
				break;

			case 'item_addparamwhite':
				$aResponse = $this->ajaxExec_AddParamToFirewallWhitelist();
				break;

			default:
				$aResponse = parent::processAjaxAction( $action );
		}

		return $aResponse;
	}

	protected function ajaxExec_AddParamToFirewallWhitelist() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$success = false;

		$entryID = Services::Request()->post( 'rid' );
		if ( empty( $entryID ) || !is_numeric( $entryID ) || $entryID < 1 ) {
			$msg = __( 'Invalid audit entry selected for this action', 'wp-simple-firewall' );
		}
		else {
			try {
				$msg = ( new Lib\Utility\AutoWhitelistParamFromAuditEntry() )
					->setMod( $mod )
					->run( (int)$entryID );
				$success = true;
			}
			catch ( \Exception $e ) {
				$msg = $e->getMessage();
			}
		}

		return [
			'success' => $success,
			'message' => $msg
		];
	}

	private function ajaxExec_BuildTableAuditTrail() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$oTableBuilder = ( new Shield\Tables\Build\AuditTrail() )
			->setMod( $mod )
			->setDbHandler( $mod->getDbHandler_AuditTrail() );

		return [
			'success' => true,
			'html'    => $oTableBuilder->render()
		];
	}
}