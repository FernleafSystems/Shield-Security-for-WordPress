<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	MaintenanceItemIgnore,
	MaintenanceItemUnignore
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type MaintenanceItemCta array{
 *   href:string,
 *   label:string,
 *   target?:string
 * }
 * @phpstan-type MaintenanceUiAction array{
 *   href:string,
 *   label:string,
 *   icon:string,
 *   tooltip:string,
 *   target?:string,
 *   ajax_action?:array<string,mixed>
 * }
 * @phpstan-type MaintenanceExpansionRow array{
 *   title:string,
 *   subtitle:string,
 *   context:string,
 *   identifier:string,
 *   action:MaintenanceItemCta,
 *   is_ignored:bool,
 *   secondary_actions:list<MaintenanceUiAction>
 * }
 * @phpstan-type MaintenanceExpansionTable array{
 *   columns:array{item:string,details:string,action:string},
 *   rows:list<MaintenanceExpansionRow>,
 *   empty_text:string
 * }
 * @phpstan-type MaintenanceItemExpansion array{
 *   id:string,
 *   type:string,
 *   status:string,
 *   table:MaintenanceExpansionTable
 * }
 * @phpstan-type QueueItem array{
 *   key:string,
 *   zone:string,
 *   label:string,
 *   count:int,
 *   severity:string,
 *   description:string,
 *   href:string,
 *   action:string,
 *   target:string
 * }
 * @phpstan-type MaintenanceState array{
 *   key:string,
 *   label:string,
 *   description:string,
 *   count:int,
 *   ignored_count:int,
 *   severity:string,
 *   href:string,
 *   action:string,
 *   target:string,
 *   supports_sub_items:bool,
 *   active_identifiers:list<string>,
 *   ignored_identifiers:list<string>
 * }
 * @phpstan-type MaintenanceQueueItem QueueItem&array{
 *   cta:array{}|MaintenanceItemCta,
 *   toggle_action:array{}|MaintenanceUiAction,
 *   expansion:array{}|MaintenanceItemExpansion
 * }
 */
class MaintenanceQueueItemDisplayNormalizer {

	/**
	 * @param list<QueueItem> $items
	 * @return list<MaintenanceQueueItem>
	 */
	public function normalizeAll( array $items ) :array {
		$statesByKey = $this->buildMaintenanceIssueStateProvider()->buildStates();
		$normalized = [];
		$seenKeys = [];

		foreach ( $items as $item ) {
			$normalizedItem = $this->normalizeWithState(
				$item,
				$statesByKey[ $item[ 'key' ] ] ?? $this->emptyStateForItem( $item )
			);
			$normalized[] = $normalizedItem;
			$seenKeys[ $normalizedItem[ 'key' ] ] = true;
		}

		foreach ( $statesByKey as $state ) {
			if ( isset( $seenKeys[ $state[ 'key' ] ] )
				|| $state[ 'count' ] > 0
				|| $state[ 'ignored_count' ] < 1 ) {
				continue;
			}

			$normalized[] = $this->normalizeWithState(
				[
					'key'         => $state[ 'key' ],
					'zone'        => 'maintenance',
					'label'       => $state[ 'label' ],
					'count'       => 0,
					'severity'    => $state[ 'severity' ],
					'description' => $state[ 'description' ],
					'href'        => $state[ 'href' ],
					'action'      => $state[ 'action' ],
					'target'      => $state[ 'target' ],
				],
				$state
			);
		}

		return \array_values( $normalized );
	}

	/**
	 * @param QueueItem $item
	 * @return MaintenanceQueueItem
	 */
	public function normalize( array $item ) :array {
		return $this->normalizeWithState( $item, $this->emptyStateForItem( $item ) );
	}

	/**
	 * @param QueueItem $item
	 * @param MaintenanceState $state
	 * @return MaintenanceQueueItem
	 */
	private function normalizeWithState( array $item, array $state ) :array {
		if ( $item[ 'zone' ] !== 'maintenance' ) {
			return $item;
		}

		$item[ 'label' ] = $state[ 'label' ] !== '' ? $state[ 'label' ] : $item[ 'label' ];
		$item[ 'description' ] = $state[ 'description' ] !== '' ? $state[ 'description' ] : $item[ 'description' ];
		$item[ 'count' ] = $state[ 'count' ];
		$item[ 'severity' ] = $state[ 'severity' ] !== '' ? $state[ 'severity' ] : $item[ 'severity' ];
		$item[ 'cta' ] = $this->buildCta( $item );
		$item[ 'toggle_action' ] = $this->buildToggleAction( $item, $state );
		$item[ 'expansion' ] = $this->buildExpansion( $item, $state );
		return $item;
	}

