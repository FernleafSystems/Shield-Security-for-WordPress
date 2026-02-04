<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

class ResultsSet extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet {

	public function getMalware() :ResultsSet {
		return $this->filterByFieldEquals( 'is_mal', true );
	}

	private function filterByFieldEquals( string $field, $equals ) :ResultsSet {
		$res = new ResultsSet();
		/** @var ResultItem $item */
		foreach ( $this->getItems() as $item ) {
			if ( $item->{$field} == $equals ) {
				$res->addItem( $item );
			}
		}
		return $res;
	}
}