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
		$bSuccess = false;

		$nId = Services::Request()->post( 'rid' );
		if ( empty( $nId ) || !is_numeric( $nId ) || $nId < 1 ) {
			$sMessage = __( 'Invalid audit entry selected for this action', 'wp-simple-firewall' );
		}
		else {
			/** @var Shield\Databases\AuditTrail\EntryVO $oEntry */
			$oEntry = $mod->getDbHandler_AuditTrail()
						  ->getQuerySelector()
						  ->byId( $nId );

			if ( empty( $oEntry ) ) {
				$sMessage = __( 'Audit entry could not be loaded.', 'wp-simple-firewall' );
			}
			else {
				$aData = $oEntry->meta;
				$sParam = isset( $aData[ 'param' ] ) ? $aData[ 'param' ] : '';
				$sUri = isset( $aData[ 'uri' ] ) ? $aData[ 'uri' ] : '*';
				if ( empty( $sParam ) ) {
					$sMessage = __( 'Parameter associated with this audit entry could not be found.', 'wp-simple-firewall' );
				}
				else {
					/** @var Shield\Modules\Firewall\ModCon $oModFire */
					$oModFire = $this->getCon()->getModule( 'firewall' );
					$oModFire->addParamToWhitelist( $sParam, $sUri );
					$sMessage = sprintf( __( 'Parameter "%s" whitelisted successfully', 'wp-simple-firewall' ), $sParam );
					$bSuccess = true;
				}
			}
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage
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