<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker,
	Maintenance,
	Plugins,
	Themes,
	Vulnerabilities
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\ActionsQueueItemIcons;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

/**
 * @phpstan-type GroupDefinition array{
 *   key:string,
 *   label:string,
 *   sort_order:int,
 *   section_key:string,
 *   section_order:int,
 *   section_label:string,
 *   icon_class:string,
 *   detail_shell:'asset_cards'|'direct_table'|'maintenance',
 *   card_type:'expandable'|'linked'|'category',
 *   summary_keys:list<string>,
 *   healthy_interaction_mode:'none'|'ignored_only'|'default_detail',
 *   healthy_ignored_source:''|'wordpress'|'plugins'|'themes'|'malware',
 *   render_action_class:class-string<BaseAction>,
 *   render_action_data:array<string,mixed>
 * }
 * @phpstan-type SummaryBehaviour array{
 *   definition_key:string,
 *   seed_strategy:'attention_aggregate'|'asset_cards'|'plugin_assets'|'vulnerability_section'|'maintenance',
 *   asset_source?:'plugins'|'themes',
 *   vulnerability_section?:'vulnerable'|'abandoned'
 * }
 */
class ActionsQueueGroupDefinitions {

	private const GROUP_METADATA = [
		'wordpress' => [
			'sort_order'               => 2,
			'section_key'              => 'wordpress',
			'section_order'            => 0,
			'detail_shell'             => 'direct_table',
			'card_type'                => 'expandable',
			'healthy_interaction_mode' => 'ignored_only',
			'healthy_ignored_source'   => 'wordpress',
			'render_action_class'      => ActionsQueueAssetFileStatusDetail::class,
			'render_action_data'       => [
				'type' => 'wordpress',
				'file' => 'wordpress',
			],
		],
		'plugins' => [
			'sort_order'               => 3,
			'section_key'              => 'plugins',
			'section_order'            => 2,
			'detail_shell'             => 'asset_cards',
			'card_type'                => 'expandable',
			'healthy_interaction_mode' => 'ignored_only',
			'healthy_ignored_source'   => 'plugins',
			'render_action_class'      => Plugins::class,
			'render_action_data'       => 'scan_results',
		],
		'themes' => [
			'sort_order'               => 4,
			'section_key'              => 'themes',
			'section_order'            => 3,
			'detail_shell'             => 'asset_cards',
			'card_type'                => 'expandable',
			'healthy_interaction_mode' => 'ignored_only',
			'healthy_ignored_source'   => 'themes',
			'render_action_class'      => Themes::class,
			'render_action_data'       => 'scan_results',
		],
		'vulnerabilities' => [
			'sort_order'               => 0,
			'section_key'              => 'vulnerabilities',
			'section_order'            => 1,
			'detail_shell'             => 'direct_table',
			'card_type'                => 'linked',
			'healthy_interaction_mode' => 'none',
			'healthy_ignored_source'   => '',
			'render_action_class'      => Vulnerabilities::class,
			'render_action_data'       => [
				'section' => 'vulnerable',
			],
		],
		'abandoned' => [
			'sort_order'               => 1,
			'section_key'              => 'abandoned',
			'section_order'            => 4,
			'detail_shell'             => 'direct_table',
			'card_type'                => 'linked',
			'healthy_interaction_mode' => 'none',
			'healthy_ignored_source'   => '',
			'render_action_class'      => Vulnerabilities::class,
			'render_action_data'       => [
				'section' => 'abandoned',
			],
		],
		'malware' => [
			'sort_order'               => 5,
			'section_key'              => 'wordpress',
			'section_order'            => 0,
			'detail_shell'             => 'direct_table',
			'card_type'                => 'expandable',
			'healthy_interaction_mode' => 'ignored_only',
			'healthy_ignored_source'   => 'malware',
			'render_action_class'      => ActionsQueueAssetFileStatusDetail::class,
			'render_action_data'       => [
				'type' => 'malware',
				'file' => 'malware',
			],
		],
		'file_locker' => [
			'sort_order'               => 6,
			'section_key'              => 'wordpress',
			'section_order'            => 0,
			'detail_shell'             => 'asset_cards',
			'card_type'                => 'expandable',
			'healthy_interaction_mode' => 'default_detail',
			'healthy_ignored_source'   => '',
			'render_action_class'      => FileLocker::class,
			'render_action_data'       => 'scan_results',
		],
		'maintenance' => [
			'sort_order'               => 7,
			'section_key'              => '',
			'section_order'            => 99,
			'icon_class'               => 'bi bi-wrench',
			'detail_shell'             => 'maintenance',
			'card_type'                => 'category',
			'healthy_interaction_mode' => 'none',
			'healthy_ignored_source'   => '',
			'render_action_class'      => Maintenance::class,
			'render_action_data'       => 'none',
		],
	];

