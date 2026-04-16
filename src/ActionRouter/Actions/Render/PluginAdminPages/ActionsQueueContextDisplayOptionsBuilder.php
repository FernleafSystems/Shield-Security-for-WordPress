<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\ScanResultsDisplayFormSubmit
};

/**
 * @phpstan-import-type OperatorChromeDisplayOptionsInput from OperatorChromeContract
 */
class ActionsQueueContextDisplayOptionsBuilder {

	private ActionsQueueScanResultsOptions $queueScanResultsOptions;

	public function __construct( ?ActionsQueueScanResultsOptions $queueScanResultsOptions = null ) {
		$this->queueScanResultsOptions = $queueScanResultsOptions ?? new ActionsQueueScanResultsOptions();
	}

	/**
	 * @param array<string,mixed> $renderActionData
	 * @return OperatorChromeDisplayOptionsInput
	 */
	public function buildForGroup(
		string $definitionKey,
		string $detailShell,
		array $renderActionData
	) :array {
		if ( !$this->supportsGroup( $definitionKey, $detailShell, $renderActionData ) ) {
			return [];
		}

		$options = $this->queueScanResultsOptions->currentOptionsFromActionData( $renderActionData );

		return [
			'title'       => __( 'Display Results', 'wp-simple-firewall' ),
			'action_json' => OperatorChromeContract::encodeJson(
				ActionData::Build(
					ScanResultsDisplayFormSubmit::class,
					true,
					$this->queueScanResultsOptions->buildDisplayContextActionData()
				)
			),
			'controls'    => [
				$this->buildControl(
					'include_ignored',
					__( 'Include Ignored Results', 'wp-simple-firewall' ),
					$options[ 'include_ignored' ],
					$options[ 'ignored_only' ]
				),
				$this->buildControl(
					'include_repaired',
					__( 'Include Repaired Results', 'wp-simple-firewall' ),
					$options[ 'include_repaired' ]
				),
				$this->buildControl(
					'include_deleted',
					__( 'Include Deleted Results', 'wp-simple-firewall' ),
					$options[ 'include_deleted' ]
				),
			],
		];
	}

	/**
	 * @return array{name:string,label:string,checked:bool,disabled:bool}
	 */
	private function buildControl( string $name, string $label, bool $checked, bool $disabled = false ) :array {
		return [
			'name'     => $name,
			'label'    => $label,
			'checked'  => $checked,
			'disabled' => $disabled,
		];
	}

	/**
	 * @param array<string,mixed> $renderActionData
	 */
	private function supportsGroup( string $definitionKey, string $detailShell, array $renderActionData ) :bool {
		if ( \in_array( $definitionKey, [ 'wordpress', 'malware' ], true ) ) {
			return $detailShell === 'direct_table';
		}

		if ( !\in_array( $definitionKey, [ 'plugins', 'themes' ], true ) ) {
			return false;
		}

		if ( $detailShell === 'direct_table' ) {
			return true;
		}

		$explicitOptions = $this->queueScanResultsOptions->explicitOptionsFromActionData( $renderActionData );
		return $detailShell === 'asset_cards'
			&& \is_array( $explicitOptions )
			&& !empty( $explicitOptions[ 'ignored_only' ] );
	}
}
