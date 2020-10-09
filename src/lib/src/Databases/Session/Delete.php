<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Delete extends Base\Delete {

	/**
	 * @param int $olderThan
	 * @return bool
	 */
	public function forExpiredLoginAt( int $olderThan ) {
		return $this->reset()
					->addWhereOlderThan( $olderThan, 'logged_in_at' )
					->query();
	}

	/**
	 * @param int $olderThan
	 * @return bool
	 */
	public function forExpiredLoginIdle( int $olderThan ) {
		return $this->reset()
					->addWhereOlderThan( $olderThan, 'last_activity_at' )
					->query();
	}

	/**
	 * @param string $username
	 * @return false|int
	 */
	public function forUsername( string $username ) {
		return $this->reset()
					->addWhereEquals( 'wp_username', $username )
					->query();
	}
}