	private const SUMMARY_BEHAVIOUR_OVERRIDES = [
		'plugin_files' => [
			'definition_key' => 'plugins',
			'seed_strategy'  => 'plugin_assets',
			'asset_source'   => 'plugins',
		],
		'plugin_files_ignored' => [
			'definition_key' => 'plugins',
			'seed_strategy'  => 'plugin_assets',
			'asset_source'   => 'plugins',
		],
		'theme_files' => [
			'definition_key' => 'themes',
			'seed_strategy'  => 'asset_cards',
			'asset_source'   => 'themes',
		],
		'vulnerable_assets' => [
			'definition_key'        => 'vulnerabilities',
			'seed_strategy'         => 'vulnerability_section',
			'vulnerability_section' => 'vulnerable',
		],
		'abandoned' => [
			'definition_key'        => 'abandoned',
			'seed_strategy'         => 'vulnerability_section',
			'vulnerability_section' => 'abandoned',
		],
	];

	private const REVIEW_MAINTENANCE_GROUPS = [
		'maintenance_system' => [
			'icon_key'  => 'system_ssl_certificate',
			'item_keys' => [
				'system_lib_openssl',
				'system_ssl_certificate',
				'system_php_version',
			],
		],
		'maintenance_wordpress' => [
			'icon_key'  => 'wp_updates',
			'item_keys' => [
				'wp_updates',
				'wp_db_password',
				'default_admin_user',
			],
		],
	];

	/**
	 * @var array<string,GroupDefinition>|null
	 */
	private ?array $definitions = null;

	private ScanResultsDisplayOptions $queueScanResultsOptions;

	public function __construct( ?ScanResultsDisplayOptions $queueScanResultsOptions = null ) {
		$this->queueScanResultsOptions = $queueScanResultsOptions ?? new ScanResultsDisplayOptions();
	}

