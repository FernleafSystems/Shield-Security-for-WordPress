<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Diff;

class DiffUsers extends Base {

	public function run() {

		var_dump( $this->getAdded() );
		var_dump( $this->getRemoved() );
		var_dump( $this->getChangedItems() );

	}

	/**
	 * @return string[]
	 */
	protected function getAttributesToCompare() {
		return [
			'user_pass',
			'user_email',
			'is_admin',
		];
	}
}