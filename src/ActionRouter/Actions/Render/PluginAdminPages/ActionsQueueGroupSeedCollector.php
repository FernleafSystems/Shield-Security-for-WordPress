<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type AttentionItem from \FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems
 * @phpstan-import-type BucketSource from ActionsQueueBucketsBuilder
 * @phpstan-import-type GroupLink from ActionsQueueGroupsBuilder
 * @phpstan-import-type GroupSeed from ActionsQueueGroupContractBuilder
 * @phpstan-import-type MaintenanceQueueItem from MaintenanceQueueItemDisplayNormalizer
 * @phpstan-import-type QueueAssetPane from ScansResultsViewBuilder
 * @phpstan-import-type VulnerabilityAction from ScansVulnerabilitiesBuilder
 * @phpstan-import-type VulnerabilitiesPayload from ScansVulnerabilitiesBuilder
 */
class ActionsQueueGroupSeedCollector {

	private ?array $pluginsPane = null;
	private ?array $themesPane = null;
	private ?array $vulnerabilitiesPayload = null;

	private \Closure $buildPluginsPane;
	private \Closure $buildThemesPane;
	private \Closure $buildVulnerabilitiesPayload;
	private \Closure $normalizeMaintenanceQueueItems;

	public function __construct(
		private ActionsQueueGroupDefinitions $groupDefinitions,
		private ActionsQueueMaintenanceGroupSeedBuilder $maintenanceSeedBuilder,
		\Closure $buildPluginsPane,
		\Closure $buildThemesPane,
		\Closure $buildVulnerabilitiesPayload,
		\Closure $normalizeMaintenanceQueueItems
	) {
		$this->buildPluginsPane = $buildPluginsPane;
		$this->buildThemesPane = $buildThemesPane;
		$this->buildVulnerabilitiesPayload = $buildVulnerabilitiesPayload;
		$this->normalizeMaintenanceQueueItems = $normalizeMaintenanceQueueItems;
	}

	/**
	 * @phpstan-param BucketSource $bucketSource
	 * @return list<GroupSeed>
	 */
	public function collect( array $bucketSource ) :array {
		$seeds = [];
		$maintenanceItemsByKey = null;
		$pluginsExpanded = false;
		$themesExpanded = false;
		$vulnerableExpanded = false;
		$abandonedExpanded = false;

		foreach ( $bucketSource[ 'attention_items' ] as $item ) {
			$definitionKey = $this->groupDefinitions->groupKeyForSummaryKey( $item[ 'key' ] );

			switch ( $definitionKey ) {
				case 'plugins':
					if ( !$pluginsExpanded ) {
						$pluginsExpanded = true;
						$seeds = \array_merge( $seeds, $this->buildPluginThemeSeeds( 'plugins', $item ) );
					}
					continue 2;

				case 'themes':
					if ( !$themesExpanded ) {
						$themesExpanded = true;
						$seeds = \array_merge( $seeds, $this->buildPluginThemeSeeds( 'themes', $item ) );
					}
					continue 2;

				case 'vulnerabilities':
					if ( $item[ 'key' ] === 'vulnerable_assets' && !$vulnerableExpanded ) {
						$vulnerableExpanded = true;
						$seeds = \array_merge(
							$seeds,
							$this->buildVulnerabilitySeeds( $this->vulnerabilitiesPayload()[ 'sections' ][ 'vulnerable' ] ?? null, $item )
						);
					}
					elseif ( $item[ 'key' ] === 'abandoned' && !$abandonedExpanded ) {
						$abandonedExpanded = true;
						$seeds = \array_merge(
							$seeds,
							$this->buildVulnerabilitySeeds( $this->vulnerabilitiesPayload()[ 'sections' ][ 'abandoned' ] ?? null, $item )
						);
					}
					continue 2;

				case 'maintenance':
					if ( $maintenanceItemsByKey === null ) {
						$maintenanceItemsByKey = $this->indexMaintenanceItemsByKey(
							$this->normalizeMaintenanceQueueItems( \array_values( \array_filter(
								$bucketSource[ 'attention_items' ],
								static fn( array $maintenanceItem ) :bool => ( $maintenanceItem[ 'zone' ] ?? '' ) === 'maintenance'
							) ) )
						);
					}
					if ( isset( $maintenanceItemsByKey[ $item[ 'key' ] ] ) ) {
						$seeds[] = $this->maintenanceSeedBuilder->build( $maintenanceItemsByKey[ $item[ 'key' ] ] );
					}
					continue 2;
			}

			$this->mergeAttentionSeed( $seeds, $definitionKey, $item );
		}

		return \array_values( \array_filter( $seeds, static fn( array $seed ) :bool => $seed[ 'label' ] !== '' ) );
	}

	/**
	 * @param array<int|string,GroupSeed> $seeds
	 * @phpstan-param AttentionItem $item
	 */
	private function mergeAttentionSeed( array &$seeds, string $definitionKey, array $item ) :void {
		$seedKey = $definitionKey;
		if ( !isset( $seeds[ $seedKey ] ) ) {
			$definition = $this->groupDefinitions->definitionForGroupKey( $definitionKey );
			$seeds[ $seedKey ] = [
				'key'              => $definitionKey,
				'is_healthy'       => false,
				'definition_key'   => $definitionKey,
				'heading_label'    => $definition[ 'label' ],
				'label'            => $definition[ 'label' ],
				'item_count'       => 0,
				'status'           => 'good',
				'narrative'        => '',
				'detail_shell'     => $definition[ 'detail_shell' ],
				'links'            => [],
				'management_link'  => [],
				'detail_table'     => [],
				'attention_items'  => [],
				'maintenance_rows' => [],
				'summary_row'      => [],
			];
		}

		$seeds[ $seedKey ][ 'item_count' ] += $item[ 'count' ];
		$seeds[ $seedKey ][ 'status' ] = StatusPriority::highest( [
			$seeds[ $seedKey ][ 'status' ],
			$item[ 'severity' ],
		], 'good' );
		$seeds[ $seedKey ][ 'attention_items' ][] = $item;
	}

