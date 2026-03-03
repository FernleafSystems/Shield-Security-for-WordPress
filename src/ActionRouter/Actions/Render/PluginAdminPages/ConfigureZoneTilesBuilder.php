<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\SecurityZonesCon;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

class ConfigureZoneTilesBuilder {

	use PluginControllerConsumer;

	/**
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   stat_line:string,
	 *   settings_href:string,
	 *   settings_label:string,
	 *   panel:array{
	 *     title:string,
	 *     status:string,
	 *     status_label:string,
	 *     components:list<array{
	 *       title:string,
	 *       status:string,
	 *       status_label:string,
	 *       note:string
	 *     }>
	 *   }
	 * }>
	 */
	public function build() :array {
		return \array_map(
			fn( array $definition ) :array => $this->buildTileFromDefinition( $definition ),
			$this->getTileDefinitions()
		);
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   label:string,
	 *   icon:string,
	 *   zone_slug?:string,
	 *   component_slug?:string
	 * }>
	 */
	private function getTileDefinitions() :array {
		return [
			[
				'key'      => 'secadmin',
				'label'    => __( 'Security Admin', 'wp-simple-firewall' ),
				'icon'     => 'shield-lock',
				'zone_slug' => Zone\Secadmin::Slug(),
			],
			[
				'key'      => 'login',
				'label'    => __( 'Login Protection', 'wp-simple-firewall' ),
				'icon'     => 'person-lock',
				'zone_slug' => Zone\Login::Slug(),
			],
			[
				'key'      => 'firewall',
				'label'    => __( 'Firewall', 'wp-simple-firewall' ),
				'icon'     => 'fire',
				'zone_slug' => Zone\Firewall::Slug(),
			],
			[
				'key'      => 'ips',
				'label'    => __( 'Bots and IPs', 'wp-simple-firewall' ),
				'icon'     => 'robot',
				'zone_slug' => Zone\Ips::Slug(),
			],
			[
				'key'      => 'scans',
				'label'    => __( 'HackGuard', 'wp-simple-firewall' ),
				'icon'     => 'bug',
				'zone_slug' => Zone\Scans::Slug(),
			],
			[
				'key'      => 'spam',
				'label'    => __( 'Comments Filter', 'wp-simple-firewall' ),
				'icon'     => 'chat-dots',
				'zone_slug' => Zone\Spam::Slug(),
			],
			[
				'key'            => 'audit_trail',
				'label'          => __( 'Audit Trail', 'wp-simple-firewall' ),
				'icon'           => 'journal-text',
				'component_slug' => Component\ActivityLogging::Slug(),
			],
			[
				'key'            => 'traffic_monitor',
				'label'          => __( 'Traffic Monitor', 'wp-simple-firewall' ),
				'icon'           => 'graph-up',
				'component_slug' => Component\RequestLogging::Slug(),
			],
		];
	}

	/**
	 * @param array{
	 *   key:string,
	 *   label:string,
	 *   icon:string,
	 *   zone_slug?:string,
	 *   component_slug?:string
	 * } $definition
	 * @return array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   stat_line:string,
	 *   settings_href:string,
	 *   settings_label:string,
	 *   panel:array{
	 *     title:string,
	 *     status:string,
	 *     status_label:string,
	 *     components:list<array{
	 *       title:string,
	 *       status:string,
	 *       status_label:string,
	 *       note:string
	 *     }>
	 *   }
	 * }
	 */
	private function buildTileFromDefinition( array $definition ) :array {
		$components = $this->buildComponentContracts( $definition );
		$status = $this->aggregateTileStatus( $components );

		return [
			'key'            => $definition[ 'key' ],
			'panel_target'   => $definition[ 'key' ],
			'is_enabled'     => true,
			'is_disabled'    => false,
			'label'          => $definition[ 'label' ],
			'icon_class'     => self::con()->svgs->iconClass( $definition[ 'icon' ] ),
			'status'         => $status,
			'status_label'   => $this->tileStatusLabel( $status ),
			'stat_line'      => $this->buildTileStatLine( $components ),
			'settings_href'  => $this->buildSettingsHref( $definition ),
			'settings_label' => sprintf( __( 'Configure %s Settings', 'wp-simple-firewall' ), $definition[ 'label' ] ),
			'panel'          => [
				'title'        => $definition[ 'label' ],
				'status'       => $status,
				'status_label' => $this->tileStatusLabel( $status ),
				'components'   => $components,
			],
		];
	}

	/**
	 * @param array{
	 *   key:string,
	 *   label:string,
	 *   icon:string,
	 *   zone_slug?:string,
	 *   component_slug?:string
	 * } $definition
	 */
	private function buildSettingsHref( array $definition ) :string {
		if ( !empty( $definition[ 'zone_slug' ] ) ) {
			return self::con()->plugin_urls->zone( $definition[ 'zone_slug' ] );
		}
		return self::con()->plugin_urls->cfgForZoneComponent( $definition[ 'component_slug' ] );
	}

