<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore;

use FernleafSystems\Wordpress\Services\Core\Db;

class CacheStoreTestDb extends Db {

	private string $basePrefix;

	/**
	 * @var string[]
	 */
	public array $droppedTables = [];

	public function __construct( string $basePrefix = 'wp_' ) {
		$this->basePrefix = $basePrefix;
	}

	public function setBasePrefix( string $basePrefix ) :void {
		$this->basePrefix = $basePrefix;
	}

	public function getPrefix( bool $siteBase = true ) :string {
		return $this->basePrefix;
	}

	public function doDropTable( string $table ) :bool {
		$this->droppedTables[] = $table;
		return true;
	}
}
