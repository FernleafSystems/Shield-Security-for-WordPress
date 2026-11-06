<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScanResultsTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type VulnerabilityAction array{
 *   href:string,
 *   label:string,
 *   type:string,
 *   icon?:string,
 *   attributes?:array<string,string>
 * }
 * @phpstan-type IgnoreToggleAction array{
 *   kind:'ignore'|'unignore',
 *   label:string,
 *   icon:string,
 *   tooltip:string,
 *   ajax_action:array<string,mixed>
 * }
 * @phpstan-type VulnerabilityItem array{
 *   key:string,
 *   asset_key:string,
 *   asset_type:'plugin'|'theme',
 *   label:string,
 *   description:string,
 *   count:int,
 *   severity:string,
 *   actions:list<VulnerabilityAction>,
 *   ignore_action?:IgnoreToggleAction
 * }
 * @phpstan-type VulnerabilitySection array{
 *   label:string,
 *   count:int,
 *   status:string,
 *   items:list<VulnerabilityItem>,
 *   ignored_items:list<VulnerabilityItem>
 * }
 * @phpstan-type VulnerabilitiesPayload array{
 *   count:int,
 *   status:string,
 *   sections:array{
 *     vulnerable:VulnerabilitySection,
 *     abandoned:VulnerabilitySection
 *   }
 * }
 */
class ScansVulnerabilitiesBuilder {

	use PluginControllerConsumer;

	/**
	 * @return VulnerabilitiesPayload
	 */
	public function build() :array {
		return $this->buildForOptionalAssetType( '' );
	}

	/**
	 * @param 'plugin'|'theme' $assetType
	 * @return VulnerabilitiesPayload
	 */
	public function buildForAssetType( string $assetType ) :array {
		$assetType = $this->normalizeAssetTypeFilter( $assetType );
		if ( $assetType === '' ) {
			throw new \InvalidArgumentException( 'Asset type filter must be "plugin" or "theme".' );
		}

		return $this->buildForOptionalAssetType( $assetType );
	}

	/**
	 * @param 'plugin'|'theme'|'' $assetType
	 * @return VulnerabilitiesPayload
	 */
	private function buildForOptionalAssetType( string $assetType ) :array {
		$vulnerableItems = $this->buildVulnerableItems( $assetType );
		$abandonedItems = $this->buildAbandonedItems( $assetType );

		return [
			'count'    => $this->countDistinctAffectedAssets( $vulnerableItems, $abandonedItems ),
			'status'   => !empty( $vulnerableItems )
				? 'critical'
				: ( !empty( $abandonedItems ) ? 'critical' : 'good' ),
			'sections' => [
				'vulnerable' => $this->buildSection(
					__( 'Known Vulnerabilities', 'wp-simple-firewall' ),
					$vulnerableItems
				),
				'abandoned'  => $this->buildSection(
					__( 'Abandoned Assets', 'wp-simple-firewall' ),
					$abandonedItems,
					$this->buildIgnoredAbandonedItems( $assetType )
				),
			],
		];
	}

	/**
	 * @param 'plugin'|'theme'|'' $assetType
	 * @return list<VulnerabilityItem>
	 */
	protected function buildVulnerableItems( string $assetType = '' ) :array {
		try {
			$results = self::con()->comps->scans->WPV()->getResultsForDisplay();
		}
		catch ( \Throwable $e ) {
			return [];
		}

		$items = [];
		$itemsByTypedAsset = [];
		foreach ( $results->getAllItems() as $item ) {
			$itemType = (string)( $item->VO->item_type ?? '' );
			$slug = (string)( $item->VO->item_id ?? '' );
			if ( !$this->isKnownScanItemType( $itemType ) ) {
				continue;
			}

			$groupKey = $itemType."\n".$slug;
			$itemsByTypedAsset[ $groupKey ][ 'item_type' ] = $itemType;
			$itemsByTypedAsset[ $groupKey ][ 'slug' ] = $slug;
			$itemsByTypedAsset[ $groupKey ][ 'items' ][] = $item;
		}

		foreach ( $itemsByTypedAsset as $typedAsset ) {
			$asset = $this->resolveAssetForScanItem(
				(string)$typedAsset[ 'slug' ],
				(string)$typedAsset[ 'item_type' ]
			);
			if ( $asset === null ) {
				continue;
			}
			if ( !$this->assetMatchesFilter( $asset, $assetType ) ) {
				continue;
			}

			$count = \count( $typedAsset[ 'items' ] );
			$items[] = $this->buildAssetRow(
				'vulnerability',
				$asset,
				$count,
				$this->buildVulnerableAssetActions( $asset ),
				\sprintf(
					_n( '%s known vulnerability needs review.', '%s known vulnerabilities need review.', $count, 'wp-simple-firewall' ),
					$count
				)
			);
		}

		return $this->sortItems( $items );
	}

