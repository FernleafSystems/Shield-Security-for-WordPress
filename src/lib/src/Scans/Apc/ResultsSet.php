<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

class ResultsSet extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet {

	/**
	 * @return ResultItem|null
	 */
	public function getItemForSlug( string $slug ) {
		$theItem = null;
		/** @var ResultItem $item */
		foreach ( $this->getItems() as $item ) {
			if ( $item->VO->item_id === $slug ) {
				$theItem = $item;
				break;
			}
		}
		return $theItem;
	}
}