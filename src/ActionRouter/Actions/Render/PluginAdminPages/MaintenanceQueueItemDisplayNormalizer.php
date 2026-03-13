<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

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
 * @phpstan-type MaintenanceExpansionRow array{
 *   title:string,
 *   subtitle:string,
 *   context:string,
 *   identifier:string,
 *   action:MaintenanceItemCta
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
 * @phpstan-type MaintenanceQueueItem QueueItem&array{cta:array{}|MaintenanceItemCta, expansion:array{}|MaintenanceItemExpansion}
 */
class MaintenanceQueueItemDisplayNormalizer {

	/**
	 * @param list<QueueItem> $items
	 * @return list<MaintenanceQueueItem>
	 */
	public function normalizeAll( array $items ) :array {
		return \array_values( \array_map(
			fn( array $item ) :array => $this->normalize( $item ),
			$items
		) );
	}

	/**
	 * @param QueueItem $item
	 * @return MaintenanceQueueItem
	 */
	public function normalize( array $item ) :array {
		if ( $item[ 'zone' ] !== 'maintenance' ) {
			return $item;
		}

		$item[ 'cta' ] = $this->buildCta( $item );
		$item[ 'expansion' ] = $this->buildExpansion( $item );
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
	private function buildExpansion( array $item ) :array {
		$rows = [];

		switch ( $item[ 'key' ] ) {
			case 'wp_plugins_updates':
				$rows = $this->buildPluginUpdateRows();
				break;

			case 'wp_themes_updates':
				$rows = $this->buildThemeUpdateRows();
				break;

			case 'wp_plugins_inactive':
				$rows = $this->buildInactivePluginRows();
				break;

			case 'wp_themes_inactive':
				$rows = $this->buildInactiveThemeRows();
				break;
		}

		return $rows === []
			? []
			: $this->buildSimpleTableExpansion( $item, $rows );
	}

	/**
	 * @return list<MaintenanceExpansionRow>
	 */
	private function buildPluginUpdateRows() :array {
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
				)
			);
		}

		return $this->sortExpansionRows( $rows );
	}

	/**
	 * @return list<MaintenanceExpansionRow>
	 */
	private function buildThemeUpdateRows() :array {
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
				$this->buildRowAction( $updatesHref, __( 'Open updates', 'wp-simple-firewall' ) )
			);
		}

		return $this->sortExpansionRows( $rows );
	}

	/**
	 * @return list<MaintenanceExpansionRow>
	 */
	private function buildInactivePluginRows() :array {
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
				$this->buildRowAction(
					$plugins->getUrl_Activate( $plugin->file ),
					__( 'Activate', 'wp-simple-firewall' )
				)
			);
		}

		return $this->sortExpansionRows( $rows );
	}

	/**
	 * @return list<MaintenanceExpansionRow>
	 */
	private function buildInactiveThemeRows() :array {
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
				$this->buildRowAction( $themesHref, __( 'Open themes', 'wp-simple-firewall' ) )
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
		array $action
	) :array {
		return [
			'title'      => $title,
			'subtitle'   => $subtitle,
			'context'    => $context,
			'identifier' => $identifier,
			'action'     => $action,
		];
	}

	/**
	 * @param list<MaintenanceExpansionRow> $rows
	 * @return list<MaintenanceExpansionRow>
	 */
	private function sortExpansionRows( array $rows ) :array {
		\uasort( $rows, static fn( array $a, array $b ) :int => \strnatcasecmp( $a[ 'title' ], $b[ 'title' ] ) );
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
	 * @return MaintenanceItemCta
	 */
	private function buildRowAction( string $href, string $label ) :array {
		return [
			'href'  => $href,
			'label' => $label,
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
}
