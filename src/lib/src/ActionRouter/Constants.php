<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

class Constants {

	public const NAV_ID = 'nav';
	public const NAV_SUB_ID = 'nav_sub';
	/**
	 * @type Actions\BaseAction[]
	 */
	public const ACTIONS = [
		Actions\ActivityLogTableAction::class,
		Actions\AdminNoteBulkAction::class,
		Actions\AdminNoteDelete::class,
		Actions\AdminNoteInsert::class,
		Actions\DismissAdminNotice::class,
		Actions\DynamicPageLoad::class,
		Actions\FileDownload::class,
		Actions\HackGuardPluginReinstall::class,
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
		Actions\LicenseActionLookup::class,
		Actions\LicenseCheckDebug::class,
		Actions\LicenseScheduleCheck::class,
		Actions\MainWP\MainwpExtensionTableSites::class,
		Actions\MainWP\MainwpServerSiteAction::class,
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
		Actions\PluginDeleteForceOff::class,
		Actions\PluginDumpTelemetry::class,
		Actions\PluginImportExport_UpdateNotified::class,
		Actions\PluginImportExport_Export::class,
		Actions\PluginImportExport_HandshakeConfirm::class,
		Actions\PluginImportFromFileUpload::class,
		Actions\PluginImportFromSite::class,
		Actions\PluginIpDetect::class,
		Actions\PluginMarkTourFinished::class,
		Actions\Render\Components\OffCanvas\OffCanvasBase::class,
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
		Actions\ScansFileLockerAction::class,
		Actions\ScanResultsTableAction::class,
		Actions\TrafficLogTableAction::class,
		Actions\UserSessionDelete::class,
		Actions\UserSessionsTableRender::class,
		Actions\UserSessionsTableBulkAction::class,

		Actions\Debug\SimplePluginTests::class,
		Actions\FullPageDisplay\DisplayBlockPage::class,
		Actions\FullPageDisplay\StandardFullPageDisplay::class,

		Actions\AjaxRender::class,

		Actions\DynamicLoad\Config::class,

		Actions\Render::class,
		Actions\Render\GenericRender::class,

		Actions\Render\Components\AdminNotes::class,
		Actions\Render\Components\AdminNotice::class,
		Actions\Render\Components\BannerGoPro::class,
		Actions\Render\Components\DashboardWidget::class,
		Actions\Render\Components\FormSecurityAdminLoginBox::class,
		Actions\Render\Components\PrivacyPolicy::class,
		Actions\Render\Components\ToastPlaceholder::class,
		Actions\Render\Components\RenderPluginBadge::class,
		Actions\Render\Components\Debug\DebugRecentEvents::class,
		Actions\Render\Components\Docs\DocsChangelog::class,
		Actions\Render\Components\Docs\DocsEvents::class,
		Actions\Render\Components\Email\Footer::class,
		Actions\Render\Components\Email\UnblockMagicLink::class,
		Actions\Render\Components\Email\MfaLoginCode::class,
		Actions\Render\Components\Email\FirewallBlockAlert::class,
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
		Actions\Render\Components\Meters\MeterCardPrimary::class,
		Actions\Render\Components\Meters\ProgressMeters::class,
		Actions\Render\Components\OffCanvas\IpAnalysis::class,
		Actions\Render\Components\OffCanvas\IpRuleAddForm::class,
		Actions\Render\Components\OffCanvas\MeterAnalysis::class,
		Actions\Render\Components\OffCanvas\ModConfig::class,
		Actions\Render\Components\Options\OptionsForm::class,
		Actions\Render\Components\Reports\Components\InfoKeyStats::class,
		Actions\Render\Components\Reports\ReportsCollatorForAlerts::class,
		Actions\Render\Components\Reports\ReportsCollatorForInfo::class,
		Actions\Render\Components\Reports\ChartsCustom::class,
		Actions\Render\Components\Reports\ChartsSummary::class,
		Actions\Render\Components\Reports\Components\AlertFileLocker::class,
		Actions\Render\Components\Reports\Components\AlertScanResults::class,
		Actions\Render\Components\Reports\Components\AlertScanRepairs::class,
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
		Actions\Render\Components\UserMfa\ConfigPage::class,
		Actions\Render\Components\UserMfa\ConfigEdit::class,
		Actions\Render\Components\UserMfa\ConfigForm::class,
		Actions\Render\Components\UserMfa\ConfigFormForProvider::class,
		Actions\Render\Components\Users\ProfileSuspend::class,
		Actions\Render\FullPage\Block\BlockAuthorFishing::class,
		Actions\Render\FullPage\Block\BlockFirewall::class,
		Actions\Render\FullPage\Block\BlockIpAddressShield::class,
		Actions\Render\FullPage\Block\BlockIpAddressCrowdsec::class,
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
		Actions\Render\Legacy\GaspJs::class,
		Actions\Render\Legacy\RecaptchaJs::class,
		Actions\Render\MainWP\SitesListTableColumn::class,
		Actions\Render\MainWP\ExtensionPageContainer::class,
		Actions\Render\MainWP\ExtPage\MwpOutOfDate::class,
		Actions\Render\MainWP\ExtPage\NotShieldPro::class,
		Actions\Render\MainWP\ExtPage\ShieldOutOfDate::class,
		Actions\Render\MainWP\ExtPage\TabSiteManage::class,
		Actions\Render\MainWP\ExtPage\TabSitesListing::class,
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