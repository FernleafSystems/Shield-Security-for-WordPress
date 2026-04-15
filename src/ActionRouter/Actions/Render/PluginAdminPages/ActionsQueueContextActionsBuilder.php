<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\ScanResultsTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\ScanResultsScopeResolver;

/**
 * @phpstan-import-type OperatorChromeActionInput from OperatorChromeContract
 */
class ActionsQueueContextActionsBuilder {

	private ActionsQueueScanResultsOptions $queueScanResultsOptions;
	private ScanResultsScopeResolver $scopeResolver;
	private PluginReinstallContextActionBuilder $pluginReinstallActionBuilder;

	public function __construct(
		?ActionsQueueScanResultsOptions $queueScanResultsOptions = null,
		?ScanResultsScopeResolver $scopeResolver = null,
		?PluginReinstallContextActionBuilder $pluginReinstallActionBuilder = null
	) {
		$this->queueScanResultsOptions = $queueScanResultsOptions ?? new ActionsQueueScanResultsOptions();
		$this->scopeResolver = $scopeResolver ?? new ScanResultsScopeResolver();
		$this->pluginReinstallActionBuilder = $pluginReinstallActionBuilder ?? new PluginReinstallContextActionBuilder();
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

		$scope = $this->determineScopeForGroup( $definitionKey, $renderActionData );
		if ( $scope === [] ) {
			return [];
		}

		$actions = [
			[
				'kind'             => 'ajax',
				'label'            => __( 'Ignore All Results', 'wp-simple-firewall' ),
				'type'             => 'deactivate',
				'icon_class'       => 'bi bi-eye-slash-fill',
				'ajax_action_json' => OperatorChromeContract::encodeJson(
					ActionData::Build( ScanResultsTableAction::class, true, \array_merge(
						$scope,
						$this->queueScanResultsOptions->buildExplicitActionData(
							$this->queueScanResultsOptions->activeOnly()
						),
						[
							'sub_action' => 'ignore_all',
						]
					) )
				),
				'confirm_text'     => $this->buildConfirmText( $definitionKey, $label ),
			],
		];

		if ( $definitionKey === 'plugins' && $scope[ 'type' ] === 'plugin' ) {
			$actions = \array_merge(
				$actions,
				$this->pluginReinstallActionBuilder->buildForPluginFile( $scope[ 'file' ], $label )
			);
		}

		return $actions;
	}

	/**
	 * @param array<string,mixed> $renderActionData
	 * @return array{type:string,file:string}|array{}
	 */
	private function determineScopeForGroup( string $definitionKey, array $renderActionData ) :array {
		switch ( $definitionKey ) {
			case 'wordpress':
				return $this->scopeResolver->normalizeActionScope( 'wordpress', 'wordpress' );
			case 'malware':
				return $this->scopeResolver->normalizeActionScope( 'malware', 'malware' );
			case 'plugins':
			case 'themes':
				$subjectType = \trim( (string)( $renderActionData[ 'subject_type' ] ?? '' ) );
				$subjectId = \trim( (string)( $renderActionData[ 'subject_id' ] ?? '' ) );
				return $subjectType !== '' && $subjectId !== ''
					? $this->scopeResolver->canonicalActionDataForSubject( $subjectType, $subjectId )
					: [];
			default:
				return [];
		}
	}

	private function buildConfirmText( string $definitionKey, string $label ) :string {
		switch ( $definitionKey ) {
			case 'wordpress':
				return __( 'Ignore all active WordPress core file results?', 'wp-simple-firewall' );
			case 'malware':
				return __( 'Ignore all active malware results?', 'wp-simple-firewall' );
			case 'plugins':
				return \sprintf(
					__( 'Ignore all active results for %s?', 'wp-simple-firewall' ),
					$label
				);
			case 'themes':
				return \sprintf(
					__( 'Ignore all active results for %s?', 'wp-simple-firewall' ),
					$label
				);
			default:
				return __( 'Ignore all active results for this view?', 'wp-simple-firewall' );
		}
	}
}
