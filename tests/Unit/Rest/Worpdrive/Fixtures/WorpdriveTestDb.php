<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures;

use FernleafSystems\Wordpress\Services\Core\Db;

class WorpdriveTestDb extends Db {

	public array $calls = [];

	public function getPrefix( bool $siteBase = true ) :string {
		$this->calls[] = [ 'getPrefix', $siteBase ];
		return $siteBase ? 'wp_' : 'wp_site_';
	}

	public function loadWpdb() :\wpdb {
		$this->calls[] = [ 'loadWpdb' ];
		return new \wpdb();
	}

	public function selectCustom( $query, $format = \ARRAY_A ) {
		$this->calls[] = [ 'selectCustom', $query, $format ];
		return [ [ 'query' => $query ] ];
	}

	public function doSql( string $sql ) {
		$this->calls[] = [ 'doSql', $sql ];
		return true;
	}

	public function getVar( $sql ) {
		$this->calls[] = [ 'getVar', $sql ];
		return 'value';
	}

	public function showTableStatus( $format = \OBJECT ) {
		$this->calls[] = [ 'showTableStatus', $format ];
		return [
			[ 'Name' => 'wp_posts' ],
		];
	}
}