	/**
	 * @return array<string,GroupDefinition>
	 */
	public function all() :array {
		if ( $this->definitions !== null ) {
			return $this->definitions;
		}

		$definitions = [];

		foreach ( PluginNavs::actionsQueueScanDefinitions() as $key => $scanDefinition ) {
			$metadata = self::GROUP_METADATA[ $key ];
			$labelOverride = $this->labelOverrideForGroupKey( $key );
			$definitions[ $key ] = [
				'key'                      => $key,
				'label'                    => $labelOverride !== '' ? $labelOverride : $scanDefinition[ 'label' ],
				'sort_order'               => $metadata[ 'sort_order' ],
				'section_key'              => $metadata[ 'section_key' ],
				'section_order'            => $metadata[ 'section_order' ],
				'section_label'            => $this->sectionLabelOverrideForGroupKey( $key ),
				'icon_class'               => $metadata[ 'icon_class' ] ?? PluginNavs::actionsLandingScanRailIconClass( $key ),
				'detail_shell'             => $metadata[ 'detail_shell' ],
				'card_type'                => $metadata[ 'card_type' ],
				'summary_keys'             => $scanDefinition[ 'summary_keys' ],
				'healthy_interaction_mode' => $metadata[ 'healthy_interaction_mode' ],
				'healthy_ignored_source'   => $metadata[ 'healthy_ignored_source' ],
				'render_action_class'      => $metadata[ 'render_action_class' ],
				'render_action_data'       => $this->renderActionDataForMode( $metadata[ 'render_action_data' ] ),
			];
		}

		foreach ( $definitions as &$definition ) {
			$sectionKey = $definition[ 'section_key' ];
			if ( $definition[ 'section_label' ] === ''
				&& $sectionKey !== ''
				&& isset( $definitions[ $sectionKey ] ) ) {
				$ownerSectionLabel = $definitions[ $sectionKey ][ 'section_label' ];
				$definition[ 'section_label' ] = $ownerSectionLabel !== ''
					? $ownerSectionLabel
					: $definitions[ $sectionKey ][ 'label' ];
			}
		}
		unset( $definition );

		$maintenance = self::GROUP_METADATA[ 'maintenance' ];
		$definitions[ 'maintenance' ] = [
			'key'                      => 'maintenance',
			'label'                    => $this->labelOverrideForGroupKey( 'maintenance' ),
			'sort_order'               => $maintenance[ 'sort_order' ],
			'section_key'              => $maintenance[ 'section_key' ],
			'section_order'            => $maintenance[ 'section_order' ],
			'section_label'            => '',
			'icon_class'               => $maintenance[ 'icon_class' ],
			'detail_shell'             => $maintenance[ 'detail_shell' ],
			'card_type'                => $maintenance[ 'card_type' ],
			'summary_keys'             => [],
			'healthy_interaction_mode' => 'none',
			'healthy_ignored_source'   => '',
			'render_action_class'      => $maintenance[ 'render_action_class' ],
			'render_action_data'       => [],
		];

		$itemIcons = new ActionsQueueItemIcons();
		foreach ( self::REVIEW_MAINTENANCE_GROUPS as $groupKey => $group ) {
			$definitions[ $groupKey ] = [
				'key'                      => $groupKey,
				'label'                    => $this->reviewMaintenanceLabelForGroupKey( $groupKey ),
				'sort_order'               => $maintenance[ 'sort_order' ],
				'section_key'              => $maintenance[ 'section_key' ],
				'section_order'            => $maintenance[ 'section_order' ],
				'section_label'            => '',
				'icon_class'               => $itemIcons->iconClassForKey( $group[ 'icon_key' ] ),
				'detail_shell'             => $maintenance[ 'detail_shell' ],
				'card_type'                => $maintenance[ 'card_type' ],
				'summary_keys'             => [],
				'healthy_interaction_mode' => 'none',
				'healthy_ignored_source'   => '',
				'render_action_class'      => $maintenance[ 'render_action_class' ],
				'render_action_data'       => [],
			];
		}

		$this->definitions = $definitions;
		return $this->definitions;
	}

	public function groupKeyForSummaryKey( string $summaryKey ) :string {
		return $this->summaryBehaviourForKey( $summaryKey )[ 'definition_key' ];
	}

	/**
	 * @return list<string>
	 */
	public function criticalScanGroupKeys() :array {
		return \array_values( \array_keys( PluginNavs::actionsQueueScanDefinitions() ) );
	}

	public function groupKeyForGroupKey( string $groupKey ) :string {
		if ( \strpos( $groupKey, ':' ) !== false ) {
			$definitionKey = \strstr( $groupKey, ':', true );
			if ( \is_string( $definitionKey ) && isset( $this->all()[ $definitionKey ] ) ) {
				return $definitionKey;
			}
		}

		return isset( $this->all()[ $groupKey ] )
			? $groupKey
			: 'maintenance';
	}

	/**
	 * @return GroupDefinition
	 */
	public function definitionForGroupKey( string $groupKey ) :array {
		return $this->all()[ $this->groupKeyForGroupKey( $groupKey ) ];
	}

	public function sortOrderForGroupKey( string $groupKey ) :int {
		return $this->definitionForGroupKey( $groupKey )[ 'sort_order' ];
	}

	public function sectionKeyForGroupKey( string $groupKey ) :string {
		return $this->definitionForGroupKey( $groupKey )[ 'section_key' ];
	}

	public function sectionOrderForGroupKey( string $groupKey ) :int {
		return $this->definitionForGroupKey( $groupKey )[ 'section_order' ];
	}