	/**
	 * @param QueueItem $item
	 * @return array{}|MaintenanceItemCta
	 */
	private function buildCta( array $item ) :array {
		$href = $item[ 'href' ];
		$target = $item[ 'target' ];

		switch ( $item[ 'key' ] ) {
			case 'wp_plugins_inactive':
				return [
					'href'  => $href,
					'label' => __( 'Go to plugins', 'wp-simple-firewall' ),
				];

			case 'wp_themes_inactive':
				return [
					'href'  => $href,
					'label' => __( 'Go to themes', 'wp-simple-firewall' ),
				];

			default:
				$action = $item[ 'action' ];
				return ( $href !== '' && $action !== '' )
					? [
						'href'   => $href,
						'label'  => $action,
						'target' => $target,
					]
					: [];
		}
	}

	/**
	 * @param QueueItem $item
	 * @return array{}|MaintenanceItemExpansion
	 */
	private function buildExpansion( array $item, array $state ) :array {
		$rows = [];
		$ignoredIdentifiers = $state[ 'ignored_identifiers' ];

		switch ( $item[ 'key' ] ) {
			case 'wp_plugins_updates':
				$rows = $this->buildPluginUpdateRows( $ignoredIdentifiers );
				break;

			case 'wp_themes_updates':
				$rows = $this->buildThemeUpdateRows( $ignoredIdentifiers );
				break;

			case 'wp_plugins_inactive':
				$rows = $this->buildInactivePluginRows( $ignoredIdentifiers );
				break;

			case 'wp_themes_inactive':
				$rows = $this->buildInactiveThemeRows( $ignoredIdentifiers );
				break;
		}

		return $rows === []
			? []
			: $this->buildSimpleTableExpansion( $item, $rows );
	}

	/**
	 * @return list<MaintenanceExpansionRow>
	 */
	private function buildPluginUpdateRows( array $ignoredIdentifiers ) :array {
		$rows = [];
		$plugins = Services::WpPlugins();
		$updates = $plugins->getUpdates();

		foreach ( \array_keys( $plugins->getPlugins() ) as $file ) {
			if ( !isset( $updates[ $file ] ) ) {
				continue;
			}

			$plugin = $plugins->getPluginAsVo( $file, true );
			if ( !$plugin instanceof WpPluginVo ) {
				continue;
			}

			$rows[] = $this->buildExpansionRow(
				$this->pluginTitle( $plugin ),
				__( 'Plugin update available', 'wp-simple-firewall' ),
				$this->buildUpdateContext( $plugin->Version, $this->extractUpdateVersion( $updates[ $file ] ) ),
				$plugin->file,
				$this->buildRowAction(
					$plugins->getUrl_Upgrade( $plugin->file ),
					__( 'Update', 'wp-simple-firewall' )
				),
				\in_array( $plugin->file, $ignoredIdentifiers, true ),
				'wp_plugins_updates'
			);
		}

		return $this->sortExpansionRows( $rows );
	}

	/**
	 * @return list<MaintenanceExpansionRow>
	 */
	private function buildThemeUpdateRows( array $ignoredIdentifiers ) :array {
		$rows = [];
		$themes = Services::WpThemes();
		$updates = $themes->getUpdates();
		$updatesHref = Services::WpGeneral()->getAdminUrl_Updates();

		foreach ( \array_keys( $themes->getThemes() ) as $stylesheet ) {
			if ( !isset( $updates[ $stylesheet ] ) ) {
				continue;
			}

			$theme = $themes->getThemeAsVo( $stylesheet, true );
			if ( !$theme instanceof WpThemeVo ) {
				continue;
			}

			$rows[] = $this->buildExpansionRow(
				$this->themeTitle( $theme ),
				__( 'Theme update available', 'wp-simple-firewall' ),
				$this->buildUpdateContext( $theme->Version, $this->extractUpdateVersion( $updates[ $stylesheet ] ) ),
				$theme->stylesheet,
				$this->buildRowAction( $updatesHref, __( 'Open updates', 'wp-simple-firewall' ) ),
				\in_array( $theme->stylesheet, $ignoredIdentifiers, true ),
				'wp_themes_updates'
			);
		}

		return $this->sortExpansionRows( $rows );
	}

