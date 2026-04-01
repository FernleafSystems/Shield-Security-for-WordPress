<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker,
	Maintenance,
	Malware,
	Plugins,
	Themes,
	Vulnerabilities,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\ActionsQueueItemIcons;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

/**
 * @phpstan-type GroupDefinition array{
 *   key:string,
 *   label:string,
 *   sort_order:int,
 *   icon_class:string,
 *   detail_shell:'asset_cards'|'direct_table'|'maintenance',
 *   card_type:'expandable'|'linked'|'category',
 *   drill_hint_single:string,
 *   drill_hint_plural:string,
 *   summary_keys:list<string>,
 *   healthy_ignored_source:''|'wordpress'|'plugins'|'themes',
 *   render_action_class:class-string<BaseAction>,
 *   render_action_data:array<string,mixed>
 * }
 * @phpstan-type SummaryBehaviour array{
 *   definition_key:string,
 *   seed_strategy:'attention_aggregate'|'asset_cards'|'vulnerability_section'|'maintenance',
 *   asset_source?:'plugins'|'themes',
 *   vulnerability_section?:'vulnerable'|'abandoned'
 * }
 */
class ActionsQueueGroupDefinitions {

	private const GROUP_METADATA = [
		'wordpress' => [
			'sort_order'            => 2,
			'detail_shell'          => 'direct_table',
			'card_type'             => 'expandable',
			'drill_hint_single'     => 'View %s file',
			'drill_hint_plural'     => 'View %s files',
			'healthy_ignored_source' => 'wordpress',
			'render_action_class'   => Wordpress::class,
			'render_action_data'    => 'scan_results',
		],
		'plugins' => [
			'sort_order'            => 3,
			'detail_shell'          => 'asset_cards',
			'card_type'             => 'expandable',
			'drill_hint_single'     => 'View %s file',
			'drill_hint_plural'     => 'View %s files',
			'healthy_ignored_source' => 'plugins',
			'render_action_class'   => Plugins::class,
			'render_action_data'    => 'scan_results',
		],
		'themes' => [
			'sort_order'            => 4,
			'detail_shell'          => 'asset_cards',
			'card_type'             => 'expandable',
			'drill_hint_single'     => 'View %s file',
			'drill_hint_plural'     => 'View %s files',
			'healthy_ignored_source' => 'themes',
			'render_action_class'   => Themes::class,
			'render_action_data'    => 'scan_results',
		],
		'vulnerabilities' => [
			'sort_order'            => 0,
			'detail_shell'          => 'direct_table',
			'card_type'             => 'linked',
			'drill_hint_single'     => '',
			'drill_hint_plural'     => '',
			'healthy_ignored_source' => '',
			'render_action_class'   => Vulnerabilities::class,
			'render_action_data'    => [
				'section' => 'vulnerable',
			],
		],
		'abandoned' => [
			'sort_order'            => 1,
			'detail_shell'          => 'direct_table',
			'card_type'             => 'linked',
			'drill_hint_single'     => '',
			'drill_hint_plural'     => '',
			'healthy_ignored_source' => '',
			'render_action_class'   => Vulnerabilities::class,
			'render_action_data'    => [
				'section' => 'abandoned',
			],
		],
		'malware' => [
			'label_override'        => 'Malware Detections',
			'sort_order'            => 5,
			'detail_shell'          => 'direct_table',
			'card_type'             => 'expandable',
			'drill_hint_single'     => 'View %s file',
			'drill_hint_plural'     => 'View %s files',
			'healthy_ignored_source' => '',
			'render_action_class'   => Malware::class,
			'render_action_data'    => 'scan_results',
		],
		'file_locker' => [
			'label_override'        => 'File Changes',
			'sort_order'            => 6,
			'detail_shell'          => 'asset_cards',
			'card_type'             => 'expandable',
			'drill_hint_single'     => 'View %s file',
			'drill_hint_plural'     => 'View %s files',
			'healthy_ignored_source' => '',
			'render_action_class'   => FileLocker::class,
			'render_action_data'    => 'scan_results',
		],
		'maintenance' => [
			'label_override'        => 'Maintenance Items',
			'sort_order'            => 7,
			'icon_class'            => 'bi bi-wrench',
			'detail_shell'          => 'maintenance',
			'card_type'             => 'category',
			'drill_hint_single'     => '',
			'drill_hint_plural'     => '',
			'healthy_ignored_source' => '',
			'render_action_class'   => Maintenance::class,
			'render_action_data'    => 'none',
			'summary_keys'          => [],
		],
	];