	public function sectionLabelForGroupKey( string $groupKey ) :string {
		return $this->definitionForGroupKey( $groupKey )[ 'section_label' ];
	}

	/**
	 * @return SummaryBehaviour
	 */
	public function summaryBehaviourForKey( string $summaryKey ) :array {
		if ( isset( self::SUMMARY_BEHAVIOUR_OVERRIDES[ $summaryKey ] ) ) {
			return self::SUMMARY_BEHAVIOUR_OVERRIDES[ $summaryKey ];
		}

		$definition = PluginNavs::actionsQueueScanDefinitionForSummaryKey( $summaryKey );
		if ( $definition !== null ) {
			return [
				'definition_key' => $definition[ 'slug' ],
				'seed_strategy'  => 'attention_aggregate',
			];
		}

		return [
			'definition_key' => 'maintenance',
			'seed_strategy'  => 'maintenance',
		];
	}

	public function healthyIgnoredSourceForGroupKey( string $groupKey ) :string {
		return $this->definitionForGroupKey( $groupKey )[ 'healthy_ignored_source' ];
	}

	public function healthyInteractionModeForGroupKey( string $groupKey ) :string {
		return $this->definitionForGroupKey( $groupKey )[ 'healthy_interaction_mode' ];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function ignoredRenderActionDataForGroupKey( string $groupKey, int $ignoredCount ) :array {
		if ( $ignoredCount < 1 ) {
			return [];
		}

		$source = $this->healthyIgnoredSourceForGroupKey( $groupKey );
		if ( \in_array( $source, [ 'wordpress', 'malware' ], true ) ) {
			return ( new ActionsQueueScanResultsTableBuilder( null, $this->queueScanResultsOptions ) )
				->buildScopeActionData( $source, $source, $this->queueScanResultsOptions->ignoredOnly() );
		}

		return $source !== ''
			? $this->queueScanResultsOptions->buildForcedIgnoredActionData()
			: [];
	}

	public function reviewMaintenanceGroupKeyForItemKey( string $itemKey ) :string {
		foreach ( self::REVIEW_MAINTENANCE_GROUPS as $groupKey => $group ) {
			if ( \in_array( $itemKey, $group[ 'item_keys' ], true ) ) {
				return $groupKey;
			}
		}

		return $itemKey;
	}

	public function isReviewMaintenanceAggregateGroupKey( string $groupKey ) :bool {
		return isset( self::REVIEW_MAINTENANCE_GROUPS[ $groupKey ] );
	}

	/**
	 * @param array<string,mixed>|string $mode
	 * @return array<string,mixed>
	 */
	private function renderActionDataForMode( $mode ) :array {
		if ( \is_array( $mode ) ) {
			return isset( $mode[ 'type' ], $mode[ 'file' ] )
				? $this->queueScanResultsOptions->mergeIntoActionData( $mode )
				: $mode;
		}

		return $mode === 'scan_results'
			? $this->queueScanResultsOptions->buildDisplayContextActionData()
			: [];
	}

	private function labelOverrideForGroupKey( string $groupKey ) :string {
		switch ( $groupKey ) {
			case 'malware':
				return __( 'Malware Detections', 'wp-simple-firewall' );

			case 'file_locker':
				return __( 'Critical File Changes', 'wp-simple-firewall' );

			case 'maintenance':
				return __( 'Maintenance Items', 'wp-simple-firewall' );

			default:
				return '';
		}
	}

	private function sectionLabelOverrideForGroupKey( string $groupKey ) :string {
		switch ( $groupKey ) {
			case 'wordpress':
				return __( 'File Integrity', 'wp-simple-firewall' );

			case 'vulnerabilities':
				return __( 'Known Vulnerabilities', 'wp-simple-firewall' );

			default:
				return '';
		}
	}

	private function reviewMaintenanceLabelForGroupKey( string $groupKey ) :string {
		switch ( $groupKey ) {
			case 'maintenance_system':
				return __( 'System', 'wp-simple-firewall' );

			case 'maintenance_wordpress':
				return __( 'WordPress', 'wp-simple-firewall' );

			default:
				return '';
		}
	}
}
