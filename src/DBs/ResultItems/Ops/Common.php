<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops;

trait Common {

	public function filterByScan( string $scan ) {
		return $this->addWhereEquals( 'scan', $scan );
	}

	public function filterByAssetKey( string $assetKey ) {
		return $this->addWhereEquals( 'asset_key', $assetKey );
	}

	public function filterByAssetType( string $assetType ) {
		return $this->addWhereEquals( 'asset_type', $assetType );
	}

	public function filterByItemNotRepaired() {
		return $this->addWhereEquals( 'resolution_reason', 'repaired' );
	}

	public function filterByItemNotDeleted() {
		return $this->addWhereEquals( 'resolution_reason', 'deleted' );
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

	public function filterByResolved() {
		return $this->addWhereNewerThan( 0, 'resolved_at' );
	}

	public function filterByUnresolved() {
		return $this->addWhereEquals( 'resolved_at', 0 );
	}
}
