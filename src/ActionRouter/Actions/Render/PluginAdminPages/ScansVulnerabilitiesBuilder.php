<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

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
 * @phpstan-type VulnerabilityItem array{
 *   key:string,
 *   asset_key:string,
 *   label:string,
 *   description:string,
 *   count:int,
 *   severity:string,
 *   actions:list<VulnerabilityAction>
 * }
 * @phpstan-type VulnerabilitySection array{
 *   label:string,
 *   items:list<VulnerabilityItem>
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
		$vulnerableItems = $this->buildVulnerableItems();
		$abandonedItems = $this->buildAbandonedItems();

		return [
			'count'    => $this->countDistinctAffectedAssets( $vulnerableItems, $abandonedItems ),
			'status'   => !empty( $vulnerableItems )
				? 'critical'
				: ( !empty( $abandonedItems ) ? 'warning' : 'good' ),
			'sections' => [
				'vulnerable' => [
					'label' => __( 'Known Vulnerabilities', 'wp-simple-firewall' ),
					'items' => $vulnerableItems,
				],
				'abandoned'  => [
					'label' => __( 'Abandoned Assets', 'wp-simple-firewall' ),
					'items' => $abandonedItems,
				],
			],
		];
	}

	/**
	 * @return list<VulnerabilityItem>
	 */
	protected function buildVulnerableItems() :array {
		try {
			$results = self::con()->comps->scans->WPV()->getResultsForDisplay();
		}
		catch ( \Throwable $e ) {
			return [];
		}

		$items = [];
		foreach ( $results->getUniqueSlugs() as $slug ) {
			$asset = $this->getAsset( $slug );
			if ( $asset === null ) {
				continue;
			}

			$count = \count( $results->getItemsForSlug( $slug ) );
			$items[] = $this->buildAssetRow(
				'vulnerability',
				$asset,
				$count,
				\sprintf(
					_n( '%s known vulnerability needs review.', '%s known vulnerabilities need review.', $count, 'wp-simple-firewall' ),
					$count
				)
			);
		}

		return $this->sortItems( $items );
	}

	/**
	 * @return list<VulnerabilityItem>
	 */
	protected function buildAbandonedItems() :array {
		try {
			$results = self::con()->comps->scans->APC()->getResultsForDisplay();
		}
		catch ( \Throwable $e ) {
			return [];
		}

		$items = [];
		foreach ( $results->getItems() as $item ) {
			$asset = $this->getAsset( (string)$item->VO->item_id );
			if ( $asset === null ) {
				continue;
			}

			$items[] = $this->buildAssetRow(
				'abandoned',
				$asset,
				1,
				__( 'This asset appears to be abandoned and should be reviewed.', 'wp-simple-firewall' )
			);
		}

		return $this->sortItems( $items );
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return VulnerabilityItem
	 */
	private function buildAssetRow( string $prefix, $asset, int $count, string $description ) :array {
		$isPlugin = $asset instanceof WpPluginVo;
		$name = $isPlugin ? $asset->Title : $asset->Name;

		return [
			'key'         => $prefix.'-'.$asset->unique_id,
			'asset_key'   => (string)$asset->unique_id,
			'label'       => (string)$name,
			'description' => $description,
			'count'       => $count,
			'severity'    => $prefix === 'vulnerability' ? 'critical' : 'warning',
			'actions'     => $this->buildAssetActions( $asset ),
		];
	}

	/**
	 * @return WpPluginVo|WpThemeVo|null
	 */
	private function getAsset( string $slug ) {
		return Services::WpPlugins()->getPluginAsVo( $slug, true ) ?? Services::WpThemes()->getThemeAsVo( $slug, true );
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

		return \array_values( $items );
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return list<VulnerabilityAction>
	 */
	private function buildAssetActions( $asset ) :array {
		return [
			$this->buildNativeAction( $asset ),
			[
				'href'  => $asset instanceof WpPluginVo
					? self::con()->plugin_urls->investigatePluginVulnerabilities( $asset->file )
					: self::con()->plugin_urls->investigateThemeVulnerabilities( $asset->stylesheet ),
				'label' => __( 'View vulnerability results', 'wp-simple-firewall' ),
				'type'  => 'navigate',
				'icon'  => 'bi bi-list-ul',
			],
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
	 * @return array{href:string,label:string,type:string}
	 */
	private function buildNativeAction( $asset ) :array {
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
				? Services::WpGeneral()->getAdminUrl_Plugins()
				: Services::WpGeneral()->getAdminUrl_Themes(),
			'label' => $isPlugin
				? __( 'Go to plugins', 'wp-simple-firewall' )
				: __( 'Go to themes', 'wp-simple-firewall' ),
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
			$keys[ $item[ 'asset_key' ] ] = true;
		}

		return \count( $keys );
	}
}
