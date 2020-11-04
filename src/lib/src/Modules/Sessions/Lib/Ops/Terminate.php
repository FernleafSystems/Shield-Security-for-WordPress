<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Delete;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Terminate {

	use ModConsumer;

	/**
	 * @return bool
	 */
	public function all() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getDbHandler_Sessions()->tableDelete( true );
	}

	/**
	 * @param int $nId
	 * @return bool
	 */
	public function byRecordId( $nId ) {
		$this->getCon()->fireEvent( 'session_terminate' );
		return $this->getDeleter()->deleteById( (int)$nId );
	}

	public function byUsername( string $username ) :bool {
		return $this->getDeleter()->forUsername( $username ) !== false;
	}

	private function getDeleter() :Delete {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getDbHandler_Sessions()->getQueryDeleter();
	}
}