	/**
	 * @param array{
	 *   key:string,
	 *   label:string,
	 *   icon:string,
	 *   zone_slug?:string,
	 *   component_slug?:string
	 * } $definition
	 * @return list<array{
	 *   title:string,
	 *   status:string,
	 *   status_label:string,
	 *   note:string
	 * }>
	 */
	private function buildComponentContracts( array $definition ) :array {
		$components = [];
		if ( !empty( $definition[ 'zone_slug' ] ) ) {
			$zonesCon = $this->zonesCon();
			$zone = $zonesCon->getZone( $definition[ 'zone_slug' ] );
			$components = $zonesCon->getComponentsForZone( $zone );
		}
		elseif ( !empty( $definition[ 'component_slug' ] ) ) {
			$components[] = $this->zonesCon()->getZoneComponent( $definition[ 'component_slug' ] );
		}

		return \array_map(
			fn( Component\Base $component ) :array => $this->buildSingleComponentContract( $component ),
			$components
		);
	}

	/**
	 * @return array{
	 *   title:string,
	 *   status:string,
	 *   status_label:string,
	 *   note:string
	 * }
	 */
	private function buildSingleComponentContract( Component\Base $component ) :array {
		$status = $this->componentStatusToSeverity( $component->enabledStatus() );
		return [
			'title'        => $component->title(),
			'status'       => $status,
			'status_label' => $this->componentStatusLabel( $status ),
			'note'         => $this->componentNote( $component ),
		];
	}

	private function componentNote( Component\Base $component ) :string {
		$explanations = $component->explanation();
		$first = \is_array( $explanations ) ? (string)\current( $explanations ) : '';
		return !empty( $first ) ? $first : $component->subtitle();
	}

	/**
	 * @param list<array{title:string,status:string,status_label:string,note:string}> $components
	 */
	private function aggregateTileStatus( array $components ) :string {
		$status = 'good';
		foreach ( $components as $component ) {
			if ( $this->severityRank( $component[ 'status' ] ) > $this->severityRank( $status ) ) {
				$status = $component[ 'status' ];
			}
		}
		return $status;
	}

	/**
	 * @param list<array{title:string,status:string,status_label:string,note:string}> $components
	 */
	private function buildTileStatLine( array $components ) :string {
		$criticalCount = 0;
		$warningCount = 0;
		foreach ( $components as $component ) {
			if ( $component[ 'status' ] === 'critical' ) {
				$criticalCount++;
			}
			elseif ( $component[ 'status' ] === 'warning' ) {
				$warningCount++;
			}
		}

		if ( $criticalCount > 0 && $warningCount > 0 ) {
			return sprintf(
				__( '%1$s critical, %2$s need work', 'wp-simple-firewall' ),
				$criticalCount,
				$warningCount
			);
		}
		if ( $criticalCount > 0 ) {
			return sprintf(
				_n( '%s critical component', '%s critical components', $criticalCount, 'wp-simple-firewall' ),
				$criticalCount
			);
		}
		if ( $warningCount > 0 ) {
			return sprintf(
				_n( '%s component needs work', '%s components need work', $warningCount, 'wp-simple-firewall' ),
				$warningCount
			);
		}
		return __( 'All components healthy', 'wp-simple-firewall' );
	}

	private function tileStatusLabel( string $status ) :string {
		switch ( $status ) {
			case 'critical':
				return __( 'Critical', 'wp-simple-firewall' );
			case 'warning':
				return __( 'Needs Work', 'wp-simple-firewall' );
			case 'good':
			default:
				return __( 'Good', 'wp-simple-firewall' );
		}
	}

	private function componentStatusLabel( string $status ) :string {
		switch ( $status ) {
			case 'critical':
				return __( 'Issue', 'wp-simple-firewall' );
			case 'warning':
				return __( 'Needs Work', 'wp-simple-firewall' );
			case 'good':
			default:
				return __( 'Active', 'wp-simple-firewall' );
		}
	}

	private function componentStatusToSeverity( string $componentStatus ) :string {
		switch ( $componentStatus ) {
			case EnumEnabledStatus::BAD:
				return 'critical';
			case EnumEnabledStatus::OKAY:
			case EnumEnabledStatus::NEUTRAL:
				return 'warning';
			case EnumEnabledStatus::GOOD:
			case EnumEnabledStatus::NEUTRAL_ENABLED:
			default:
				return 'good';
		}
	}

	private function severityRank( string $status ) :int {
		switch ( $status ) {
			case 'critical':
				return 2;
			case 'warning':
				return 1;
			case 'good':
			default:
				return 0;
		}
	}

	private function zonesCon() :SecurityZonesCon {
		return self::con()->comps->zones;
	}
}

