<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Meta\Ops;

trait Common {

	public function filterByCollection( int $collectionID ) :self {
		return $this->addWhereEquals( 'collection_ref', $collectionID );
	}

	public function filterByCollections( array $collectionIDs ) :self {
		return $this->addWhereIn( 'collection_ref', $collectionIDs );
	}

	public function filterByFilePath( int $filepathID ) :self {
		return $this->addWhereEquals( 'filepath_ref', $filepathID );
	}

	public function filterByFilePaths( array $filepathIDs ) :self {
		return $this->addWhereIn( 'filepath_ref', $filepathIDs );
	}
}