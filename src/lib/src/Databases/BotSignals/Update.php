<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\EntryVO;
use FernleafSystems\Wordpress\Services\Services;

class Update extends \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Update {

	/**
	 * @param EntryVO $entryVO
	 * @param array   $updateData
	 * @return bool
	 */
	public function updateEntry( $entryVO, $updateData = [] ) {
		$updateData[ 'updated_at' ] = Services::Request()->ts();
		return parent::updateEntry( $entryVO, $updateData );
	}
}