<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\SecurityZonesCon;

/**
 * @phpstan-type TileDefinition array{
 *   key:string,
 *   label:string,
 *   icon:string,
 *   summary:string,
 *   goal:string,
 *   include_in_posture?:bool
 * }
 * @phpstan-type ConfigureStatus 'good'|'warning'|'critical'|'neutral'
 * @phpstan-type RawConfigureScope array{
 *   zone_component_slugs:list<string>,
 *   option_keys:list<string>,
 *   config_item:string,
 *   title:string
 * }
 * @phpstan-type ConfigureScope array{
 *   zone_component_slugs:non-empty-list<string>,
 *   option_keys:non-empty-list<string>,
 *   config_item:string,
 *   title:string
 * }
 * @phpstan-type ConfigureActionData array{
 *   zone_component_action:string,
 *   zone_component_slug:non-empty-string,
 *   option_keys:non-empty-string,
 *   form_context:'offcanvas',
 *   config_item?:non-empty-string,
 *   configure_panel?:non-empty-string
 * }
 * @phpstan-type ConfigureActionContract array{
 *   label:string,
 *   title:string,
 *   href:'',
 *   target:'',
 *   is_action:true,
 *   icon:string,
 *   classes:list<string>,
 *   data:ConfigureActionData
 * }
 * @phpstan-type ConfigureRowContract array{
 *   key:string,
 *   title:string,
 *   status:ConfigureStatus,
 *   status_label:string,
 *   status_icon_class:string,
 *   note:string,
 *   explanations:list<string>,
 *   config_action:ConfigureActionContract
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
 *   status:ConfigureStatus,
 *   status_label:string,
 *   status_icon_class:string,
 *   stat_line:string,
 *   panel:array{
 *     title:string,
 *     status:ConfigureStatus,
 *     status_label:string,
 *     rows:list<ConfigureRowContract>
 *   }
 * }
 */
class ConfigureZoneTilesBuilder {

	use PluginControllerConsumer;
	use StandardStatusMapping;

