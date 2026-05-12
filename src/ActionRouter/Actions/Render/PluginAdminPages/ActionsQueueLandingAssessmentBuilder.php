<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\ActionsQueueItemIcons;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Base as MeterComponentBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-type AssessmentRow array{
 *   key:string,
 *   label:string,
 *   description:string,
 *   drill_bucket:'critical'|'review',
 *   item_icon_class:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string
 * }
 * @phpstan-type AssessmentRowsByZone array{
 *   scans:list<AssessmentRow>,
 *   maintenance:list<AssessmentRow>
 * }
 */
class ActionsQueueLandingAssessmentBuilder {

	use PluginControllerConsumer;
	use StandardStatusMapping;

	private ?ActionsQueueItemIcons $itemIcons = null;
	private ?ScansResultsRailTabAvailability $scanRailTabAvailability = null;

	/**
	 * @return AssessmentRowsByZone
	 */
	public function build() :array {
		return [
			'scans'       => $this->buildForZone( 'scans' ),
			'maintenance' => $this->buildForZone( 'maintenance' ),
		];
	}

	/**
	 * @return list<AssessmentRow>
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
	 * @return list<AssessmentRow>
	 */
	private function buildMaintenanceRows() :array {
		$rows = [];

		foreach ( $this->buildMaintenanceIssueStateProvider()->buildStates() as $state ) {
			$rows[] = [
				'key'               => $state[ 'key' ],
				'label'             => $state[ 'label' ],
				'description'       => $state[ 'description' ],
				'drill_bucket'      => $state[ 'drill_bucket' ],
				'item_icon_class'   => $this->itemIcons()->iconClassForKey( $state[ 'key' ] ),
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
	 *   availability_strategy:string,
	 *   drill_bucket:'critical'|'review'
	 * } $definition
	 * @return list<AssessmentRow>
	 */
	private function buildRowsForDefinition( array $definition ) :array {
		if ( !$this->isAvailableForStrategy( $definition[ 'availability_strategy' ] ) ) {
			return [];
		}

		$row = $this->buildAssessmentRow(
			$definition[ 'key' ],
			$definition[ 'component_class' ],
			$definition[ 'drill_bucket' ]
		);
		return $row === null ? [] : [ $row ];
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   zone:string,
	 *   component_class:class-string<\FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Base>,
	 *   availability_strategy:string,
	 *   drill_bucket:'critical'|'review'
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
			case 'scan_file_locker_enabled':
				return $this->scanRailTabAvailability()->build( 'file_locker' )[ 'is_available' ];
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
	 *   drill_bucket:'critical'|'review',
	 *   item_icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 * }|null
	 */
	protected function buildAssessmentRow( string $key, string $componentClass, string $drillBucket ) :?array {
		$component = $this->buildAssessmentComponent( $componentClass );

		if ( !$component[ 'is_applicable' ] ) {
			return null;
		}

		$status = $component[ 'is_protected' ]
			? 'good'
			: ( $drillBucket === 'critical' ? 'critical' : 'warning' );
		$text = $this->normalizedAssessmentText( $key, $component );

		return [
			'key'               => $key,
			'label'             => $text[ 'label' ],
			'description'       => $text[ 'description' ],
			'drill_bucket'      => $drillBucket,
			'item_icon_class'   => $this->itemIcons()->iconClassForKey( $key ),
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

	/**
	 * @param array<string,mixed> $component
	 * @return array{label:string,description:string}
	 */
	private function normalizedAssessmentText( string $key, array $component ) :array {
		if ( $key === 'abandoned' ) {
			return [
				'label'       => __( 'Abandoned Assets', 'wp-simple-firewall' ),
				'description' => (bool)( $component[ 'is_protected' ] ?? false )
					? __( "There doesn't appear to be any abandoned assets on your site.", 'wp-simple-firewall' )
					: __( 'There appear to be abandoned assets installed on your site.', 'wp-simple-firewall' ),
			];
		}

		return [
			'label'       => (string)( $component[ 'title' ] ?? '' ),
			'description' => (string)(
				( $component[ 'is_protected' ] ?? false )
					? ( $component[ 'desc_protected' ] ?? '' )
					: ( $component[ 'desc_unprotected' ] ?? '' )
			),
		];
	}

	protected function buildMaintenanceIssueStateProvider() :MaintenanceIssueStateProvider {
		return new MaintenanceIssueStateProvider();
	}

	private function itemIcons() :ActionsQueueItemIcons {
		if ( $this->itemIcons === null ) {
			$this->itemIcons = new ActionsQueueItemIcons();
		}

		return $this->itemIcons;
	}

	private function scanRailTabAvailability() :ScansResultsRailTabAvailability {
		if ( $this->scanRailTabAvailability === null ) {
			$this->scanRailTabAvailability = new ScansResultsRailTabAvailability();
		}

		return $this->scanRailTabAvailability;
	}
}
