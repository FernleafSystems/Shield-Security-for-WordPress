<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\FindSourceFromIp;

class AjaxHandler extends Shield\Modules\Base\AjaxHandlerShield {

	/**
	 * @param string $sAction
	 * @return array
	 */
	protected function processAjaxAction( $sAction ) {
		switch ( $sAction ) {
			case 'bulk_action':
				$aResponse = $this->ajaxExec_BulkItemAction();
				break;

			case 'delete_forceoff':
				$aResponse = $this->ajaxExec_DeleteForceOff();
				break;

			case 'render_table_adminnotes':
				$aResponse = $this->ajaxExec_RenderTableAdminNotes();
				break;

			case 'note_delete':
				$aResponse = $this->ajaxExec_AdminNotesDelete();
				break;

			case 'note_insert':
				$aResponse = $this->ajaxExec_AdminNotesInsert();
				break;

			case 'import_from_site':
				$aResponse = $this->ajaxExec_ImportFromSite();
				break;

			case 'plugin_badge_close':
				$aResponse = $this->ajaxExec_PluginBadgeClose();
				break;

			case 'set_plugin_tracking':
				$aResponse = $this->ajaxExec_SetPluginTrackingPerm();
				break;

			case 'send_deactivate_survey':
				$aResponse = $this->ajaxExec_SendDeactivateSurvey();
				break;

			case 'sgoptimizer_turnoff':
				$aResponse = $this->ajaxExec_TurnOffSiteGroundOptions();
				break;

			case 'ipdetect':
				$aResponse = $this->ajaxExec_IpDetect();
				break;

			case 'mark_tour_finished':
				$aResponse = $this->ajaxExec_MarkTourFinished();
				break;

			default:
				$aResponse = parent::processAjaxAction( $sAction );
		}

		return $aResponse;
	}

