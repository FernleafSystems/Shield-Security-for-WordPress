<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops;

class Handler {

	public bool $use_table_ready_cache = false;

	/**
	 * @var array<string,mixed>
	 */
	private array $tableDefinition;

	/**
	 * @param array<string,mixed> $tableDefinition
	 */
	public function __construct( array $tableDefinition ) {
		$this->tableDefinition = $tableDefinition;
	}

	public function execute() :void {
	}

	public function isReady() :bool {
		return false;
	}

	public function commitEvents( array $events ) :void {
	}

	public function commitEvent( string $evt, int $count = 1 ) :bool {
		return false;
	}
}
