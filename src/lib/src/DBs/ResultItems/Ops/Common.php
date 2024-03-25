<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops;

trait Common {

	public function filterByItemNotRepaired() {
		return $this->addWhereEquals( 'item_repaired_at', 0 );
	}

	public function filterByItemNotDeleted() {
		return $this->addWhereEquals( 'item_deleted_at', 0 );
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

	public function filterByTypePlugin() {
		return $this->filterByItemType( Handler::ITEM_TYPE_PLUGIN );
	}

	public function filterByTypeTheme() {
		return $this->filterByItemType( Handler::ITEM_TYPE_THEME );
	}
}