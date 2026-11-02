<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\ScanResultsScopeResolver;

/**
 * @phpstan-import-type OperatorChromeActionInput from OperatorChromeContract
 */
class ActionsQueueContextActionsBuilder {

	private ScanResultsDisplayOptions $queueScanResultsOptions;
	private ActionsQueueScanResultScopeResolver $scopeResolver;
	private PluginReinstallContextActionBuilder $pluginReinstallActionBuilder;
	private ThemeReinstallContextActionBuilder $themeReinstallActionBuilder;

	public function __construct(
		?ScanResultsDisplayOptions $queueScanResultsOptions = null,
		?ActionsQueueScanResultScopeResolver $scopeResolver = null,
		?PluginReinstallContextActionBuilder $pluginReinstallActionBuilder = null,
		?ThemeReinstallContextActionBuilder $themeReinstallActionBuilder = null
	) {
		$this->queueScanResultsOptions = $queueScanResultsOptions ?? new ScanResultsDisplayOptions();
		$this->scopeResolver = $scopeResolver ?? new ActionsQueueScanResultScopeResolver();
		$this->pluginReinstallActionBuilder = $pluginReinstallActionBuilder ?? new PluginReinstallContextActionBuilder();
		$this->themeReinstallActionBuilder = $themeReinstallActionBuilder ?? new ThemeReinstallContextActionBuilder();
	}

	/**
	 * @param array<string,mixed> $renderActionData
	 * @return list<OperatorChromeActionInput>
	 */
	public function buildForGroup(
		string $definitionKey,
		string $label,
		string $detailShell,
		int $itemCount,
		array $renderActionData
	) :array {
		if ( $detailShell !== 'direct_table' || $itemCount < 1 ) {
			return [];
		}

		$explicitOptions = $this->queueScanResultsOptions->explicitOptionsFromActionData( $renderActionData );
		if ( $explicitOptions !== null && $explicitOptions[ 'ignored_only' ] ) {
			return [];
		}

		$scope = $this->scopeResolver->resolveForGroup( $definitionKey, $renderActionData );
		if ( $scope === [] ) {
			return [];
		}

		// Ignoring all results is in the results table's own actions column, beside its other actions; what's
		// left here acts on the whole plugin or theme.
		$actions = [];
		if ( $definitionKey === 'plugins' && $scope[ 'type' ] === ScanResultsScopeResolver::SCOPE_TYPE_PLUGIN ) {
			$actions = \array_merge(
				$actions,
				$this->pluginReinstallActionBuilder->buildForPluginFile( $scope[ 'file' ], $label )
			);
		}
		if ( $definitionKey === 'themes' && $scope[ 'type' ] === ScanResultsScopeResolver::SCOPE_TYPE_THEME ) {
			$actions = \array_merge(
				$actions,
				$this->themeReinstallActionBuilder->buildForThemeStylesheet( $scope[ 'file' ], $label )
			);
		}

		return $actions;
	}
}
