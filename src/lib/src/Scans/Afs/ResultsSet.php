<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class ResultsSet extends Base\ResultsSet {

	public function getMalware() :ResultsSet {
		$malwareResults = new ResultsSet();
		/** @var ResultItem $item */
		foreach ( $this->getItems() as $item ) {
			if ( $item->is_mal ) {
				$malwareResults->addItem( $item );
			}
		}
		return $malwareResults;
	}
}