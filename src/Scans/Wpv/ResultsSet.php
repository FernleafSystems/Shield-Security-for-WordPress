<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

/**
 * @extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet<ResultItem>
 */
class ResultsSet extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet {
	/**
	 * @return ResultItem[]
	 */
	public function getItemsForSlug( string $slug ): array {
		return \array_values( \array_filter(
			$this->getWpvItems(),
			static fn( ResultItem $item ): bool => $item->VO->item_id === $slug,
		) );
	}

	/**
	 * @return string[]
	 */
	public function getUniqueSlugs(): array {
		return \array_values( \array_unique( \array_map(
			static fn( ResultItem $item ): string => $item->VO->item_id,
			$this->getWpvItems()
		) ) );
	}

	/**
	 * @return list<ResultItem>
	 */
	private function getWpvItems(): array {
		return \array_values( \array_filter(
			$this->getAllItems(),
			static fn( $item ): bool => $item instanceof ResultItem
		) );
	}
}
