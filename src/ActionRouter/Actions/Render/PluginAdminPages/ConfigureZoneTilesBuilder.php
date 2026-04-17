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

/**
 * @phpstan-type TileDefinition array{
 *   key:string,
 *   label:string,
 *   icon:string,
 *   summary:string,
 *   zone_slug?:string,
 *   component_slug?:string,
 *   component_slugs?:list<string>,
 *   include_in_posture?:bool,
 *   force_neutral?:bool,
 *   stat_line?:string
 * }
 * @phpstan-type ConfigureRowContract array{
 *   key:string,
 *   title:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string,
 *   note:string,
 *   explanations:list<string>,
 *   config_action:array<string,mixed>
 * }
 * @phpstan-type ConfigureZoneTileContract array{
 *   key:string,
 *   panel_target:string,
 *   is_enabled:bool,
 *   is_disabled:bool,
 *   include_in_posture:bool,
 *   label:string,
 *   icon_class:string,
 *   summary:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string,
 *   stat_line:string,
 *   panel:array{
 *     title:string,
 *     status:string,
 *     status_label:string,
 *     rows:list<ConfigureRowContract>
 *   }
 * }
 */
class ConfigureZoneTilesBuilder {

	private const GENERAL_SETTINGS_ROW_KEY = 'general_settings';

	use PluginControllerConsumer;
	use StandardStatusMapping;

	/**
	 * @return list<ConfigureZoneTileContract>
	 */
	public function build() :array {
		return \array_map(
			fn( array $definition ) :array => $this->buildTileFromDefinition( $definition ),
			$this->getTileDefinitions()
		);
	}

	/**
	 * @return list<TileDefinition>
	 */
	private function getTileDefinitions() :array {
		return PluginNavs::configureLandingTileDefinitions();
	}

