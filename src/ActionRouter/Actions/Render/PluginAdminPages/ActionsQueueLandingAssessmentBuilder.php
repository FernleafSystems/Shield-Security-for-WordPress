<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Components;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ActionsQueueLandingAssessmentBuilder {

	use PluginControllerConsumer;

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
			if ( !$this->isAvailableForStrategy( $definition[ 'availability_strategy' ] ) ) {
				continue;
			}

			$row = $this->buildAssessmentRow( $definition[ 'key' ], $definition[ 'component_class' ] );
			if ( $row === null ) {
				continue;
			}

			$rowsByZone[ $definition[ 'zone' ] ][] = $row;
		}

		return $rowsByZone;
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
			case 'scan_afs_plugins_and_themes_enabled':
				return $scansCon->AFS()->isScanEnabledPlugins() && $scansCon->AFS()->isScanEnabledThemes();
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
			'status_label'      => $this->statusLabel( $status ),
			'status_icon_class' => $this->statusIconClass( $status ),
		];
	}

	/**
	 * @param class-string<\FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Base> $componentClass
	 * @return array<string,mixed>
	 */
	protected function buildAssessmentComponent( string $componentClass ) :array {
		return ( new Components() )->buildComponent(
			$componentClass,
			\FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Base::CHANNEL_ACTION
		);
	}

	protected function statusLabel( string $status ) :string {
		switch ( $status ) {
			case 'critical':
				return __( 'Critical', 'wp-simple-firewall' );
			case 'warning':
				return __( 'Warning', 'wp-simple-firewall' );
			default:
				return __( 'Good', 'wp-simple-firewall' );
		}
	}

	protected function statusIconClass( string $status ) :string {
		switch ( $status ) {
			case 'critical':
				$icon = 'x-circle-fill';
				break;
			case 'warning':
				$icon = 'exclamation-circle-fill';
				break;
			default:
				$icon = 'check-circle-fill';
				break;
		}
		return self::con()->svgs->iconClass( $icon );
	}
}
