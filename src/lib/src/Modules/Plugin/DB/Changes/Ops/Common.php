<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Changes\Ops;

trait Common {

	public function filterIsDiff() :self {
		return $this->addWhere( 'is_diff', 1 );
	}

	public function filterIsFull() :self {
		return $this->addWhere( 'is_diff', 0 );
	}
}