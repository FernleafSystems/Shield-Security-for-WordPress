<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Delete;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\ModCon;

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
	 * @param int $id
	 * @return bool
	 */
	public function byRecordId( int $id ) {
		$this->getCon()->fireEvent( 'session_terminate' );
		return $this->getDeleter()
					->setIsSoftDelete()
					->deleteById( $id );
	}

	public function byUsername( string $username ) :bool {
		return $this->getDeleter()
					->setIsSoftDelete()
					->forUsername( $username ) !== false;
	}

	private function getDeleter() :Delete {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getDbHandler_Sessions()->getQueryDeleter();
	}
}