	/**
	 * @return list<MaintenanceExpansionRow>
	 */
	private function buildInactivePluginRows( array $ignoredIdentifiers ) :array {
		$rows = [];
		$plugins = Services::WpPlugins();
		$activePlugins = \array_fill_keys( $plugins->getActivePlugins(), true );

		foreach ( \array_keys( $plugins->getPlugins() ) as $file ) {
			if ( isset( $activePlugins[ $file ] ) ) {
				continue;
			}

			$plugin = $plugins->getPluginAsVo( $file, true );
			if ( !$plugin instanceof WpPluginVo ) {
				continue;
			}

			$rows[] = $this->buildExpansionRow(
				$this->pluginTitle( $plugin ),
				__( 'Plugin is currently inactive', 'wp-simple-firewall' ),
				$this->buildVersionContext( $plugin->Version ),
				$plugin->file,
				$this->buildRowAction( $this->buildPluginSearchHref( $plugin->file ), __( 'Open plugin', 'wp-simple-firewall' ) ),
				\in_array( $plugin->file, $ignoredIdentifiers, true ),
				'wp_plugins_inactive'
			);
		}

		return $this->sortExpansionRows( $rows );
	}

	/**
	 * @return list<MaintenanceExpansionRow>
	 */
	private function buildInactiveThemeRows( array $ignoredIdentifiers ) :array {
		$rows = [];
		$themes = Services::WpThemes();
		$activeThemes = \array_fill_keys( $this->getActiveThemeStylesheets(), true );
		$themesHref = Services::WpGeneral()->getAdminUrl_Themes();

		foreach ( \array_keys( $themes->getThemes() ) as $stylesheet ) {
			if ( isset( $activeThemes[ $stylesheet ] ) ) {
				continue;
			}

			$theme = $themes->getThemeAsVo( $stylesheet, true );
			if ( !$theme instanceof WpThemeVo ) {
				continue;
			}

			$rows[] = $this->buildExpansionRow(
				$this->themeTitle( $theme ),
				__( 'Theme is currently inactive', 'wp-simple-firewall' ),
				$this->buildVersionContext( $theme->Version ),
				$theme->stylesheet,
				$this->buildRowAction( $themesHref, __( 'Open themes', 'wp-simple-firewall' ) ),
				\in_array( $theme->stylesheet, $ignoredIdentifiers, true ),
				'wp_themes_inactive'
			);
		}

		return $this->sortExpansionRows( $rows );
	}

