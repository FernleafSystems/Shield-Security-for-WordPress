<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\SecurityZonesCon;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

class ConfigureZoneTilesBuilder {

	use PluginControllerConsumer;
	use StandardStatusMapping;

	/**
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   include_in_posture:bool,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string,
	 *   stat_line:string,
	 *   panel:array{
	 *     title:string,
	 *     status:string,
	 *     status_label:string,
	 *     components:list<array{
	 *       title:string,
	 *       status:string,
	 *       status_label:string,
	 *       status_icon_class:string,
	 *       note:string,
	 *       explanations:list<string>,
	 *       config_action:array<string,mixed>
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
	 *   component_slug?:string,
	 *   component_slugs?:list<string>,
	 *   include_in_posture?:bool,
	 *   force_neutral?:bool
	 * }>
	 */
	private function getTileDefinitions() :array {
		return PluginNavs::configureLandingTileDefinitions();
	}

	/**
	 * @param array{
	 *   key:string,
	 *   label:string,
	 *   icon:string,
	 *   zone_slug?:string,
	 *   component_slug?:string,
	 *   component_slugs?:list<string>,
	 *   include_in_posture?:bool,
	 *   force_neutral?:bool
	 * } $definition
	 * @return array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   include_in_posture:bool,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string,
	 *   stat_line:string,
	 *   panel:array{
	 *     title:string,
	 *     status:string,
	 *     status_label:string,
	 *     components:list<array{
	 *       title:string,
	 *       status:string,
	 *       status_label:string,
	 *       status_icon_class:string,
	 *       note:string,
	 *       explanations:list<string>,
	 *       config_action:array<string,mixed>
	 *     }>
	 *   }
	 * }
	 */
	private function buildTileFromDefinition( array $definition ) :array {
		$forceNeutral = !empty( $definition[ 'force_neutral' ] );
		$zone = $this->zoneForDefinition( $definition );
		$visibleComponents = $this->componentsForDefinition( $definition, $zone );
		$components = $this->buildComponentContracts( $zone, $visibleComponents, $forceNeutral );
		$status = $forceNeutral ? 'neutral' : $this->aggregateTileStatus( $components );
		$includeInPosture = !\array_key_exists( 'include_in_posture', $definition )
			|| (bool)$definition[ 'include_in_posture' ];

		return [
			'key'               => $definition[ 'key' ],
			'panel_target'      => $definition[ 'key' ],
			'is_enabled'        => true,
			'is_disabled'       => false,
			'include_in_posture' => $includeInPosture,
			'label'             => $definition[ 'label' ],
			'icon_class'        => self::con()->svgs->iconClass( $definition[ 'icon' ] ),
			'status'            => $status,
			'status_label'      => $this->tileStatusLabel( $status ),
			'status_icon_class' => $this->tileStatusIconClass( $status ),
			'stat_line'         => $forceNeutral
				? __( 'General settings', 'wp-simple-firewall' )
				: $this->buildTileStatLine( $components ),
			'panel'             => [
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
	 *   component_slug?:string,
	 *   component_slugs?:list<string>,
	 *   include_in_posture?:bool,
	 *   force_neutral?:bool
	 * } $definition
	 * @return list<array{
	 *   title:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string,
	 *   note:string,
	 *   explanations:list<string>,
	 *   config_action:array<string,mixed>
	 * }>
	 */
	private function buildComponentContracts( ?Zone\Base $zone, array $visibleComponents, bool $forceNeutral ) :array {
		$components = \array_map(
			fn( Component\Base $component ) :array => $this->buildSingleComponentContract( $component, $forceNeutral ),
			$visibleComponents
		);

		if ( !$forceNeutral ) {
			$generalSettings = $this->buildGeneralSettingsComponentContract( $zone, $visibleComponents );
			if ( !empty( $generalSettings ) ) {
				$components[] = $generalSettings;
			}
		}

		return $components;
	}

	/**
	 * @return array{
	 *   title:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string,
	 *   note:string,
	 *   explanations:list<string>,
	 *   config_action:array<string,mixed>
	 * }
	 */
	private function buildSingleComponentContract( Component\Base $component, bool $forceNeutral = false ) :array {
		$status = $forceNeutral ? 'neutral' : $this->componentStatusToSeverity( $component->enabledStatus() );
		$explanations = \array_values( \array_filter(
			\array_map(
				fn( $expl ) :string => \trim( (string)$expl ),
				$component->explanation()
			),
			fn( string $expl ) :bool => $expl !== ''
		) );

		return [
			'title'             => $component->title(),
			'status'            => $status,
			'status_label'      => $this->componentStatusLabel( $status ),
			'status_icon_class' => $this->componentStatusIconClass( $status ),
			'note'              => $this->componentNote( $component ),
			'explanations'      => $explanations,
			'config_action'     => $this->normalizeActionContract( $component->getActions()[ 'config' ] ?? null ),
		];
	}

	/**
	 * @param array{
	 *   key:string,
	 *   label:string,
	 *   icon:string,
	 *   zone_slug?:string,
	 *   component_slug?:string,
	 *   component_slugs?:list<string>,
	 *   include_in_posture?:bool,
	 *   force_neutral?:bool
	 * } $definition
	 * @return array{
	 *   title:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string,
	 *   note:string,
	 *   explanations:list<string>,
	 *   config_action:array<string,mixed>
	 * }|array{}
	 */
	private function buildGeneralSettingsComponentContract( ?Zone\Base $zone, array $visibleComponents ) :array {
		$scope = ( new ConfigureGeneralSettingsScopeResolver() )->resolve( $zone, $visibleComponents );
		if ( empty( $scope ) ) {
			return [];
		}

		return [
			'title'             => __( 'General settings', 'wp-simple-firewall' ),
			'status'            => 'neutral',
			'status_label'      => $this->componentStatusLabel( 'neutral' ),
			'status_icon_class' => $this->componentStatusIconClass( 'neutral' ),
			'note'              => __( 'Additional settings in this zone that are not covered by a dedicated callout.', 'wp-simple-firewall' ),
			'explanations'      => [],
			'config_action'     => $this->normalizeActionContract( [
				'title' => __( 'Edit Settings', 'wp-simple-firewall' ),
				'icon'  => self::con()->svgs->iconClass( 'gear' ),
				'data'  => [
					'zone_component_action' => ZoneComponentConfig::SLUG,
					'zone_component_slug'   => \implode( ',', $scope[ 'zone_component_slugs' ] ),
					'option_keys'           => \implode( ',', $scope[ 'option_keys' ] ),
				],
			] ),
		];
	}

	private function componentStatusIconClass( string $status ) :string {
		return $this->standardStatusIconClass( $status, 'exclamation-triangle-fill' );
	}

	private function componentNote( Component\Base $component ) :string {
		$subtitle = \trim( $component->subtitle() );
		if ( $subtitle !== '' ) {
			return $subtitle;
		}
		$explanations = $component->explanation();
		$first = \is_array( $explanations ) ? \trim( (string)\current( $explanations ) ) : '';
		return $first;
	}

	/**
	 * @param list<array{title:string,status:string,status_label:string,note:string}> $components
	 */
	private function aggregateTileStatus( array $components ) :string {
		return StatusPriority::highest( \array_column( $components, 'status' ), 'good' );
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
			case 'neutral':
				return __( 'General', 'wp-simple-firewall' );
			case 'good':
			default:
				return __( 'Good', 'wp-simple-firewall' );
		}
	}

	private function tileStatusIconClass( string $status ) :string {
		return $this->standardStatusIconClass( $status, 'exclamation-triangle-fill' );
	}

	private function componentStatusLabel( string $status ) :string {
		switch ( $status ) {
			case 'critical':
				return __( 'Issue', 'wp-simple-firewall' );
			case 'warning':
				return __( 'Needs Work', 'wp-simple-firewall' );
			case 'neutral':
				return __( 'General', 'wp-simple-firewall' );
			case 'good':
			default:
				return __( 'Active', 'wp-simple-firewall' );
		}
	}

	private function componentStatusToSeverity( string $componentStatus ) :string {
		return EnumEnabledStatus::toSeverity( $componentStatus, 'good' );
	}

	/**
	 * @param array{
	 *   key:string,
	 *   label:string,
	 *   icon:string,
	 *   zone_slug?:string,
	 *   component_slug?:string,
	 *   component_slugs?:list<string>,
	 *   include_in_posture?:bool,
	 *   force_neutral?:bool
	 * } $definition
	 * @return Component\Base[]
	 */
	private function componentsForDefinition( array $definition, ?Zone\Base $zone = null ) :array {
		if ( $zone !== null ) {
			return $this->zonesCon()->getComponentsForZone( $zone );
		}

		$components = [];
		foreach ( $this->definitionComponentSlugs( $definition ) as $componentSlug ) {
			$components[] = $this->zonesCon()->getZoneComponent( $componentSlug );
		}
		return $components;
	}

	/**
	 * @param array{
	 *   key:string,
	 *   label:string,
	 *   icon:string,
	 *   zone_slug?:string,
	 *   component_slug?:string,
	 *   component_slugs?:list<string>,
	 *   include_in_posture?:bool,
	 *   force_neutral?:bool
	 * } $definition
	 * @return list<string>
	 */
	private function definitionComponentSlugs( array $definition ) :array {
		if ( !empty( $definition[ 'component_slug' ] ) ) {
			return [ (string)$definition[ 'component_slug' ] ];
		}

		$slugs = $definition[ 'component_slugs' ] ?? [];
		if ( !\is_array( $slugs ) ) {
			return [];
		}

		return \array_values( \array_filter( \array_map(
			fn( $slug ) :string => \trim( (string)$slug ),
			$slugs
		) ) );
	}

	/**
	 * @param array{
	 *   key:string,
	 *   label:string,
	 *   icon:string,
	 *   zone_slug?:string,
	 *   component_slug?:string,
	 *   component_slugs?:list<string>,
	 *   include_in_posture?:bool,
	 *   force_neutral?:bool
	 * } $definition
	 * @return ?Zone\Base
	 */
	private function zoneForDefinition( array $definition ) :?Zone\Base {
		if ( empty( $definition[ 'zone_slug' ] ) ) {
			return null;
		}

		return $this->zonesCon()->getZone( $definition[ 'zone_slug' ] );
	}

	/**
	 * @param array<string,mixed>|null $action
	 * @return array<string,mixed>
	 */
	private function normalizeActionContract( ?array $action ) :array {
		if ( empty( $action ) ) {
			return [];
		}

		$data = $action[ 'data' ] ?? [];
		if ( !\is_array( $data ) ) {
			$data = [];
		}

		$data[ 'form_context' ] = 'offcanvas';
		if ( !isset( $data[ 'zone_component_action' ] ) ) {
			$data[ 'zone_component_action' ] = ZoneComponentConfig::SLUG;
		}

		return [
			'title'   => (string)( $action[ 'title' ] ?? __( 'Edit Settings', 'wp-simple-firewall' ) ),
			'href'    => (string)( $action[ 'href' ] ?? 'javascript:{}' ),
			'icon'    => (string)( $action[ 'icon' ] ?? self::con()->svgs->iconClass( 'gear' ) ),
			'tooltip' => (string)( $action[ 'tooltip' ] ?? '' ),
			'classes' => [ 'zone_component_action' ],
			'data'    => $this->normalizeActionDataAttributes( $data ),
		];
	}

	/**
	 * @param array<mixed> $data
	 * @return array<string,string>
	 */
	private function normalizeActionDataAttributes( array $data ) :array {
		$normalized = [];
		foreach ( $data as $key => $value ) {
			$attribute = sanitize_key( (string)$key );
			if ( $attribute === '' ) {
				continue;
			}
			$normalized[ $attribute ] = (string)$value;
		}
		return $normalized;
	}

	private function zonesCon() :SecurityZonesCon {
		return self::con()->comps->zones;
	}
}
