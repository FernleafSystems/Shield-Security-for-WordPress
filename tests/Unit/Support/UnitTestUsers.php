<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Services\Core\Users;

class UnitTestUsers extends Users {

	private int $currentUserId;

	public function __construct( int $currentUserId = 1 ) {
		$this->currentUserId = $currentUserId;
	}

	public function getCurrentWpUserId() {
		return $this->currentUserId;
	}
}
