<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Data;

class ExportMap {

	private array $dumpStatus;

	/**
	 * @throws \Exception
	 */
	public function __construct( array $dumpStatus = [] ) {
		$this->dumpStatus = $dumpStatus;
	}

	public function status() :array {
		return $this->dumpStatus;
	}

	public function updateStatus( string $table, array $status ) :void {
		$this->dumpStatus[ $table ] = $status;
	}
}