	/**
	 * @param QueueItem $item
	 * @param list<MaintenanceExpansionRow> $rows
	 * @return MaintenanceItemExpansion
	 */
	private function buildSimpleTableExpansion( array $item, array $rows ) :array {
		return [
			'id'     => 'maintenance-expand-'.sanitize_key( $item[ 'key' ] ),
			'type'   => DetailExpansionType::SIMPLE_TABLE,
			'status' => $item[ 'severity' ],
			'table'  => [
				'columns'    => [
					'item'    => __( 'Item', 'wp-simple-firewall' ),
					'details' => __( 'Details', 'wp-simple-firewall' ),
					'action'  => __( 'Action', 'wp-simple-firewall' ),
				],
				'rows'       => $rows,
				'empty_text' => __( 'No items are currently available.', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return MaintenanceExpansionRow
	 */
	private function buildExpansionRow(
		string $title,
		string $subtitle,
		string $context,
		string $identifier,
		array $action,
		bool $isIgnored,
		string $maintenanceKey
	) :array {
		return [
			'title'             => $title,
			'subtitle'          => $subtitle,
			'context'           => $context,
			'identifier'        => $identifier,
			'action'            => $action,
			'is_ignored'        => $isIgnored,
			'secondary_actions' => [
				$this->buildRowToggleAction( $maintenanceKey, $identifier, $isIgnored ),
			],
		];
	}

	/**
	 * @param list<MaintenanceExpansionRow> $rows
	 * @return list<MaintenanceExpansionRow>
	 */
	private function sortExpansionRows( array $rows ) :array {
		\uasort( $rows, static function ( array $a, array $b ) :int {
			$ignoredCmp = (int)( $a[ 'is_ignored' ] ?? false ) <=> (int)( $b[ 'is_ignored' ] ?? false );
			if ( $ignoredCmp !== 0 ) {
				return $ignoredCmp;
			}
			return \strnatcasecmp( $a[ 'title' ], $b[ 'title' ] );
		} );
		return \array_values( $rows );
	}

	private function pluginTitle( WpPluginVo $plugin ) :string {
		return $plugin->Title !== ''
			? $plugin->Title
			: ( $plugin->Name !== '' ? $plugin->Name : $plugin->file );
	}

	private function themeTitle( WpThemeVo $theme ) :string {
		return $theme->Name !== ''
			? $theme->Name
			: $theme->stylesheet;
	}

	/**
	 * @param QueueItem $item
	 * @param MaintenanceState $state
	 * @return array{}|MaintenanceUiAction
	 */
	private function buildToggleAction( array $item, array $state ) :array {
		if ( $state[ 'supports_sub_items' ] ) {
			return [];
		}

		return $this->buildUiToggleAction(
			$item[ 'key' ],
			'',
			$state[ 'ignored_count' ] > 0 && $state[ 'count' ] === 0
		);
	}

	/**
	 * @return MaintenanceItemCta
	 */
	private function buildRowAction( string $href, string $label ) :array {
		return [
			'href'  => $href,
			'label' => $label,
		];
	}

	/**
	 * @return MaintenanceUiAction
	 */
	private function buildRowToggleAction( string $maintenanceKey, string $identifier, bool $isIgnored ) :array {
		return $this->buildUiToggleAction( $maintenanceKey, $identifier, $isIgnored );
	}

	/**
	 * @return MaintenanceUiAction
	 */
	private function buildUiToggleAction( string $maintenanceKey, string $identifier, bool $isIgnored ) :array {
		$actionClass = $isIgnored ? MaintenanceItemUnignore::class : MaintenanceItemIgnore::class;
		$label = $isIgnored ? __( 'Unignore', 'wp-simple-firewall' ) : __( 'Ignore', 'wp-simple-firewall' );
		$tooltip = $isIgnored
			? __( 'Stop ignoring this maintenance item', 'wp-simple-firewall' )
			: __( 'Ignore this maintenance item', 'wp-simple-firewall' );
		$ajaxAction = ActionData::Build( $actionClass, true, \array_filter( [
			'maintenance_key' => $maintenanceKey,
			'identifier'      => $identifier,
		], static fn( string $value ) :bool => $value !== '' ) );

		return [
			'href'        => 'javascript:{}',
			'label'       => $label,
			'icon'        => $isIgnored ? 'bi bi-eye-fill' : 'bi bi-eye-slash-fill',
			'tooltip'     => $tooltip,
			'ajax_action' => $ajaxAction,
		];
	}

	private function buildUpdateContext( string $currentVersion, string $availableVersion ) :string {
		if ( $currentVersion !== '' && $availableVersion !== '' ) {
			return \sprintf(
				__( 'Current: %1$s | Available: %2$s', 'wp-simple-firewall' ),
				$currentVersion,
				$availableVersion
			);
		}
		if ( $currentVersion !== '' ) {
			return $this->buildVersionContext( $currentVersion );
		}
		return __( 'Update available', 'wp-simple-firewall' );
	}

	private function buildVersionContext( string $version ) :string {
		return $version !== ''
			? \sprintf( __( 'Version: %s', 'wp-simple-firewall' ), $version )
			: __( 'Version unavailable', 'wp-simple-firewall' );
	}

	/**
	 * @param array<string,mixed>|object $updateData
	 */
	private function extractUpdateVersion( $updateData ) :string {
		if ( \is_array( $updateData ) ) {
			return \trim( (string)( $updateData[ 'new_version' ] ?? $updateData[ 'version' ] ?? '' ) );
		}
		if ( \is_object( $updateData ) ) {
			return \trim( (string)( $updateData->new_version ?? $updateData->version ?? '' ) );
		}
		return '';
	}

	/**
	 * @return list<string>
	 */
	private function getActiveThemeStylesheets() :array {
		$themes = Services::WpThemes();
		$active = [];

		$current = $themes->getCurrent();
		if ( \is_object( $current ) && \method_exists( $current, 'get_stylesheet' ) ) {
			$active[] = $current->get_stylesheet();
		}

		$parent = $themes->getCurrentParent();
		if ( \is_object( $parent ) && \method_exists( $parent, 'get_stylesheet' ) ) {
			$active[] = $parent->get_stylesheet();
		}

		return \array_values( \array_filter( \array_unique( $active ) ) );
	}

	/**
	 * @param QueueItem $item
	 * @return MaintenanceState
	 */
	private function emptyStateForItem( array $item ) :array {
		return [
			'key'                 => $item[ 'key' ],
			'label'               => $item[ 'label' ],
			'description'         => $item[ 'description' ],
			'count'               => $item[ 'count' ],
			'ignored_count'       => 0,
			'severity'            => $item[ 'severity' ],
			'href'                => $item[ 'href' ],
			'action'              => $item[ 'action' ],
			'target'              => $item[ 'target' ],
			'supports_sub_items'  => false,
			'active_identifiers'  => [],
			'ignored_identifiers' => [],
		];
	}

	protected function buildMaintenanceIssueStateProvider() :MaintenanceIssueStateProvider {
		return new MaintenanceIssueStateProvider();
	}

	private function buildPluginSearchHref( string $pluginFile ) :string {
		return Services::WpGeneral()->getAdminUrl_Plugins().'?s='.rawurlencode( $pluginFile );
	}
}
