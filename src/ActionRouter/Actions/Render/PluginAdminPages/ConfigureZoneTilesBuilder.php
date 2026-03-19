<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\BuildOptionsForDisplay;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\SecurityZonesCon;

/**
 * @phpstan-type InlineControlOption array{
 *   key:string,
 *   label:string,
 *   is_disabled:bool
 * }
 * @phpstan-type InlineControl array{
 *   type:'toggle'|'select'|'none',
 *   option_key:string,
 *   value:bool|string|null,
 *   is_disabled:bool,
 *   options:list<InlineControlOption>
 * }
 */
class ConfigureZoneTilesBuilder {

	use PluginControllerConsumer;
	use StandardStatusMapping;

	private ?array $displayedOptionCache = null;

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
	 *   settings_href:string,
	 *   settings_label:string,
	 *   settings_action:array<string,mixed>,
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
	 *       config_action:array<string,mixed>,
	 *       inline_control:InlineControl
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
	 *   settings_href:string,
	 *   settings_label:string,
	 *   settings_action:array<string,mixed>,
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
	 *       config_action:array<string,mixed>,
	 *       inline_control:InlineControl
	 *     }>
	 *   }
	 * }
	 */
	private function buildTileFromDefinition( array $definition ) :array {
		$forceNeutral = !empty( $definition[ 'force_neutral' ] );
		$components = $this->buildComponentContracts( $definition, $forceNeutral );
		$status = $forceNeutral ? 'neutral' : $this->aggregateTileStatus( $components );
		$settingsAction = $this->buildSettingsActionFromDefinition( $definition );
		$includeInPosture = !\array_key_exists( 'include_in_posture', $definition )
			|| (bool)$definition[ 'include_in_posture' ];

		return [
			'key'            => $definition[ 'key' ],
			'panel_target'   => $definition[ 'key' ],
			'is_enabled'     => true,
			'is_disabled'    => false,
			'include_in_posture' => $includeInPosture,
			'label'          => $definition[ 'label' ],
			'icon_class'     => self::con()->svgs->iconClass( $definition[ 'icon' ] ),
			'status'         => $status,
			'status_label'   => $this->tileStatusLabel( $status ),
			'status_icon_class' => $this->tileStatusIconClass( $status ),
			'stat_line'      => $forceNeutral
				? __( 'General settings', 'wp-simple-firewall' )
				: $this->buildTileStatLine( $components ),
			'settings_href'  => $this->buildSettingsHref( $definition ),
			'settings_label' => sprintf( __( 'Configure %s Settings', 'wp-simple-firewall' ), $definition[ 'label' ] ),
			'settings_action' => $settingsAction,
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
	 *   component_slug?:string,
	 *   component_slugs?:list<string>,
	 *   include_in_posture?:bool,
	 *   force_neutral?:bool
	 * } $definition
	 */
	private function buildSettingsHref( array $definition ) :string {
		if ( !empty( $definition[ 'zone_slug' ] ) ) {
			return self::con()->plugin_urls->zone( $definition[ 'zone_slug' ] );
		}
		if ( !empty( $definition[ 'component_slug' ] ) ) {
			return self::con()->plugin_urls->cfgForZoneComponent( $definition[ 'component_slug' ] );
		}
		$componentSlugs = $this->definitionComponentSlugs( $definition );
		return empty( $componentSlugs ) ? 'javascript:{}' : self::con()->plugin_urls->cfgForZoneComponent( $componentSlugs[ 0 ] );
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
	 * @return array<string,mixed>
	 */
	private function buildSettingsActionFromDefinition( array $definition ) :array {
		$action = null;
		if ( !empty( $definition[ 'zone_slug' ] ) ) {
			$zone = $this->zonesCon()->getZone( $definition[ 'zone_slug' ] );
			$action = $zone->getAction_Config();
		}
		elseif ( !empty( $definition[ 'component_slug' ] ) ) {
			$component = $this->zonesCon()->getZoneComponent( $definition[ 'component_slug' ] );
			$action = $component->getActions()[ 'config' ] ?? null;
		}
		else {
			$componentSlugs = $this->definitionComponentSlugs( $definition );
			if ( !empty( $componentSlugs ) ) {
				$action = [
					'title' => sprintf( __( 'Configure %s Settings', 'wp-simple-firewall' ), $definition[ 'label' ] ),
					'icon'  => self::con()->svgs->iconClass( 'gear' ),
					'data'  => [
						'zone_component_action' => ZoneComponentConfig::SLUG,
						'zone_component_slug'   => \implode( ',', $componentSlugs ),
					],
				];
			}
		}
		return $this->normalizeActionContract( $action );
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
	 *   config_action:array<string,mixed>,
	 *   inline_control:InlineControl
	 * }>
	 */
	private function buildComponentContracts( array $definition, bool $forceNeutral ) :array {
		return \array_map(
			fn( Component\Base $component ) :array => $this->buildSingleComponentContract( $component, $forceNeutral ),
			$this->componentsForDefinition( $definition )
		);
	}

	/**
	 * @return array{
	 *   title:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string,
	 *   note:string,
	 *   explanations:list<string>,
	 *   config_action:array<string,mixed>,
	 *   inline_control:InlineControl
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
			'inline_control'    => $this->buildInlineControl( $component ),
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
	 * @return InlineControl
	 */
	private function buildInlineControl( Component\Base $component ) :array {
		$fallback = null;

		foreach ( $component->getOptions() as $optionKey ) {
			$displayedOption = $this->getDisplayedOption( $optionKey );
			if ( !\is_array( $displayedOption ) || !\in_array( $displayedOption[ 'type' ] ?? '', [ 'checkbox', 'select' ], true ) ) {
				continue;
			}

			$control = $this->displayedOptionToInlineControl( $displayedOption );
			if ( !$control[ 'is_disabled' ] ) {
				return $control;
			}
			$fallback ??= $control;
		}

		return $fallback ?? $this->emptyInlineControl();
	}

	private function getDisplayedOption( string $optionKey ) :?array {
		$this->displayedOptionCache ??= [];
		if ( \array_key_exists( $optionKey, $this->displayedOptionCache ) ) {
			return $this->displayedOptionCache[ $optionKey ];
		}

		$optionDef = self::con()->cfg->configuration->options[ $optionKey ] ?? null;
		$displayedOption = null;
		if ( \is_array( $optionDef ) && !empty( $optionDef[ 'section' ] ) ) {
			$builder = new class( [ $optionKey ], [] ) extends BuildOptionsForDisplay {
				public function buildDisplayedOptionsForSection( string $section ) :array {
					return $this->buildOptionsForSection( $section );
				}
			};

			foreach ( $builder->buildDisplayedOptionsForSection( (string)$optionDef[ 'section' ] ) as $option ) {
				if ( ( $option[ 'key' ] ?? '' ) === $optionKey ) {
					$displayedOption = $option;
					break;
				}
			}
		}

		$this->displayedOptionCache[ $optionKey ] = \is_array( $displayedOption ) ? $displayedOption : null;
		return $this->displayedOptionCache[ $optionKey ];
	}

	/**
	 * @param array<string,mixed> $option
	 * @return InlineControl
	 */
	private function displayedOptionToInlineControl( array $option ) :array {
		$type = $option[ 'type' ] === 'checkbox' ? 'toggle' : 'select';
		$options = [];

		if ( $type === 'select' ) {
			foreach ( $option[ 'value_options' ] ?? [] as $key => $settings ) {
				$options[] = [
					'key'         => (string)$key,
					'label'       => $this->plainTextLabel( (string)( $settings[ 'name' ] ?? '' ) ),
					'is_disabled' => empty( $settings[ 'is_available' ] ),
				];
			}
		}

		$isDisabled = !empty( $option[ 'disabled' ] );
		if ( $type === 'select' && !$isDisabled && !empty( $options ) ) {
			$isDisabled = \count( \array_filter(
				$options,
				static fn( array $valueOption ) :bool => !$valueOption[ 'is_disabled' ]
			) ) === 0;
		}

		return [
			'type'        => $type,
			'option_key'  => (string)( $option[ 'key' ] ?? '' ),
			'value'       => $type === 'toggle' ? ( $option[ 'value' ] === 'Y' ) : (string)( $option[ 'value' ] ?? '' ),
			'is_disabled' => $isDisabled,
			'options'     => $options,
		];
	}

	private function plainTextLabel( string $label ) :string {
		return \trim( \html_entity_decode( \strip_tags( $label ), \ENT_QUOTES ) );
	}

	/**
	 * @return InlineControl
	 */
	private function emptyInlineControl() :array {
		return [
			'type'        => 'none',
			'option_key'  => '',
			'value'       => null,
			'is_disabled' => true,
			'options'     => [],
		];
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
	private function componentsForDefinition( array $definition ) :array {
		if ( !empty( $definition[ 'zone_slug' ] ) ) {
			$zone = $this->zonesCon()->getZone( $definition[ 'zone_slug' ] );
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

		return \array_values( \array_filter(
			\array_map(
				fn( $slug ) :string => \trim( (string)$slug ),
				$slugs
			),
			fn( string $slug ) :bool => $slug !== ''
		) );
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
