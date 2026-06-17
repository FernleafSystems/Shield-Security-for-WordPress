<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions,
	Utility\AjaxRenderTargetPolicy,
	Utility\RenderActionTarget
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class AjaxRenderTargetPolicyTest extends BaseUnitTest {

	/**
	 * @dataProvider allowedRenderTargetProvider
	 */
	public function test_current_ajax_render_producer_targets_are_allowed( string $actionClass ) :void {
		$policy = new AjaxRenderTargetPolicy();

		$this->assertTrue( $policy->isAllowed( $actionClass ) );
		$this->assertTrue( $policy->isAllowed( $actionClass::SLUG ) );
	}

	/**
	 * @dataProvider blockedRenderTargetProvider
	 */
	public function test_public_or_internal_render_targets_are_denied( string $classOrSlug ) :void {
		$this->assertFalse( ( new AjaxRenderTargetPolicy() )->isAllowed( $classOrSlug ) );
	}

	public function test_allowed_render_slugs_resolve_to_registered_render_actions() :void {
		$policy = new AjaxRenderTargetPolicy();

		foreach ( $policy->allowedRenderSlugs() as $slug ) {
			$this->assertNotSame( '', RenderActionTarget::resolve( $slug ), $slug );
		}
	}

	public static function allowedRenderTargetProvider() :array {
		return [
			[ Actions\Render\Components\Widgets\DashboardLiveMonitorTicker::class ],
			[ Actions\Render\Components\Traffic\TrafficLiveLogs::class ],
			[ Actions\Render\Components\Widgets\WpDashboardSummary::class ],
			[ Actions\Render\Components\Scans\ScansFileLockerDiff::class ],
			[ Actions\Render\Components\OffCanvas\IpAnalysis::class ],
			[ Actions\Render\Components\OffCanvas\ImportExportSitesAuthoriseUrls::class ],
			[ Actions\Render\Components\OffCanvas\IpRuleAddForm::class ],
			[ Actions\Render\Components\Rules\RuleBuilder::class ],
			[ Actions\Render\Components\Rules\RulesManager::class ],
			[ Actions\Render\Components\SuperSearchResults::class ],
			[ Actions\Render\Components\OffCanvas\SearchHelp::class ],
			[ Actions\Render\Components\OffCanvas\ZoneComponentConfig::class ],
			[ Actions\Render\Components\OffCanvas\FormReportCreate::class ],
			[ Actions\Render\Components\UserMfa\ConfigForm::class ],
			[ Actions\Render\PluginAdminPages\ActionsQueueDrillDownGroups::class ],
			[ Actions\Render\PluginAdminPages\ActionsQueueAssetFileStatusDetail::class ],
			[ Actions\Render\Components\Scans\Results\Wordpress::class ],
			[ Actions\Render\Components\Scans\Results\Plugins::class ],
			[ Actions\Render\Components\Scans\Results\Themes::class ],
			[ Actions\Render\Components\Scans\Results\Vulnerabilities::class ],
			[ Actions\Render\Components\Scans\Results\Malware::class ],
			[ Actions\Render\Components\Scans\Results\FileLocker::class ],
			[ Actions\Render\Components\Scans\Results\CloakedPlugins::class ],
			[ Actions\Render\Components\Scans\Results\Maintenance::class ],
			[ Actions\Render\PluginAdminPages\ConfigureDrillDownDiagnosis::class ],
			[ Actions\Render\PluginAdminPages\ConfigureSearchResults::class ],
			[ Actions\Render\PluginAdminPages\InvestigateByUserPanelBody::class ],
			[ Actions\Render\PluginAdminPages\InvestigateByIpPanelBody::class ],
			[ Actions\Render\PluginAdminPages\InvestigateByPluginPanelBody::class ],
			[ Actions\Render\PluginAdminPages\InvestigateByThemePanelBody::class ],
			[ Actions\Render\PluginAdminPages\InvestigateByCorePanelBody::class ],
			[ Actions\Render\PluginAdminPages\TrafficLogLivePanelBody::class ],
			[ Actions\Render\Components\Scans\ItemAnalysis\Container::class ],
		];
	}

	public static function blockedRenderTargetProvider() :array {
		return [
			[ Actions\Render\FullPage\Report\SecurityReport::SLUG ],
			[ Actions\Render\Components\Reports\Components\ReportAreaChanges::SLUG ],
			[ Actions\Render\FullPage\Block\BlockFirewall::SLUG ],
			[ Actions\Render\FullPage\Mfa\Components\LoginIntentFormShield::SLUG ],
			[ Actions\Render\Components\Scans\ScansProgress::SLUG ],
			[ Actions\Render\Components\RenderPluginBadge::SLUG ],
			[ Actions\Render\Components\PrivacyPolicy::SLUG ],
			[ Actions\Render\Components\ToastPlaceholder::SLUG ],
			[ Actions\PluginImportExport_UpdateNotified::SLUG ],
		];
	}
}