	/**
	 * @param 'plugin'|'theme'|'' $assetType
	 * @return list<VulnerabilityItem>
	 */
	protected function buildAbandonedItems( string $assetType = '' ) :array {
		try {
			$results = self::con()->comps->scans->APC()->getResultsForDisplay();
		}
		catch ( \Throwable $e ) {
			return [];
		}

		return $this->buildAbandonedRows( $results->getAllItems(), $assetType, false );
	}

	/**
	 * Abandoned results the user chose to ignore. These count towards nothing; they're listed only so that
	 * the user can see them and stop ignoring them.
	 * @param 'plugin'|'theme'|'' $assetType
	 * @return list<VulnerabilityItem>
	 */
	protected function buildIgnoredAbandonedItems( string $assetType = '' ) :array {
		try {
			$results = self::con()->comps->scans->APC()->getAllResults()->getIgnored();
		}
		catch ( \Throwable $e ) {
			return [];
		}

		return $this->buildAbandonedRows( $results->getAllItems(), $assetType, true );
	}

	/**
	 * @param list<\FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc\ResultItem> $results
	 * @param 'plugin'|'theme'|'' $assetType
	 * @return list<VulnerabilityItem>
	 */
	private function buildAbandonedRows( array $results, string $assetType, bool $isIgnored ) :array {
		$items = [];
		foreach ( $results as $item ) {
			$asset = $this->resolveAssetForScanItem(
				(string)( $item->VO->item_id ?? '' ),
				(string)( $item->VO->item_type ?? '' )
			);
			if ( $asset === null ) {
				continue;
			}
			if ( !$this->assetMatchesFilter( $asset, $assetType ) ) {
				continue;
			}

			$items[] = \array_merge(
				$this->buildAssetRow(
					'abandoned',
					$asset,
					1,
					$this->buildAbandonedAssetActions( $asset ),
					__( 'This asset appears to be abandoned and should be reviewed.', 'wp-simple-firewall' )
				),
				[ 'ignore_action' => $this->buildIgnoreToggleAction( (int)$item->VO->resultitem_id, $isIgnored, $asset instanceof WpPluginVo ) ]
			);
		}

		return $this->sortItems( $items );
	}