	/**
	 * @param TileDefinition $definition
	 * @return ConfigureZoneTileContract
	 */
	private function buildTileFromDefinition( array $definition ) :array {
		$forceNeutral = !empty( $definition[ 'force_neutral' ] );
		$zone = $this->zoneForDefinition( $definition );
		$visibleComponents = $this->componentsForDefinition( $definition, $zone );
		$rows = $this->buildRowContracts( $zone, $visibleComponents, $forceNeutral );
		$status = $forceNeutral ? 'neutral' : $this->aggregateTileStatus( $rows );
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
			'summary'           => $this->requiredSummary( $definition ),
			'status'            => $status,
			'status_label'      => $this->tileStatusLabel( $status ),
			'status_icon_class' => $this->tileStatusIconClass( $status ),
			'stat_line'         => $forceNeutral
				? $this->forcedNeutralStatLine( $definition )
				: $this->buildTileStatLine( $rows ),
			'panel'             => [
				'title'        => $definition[ 'label' ],
				'status'       => $status,
				'status_label' => $this->tileStatusLabel( $status ),
				'rows'         => $rows,
			],
		];
	}

	/**
	 * @param TileDefinition $definition
	 */
	private function forcedNeutralStatLine( array $definition ) :string {
		$statLine = \trim( (string)( $definition[ 'stat_line' ] ?? '' ) );
		if ( $statLine === '' ) {
			throw new \LogicException( 'Forced-neutral configure tiles require a non-empty stat line.' );
		}
		return $statLine;
	}

	/**
	 * @param TileDefinition $definition
	 */
	private function requiredSummary( array $definition ) :string {
		$summary = \trim( (string)( $definition[ 'summary' ] ?? '' ) );
		if ( $summary === '' ) {
			throw new \LogicException( 'Configure tiles require a non-empty summary.' );
		}
		return $summary;
	}

	/**
	 * @param list<Component\Base> $visibleComponents
	 * @return list<ConfigureRowContract>
	 */
	private function buildRowContracts( ?Zone\Base $zone, array $visibleComponents, bool $forceNeutral ) :array {
		$rowInputs = [];
		foreach ( $visibleComponents as $component ) {
			foreach ( $component->configureRows() as $rowInput ) {
				$rowInputs[] = $rowInput;
			}
		}

		$rows = \array_map(
			fn( array $rowInput ) :array => $this->buildSingleRowContract( $rowInput, $forceNeutral ),
			$rowInputs
		);

		if ( !$forceNeutral ) {
			$generalSettings = $this->buildGeneralSettingsRowContract( $zone, $rows );
			if ( $generalSettings !== [] ) {
				$rows[] = $generalSettings;
			}
		}

		$this->assertValidUniqueRowKeys( $rows );
		return $rows;
	}

	/**
	 * @param array{
	 *   key:string,
	 *   title:string,
	 *   enabled_status:string,
	 *   note:string,
	 *   explanations:list<string>,
	 *   config_scope:array{
	 *     zone_component_slugs:list<string>,
	 *     option_keys:list<string>,
	 *     config_item:string,
	 *     title:string
	 *   }
	 * } $rowInput
	 * @return ConfigureRowContract
	 */
	private function buildSingleRowContract( array $rowInput, bool $forceNeutral = false ) :array {
		$status = $forceNeutral ? 'neutral' : $this->componentStatusToConfigureStatus( $rowInput[ 'enabled_status' ] );
		$explanations = \array_values( \array_filter(
			\array_map(
				static fn( $expl ) :string => \trim( (string)$expl ),
				$rowInput[ 'explanations' ]
			),
			static fn( string $expl ) :bool => $expl !== ''
		) );
		$configAction = $this->buildConfigActionForScope( $rowInput[ 'config_scope' ] );

		return [
			'key'               => $rowInput[ 'key' ],
			'title'             => $rowInput[ 'title' ],
			'status'            => $status,
			'status_label'      => $this->componentStatusLabel( $status ),
			'status_icon_class' => $this->componentStatusIconClass( $status ),
			'note'              => $rowInput[ 'note' ],
			'explanations'      => $explanations,
			'config_action'     => $configAction,
		];
	}

	/**
	 * @param list<ConfigureRowContract> $rows
	 * @return array{}|ConfigureRowContract
	 */
	private function buildGeneralSettingsRowContract( ?Zone\Base $zone, array $rows ) :array {
		$scope = ( new ConfigureGeneralSettingsScopeResolver() )->resolve(
			$zone,
			$this->extractCoveredOptionKeysFromRows( $rows )
		);
		if ( empty( $scope ) ) {
			return [];
		}

		return [
			'key'               => self::GENERAL_SETTINGS_ROW_KEY,
			'title'             => __( 'General settings', 'wp-simple-firewall' ),
			'status'            => 'neutral',
			'status_label'      => $this->componentStatusLabel( 'neutral' ),
			'status_icon_class' => $this->componentStatusIconClass( 'neutral' ),
			'note'              => __( 'Additional settings in this zone that are not covered by a dedicated callout.', 'wp-simple-firewall' ),
			'explanations'      => [],
			'config_action'     => $this->buildConfigActionForScope( [
				'zone_component_slugs' => $scope[ 'zone_component_slugs' ],
				'option_keys'          => $scope[ 'option_keys' ],
				'config_item'          => '',
				'title'                => __( 'Edit Settings', 'wp-simple-firewall' ),
			] ),
		];
	}

	/**
	 * @param list<ConfigureRowContract> $rows
	 * @return list<string>
	 */
	private function extractCoveredOptionKeysFromRows( array $rows ) :array {
		$coveredOptionKeys = [];
		foreach ( $rows as $row ) {
			$optionKeys = (string)( $row[ 'config_action' ][ 'data' ][ 'option_keys' ] ?? '' );
			if ( $optionKeys === '' ) {
				continue;
			}
			foreach ( \explode( ',', $optionKeys ) as $optionKey ) {
				$optionKey = \trim( $optionKey );
				if ( $optionKey !== '' ) {
					$coveredOptionKeys[] = $optionKey;
				}
			}
		}

		return \array_values( \array_unique( $coveredOptionKeys ) );
	}

	/**
	 * @param list<ConfigureRowContract> $rows
	 */
	private function assertValidUniqueRowKeys( array $rows ) :void {
		$seenKeys = [];
		foreach ( $rows as $row ) {
			$key = (string)( $row[ 'key' ] ?? '' );
			if ( $key === '' ) {
				throw new \LogicException( 'Configure rows require a stable non-empty row key.' );
			}
			if ( isset( $seenKeys[ $key ] ) ) {
				throw new \LogicException( 'Configure row keys must be unique within a zone: '.$key );
			}
			$seenKeys[ $key ] = true;
		}
	}

	private function componentStatusIconClass( string $status ) :string {
		return $this->standardStatusIconClass( $status, 'exclamation-triangle-fill' );
	}

	/**
	 * @param list<array{title:string,status:string,status_label:string,note:string}> $rows
	 */
	private function aggregateTileStatus( array $rows ) :string {
		return StatusPriority::highest( \array_column( $rows, 'status' ), 'good' );
	}

	/**
	 * @param list<array{title:string,status:string,status_label:string,note:string}> $rows
	 */
	private function buildTileStatLine( array $rows ) :string {
		$criticalCount = 0;
		$warningCount = 0;
		foreach ( $rows as $row ) {
			if ( $row[ 'status' ] === 'critical' ) {
				$criticalCount++;
			}
			elseif ( $row[ 'status' ] === 'warning' ) {
				$warningCount++;
			}
		}

		if ( $criticalCount > 0 && $warningCount > 0 ) {
			$criticalText = sprintf(
				_n( '%s critical group', '%s critical groups', $criticalCount, 'wp-simple-firewall' ),
				$criticalCount
			);
			$warningText = sprintf(
				_n( '%s group needs work', '%s groups need work', $warningCount, 'wp-simple-firewall' ),
				$warningCount
			);
			return sprintf(
				__( '%1$s, %2$s', 'wp-simple-firewall' ),
				$criticalText,
				$warningText
			);
		}
		if ( $criticalCount > 0 ) {
			return sprintf(
				_n( '%s critical group', '%s critical groups', $criticalCount, 'wp-simple-firewall' ),
				$criticalCount
			);
		}
		if ( $warningCount > 0 ) {
			return sprintf(
				_n( '%s group needs work', '%s groups need work', $warningCount, 'wp-simple-firewall' ),
				$warningCount
			);
		}
		return __( 'All groups healthy', 'wp-simple-firewall' );
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

	private function componentStatusToConfigureStatus( string $componentStatus ) :string {
		if ( $componentStatus === EnumEnabledStatus::NEUTRAL ) {
			return 'neutral';
		}

		return EnumEnabledStatus::toSeverity( $componentStatus, 'good' );
	}

	/**
	 * @param array{
	 *   zone_component_slugs:list<string>,
	 *   option_keys:list<string>,
	 *   config_item:string,
	 *   title:string
	 * } $scope
	 * @return array<string,mixed>
	 */
	private function buildConfigActionForScope( array $scope ) :array {
		if ( empty( $scope[ 'zone_component_slugs' ] ) ) {
			return [];
		}

		$data = [
			'zone_component_action' => ZoneComponentConfig::SLUG,
			'zone_component_slug'   => \implode( ',', $scope[ 'zone_component_slugs' ] ),
		];
		if ( !empty( $scope[ 'option_keys' ] ) ) {
			$data[ 'option_keys' ] = \implode( ',', $scope[ 'option_keys' ] );
		}
		if ( !empty( $scope[ 'config_item' ] ) ) {
			$data[ 'config_item' ] = $scope[ 'config_item' ];
		}

		return $this->normalizeActionContract( [
			'title' => $scope[ 'title' ] ?: __( 'Edit Settings', 'wp-simple-firewall' ),
			'icon'  => self::con()->svgs->iconClass( 'gear' ),
			'data'  => $data,
		] );
	}

	/**
	 * @param TileDefinition $definition
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
	 * @param TileDefinition $definition
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
	 * @param TileDefinition $definition
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