	/**
	 * @return array
	 */
	private function ajaxExec_SendDeactivateSurvey() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		$aResults = [];
		foreach ( $_POST as $sKey => $sValue ) {
			if ( strpos( $sKey, 'reason_' ) === 0 ) {
				$aResults[] = str_replace( 'reason_', '', $sKey ).': '.$sValue;
			}
		}
		$oMod->getEmailProcessor()
			 ->send(
				 $oMod->getSurveyEmail(),
				 'Shield Deactivation Survey',
				 implode( "\n<br/>", $aResults )
			 );
		return [ 'success' => true ];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_PluginBadgeClose() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		$bSuccess = $oMod->getPluginBadgeCon()->setBadgeStateClosed();
		return [
			'success' => $bSuccess,
			'message' => $bSuccess ? 'Badge Closed' : 'Badge Not Closed'
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_SetPluginTrackingPerm() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		if ( !$oOpts->isTrackingPermissionSet() ) {
			$oOpts->setPluginTrackingPermission( (bool)Services::Request()->query( 'agree', false ) );
		}
		return [ 'success' => true ];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_BulkItemAction() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
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
			/** @var Shield\Databases\AdminNotes\Delete $oDel */
			$oDel = $oMod->getDbHandler_Notes()->getQueryDeleter();
			foreach ( $aIds as $nId ) {
				if ( is_numeric( $nId ) ) {
					$oDel->deleteById( $nId );
				}
			}
			$bSuccess = true;
			$sMessage = __( 'Selected items were deleted.', 'wp-simple-firewall' );
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_DeleteForceOff() {
		$bStillActive = $this->getCon()
							 ->deleteForceOffFile()
							 ->getIfForceOffActive();
		if ( $bStillActive ) {
			$this->getMod()
				 ->setFlashAdminNotice( __( 'File could not be automatically removed.', 'wp-simple-firewall' ), true );
		}
		return [ 'success' => !$bStillActive ];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_RenderTableAdminNotes() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		return [
			'success' => true,
			'html'    => ( new Shield\Tables\Build\AdminNotes() )
				->setMod( $oMod )
				->setDbHandler( $oMod->getDbHandler_Notes() )
				->buildTable()
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_AdminNotesDelete() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();

		$sItemId = Services::Request()->post( 'rid' );
		if ( empty( $sItemId ) ) {
			$sMessage = __( 'Note not found.', 'wp-simple-firewall' );
		}
		else {
			try {
				$bSuccess = $oMod->getDbHandler_Notes()
								 ->getQueryDeleter()
								 ->deleteById( $sItemId );

				if ( $bSuccess ) {
					$sMessage = __( 'Note deleted', 'wp-simple-firewall' );
				}
				else {
					$sMessage = __( "Note couldn't be deleted", 'wp-simple-firewall' );
				}
			}
			catch ( \Exception $oE ) {
				$sMessage = $oE->getMessage();
			}
		}

		return [
			'success' => true,
			'message' => $sMessage
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_ImportFromSite() {
		$bSuccess = false;
		$aFormParams = array_merge(
			[
				'confirm' => 'N'
			],
			$this->getAjaxFormParams()
		);

		// TODO: align with wizard AND combine with file upload errors
		if ( $aFormParams[ 'confirm' ] !== 'Y' ) {
			$sMessage = __( 'Please check the box to confirm your intent to overwrite settings', 'wp-simple-firewall' );
		}
		else {
			$sMasterSiteUrl = $aFormParams[ 'MasterSiteUrl' ];
			$sSecretKey = $aFormParams[ 'MasterSiteSecretKey' ];
			$bEnabledNetwork = $aFormParams[ 'ShieldNetwork' ] === 'Y';
			$bDisableNetwork = $aFormParams[ 'ShieldNetwork' ] === 'N';
			$bNetwork = $bEnabledNetwork ? true : ( $bDisableNetwork ? false : null );

			/** @var Shield\Databases\AdminNotes\Insert $oInserter */
			try {
				$nCode = ( new Plugin\Lib\ImportExport\Import() )
					->setMod( $this->getMod() )
					->fromSite( $sMasterSiteUrl, $sSecretKey, $bNetwork );
			}
			catch ( \Exception $oE ) {
				$nCode = $oE->getCode();
			}
			$bSuccess = $nCode == 0;
			$sMessage = $bSuccess ? __( 'Options imported successfully', 'wp-simple-firewall' ) : __( 'Options failed to import', 'wp-simple-firewall' );
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_AdminNotesInsert() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		$bSuccess = false;
		$aFormParams = $this->getAjaxFormParams();

		$sNote = isset( $aFormParams[ 'admin_note' ] ) ? $aFormParams[ 'admin_note' ] : '';
		if ( !$oMod->getCanAdminNotes() ) {
			$sMessage = __( 'Sorry, Admin Notes is only available for Pro subscriptions.', 'wp-simple-firewall' );
		}
		elseif ( empty( $sNote ) ) {
			$sMessage = __( 'Sorry, but it appears your note was empty.', 'wp-simple-firewall' );
		}
		else {
			/** @var Shield\Databases\AdminNotes\Insert $oInserter */
			$oInserter = $oMod->getDbHandler_Notes()->getQueryInserter();
			$bSuccess = $oInserter->create( $sNote );
			$sMessage = $bSuccess ? __( 'Note created successfully.', 'wp-simple-firewall' ) : __( 'Note could not be created.', 'wp-simple-firewall' );
		}
		return [
			'success' => $bSuccess,
			'message' => $sMessage
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_TurnOffSiteGroundOptions() {
		$bSuccess = ( new Plugin\Components\SiteGroundPluginCompatibility() )->switchOffOptions();
		return [
			'success' => $bSuccess,
			'message' => $bSuccess ? __( 'Switching-off conflicting options appears to have been successful.', 'wp-simple-firewall' )
				: __( 'Switching-off conflicting options appears to have failed.', 'wp-simple-firewall' )
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_IpDetect() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		$sSource = ( new FindSourceFromIp() )->run( Services::Request()->post( 'ip' ) );
		if ( !empty( $sSource ) ) {
			$oOpts->setVisitorAddressSource( $sSource );
		}
		return [
			'success' => !empty( $sSource ),
			'message' => empty( $sSource ) ? 'Could not find source' : 'IP Source Found: '.$sSource
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_MarkTourFinished() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		$oMod->getTourManager()->setCompleted( Services::Request()->post( 'tour_key' ) );
		return [
			'success' => true,
			'message' => 'Tour Finished'
		];
	}
}