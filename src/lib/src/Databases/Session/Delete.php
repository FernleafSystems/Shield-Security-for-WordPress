<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Delete extends Base\Delete {

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
}