<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultsSet
 * @property ResultItem[] $items
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc
 */
class ResultsSet extends Base\BaseResultsSet {

	/**
	 * @param string $slug
	 * @return ResultItem|null
	 */
	public function getItemForSlug( string $slug ) {
		$theItem = null;
		/** @var ResultItem $item */
		foreach ( $this->getItems() as $item ) {
			if ( $item->slug === $slug ) {
				$theItem = ( new ResultItem() )->applyFromArray( $item->getRawData() );
				break;
			}
		}
		return $theItem;
	}
}