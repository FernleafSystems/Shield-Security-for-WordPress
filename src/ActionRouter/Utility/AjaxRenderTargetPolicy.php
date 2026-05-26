<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class AjaxRenderTargetPolicy {

	/**
	 * @var list<class-string<Actions\Render\BaseRender>>
	 */
	private const ALLOWED_RENDER_TARGETS = [
		Actions\Render\Components\Widgets\DashboardLiveMonitorTicker::class,
		Actions\Render\Components\Traffic\TrafficLiveLogs::class,
		Actions\Render\Components\Widgets\WpDashboardSummary::class,
		Actions\Render\Components\Scans\ScansFileLockerDiff::class,
		Actions\Render\Components\OffCanvas\IpAnalysis::class,
		Actions\Render\Components\OffCanvas\IpRuleAddForm::class,
		Actions\Render\Components\Rules\RuleBuilder::class,
		Actions\Render\Components\Rules\RulesManager::class,
		Actions\Render\Components\SuperSearchResults::class,
		Actions\Render\Components\OffCanvas\SearchHelp::class,
		Actions\Render\Components\OffCanvas\ZoneComponentConfig::class,
		Actions\Render\Components\OffCanvas\FormReportCreate::class,
		Actions\Render\Components\UserMfa\ConfigForm::class,
		Actions\Render\PluginAdminPages\ActionsQueueDrillDownGroups::class,
		Actions\Render\PluginAdminPages\ActionsQueueAssetFileStatusDetail::class,
		Actions\Render\Components\Scans\Results\Wordpress::class,
		Actions\Render\Components\Scans\Results\Plugins::class,
		Actions\Render\Components\Scans\Results\Themes::class,
		Actions\Render\Components\Scans\Results\Vulnerabilities::class,
		Actions\Render\Components\Scans\Results\Malware::class,
		Actions\Render\Components\Scans\Results\FileLocker::class,
		Actions\Render\Components\Scans\Results\Maintenance::class,
		Actions\Render\PluginAdminPages\ConfigureDrillDownDiagnosis::class,
		Actions\Render\PluginAdminPages\ConfigureSearchResults::class,
		Actions\Render\PluginAdminPages\InvestigateByUserPanelBody::class,
		Actions\Render\PluginAdminPages\InvestigateByIpPanelBody::class,
		Actions\Render\PluginAdminPages\InvestigateByPluginPanelBody::class,
		Actions\Render\PluginAdminPages\InvestigateByThemePanelBody::class,
		Actions\Render\PluginAdminPages\InvestigateByCorePanelBody::class,
		Actions\Render\PluginAdminPages\TrafficLogLivePanelBody::class,
		Actions\Render\Components\Scans\ItemAnalysis\Container::class,
	];

	public function isAllowed( string $classOrSlug ) :bool {
		$renderAction = RenderActionTarget::resolve( $classOrSlug );

		return $renderAction !== ''
			   && \in_array( $renderAction::SLUG, $this->allowedRenderSlugs(), true );
	}

	/**
	 * @return list<string>
	 */
	public function allowedRenderSlugs() :array {
		return \array_values( \array_unique( \array_map(
			static fn( string $actionClass ) :string => $actionClass::SLUG,
			self::ALLOWED_RENDER_TARGETS
		) ) );
	}
}
