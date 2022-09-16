<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages\PageSecurityAdminRestricted;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Exceptions\{
	ActionDoesNotExistException,
	ActionException,
	InvalidActionNonceException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ActionProcessor {

	use ModConsumer;

	/**
	 * @throws ActionDoesNotExistException
	 * @throws ActionException
	 * @throws InvalidActionNonceException
	 */
	public function processAction( string $slug, array $data = [] ) :ActionResponse {
		if ( !$this->exists( $slug ) ) {
			throw new ActionDoesNotExistException( 'There was no action handler available for '.$slug );
		}

		$action = $this->getAction( $slug, $data );
		if ( $action->isUserAuthRequired() && !Services::WpUsers()->isUserLoggedIn() ) {
			throw new ActionException( 'Must be logged-in to execute this request' );
		}
		elseif ( $action->isSecurityAdminRestricted() && !$this->getCon()->isPluginAdmin() ) {
			$action = $this->getAction( PageSecurityAdminRestricted::SLUG, $data );
		}
		elseif ( $action->isNonceVerifyRequired() && !$this->verifyNonce() ) {
			throw new InvalidActionNonceException();
		}

		return $action->process()->response();
	}

	public function verifyNonce() :bool {
		$req = Services::Request();
		return wp_verify_nonce(
				   $req->request( ActionData::FIELD_NONCE ),
				   ActionData::FIELD_SHIELD.'-'.$req->request( ActionData::FIELD_EXECUTE )
			   ) === 1;
	}

	public function exists( string $slug ) :bool {
		return !empty( $this->findActionFromSlug( $slug ) );
	}

	public function getAction( string $slug, array $data ) :Actions\BaseAction {
		$action = $this->findActionFromSlug( $slug );
		return ( new $action( $data ) )->setMod( $this->getMod() );
	}

	public function findActionFromSlug( string $slug ) :string {
		$theAction = '';
		foreach ( $this->enum() as $action ) {
			if ( preg_match( $action::GetPattern(), $slug ) ) {
				$theAction = $action;
				break;
			}
		}
		return $theAction;
	}

	/**
	 * @return Actions\BaseAction[]
	 */
	public function available() :array {
		$actions = [];
		foreach ( $this->enum() as $action ) {
			$actions[ $action::SLUG ] = $action;
		}
		return $actions;
	}

	/**
	 * @return Actions\BaseAction[]
	 */
	public function enum() :array {
		return [
			Actions\ActivityLogTableAction::class,
			Actions\AdminNoteBulkAction::class,
			Actions\AdminNotesRender::class,
			Actions\AdminNoteDelete::class,
			Actions\AdminNoteInsert::class,
			Actions\DismissAdminNotice::class,
			Actions\DynamicLoad::class,
			Actions\FileDownload::class,
			Actions\HackGuardPluginReinstall::class,
			Actions\IpAnalyseAction::class,
			Actions\IpRuleAddRender::class,
			Actions\IpRuleAddSubmit::class,
			Actions\IpRuleDelete::class,
			Actions\IpRulesTableAction::class,
			Actions\IpAutoUnblockShieldUserLinkRequest::class,
			Actions\IpAutoUnblockShieldUserLinkVerify::class,
			Actions\IpAutoUnblockShieldVisitor::class,
			Actions\IpAutoUnblockCrowdsecVisitor::class,
			Actions\LicenseHandshakeVerifyKeyless::class,
			Actions\LicenseHandshakeVerifySnapi::class,
			Actions\LicenseAction::class,
			Actions\LicenseCheckDebug::class,
			Actions\LicenseScheduleCheck::class,
			Actions\MainwpExtensionTableSites::class,
			Actions\MainwpSiteAction::class,
			Actions\MerlinAction::class,
			Actions\MfaBackupCodeAdd::class,
			Actions\MfaBackupCodeDelete::class,
			Actions\MfaCanEmailSendVerify::class,
			Actions\MfaEmailDisable::class,
			Actions\MfaEmailToggle::class,
			Actions\MfaEmailSendIntent::class,
			Actions\MfaEmailSendVerification::class,
			Actions\MfaLoginVerifyStep::class,
			Actions\MfaRemoveAll::class,
			Actions\MfaGoogleAuthToggle::class,
			//			Actions\MfaSmsAdd::class,
			//			Actions\MfaSmsRemove::class,
			//			Actions\MfaSmsVerify::class,
			//			Actions\MfaSmsIntentSend::class,
			Actions\MfaU2fAdd::class,
			Actions\MfaU2fRemove::class,
			Actions\MfaYubikeyToggle::class,
			Actions\ModuleOptionsSave::class,
			Actions\CaptureNotBot::class,
			Actions\PluginAutoDbRepair::class,
			Actions\PluginBadgeClose::class,
			Actions\PluginDashboardWidgetRender::class,
			Actions\PluginDeleteForceOff::class,
			Actions\PluginDumpTelemetry::class,
			Actions\PluginImportExport_UpdateNotified::class,
			Actions\PluginImportExport_Export::class,
			Actions\PluginImportExport_HandshakeConfirm::class,
			Actions\PluginImportFromFileUpload::class,
			Actions\PluginImportFromSite::class,
			Actions\PluginIpDetect::class,
			Actions\PluginMarkTourFinished::class,
			Actions\PluginOffCanvasRender::class,
			Actions\PluginSuperSearch::class,
			Actions\PluginSetTracking::class,
			Actions\ReportingChartCustom::class,
			Actions\ReportingChartSummary::class,
			Actions\SecurityAdminCheck::class,
			Actions\SecurityAdminLogin::class,
			Actions\SecurityAdminRequestRemoveByEmail::class,
			Actions\SecurityAdminRemoveByEmail::class,
			Actions\ScansCheck::class,
			Actions\ScansStart::class,
			Actions\ScansFileLockerDiff::class,
			Actions\ScansFileLockerAction::class,
			Actions\ScanResultsTableAction::class,
			Actions\TrafficLogTableAction::class,
			Actions\UserSessionDelete::class,
			Actions\UserSessionsTableRender::class,
			Actions\UserSessionsTableBulkAction::class,

			Actions\Render\PageAdminPlugin::class,
			Actions\Render\PluginAdminPages\ActivityLogTable::class,
			Actions\Render\PluginAdminPages\PageDebug::class,
			Actions\Render\PluginAdminPages\PageDocs::class,
			Actions\Render\PluginAdminPages\PageImportExport::class,
			Actions\Render\PluginAdminPages\PageIpRulesTable::class,
			Actions\Render\PluginAdminPages\PageLicense::class,
			Actions\Render\PluginAdminPages\PageAdminNotes::class,
			Actions\Render\PluginAdminPages\PageMerlin::class,
			Actions\Render\PluginAdminPages\PageOverview::class,
			Actions\Render\PluginAdminPages\PageReports::class,
			Actions\Render\PluginAdminPages\PageSecurityAdminRestricted::class,
			Actions\Render\PluginAdminPages\PageRulesSummary::class,
			Actions\Render\PluginAdminPages\PageScansResults::class,
			Actions\Render\PluginAdminPages\PageScansRun::class,
			Actions\Render\PluginAdminPages\PageConfig::class,
			Actions\Render\PluginAdminPages\PageStats::class,
			Actions\Render\PluginAdminPages\TrafficLogTable::class,
			Actions\Render\PluginAdminPages\PageUserSessions::class,
		];
	}
}