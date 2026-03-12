<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

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

		foreach ( $this->getDefinitions() as $definition ) {
			foreach ( $this->buildRowsForDefinition( $definition ) as $row ) {
				$rowsByZone[ $definition[ 'zone' ] ][] = $row;
			}
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

		if ( empty( $component[ 'is_applicable' ] ) ) {
			return null;
		}

		$status = (bool)( $component[ 'is_protected' ] ?? false )
			? 'good'
			: ( !empty( $component[ 'is_critical' ] ) ? 'critical' : 'warning' );

		return [
			'key'               => $key,
			'label'             => (string)( $component[ 'title' ] ?? '' ),
			'description'       => (string)( ( $component[ 'is_protected' ] ?? false )
				? ( $component[ 'desc_protected' ] ?? '' )
				: ( $component[ 'desc_unprotected' ] ?? '' ) ),
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
}
