<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Base as MeterComponentBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ActionsQueueLandingAssessmentBuilder {

	use PluginControllerConsumer;
	use StandardStatusMapping;

	/**
	 * @return array<string,list<array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 * }>>
	 */
	public function build() :array {
		$rowsByZone = [];

		$scans = $this->buildForZone( 'scans' );
		if ( !empty( $scans ) ) {
			$rowsByZone[ 'scans' ] = $scans;
		}

		$maintenance = $this->buildForZone( 'maintenance' );
		if ( !empty( $maintenance ) ) {
			$rowsByZone[ 'maintenance' ] = $maintenance;
		}

		return $rowsByZone;
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 * }>
	 */
	public function buildForZone( string $zone ) :array {
		if ( $zone === 'maintenance' ) {
			return $this->buildMaintenanceRows();
		}

		$rows = [];

		foreach ( $this->getDefinitions() as $definition ) {
			if ( $definition[ 'zone' ] !== $zone ) {
				continue;
			}

			foreach ( $this->buildRowsForDefinition( $definition ) as $row ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 * }>
	 */
	private function buildMaintenanceRows() :array {
		$rows = [];

		foreach ( $this->buildMaintenanceIssueStateProvider()->buildStates() as $state ) {
			$rows[] = [
				'key'               => $state[ 'key' ],
				'label'             => $state[ 'label' ],
				'description'       => $state[ 'description' ],
				'status'            => $state[ 'severity' ],
				'status_label'      => $this->standardStatusLabel( $state[ 'severity' ] ),
				'status_icon_class' => $this->standardStatusIconClass( $state[ 'severity' ] ),
			];
		}

		return $rows;
	}

	/**
	 * @param array{
	 *   key:string,
	 *   zone:string,
	 *   component_class:class-string<\FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Base>,
	 *   availability_strategy:string
	 * } $definition
	 * @return list<array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 * }>
	 */
	private function buildRowsForDefinition( array $definition ) :array {
		if ( !$this->isAvailableForStrategy( $definition[ 'availability_strategy' ] ) ) {
			return [];
		}

		$row = $this->buildAssessmentRow( $definition[ 'key' ], $definition[ 'component_class' ] );
		return $row === null ? [] : [ $row ];
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   zone:string,
	 *   component_class:class-string<\FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Base>,
	 *   availability_strategy:string
	 * }>
	 */
	protected function getDefinitions() :array {
		return PluginNavs::actionsLandingAssessmentDefinitions();
	}

	protected function isAvailableForStrategy( string $strategy ) :bool {
		$scansCon = self::con()->comps->scans;

		switch ( $strategy ) {
			case 'always':
				return true;
			case 'scan_afs_core_enabled':
				return $scansCon->AFS()->isScanEnabledWpCore();
			case 'scan_afs_plugins_enabled':
				return $scansCon->AFS()->isScanEnabledPlugins();
			case 'scan_afs_themes_enabled':
				return $scansCon->AFS()->isScanEnabledThemes();
			case 'scan_malware_enabled':
				return $scansCon->AFS()->isEnabledMalwareScanPHP();
			case 'scan_wpv_enabled':
				return $scansCon->WPV()->isEnabled();
			case 'scan_apc_enabled':
				return $scansCon->APC()->isEnabled();
			default:
				return false;
		}
	}

	/**
	 * @param class-string<\FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Base> $componentClass
	 * @return array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 * }|null
	 */
	protected function buildAssessmentRow( string $key, string $componentClass ) :?array {
		$component = $this->buildAssessmentComponent( $componentClass );

		if ( !$component[ 'is_applicable' ] ) {
			return null;
		}

		$status = $component[ 'is_protected' ]
			? 'good'
			: ( $component[ 'is_critical' ] ? 'critical' : 'warning' );

		return [
			'key'               => $key,
			'label'             => $component[ 'title' ],
			'description'       => $component[ 'is_protected' ]
				? $component[ 'desc_protected' ]
				: $component[ 'desc_unprotected' ],
			'status'            => $status,
			'status_label'      => $this->standardStatusLabel( $status ),
			'status_icon_class' => $this->standardStatusIconClass( $status ),
		];
	}

	/**
	 * @param class-string<\FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Base> $componentClass
	 * @return array<string,mixed>
	 */
	protected function buildAssessmentComponent( string $componentClass ) :array {
		return ( new $componentClass() )->build( MeterComponentBase::CHANNEL_ACTION );
	}

	protected function buildMaintenanceIssueStateProvider() :MaintenanceIssueStateProvider {
		return new MaintenanceIssueStateProvider();
	}
}
