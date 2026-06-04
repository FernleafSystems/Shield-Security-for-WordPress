<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\Host;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\WorpdriveClient\Host\WorpdriveDatabase;

class ShieldWorpdriveDatabase implements WorpdriveDatabase {

	public function getPrefix( bool $siteBase = true ) :string {
		return Services::WpDb()->getPrefix( $siteBase );
	}

	public function loadWpdb() {
		return Services::WpDb()->loadWpdb();
	}

	public function selectCustom( $query, $format = null ) {
		return $format === null ? Services::WpDb()->selectCustom( $query ) : Services::WpDb()->selectCustom( $query, $format );
	}

	public function doSql( string $sql ) {
		return Services::WpDb()->doSql( $sql );
	}

	public function getVar( $query ) {
		return Services::WpDb()->getVar( $query );
	}

	public function showTableStatus( $format = null ) :array {
		$status = $format === null ? Services::WpDb()->showTableStatus() : Services::WpDb()->showTableStatus( $format );
		if ( !\is_array( $status ) ) {
			throw new \UnexpectedValueException( 'showTableStatus() did not return an array.' );
		}
		return $status;
	}
}
