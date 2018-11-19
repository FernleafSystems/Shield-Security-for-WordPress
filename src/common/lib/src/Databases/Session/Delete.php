<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseDelete;

class Delete extends BaseDelete {

	/**
	 * @param int $bOlderThan
	 * @return bool
	 */
	public function forExpiredLoginAt( $bOlderThan ) {
		return $this->reset()
					->addWhereOlderThan( $bOlderThan, 'logged_in_at' )
					->query();
	}

	/**
	 * @param int $bOlderThan
	 * @return bool
	 */
	public function forExpiredLoginIdle( $bOlderThan ) {
		return $this->reset()
					->addWhereOlderThan( $bOlderThan, 'last_activity_at' )
					->query();
	}

	/**
	 * @param string $sWpUsername
	 * @return false|int
	 */
	public function forUsername( $sWpUsername ) {
		return $this->reset()
					->addWhereEquals( 'wp_username', $sWpUsername )
					->query();
	}

	/**
	 * @return Select
	 */
	protected function getSelector() {
		return ( new Select() )->setTable( $this->getTable() );
	}
}