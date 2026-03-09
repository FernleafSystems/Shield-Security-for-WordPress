<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

class ScansVulnerabilitiesBuilder {

	use PluginControllerConsumer;

	/**
	 * @return array{
	 *   count:int,
	 *   status:string,
	 *   sections:array{
	 *     vulnerable:array{label:string,items:list<array<string,mixed>>},
	 *     abandoned:array{label:string,items:list<array<string,mixed>>}
	 *   }
	 * }
	 */
	public function build() :array {
		$vulnerableItems = $this->buildVulnerableItems();
		$abandonedItems = $this->buildAbandonedItems();

		return [
			'count'    => \count( $vulnerableItems ) + \count( $abandonedItems ),
			'status'   => empty( $vulnerableItems ) && empty( $abandonedItems ) ? 'good' : 'critical',
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
	 * @return list<array<string,mixed>>
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
	 * @return list<array<string,mixed>>
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
	 * @return array<string,mixed>
	 */
	private function buildAssetRow( string $prefix, $asset, int $count, string $description ) :array {
		$isPlugin = $asset instanceof WpPluginVo;
		$slug = $isPlugin ? $asset->file : $asset->stylesheet;
		$name = $isPlugin ? $asset->Title : $asset->Name;

		return [
			'key'         => $prefix.'-'.$asset->unique_id,
			'label'       => (string)$name,
			'description' => $description,
			'count'       => $count,
			'severity'    => $prefix === 'vulnerability' ? 'critical' : 'warning',
			'cta'         => [
				'href'  => $isPlugin
					? self::con()->plugin_urls->investigateByPlugin( $slug )
					: self::con()->plugin_urls->investigateByTheme( $slug ),
				'label' => __( 'Investigate', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return WpPluginVo|WpThemeVo|null
	 */
	private function getAsset( string $slug ) {
		return Services::WpPlugins()->getPluginAsVo( $slug, true ) ?? Services::WpThemes()->getThemeAsVo( $slug, true );
	}

	/**
	 * @param array<int,array<string,mixed>> $items
	 * @return list<array<string,mixed>>
	 */
	private function sortItems( array $items ) :array {
		\usort( $items, static function ( array $a, array $b ) :int {
			$countCmp = ( $b[ 'count' ] ?? 0 ) <=> ( $a[ 'count' ] ?? 0 );
			return $countCmp !== 0
				? $countCmp
				: \strcmp( (string)( $a[ 'label' ] ?? '' ), (string)( $b[ 'label' ] ?? '' ) );
		} );

		return \array_values( $items );
	}
}