	/**
	 * @return list<ConfigureZoneTileContract>
	 */
	public function build() :array {
		$tiles = [];
		foreach ( $this->getTileDefinitions() as $definition ) {
			$tile = $this->buildTileFromDefinition( $definition );
			if ( !empty( $tile[ 'panel' ][ 'rows' ] ) ) {
				$tiles[] = $tile;
			}
		}
		return $tiles;
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
		$rows = $this->buildGoalRowContracts( $definition[ 'goal' ] );
		$status = $this->aggregateTileStatus( $rows );
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
			'summary'           => $definition[ 'summary' ],
			'status'            => $status,
			'status_label'      => $this->tileStatusLabel( $status ),
			'status_icon_class' => $this->tileStatusIconClass( $status ),
			'stat_line'         => $this->buildTileStatLine( $rows ),
			'panel'             => [
				'title'        => $definition[ 'label' ],
				'status'       => $status,
				'status_label' => $this->tileStatusLabel( $status ),
				'rows'         => $rows,
			],
		];
	}

	/**
	 * One row per topic of a goal. A topic's settings can come from several components, so its status is
	 * the most severe of those components' rows that cover its settings, and it keeps only the
	 * explanations that its own settings fix (or that no particular setting fixes).
	 * @return list<ConfigureRowContract>
	 * @throws \LogicException
	 */
	private function buildGoalRowContracts( string $goalKey ) :array {
		$goal = ConfigureGoalPanels::goals()[ $goalKey ] ?? null;
		if ( !\is_array( $goal ) ) {
			return [];
		}
		$labels = ConfigureGoalPanels::labels()[ 'topics' ];
		$options = self::con()->cfg->configuration->options;

		$rows = [];
		foreach ( \array_keys( $goal[ 'topics' ] ) as $topicKey ) {
			$optionKeys = ConfigureGoalPanels::topicOptionKeys( $goalKey, $topicKey );
			$componentSlugs = [];
			foreach ( $optionKeys as $optionKey ) {
				$componentSlugs = \array_merge( $componentSlugs, $options[ $optionKey ][ 'zone_comp_slugs' ] ?? [] );
			}
			$scope = $this->normalizeDisplayableConfigScope( [
				'zone_component_slugs' => $componentSlugs,
				'option_keys'          => $optionKeys,
				'config_item'          => '',
				'title'                => $labels[ $topicKey ][ 'label' ] ?? '',
			] );
			if ( empty( $scope ) ) {
				continue;
			}

			// Advanced options gathers odd settings from across the goal, so it only speaks for problems
			// its own settings fix, rather than taking on the state of every component they come from.
			[ $status, $explanations ] = $this->topicStatus( $scope, $topicKey === 'advanced' );
			$configAction = $this->buildConfigActionForScope( $scope );
			$configAction[ 'data' ][ 'configure_panel' ] = $goalKey.':'.$topicKey;

			$rows[] = [
				'key'               => $topicKey,
				'title'             => $labels[ $topicKey ][ 'label' ] ?? $topicKey,
				'status'            => $status,
				'status_label'      => $this->componentStatusLabel( $status ),
				'status_icon_class' => $this->componentStatusIconClass( $status ),
				'note'              => $labels[ $topicKey ][ 'summary' ] ?? '',
				'explanations'      => $explanations,
				'config_action'     => $configAction,
			];
		}

		$this->assertValidUniqueRowKeys( $rows );
		return $rows;
	}

	/**
	 * @param ConfigureScope $scope
	 * @return array{0:ConfigureStatus,1:list<string>}
	 */
	private function topicStatus( array $scope, bool $ownFixesOnly = false ) :array {
		$statuses = [];
		$explanations = [];
		foreach ( $scope[ 'zone_component_slugs' ] as $slug ) {
			if ( \strpos( $slug, 'module_' ) === 0 ) {
				continue;
			}
			foreach ( $this->zonesCon()->getZoneComponent( $slug )->configureRows() as $rowInput ) {
				if ( empty( \array_intersect( $rowInput[ 'config_scope' ][ 'option_keys' ], $scope[ 'option_keys' ] ) ) ) {
					continue;
				}
				// Explanations record the option that fixes them as they're built, so read the lookup afterwards.
				$fixLookup = Component\Base::explanationFixLookup();
				$kept = [];
				foreach ( $rowInput[ 'explanations' ] as $explanation ) {
					$fixedBy = $fixLookup[ \trim( $explanation ) ] ?? '';
					if ( \in_array( $fixedBy, $scope[ 'option_keys' ], true ) || ( $fixedBy === '' && !$ownFixesOnly ) ) {
						$kept[] = $explanation;
					}
				}
				if ( !$ownFixesOnly || !empty( $kept ) ) {
					$statuses[] = $this->componentStatusToConfigureStatus( $rowInput[ 'enabled_status' ] );
				}
				$explanations = \array_merge( $explanations, $kept );
			}
		}

		$statuses = \array_values( \array_filter( $statuses, static fn( string $s ) :bool => $s !== 'neutral' ) );
		/** @var ConfigureStatus $status */
		$status = empty( $statuses ) ? 'neutral' : StatusPriority::highest( $statuses, 'good' );
		return [ $status, \array_values( \array_unique( $explanations ) ) ];
	}

	/**
	 * @param list<ConfigureRowContract> $rows
	 * @throws \LogicException
	 */
	private function assertValidUniqueRowKeys( array $rows ) :void {
		$seenKeys = [];
		foreach ( $rows as $row ) {
			$key = $row[ 'key' ];
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
	 * @param list<array{title:string,status:ConfigureStatus,status_label:string,note:string}> $rows
	 * @return 'good'|'warning'|'critical'
	 */
	private function aggregateTileStatus( array $rows ) :string {
		$status = StatusPriority::highest( \array_column( $rows, 'status' ), 'good' );
		/** @var 'good'|'warning'|'critical' $status */
		return $status;
	}

	/**
	 * @param list<array{title:string,status:ConfigureStatus,status_label:string,note:string}> $rows
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

	/**
	 * @param ConfigureStatus $status
	 */
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

	/**
	 * @param ConfigureStatus $status
	 */
	private function tileStatusIconClass( string $status ) :string {
		return $this->standardStatusIconClass( $status, 'exclamation-triangle-fill' );
	}

	/**
	 * @param ConfigureStatus $status
	 */
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

	/**
	 * @return ConfigureStatus
	 */
	private function componentStatusToConfigureStatus( string $componentStatus ) :string {
		if ( $componentStatus === EnumEnabledStatus::NEUTRAL ) {
			return 'neutral';
		}

		return EnumEnabledStatus::toSeverity( $componentStatus, 'good' );
	}

	/**
	 * @param ConfigureScope $scope
	 * @return ConfigureActionContract
	 */
	private function buildConfigActionForScope( array $scope ) :array {
		$data = [
			'zone_component_action' => ZoneComponentConfig::SLUG,
			'zone_component_slug'   => \implode( ',', $scope[ 'zone_component_slugs' ] ),
			'option_keys'           => \implode( ',', $scope[ 'option_keys' ] ),
			'form_context'          => 'offcanvas',
		];
		if ( !empty( $scope[ 'config_item' ] ) ) {
			$data[ 'config_item' ] = $scope[ 'config_item' ];
		}

		return [
			'label'     => __( 'Configure', 'wp-simple-firewall' ),
			'title'     => $scope[ 'title' ] ?: __( 'Edit Settings', 'wp-simple-firewall' ),
			'href'      => '',
			'target'    => '',
			'is_action' => true,
			'icon'      => self::con()->svgs->iconClass( 'gear' ),
			'classes'   => [ 'zone_component_action' ],
			'data'      => $data,
		];
	}

	/**
	 * @param RawConfigureScope $scope
	 * @return array{}|ConfigureScope
	 */
	private function normalizeDisplayableConfigScope( array $scope ) :array {
		$zoneComponentSlugs = \array_values( \array_unique( \array_filter( \array_map(
			static fn( $slug ) :string => \trim( (string)$slug ),
			$scope[ 'zone_component_slugs' ]
		) ) ) );

		$optionKeys = [];
		foreach ( $scope[ 'option_keys' ] as $optionKey ) {
			$optionKey = \trim( (string)$optionKey );
			if ( $optionKey !== ''
				 && !\in_array( $optionKey, $optionKeys, true )
				 && $this->isDisplayableOptionKey( $optionKey ) ) {
				$optionKeys[] = $optionKey;
			}
		}

		if ( empty( $zoneComponentSlugs ) || empty( $optionKeys ) ) {
			return [];
		}

		$configItem = \trim( $scope[ 'config_item' ] );
		if ( $configItem !== '' && !\in_array( $configItem, $optionKeys, true ) ) {
			$configItem = '';
		}

		return [
			'zone_component_slugs' => $zoneComponentSlugs,
			'option_keys'          => $optionKeys,
			'config_item'          => $configItem,
			'title'                => \trim( $scope[ 'title' ] ),
		];
	}

	private function isDisplayableOptionKey( string $optionKey ) :bool {
		$section = (string)( self::con()->cfg->configuration->options[ $optionKey ][ 'section' ] ?? '' );
		return $section !== '' && !\in_array( $section, [ 'section_hidden', 'section_deprecated' ], true );
	}

	private function zonesCon() :SecurityZonesCon {
		return self::con()->comps->zones;
	}
}
