<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;

/**
 * @phpstan-type AssetType 'plugin'|'theme'
 * @phpstan-type GroupedAssetSummaryRow array{slug:string,file_count:int}
 */
class ActionsQueueAfsAssetSummaryProvider {

	use PluginControllerConsumer;

	/**
	 * @phpstan-param AssetType $assetType
	 * @param array<string,mixed>|null $resultsDisplayOptions
	 * @return list<GroupedAssetSummaryRow>
	 */
	public function retrieve( string $assetType, ?array $resultsDisplayOptions = null ) :array {
		$countsBySlug = [];

		/** @var ResultItem $item */
		foreach ( $this->retrieveItems( $assetType, $resultsDisplayOptions ) as $item ) {
			$slug = \trim( (string)$item->ptg_slug );
			if ( $slug === '' ) {
				continue;
			}

			$countsBySlug[ $slug ] = ( $countsBySlug[ $slug ] ?? 0 ) + 1;
		}

		\ksort( $countsBySlug );

		return \array_map(
			static fn( string $slug, int $fileCount ) :array => [
				'slug'       => $slug,
				'file_count' => $fileCount,
			],
			\array_keys( $countsBySlug ),
			\array_values( $countsBySlug )
		);
	}

	/**
	 * @phpstan-param AssetType $assetType
	 * @param array<string,mixed>|null $resultsDisplayOptions
	 * @return list<ResultItem>
	 */
	protected function retrieveItems( string $assetType, ?array $resultsDisplayOptions = null ) :array {
		self::con()->comps->scans->AFS()->prepareResultsForDisplay();

		$retriever = ( new RetrieveItems() )
			->setScanController( self::con()->comps->scans->AFS() )
			->addWheres( $this->buildMembershipWheres( $assetType ) );

		return $retriever
			->retrieveForResultsTables( \is_array( $resultsDisplayOptions ) ? $resultsDisplayOptions : null )
			->getItems();
	}

	/**
	 * @phpstan-param AssetType $assetType
	 * @return list<string>
	 */
	private function buildMembershipWheres( string $assetType ) :array {
		return [
			\sprintf( "`rim`.`meta_key`='%s'", $assetType === 'plugin' ? 'is_in_plugin' : 'is_in_theme' ),
			"`rim`.`meta_value`=1",
		];
	}
}