	/**
	 * @phpstan-param AttentionItem $item
	 * @return list<GroupSeed>
	 */
	private function buildPluginThemeSeeds( string $definitionKey, array $item ) :array {
		$definition = $this->groupDefinitions->definitionForGroupKey( $definitionKey );
		$cards = $definitionKey === 'plugins'
			? $this->pluginsPane()[ 'cards' ]
			: $this->themesPane()[ 'cards' ];
		$seeds = [];

		foreach ( $cards as $card ) {
			$fileCount = \max( 0, (int)( $card[ 'count_badge' ] ?? 0 ) );
			if ( $fileCount < 1 ) {
				continue;
			}

			$seeds[] = [
				'key'              => $definitionKey.':'.$card[ 'key' ],
				'is_healthy'       => false,
				'definition_key'   => $definitionKey,
				'heading_label'    => $definition[ 'label' ],
				'label'            => $card[ 'title' ],
				'item_count'       => $fileCount,
				'status'           => StatusPriority::normalize( $item[ 'severity' ], 'warning' ),
				'narrative'        => $card[ 'stat_text' ],
				'detail_shell'     => 'direct_table',
				'links'            => [],
				'management_link'  => [],
				'detail_table'     => $card[ 'table' ],
				'attention_items'  => [ $item ],
				'maintenance_rows' => [],
				'summary_row'      => [],
			];
		}

		return $seeds;
	}

	/**
	 * @phpstan-param AttentionItem $item
	 * @return list<GroupSeed>
	 */
	private function buildVulnerabilitySeeds( ?array $section, array $item ) :array {
		if ( !\is_array( $section ) ) {
			return [];
		}

		$seeds = [];
		foreach ( $section[ 'items' ] as $vulnerabilityItem ) {
			$seeds[] = [
				'key'              => 'vulnerabilities:'.$vulnerabilityItem[ 'key' ],
				'is_healthy'       => false,
				'definition_key'   => 'vulnerabilities',
				'heading_label'    => $section[ 'label' ],
				'label'            => $vulnerabilityItem[ 'label' ],
				'item_count'       => $vulnerabilityItem[ 'count' ],
				'status'           => StatusPriority::normalize( $vulnerabilityItem[ 'severity' ], 'warning' ),
				'narrative'        => $vulnerabilityItem[ 'description' ],
				'detail_shell'     => 'direct_table',
				'links'            => $this->buildGroupLinksFromVulnerabilityActions( $vulnerabilityItem[ 'actions' ] ),
				'management_link'  => [],
				'detail_table'     => [],
				'attention_items'  => [ $item ],
				'maintenance_rows' => [],
				'summary_row'      => [],
			];
		}

		return $seeds;
	}

	/**
	 * @param list<VulnerabilityAction> $actions
	 * @return list<GroupLink>
	 */
	private function buildGroupLinksFromVulnerabilityActions( array $actions ) :array {
		$links = [];
		foreach ( $actions as $action ) {
			$label = \trim( (string)( $action[ 'label' ] ?? '' ) );
			$href = \trim( (string)( $action[ 'href' ] ?? '' ) );
			if ( $label === '' || $href === '' ) {
				continue;
			}

			$attributes = \is_array( $action[ 'attributes' ] ?? null ) ? $action[ 'attributes' ] : [];
			$target = \trim( (string)( $attributes[ 'target' ] ?? '' ) );
			$links[] = [
				'label'      => $label,
				'href'       => $href,
				'target'     => $target,
				'rel'        => $target === '_blank' ? 'noopener noreferrer' : '',
				'icon_class' => $target === '_blank' ? 'bi-box-arrow-up-right' : '',
			];
		}
		return $links;
	}

	/**
	 * @param list<MaintenanceQueueItem> $maintenanceItems
	 * @return array<string,MaintenanceQueueItem>
	 */
	private function indexMaintenanceItemsByKey( array $maintenanceItems ) :array {
		$indexed = [];
		foreach ( $maintenanceItems as $item ) {
			$indexed[ $item[ 'key' ] ] = $item;
		}
		return $indexed;
	}

	/**
	 * @return QueueAssetPane
	 */
	private function pluginsPane() :array {
		if ( $this->pluginsPane === null ) {
			$this->pluginsPane = ( $this->buildPluginsPane )( [ 'include_ignored' => false, 'ignored_only' => false ] );
		}

		return $this->pluginsPane;
	}

	/**
	 * @return QueueAssetPane
	 */
	private function themesPane() :array {
		if ( $this->themesPane === null ) {
			$this->themesPane = ( $this->buildThemesPane )( [ 'include_ignored' => false, 'ignored_only' => false ] );
		}

		return $this->themesPane;
	}

	/**
	 * @return VulnerabilitiesPayload
	 */
	private function vulnerabilitiesPayload() :array {
		if ( $this->vulnerabilitiesPayload === null ) {
			$this->vulnerabilitiesPayload = ( $this->buildVulnerabilitiesPayload )();
		}

		return $this->vulnerabilitiesPayload;
	}

	/**
	 * @phpstan-param list<AttentionItem> $items
	 * @return list<MaintenanceQueueItem>
	 */
	private function normalizeMaintenanceQueueItems( array $items ) :array {
		return ( $this->normalizeMaintenanceQueueItems )( $items );
	}
}
