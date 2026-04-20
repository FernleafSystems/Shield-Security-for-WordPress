<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops;

use FernleafSystems\Wordpress\Services\Services;

class Update extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Update {

	public function setItemDeleted( int $recordID ) :bool {
		return $this->resolveItem( $recordID, 'deleted' );
	}

	public function setItemRepaired( int $recordID ) :bool {
		return $this->resolveItem( $recordID, 'repaired' );
	}

	public function setItemAssetReplaced( int $recordID ) :bool {
		return $this->resolveItem( $recordID, 'asset_replaced' );
	}

	public function clearResolution( int $recordID ) :bool {
		return $this->updateById( $recordID, [
			'resolved_at'       => 0,
			'resolution_reason' => '',
		] );
	}

	private function resolveItem( int $recordID, string $reason ) :bool {
		return $this->updateById( $recordID, [
			'resolved_at'       => Services::Request()->ts(),
			'resolution_reason' => $reason,
		] );
	}
}
