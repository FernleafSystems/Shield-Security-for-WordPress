<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

class ResultsSet extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet {

	/**
	 * @return ResultItem[]
	 */
	public function getItemsForSlug( string $slug ) :array {
		return \array_values( \array_filter(
			$this->getItems(),
			function ( $item ) use ( $slug ) {
				/** @var ResultItem $item */
				return $item->VO->item_id == $slug;
			}
		) );
	}

	/**
	 * @return string[]
	 */
	public function getUniqueSlugs() {
		return \array_unique( \array_map(
			function ( $item ) {
				/** @var ResultItem $item */
				return $item->VO->item_id;
			},
			$this->getItems()
		) );
	}
}