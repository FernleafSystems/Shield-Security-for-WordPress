<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

class ResultsSet extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet {
	/**
	 * @return ResultItem[]
	 */
	public function getItemsForSlug( string $slug ): array {
		return \array_values( \array_filter(
			$this->getItems(),
			fn( $item ) => /** @var ResultItem $item */ $item->VO->item_id == $slug,
		) );
	}

	/**
	 * @return string[]
	 */
	public function getUniqueSlugs(): array {
		return \array_unique( \array_map(
			fn( $item ) => /** @var ResultItem $item */ $item->VO->item_id,
			$this->getItems()
		) );
	}
}