	private const SUMMARY_BEHAVIOUR_OVERRIDES = [
		'plugin_files' => [
			'definition_key' => 'plugins',
			'seed_strategy'  => 'asset_cards',
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
			'label'      => 'System',
			'icon_key'   => 'system_ssl_certificate',
			'item_keys'  => [
				'system_lib_openssl',
				'system_ssl_certificate',
				'system_php_version',
			],
		],
		'maintenance_wordpress' => [
			'label'      => 'WordPress',
			'icon_key'   => 'wp_updates',
			'item_keys'  => [
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

	private ActionsQueueScanResultsOptions $queueScanResultsOptions;

	public function __construct( ?ActionsQueueScanResultsOptions $queueScanResultsOptions = null ) {
		$this->queueScanResultsOptions = $queueScanResultsOptions ?? new ActionsQueueScanResultsOptions();
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
			$definitions[ $key ] = [
				'key'                 => $key,
				'label'               => __( $metadata[ 'label_override' ] ?? $scanDefinition[ 'label' ], 'wp-simple-firewall' ),
				'sort_order'          => $metadata[ 'sort_order' ],
				'icon_class'          => $metadata[ 'icon_class' ] ?? PluginNavs::actionsLandingScanRailIconClass( $key ),
				'detail_shell'        => $metadata[ 'detail_shell' ],
				'card_type'           => $metadata[ 'card_type' ],
				'drill_hint_single'   => __( $metadata[ 'drill_hint_single' ], 'wp-simple-firewall' ),
				'drill_hint_plural'   => __( $metadata[ 'drill_hint_plural' ], 'wp-simple-firewall' ),
				'summary_keys'        => $scanDefinition[ 'summary_keys' ],
				'healthy_ignored_source' => $metadata[ 'healthy_ignored_source' ],
				'render_action_class' => $metadata[ 'render_action_class' ],
				'render_action_data'  => $this->renderActionDataForMode( $metadata[ 'render_action_data' ] ),
			];
		}

		$maintenance = self::GROUP_METADATA[ 'maintenance' ];
		$definitions[ 'maintenance' ] = [
			'key'                 => 'maintenance',
			'label'               => __( $maintenance[ 'label_override' ], 'wp-simple-firewall' ),
			'sort_order'          => $maintenance[ 'sort_order' ],
			'icon_class'          => $maintenance[ 'icon_class' ],
			'detail_shell'        => $maintenance[ 'detail_shell' ],
			'card_type'           => $maintenance[ 'card_type' ],
			'drill_hint_single'   => '',
			'drill_hint_plural'   => '',
			'summary_keys'        => [],
			'healthy_ignored_source' => '',
			'render_action_class' => $maintenance[ 'render_action_class' ],
			'render_action_data'  => [],
		];

		$itemIcons = new ActionsQueueItemIcons();
		foreach ( self::REVIEW_MAINTENANCE_GROUPS as $groupKey => $group ) {
			$definitions[ $groupKey ] = [
				'key'                 => $groupKey,
				'label'               => __( $group[ 'label' ], 'wp-simple-firewall' ),
				'sort_order'          => $maintenance[ 'sort_order' ],
				'icon_class'          => $itemIcons->iconClassForKey( $group[ 'icon_key' ] ),
				'detail_shell'        => $maintenance[ 'detail_shell' ],
				'card_type'           => $maintenance[ 'card_type' ],
				'drill_hint_single'   => '',
				'drill_hint_plural'   => '',
				'summary_keys'        => [],
				'healthy_ignored_source' => '',
				'render_action_class' => $maintenance[ 'render_action_class' ],
				'render_action_data'  => [],
			];
		}

		$this->definitions = $definitions;
		return $this->definitions;
	}

	public function groupKeyForSummaryKey( string $summaryKey ) :string {
		return $this->summaryBehaviourForKey( $summaryKey )[ 'definition_key' ];
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
		return (string)( $this->definitionForGroupKey( $groupKey )[ 'healthy_ignored_source' ] ?? '' );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function ignoredRenderActionDataForGroupKey( string $groupKey, int $ignoredCount ) :array {
		return $ignoredCount > 0 && $this->healthyIgnoredSourceForGroupKey( $groupKey ) !== ''
			? $this->queueScanResultsOptions->buildIgnoredOnlyActionData()
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
	 * @return array<string,mixed>
	 */
	private function renderActionDataForMode( $mode ) :array {
		if ( \is_array( $mode ) ) {
			return $mode;
		}

		return $mode === 'scan_results'
			? $this->queueScanResultsOptions->buildDisplayContextActionData()
			: [];
	}
}
