<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Services\Core\Users;

class UnitTestUsers extends Users {

	public function __construct( private int $currentUserId = 1 ) {
	}

	public function getCurrentWpUserId() {
		return $this->currentUserId;
	}
}
