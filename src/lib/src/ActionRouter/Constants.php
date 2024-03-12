<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

class Constants {

	public const NAV_ID = 'nav';
	public const NAV_SUB_ID = 'nav_sub';
	public const ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED = 'is_nonce_verify_required';
	/**
	 * @type Actions\BaseAction[]
	 */
	public const ACTIONS = [
		Actions\ActivityLogTableAction::class,
		Actions\BlockdownDisableFormSubmit::class,
		Actions\BlockdownFormSubmit::class,
		Actions\CrowdsecResetEnrollment::class,
		Actions\DismissAdminNotice::class,
		Actions\DynamicPageLoad::class,
		Actions\FileDownload::class,
		Actions\FileDownloadAsStream::class,
		Actions\IpAnalyseAction::class,
		Actions\IpRuleAddSubmit::class,
		Actions\IpRuleDelete::class,
		Actions\IpRulesTableAction::class,
		Actions\IpAutoUnblockShieldUserLinkRequest::class,
		Actions\IpAutoUnblockShieldUserLinkVerify::class,
		Actions\IpAutoUnblockShieldVisitor::class,
		Actions\IpAutoUnblockCrowdsecVisitor::class,
		Actions\LicenseHandshakeVerifyKeyless::class,
		Actions\ShieldNetHandshakeVerify::class,
		Actions\LicenseClear::class,
		Actions\LicenseLookup::class,
		Actions\LicenseCheckDebug::class,
		Actions\LicenseScheduleCheck::class,
		Actions\MainWP\MainwpExtensionTableSites::class,
		Actions\MainWP\ServerActions\MainwpServerClientActionHandler::class,
		Actions\MainWP\ServerActions\SiteActionSync::class,
		Actions\MainWP\ServerActions\SiteActionActivate::class,
		Actions\MainWP\ServerActions\SiteActionDeactivate::class,
		Actions\MainWP\ServerActions\SiteActionInstall::class,
		Actions\MainWP\ServerActions\SiteActionUpdate::class,
		Actions\MainWP\ServerActions\SiteCustomAction::class,
		Actions\MerlinAction::class,
		Actions\MfaBackupCodeAdd::class,
		Actions\MfaBackupCodeDelete::class,
		Actions\MfaCanEmailSendVerify::class,
		Actions\MfaEmailAutoLogin::class,
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
		Actions\MfaPasskeyRemoveSource::class,
		Actions\MfaPasskeyAuthenticationStart::class,
		Actions\MfaPasskeyAuthenticationVerify::class,
		Actions\MfaPasskeyRegistrationStart::class,
		Actions\MfaPasskeyRegistrationVerify::class,
		Actions\MfaYubikeyToggle::class,
		Actions\ModuleOptionsSave::class,
		Actions\OptionTransferIncludeToggle::class,
		Actions\CaptureNotBot::class,
		Actions\CaptureNotBotNonce::class,
		Actions\PluginAutoDbRepair::class,
		Actions\PluginBadgeClose::class,
		Actions\PluginDeleteForceOff::class,
		Actions\PluginDumpTelemetry::class,
		Actions\PluginImportExport_UpdateNotified::class,
		Actions\PluginImportExport_Export::class,
		Actions\PluginImportExport_HandshakeConfirm::class,
		Actions\PluginImportFromFileUpload::class,
		Actions\PluginImportFromSite::class,
		Actions\PluginIpDetect::class,
		Actions\PluginMarkTourFinished::class,
		Actions\PluginReinstall::class,
		Actions\Render\Components\OffCanvas\OffCanvasBase::class,
		Actions\PluginSuperSearch::class,
		Actions\PluginSetTracking::class,
		Actions\RuleBuilderAction::class,
		Actions\RulesManagerTableAction::class,
		Actions\ReportingChartCustom::class,
		Actions\ReportingChartSummary::class,
		Actions\ReportCreateCustom::class,
		Actions\ReportTableAction::class,
		Actions\SecurityAdminAuthClear::class,
		Actions\SecurityAdminCheck::class,
		Actions\SecurityAdminLogin::class,
		Actions\SecurityAdminRequestRemoveByEmail::class,
		Actions\SecurityAdminRemove::class,
		Actions\ScansCheck::class,
		Actions\ScansFileLockerAction::class,
		Actions\ScansHistoryTableAction::class,
		Actions\ScansMalaiFileQuery::class,
		Actions\ScansStart::class,
		Actions\ScanResultsTableAction::class,
		Actions\ScanResultsDisplayFormSubmit::class,
		Actions\SecurityOverviewViewAs::class,
		Actions\SessionsTableAction::class,
		Actions\TrafficLogTableAction::class,
		Actions\UserSessionDelete::class,

		Actions\Debug\SimplePluginTests::class,
		Actions\FullPageDisplay\DisplayBlockPage::class,
		Actions\FullPageDisplay\FullPageDisplayDynamic::class,
		Actions\FullPageDisplay\FullPageDisplayNonTerminating::class,
		Actions\FullPageDisplay\DisplayReport::class,

		Actions\AjaxRender::class,

		Actions\DynamicLoad\Config::class,

		Actions\PluginAdmin\PluginAdminPageHandler::class,

		Actions\TestRestFetchRequests::class,

		Actions\Render::class,

		Actions\Render\Components\AdminNotice::class,
		Actions\Render\Components\Widgets\WpDashboardSummary::class,
		Actions\Render\Components\FormSecurityAdminLoginBox::class,
		Actions\Render\Components\PrivacyPolicy::class,
		Actions\Render\Components\ToastPlaceholder::class,
		Actions\Render\Components\RenderPluginBadge::class,
		Actions\Render\Components\Debug\DebugRecentEvents::class,
		Actions\Render\Components\Docs\Changelog::class,
		Actions\Render\Components\Docs\EventsEnum::class,
		Actions\Render\Components\Email\Footer::class,
		Actions\Render\Components\Email\UnblockMagicLink::class,
		Actions\Render\Components\Email\MfaLoginCode::class,
		Actions\Render\Components\Email\FirewallBlockAlert::class,
		Actions\Render\Components\Email\UserLoginNotice::class,
		Actions\Render\Components\Reports\Contexts\EmailReport::class,
		Actions\Render\Components\IpAnalyse\Container::class,
		Actions\Render\Components\IpAnalyse\General::class,
		Actions\Render\Components\IpAnalyse\Activity::class,
		Actions\Render\Components\IpAnalyse\BotSignals::class,
		Actions\Render\Components\IpAnalyse\Sessions::class,
		Actions\Render\Components\IpAnalyse\Traffic::class,
		Actions\Render\Components\IPs\FormIpRuleAdd::class,
		Actions\Render\Components\Merlin\MerlinStep::class,
		Actions\Render\Components\Meters\Analysis::class,
		Actions\Render\Components\Meters\MeterCard::class,
		Actions\Render\Components\Meters\ProgressMeters::class,
		Actions\Render\Components\OffCanvas\FormScanResultsDisplayOptions::class,
		Actions\Render\Components\OffCanvas\IpAnalysis::class,
		Actions\Render\Components\OffCanvas\IpRuleAddForm::class,
		Actions\Render\Components\OffCanvas\MeterAnalysis::class,
		Actions\Render\Components\OffCanvas\ModConfig::class,
		Actions\Render\Components\OffCanvas\FormReportCreate::class,
		Actions\Render\Components\Options\OptionsForm::class,
		Actions\Render\Components\Placeholders\PlaceholderMeter::class,
		Actions\Render\Components\Reports\ReportsTable::class,
		Actions\Render\Components\Reports\FormCreateReport::class,
		Actions\Render\Components\Reports\PageReportsView::class,
		Actions\Render\Components\Reports\Components\ReportAreaChanges::class,
		Actions\Render\Components\Reports\Components\ReportAreaStats::class,
		Actions\Render\Components\Reports\Components\ReportAreaScansRepairs::class,
		Actions\Render\Components\Reports\Components\ReportAreaScansResults::class,
		Actions\Render\Components\Reports\ChartsCustom::class,
		Actions\Render\Components\Reports\ChartsSummary::class,
		Actions\Render\Components\Rules\RuleBuilder::class,
		Actions\Render\Components\Rules\RulesManager::class,
		Actions\Render\Components\Scans\FormScanResultsDisplayOptions::class,
		Actions\Render\Components\Scans\PluginVulnerabilityWarning::class,
		Actions\Render\Components\Scans\ReinstallDialog::class,
		Actions\Render\Components\Scans\ScansFileLockerDiff::class,
		Actions\Render\Components\Scans\ScansProgress::class,
		Actions\Render\Components\Scans\Results\FileLocker::class,
		Actions\Render\Components\Scans\Results\Malware::class,
		Actions\Render\Components\Scans\Results\Wordpress::class,
		Actions\Render\Components\Scans\Results\Plugins::class,
		Actions\Render\Components\Scans\Results\Themes::class,
		Actions\Render\Components\Scans\ItemAnalysis\Container::class,
		Actions\Render\Components\Scans\ItemAnalysis\Content::class,
		Actions\Render\Components\Scans\ItemAnalysis\Diff::class,
		Actions\Render\Components\Scans\ItemAnalysis\History::class,
		Actions\Render\Components\Scans\ItemAnalysis\Info::class,
		Actions\Render\Components\Scans\ItemAnalysis\Malai::class,
		Actions\Render\Components\SuperSearchResults::class,
		Actions\Render\Components\SiteHealth\Analysis::class,
		Actions\Render\Components\Traffic\TrafficLiveLogs::class,
		Actions\Render\Components\UserMfa\ConfigPage::class,
		Actions\Render\Components\UserMfa\ConfigEdit::class,
		Actions\Render\Components\UserMfa\ConfigForm::class,
		Actions\Render\Components\UserMfa\ConfigFormForProvider::class,
		Actions\Render\Components\UserMfa\LoginIntent\LoginIntentFormFieldShield::class,
		Actions\Render\Components\UserMfa\LoginIntent\LoginIntentFormFieldWpReplica::class,
		Actions\Render\Components\Users\ProfileSuspend::class,
		Actions\Render\FullPage\Block\BlockAuthorFishing::class,
		Actions\Render\FullPage\Block\BlockFirewall::class,
		Actions\Render\FullPage\Block\BlockIpAddressShield::class,
		Actions\Render\FullPage\Block\BlockIpAddressCrowdsec::class,
		Actions\Render\FullPage\Block\BlockPageSiteBlockdown::class,
		Actions\Render\FullPage\Block\BlockTrafficRateLimitExceeded::class,
		Actions\Render\FullPage\Block\Components\AutoUnblockCrowdsec::class,
		Actions\Render\FullPage\Block\Components\AutoUnblockShield::class,
		Actions\Render\FullPage\Block\Components\MagicLink::class,
		Actions\Render\FullPage\MainWP\TabManageSitePage::class,
		Actions\Render\FullPage\Mfa\ShieldLoginIntentPage::class,
		Actions\Render\FullPage\Mfa\WpReplicaLoginIntentPage::class,
		Actions\Render\FullPage\Mfa\Components\LoginIntentFormShield::class,
		Actions\Render\FullPage\Mfa\Components\LoginIntentFormWpReplica::class,
		Actions\Render\FullPage\Mfa\Components\WpLoginReplicaHeader::class,
		Actions\Render\FullPage\Mfa\Components\WpLoginReplicaBody::class,
		Actions\Render\FullPage\Mfa\Components\WpLoginReplicaFooter::class,
		Actions\Render\FullPage\Report\SecurityReport::class,
		Actions\Render\Legacy\GaspJs::class,
		Actions\Render\MainWP\SitesListTableColumn::class,
		Actions\Render\MainWP\ExtensionPageContainer::class,
		Actions\Render\MainWP\ExtPage\MwpOutOfDate::class,
		Actions\Render\MainWP\ExtPage\NotShieldPro::class,
		Actions\Render\MainWP\ExtPage\ShieldOutOfDate::class,
		Actions\Render\MainWP\ExtPage\TabSiteManage::class,
		Actions\Render\MainWP\ExtPage\TabSitesListing::class,
		Actions\Render\PageAdminPlugin::class,
		Actions\Render\PluginAdminPages\PageActivityLogTable::class,
		Actions\Render\PluginAdminPages\PageConfig::class,
		Actions\Render\PluginAdminPages\PageDebug::class,
		Actions\Render\PluginAdminPages\PageDocs::class,
		Actions\Render\PluginAdminPages\PageDynamicLoad::class,
		Actions\Render\PluginAdminPages\PageMerlin::class,
		Actions\Render\PluginAdminPages\PageDashboardMeters::class,
		Actions\Render\PluginAdminPages\PageDashboardOverview::class,
		Actions\Render\PluginAdminPages\PageImportExport::class,
		Actions\Render\PluginAdminPages\PageIpRulesTable::class,
		Actions\Render\PluginAdminPages\PageLicense::class,
		Actions\Render\PluginAdminPages\PageReports::class,
		Actions\Render\PluginAdminPages\PageRulesBuild::class,
		Actions\Render\PluginAdminPages\PageRulesManage::class,
		Actions\Render\PluginAdminPages\PageRulesSummary::class,
		Actions\Render\PluginAdminPages\PageScansHistory::class,
		Actions\Render\PluginAdminPages\PageScansResults::class,
		Actions\Render\PluginAdminPages\PageScansRun::class,
		Actions\Render\PluginAdminPages\PageSecurityAdminRestricted::class,
		Actions\Render\PluginAdminPages\PageStats::class,
		Actions\Render\PluginAdminPages\PageTrafficLogLive::class,
		Actions\Render\PluginAdminPages\PageTrafficLogTable::class,
		Actions\Render\PluginAdminPages\PageToolLockdown::class,
		Actions\Render\PluginAdminPages\PageUserSessions::class,
		Actions\Render\Utility\DbDescribeTable::class,
	];
}