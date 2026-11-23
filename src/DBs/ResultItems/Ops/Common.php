<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops;

trait Common {

	public function filterByScan( string $scan ) {
		return $this->addWhereEquals( 'scan', $scan );
	}

	public function filterByItemID( string $itemID ) {
		return $this->addWhereEquals( 'item_id', $itemID );
	}

	public function filterByItemType( string $type ) {
		return $this->addWhereEquals( 'item_type', $type );
	}

	public function filterByTypeFile() {
		return $this->filterByItemType( Handler::ITEM_TYPE_FILE );
	}

	public function filterByUnresolved() {
		return $this->addWhereEquals( 'resolved_at', 0 );
	}
}
