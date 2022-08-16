<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\FindSourceFromIp;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$map = array_merge( parent::getAjaxActionCallbackMap( $isAuth ), [
			'plugin_badge_close' => [ $this, 'ajaxExec_PluginBadgeClose' ],
		] );
		if ( $isAuth ) {
			$map = array_merge( $map, [
				'auto_db_repair'          => [ $this, 'ajaxExec_AutoDbRepair' ],
				'mod_options_save'        => [ $this, 'ajaxExec_ModOptionsSave' ],
				'bulk_action'             => [ $this, 'ajaxExec_BulkItemAction' ],
				'delete_forceoff'         => [ $this, 'ajaxExec_DeleteForceOff' ],
				'import_from_site'        => [ $this, 'ajaxExec_ImportFromSite' ],
				'ipdetect'                => [ $this, 'ajaxExec_IpDetect' ],
				'mark_tour_finished'      => [ $this, 'ajaxExec_MarkTourFinished' ],
				'note_delete'             => [ $this, 'ajaxExec_AdminNotesDelete' ],
				'note_insert'             => [ $this, 'ajaxExec_AdminNotesInsert' ],
				'plugin_badge_close'      => [ $this, 'ajaxExec_PluginBadgeClose' ],
				'render_table_adminnotes' => [ $this, 'ajaxExec_RenderTableAdminNotes' ],
				'set_plugin_tracking'     => [ $this, 'ajaxExec_SetPluginTrackingPerm' ],
				'sgoptimizer_turnoff'     => [ $this, 'ajaxExec_TurnOffSiteGroundOptions' ],
				'wizard_step'             => [ $this, 'ajaxExec_Wizard' ],
				'render_dashboard_widget' => [ $this, 'ajaxExec_RenderDashboardWidget' ],
			] );
		}
		return $map;
	}

	public function ajaxExec_AutoDbRepair() :array {
		$con = $this->getCon();

		// 1. Forcefully re-run all checks:
		$checks = ( new Shield\Controller\Checks\PreModulesBootCheck() )
			->setCon( $con )
			->run( true );
		$dbMisconfigured = count( $checks[ 'dbs' ] ) !== count( array_filter( $checks[ 'dbs' ] ) );

		if ( $dbMisconfigured ) {
			$modHG = $con->getModule_HackGuard();
			/** @var Handler[] $allHandlers */
			$allHandlers = [
				$con->getModule_AuditTrail()->getDbH_Logs(),
				$con->getModule_AuditTrail()->getDbH_Meta(),
				$con->getModule_Data()->getDbH_IPs(),
				$con->getModule_Data()->getDbH_ReqLogs(),
				$con->getModule_Data()->getDbH_UserMeta(),
				$con->getModule_IPs()->getDbH_BotSignal(),
				$con->getModule_IPs()->getDbH_IPRules(),
				$modHG->getDbH_Scans(),
				$modHG->getDbH_ScanItems(),
				$modHG->getDbH_ScanResults(),
				$modHG->getDbH_ResultItems(),
				$modHG->getDbH_ResultItemMeta()
			];
			Services::WpDb()->doSql(
				sprintf( 'DROP TABLE IF EXISTS `%s`', implode( '`,`', array_map(
					function ( $schema ) {
						return $schema->getTableSchema()->table;
					},
					$allHandlers
				) ) )
			);
			foreach ( $allHandlers as $handler ) {
				$handler::GetTableReadyCache()->setReady( $handler->getTableSchema(), false );
			}
			$msg = "Tables deleted and they'll now be recreated.";
		}
		else {
			$msg = "Tables appear to be valid and haven't been repaired.";
		}

		return [
			'success'     => true,
			'page_reload' => true,
			'message'     => $msg
		];
	}

	public function ajaxExec_ModOptionsSave() :array {
		$name = $this->getCon()->getHumanName();

		$saver = ( new Shield\Modules\Base\Options\HandleOptionsSaveRequest() )
			->setMod( $this->getMod() );
		$success = $saver->handleSave();

		return [
			'success'     => $success,
			'redirect_to' => $saver->getMod()->getUrl_OptionsConfigPage(),
			'html'        => '', //we reload the page
			'message'     => $success ?
				sprintf( __( '%s Plugin options updated successfully.', 'wp-simple-firewall' ), $name )
				: sprintf( __( 'Failed to update %s plugin options.', 'wp-simple-firewall' ), $name )
		];
	}

	public function ajaxExec_RenderDashboardWidget() :array {
		return [
			'success' => true,
			'html'    => ( new Components\DashboardWidget() )
				->setMod( $this->getMod() )
				->render( (bool)Services::Request()->post( 'refresh' ) )
		];
	}

	public function ajaxExec_Wizard() :array {
		$params = FormParams::Retrieve();
		// step will be step1, step2 etc.
		$currentStep = intval( str_replace( 'step', '', $params[ 'step' ] ) );
		$data = $params[ $params[ 'step' ] ];
		return [
			'success' => true,
			'message' => $currentStep < 3 ? $data : 'done done done',
			'next'    => $currentStep < 3 ? 'step'.++$currentStep : 'done',
		];
	}

	public function ajaxExec_PluginBadgeClose() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$success = $mod->getPluginBadgeCon()->setBadgeStateClosed();
		return [
			'success' => $success,
			'message' => $success ? 'Badge Closed' : 'Badge Not Closed'
		];
	}

	public function ajaxExec_SetPluginTrackingPerm() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( !$opts->isTrackingPermissionSet() ) {
			$opts->setPluginTrackingPermission( (bool)Services::Request()->query( 'agree', false ) );
		}
		return $this->ajaxExec_DismissAdminNotice();
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
			/** @var Shield\Databases\AdminNotes\Delete $deleter */
			$deleter = $mod->getDbHandler_Notes()->getQueryDeleter();
			foreach ( $IDs as $id ) {
				if ( is_numeric( $id ) ) {
					$deleter->deleteById( $id );
				}
			}
			$success = true;
			$msg = __( 'Selected items were deleted.', 'wp-simple-firewall' );
		}

		return [
			'success' => $success,
			'message' => $msg,
		];
	}

	public function ajaxExec_DeleteForceOff() :array {
		$this->getCon()->deleteForceOffFile();
		return [
			'success'     => true,
			'page_reload' => true,
			'message'     => __( 'Removed the forceoff file.', 'wp-simple-firewall' ),
		];
	}

	public function ajaxExec_RenderTableAdminNotes() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return [
			'success' => true,
			'html'    => ( new Shield\Tables\Build\AdminNotes() )
				->setMod( $mod )
				->setDbHandler( $mod->getDbHandler_Notes() )
				->render()
		];
	}

	public function ajaxExec_AdminNotesDelete() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$noteID = Services::Request()->post( 'rid' );
		if ( empty( $noteID ) ) {
			$msg = __( 'Note not found.', 'wp-simple-firewall' );
		}
		else {
			try {
				$success = $mod->getDbHandler_Notes()
							   ->getQueryDeleter()
							   ->deleteById( $noteID );

				if ( $success ) {
					$msg = __( 'Note deleted', 'wp-simple-firewall' );
				}
				else {
					$msg = __( "Note couldn't be deleted", 'wp-simple-firewall' );
				}
			}
			catch ( \Exception $e ) {
				$msg = $e->getMessage();
			}
		}

		return [
			'success' => true,
			'message' => $msg
		];
	}

	public function ajaxExec_ImportFromSite() :array {
		$success = false;
		$formParams = array_merge(
			[
				'confirm' => 'N'
			],
			FormParams::Retrieve()
		);

		// TODO: align with wizard AND combine with file upload errors
		if ( $formParams[ 'confirm' ] !== 'Y' ) {
			$msg = __( 'Please check the box to confirm your intent to overwrite settings', 'wp-simple-firewall' );
		}
		else {
			$doNetwork = ( $formParams[ 'ShieldNetwork' ] === 'Y' ) ? true : ( ( $formParams[ 'ShieldNetwork' ] === 'N' ) ? false : null );

			/** @var Shield\Databases\AdminNotes\Insert $oInserter */
			try {
				$code = ( new Plugin\Lib\ImportExport\Import() )
					->setMod( $this->getMod() )
					->fromSite( (string)$formParams[ 'MasterSiteUrl' ], (string)$formParams[ 'MasterSiteSecretKey' ], $doNetwork );
			}
			catch ( \Exception $e ) {
				$code = $e->getCode();
			}
			$success = $code == 0;
			$msg = $success ? __( 'Options imported successfully', 'wp-simple-firewall' ) : __( 'Options failed to import', 'wp-simple-firewall' );
		}

		return [
			'success' => $success,
			'message' => $msg
		];
	}

	public function ajaxExec_AdminNotesInsert() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$success = false;
		$formParams = FormParams::Retrieve();

		$note = trim( $formParams[ 'admin_note' ] ?? '' );
		if ( !$mod->getCanAdminNotes() ) {
			$msg = __( "Sorry, the Admin Notes feature isn't available.", 'wp-simple-firewall' );
		}
		elseif ( empty( $note ) ) {
			$msg = __( 'Sorry, but it appears your note was empty.', 'wp-simple-firewall' );
		}
		else {
			/** @var Shield\Databases\AdminNotes\Insert $inserter */
			$inserter = $mod->getDbHandler_Notes()->getQueryInserter();
			$success = $inserter->create( $note );
			$msg = $success ? __( 'Note created successfully.', 'wp-simple-firewall' ) : __( 'Note could not be created.', 'wp-simple-firewall' );
		}
		return [
			'success' => $success,
			'message' => $msg
		];
	}

	public function ajaxExec_TurnOffSiteGroundOptions() :array {
		$success = ( new Plugin\Components\SiteGroundPluginCompatibility() )->switchOffOptions();
		return [
			'success' => $success,
			'message' => $success ? __( 'Switching-off conflicting options appears to have been successful.', 'wp-simple-firewall' )
				: __( 'Switching-off conflicting options appears to have failed.', 'wp-simple-firewall' )
		];
	}

	public function ajaxExec_IpDetect() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$source = ( new FindSourceFromIp() )->run( Services::Request()->post( 'ip' ) );
		if ( !empty( $source ) ) {
			$opts->setVisitorAddressSource( $source );
		}
		return [
			'success'   => !empty( $source ),
			'message'   => empty( $source ) ? 'Could not find source' : 'IP Source Found: '.$source,
			'ip_source' => $source,
		];
	}

	public function ajaxExec_MarkTourFinished() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getTourManager()->setCompleted( Services::Request()->post( 'tour_key' ) );
		return [
			'success' => true,
			'message' => 'Tour Finished'
		];
	}
}