	/**
	 * @return IgnoreToggleAction
	 */
	private function buildIgnoreToggleAction( int $resultItemID, bool $isIgnored, bool $isPlugin ) :array {
		if ( $isPlugin ) {
			$tooltip = $isIgnored
				? __( 'Stop ignoring this abandoned plugin', 'wp-simple-firewall' )
				: __( 'Ignore this abandoned plugin', 'wp-simple-firewall' );
		}
		else {
			$tooltip = $isIgnored
				? __( 'Stop ignoring this abandoned theme', 'wp-simple-firewall' )
				: __( 'Ignore this abandoned theme', 'wp-simple-firewall' );
		}
		return [
			'kind'        => $isIgnored ? 'unignore' : 'ignore',
			'label'       => $isIgnored ? __( 'Stop ignoring', 'wp-simple-firewall' ) : __( 'Ignore', 'wp-simple-firewall' ),
			'icon'        => $isIgnored ? 'bi bi-eye-fill' : 'bi bi-eye-slash-fill',
			'tooltip'     => $tooltip,
			'ajax_action' => ActionData::Build( ScanResultsTableAction::class, true, [
				'sub_action' => $isIgnored ? 'unignore' : 'ignore',
				'rids'       => [ $resultItemID ],
			] ),
		];
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return VulnerabilityItem
	 */
	private function buildAssetRow( string $prefix, $asset, int $count, array $actions, string $description ) :array {
		$isPlugin = $asset instanceof WpPluginVo;
		$assetType = $isPlugin ? 'plugin' : 'theme';
		$name = $isPlugin ? $asset->Title : $asset->Name;

		return [
			'key'         => $prefix.'-'.$assetType.'-'.$asset->unique_id,
			'asset_key'   => (string)$asset->unique_id,
			'asset_type'  => $assetType,
			'label'       => (string)$name,
			'description' => $description,
			'count'       => $count,
			'severity'    => 'critical',
			'actions'     => $actions,
		];
	}

	/**
	 * @param list<VulnerabilityItem> $items
	 * @param list<VulnerabilityItem> $ignoredItems never counted, and never affect the status
	 * @return VulnerabilitySection
	 */
	private function buildSection( string $label, array $items, array $ignoredItems = [] ) :array {
		return [
			'label'         => $label,
			'count'         => \count( $items ),
			'status'        => empty( $items ) ? 'good' : 'critical',
			'items'         => $items,
			'ignored_items' => $ignoredItems,
		];
	}

	/**
	 * @param string $itemType
	 * @return WpPluginVo|WpThemeVo|null
	 */
	private function resolveAssetForScanItem( string $slug, string $itemType ) {
		switch ( $itemType ) {
			case 'p':
				return Services::WpPlugins()->getPluginAsVo( $slug, true );
			case 't':
				return Services::WpThemes()->getThemeAsVo( $slug, true );
			default:
				return null;
		}
	}

	private function isKnownScanItemType( string $itemType ) :bool {
		return \in_array( $itemType, [ 'p', 't' ], true );
	}

	/**
	 * @return 'plugin'|'theme'|''
	 */
	private function normalizeAssetTypeFilter( string $assetType ) :string {
		$assetType = \strtolower( \trim( $assetType ) );
		return \in_array( $assetType, [ 'plugin', 'theme' ], true ) ? $assetType : '';
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 */
	private function assetMatchesFilter( $asset, string $assetType ) :bool {
		return $assetType === ''
			   || ( $assetType === 'plugin' && $asset instanceof WpPluginVo )
			   || ( $assetType === 'theme' && $asset instanceof WpThemeVo );
	}

	/**
	 * @param list<VulnerabilityItem> $items
	 * @return list<VulnerabilityItem>
	 */
	private function sortItems( array $items ) :array {
		\usort( $items, static function ( array $a, array $b ) :int {
			$countCmp = $b[ 'count' ] <=> $a[ 'count' ];
			return $countCmp !== 0
				? $countCmp
				: \strcmp( $a[ 'label' ], $b[ 'label' ] );
		} );

		return $items;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return list<VulnerabilityAction>
	 */
	private function buildVulnerableAssetActions( $asset ) :array {
		return [
			$this->buildNativeAction( $asset, true ),
			[
				'href'       => $asset instanceof WpPluginVo
					? self::con()->plugin_urls->vulnerabilityLookupByPlugin( $asset->slug, $asset->Version )
					: self::con()->plugin_urls->vulnerabilityLookupByTheme( $asset->stylesheet, $asset->Version ),
				'label'      => __( 'Vulnerability Lookup', 'wp-simple-firewall' ),
				'type'       => 'navigate',
				'icon'       => 'bi bi-box-arrow-up-right',
				'attributes' => [
					'target' => '_blank',
					'rel'    => 'noopener noreferrer',
				],
			],
		];
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return list<VulnerabilityAction>
	 */
	private function buildAbandonedAssetActions( $asset ) :array {
		return [
			$this->buildNativeAction( $asset, false ),
		];
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return array{href:string,label:string,type:string}
	 */
	private function buildNativeAction( $asset, bool $isVulnerable ) :array {
		$isPlugin = $asset instanceof WpPluginVo;

		if ( $asset->hasUpdate() ) {
			return [
				'href'  => Services::WpGeneral()->getAdminUrl_Updates(),
				'label' => __( 'Go to updates', 'wp-simple-firewall' ),
				'type'  => 'update',
			];
		}

		return [
			'href'  => $isPlugin
				? self::con()->plugin_urls->pluginInPluginsScreen( $asset->Title, $isVulnerable ? 'vulnerable' : 'all' )
				: self::con()->plugin_urls->themeInThemesScreen( $asset->stylesheet ),
			'label' => $isPlugin
				? __( 'Go to plugin', 'wp-simple-firewall' )
				: __( 'Go to theme', 'wp-simple-firewall' ),
			'type'  => 'navigate',
		];
	}

	/**
	 * @param list<VulnerabilityItem> $vulnerableItems
	 * @param list<VulnerabilityItem> $abandonedItems
	 */
	private function countDistinctAffectedAssets( array $vulnerableItems, array $abandonedItems ) :int {
		$keys = [];
		foreach ( [ ...$vulnerableItems, ...$abandonedItems ] as $item ) {
			$keys[ $item[ 'asset_type' ]."\n".$item[ 'asset_key' ] ] = true;
		}

		return \count( $keys );
	}
}
