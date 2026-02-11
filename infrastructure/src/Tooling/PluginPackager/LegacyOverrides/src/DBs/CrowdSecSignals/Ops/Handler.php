<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\CrowdSecSignals\Ops;

class Handler {

	public bool $use_table_ready_cache = false;

	/**
	 * @var array<string,mixed>
	 */
	private array $tableDefinition;

	private ?LegacyRecordStub $record = null;

	private ?LegacyInserterStub $inserter = null;

	private ?LegacySelectorStub $selector = null;

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

	public function getRecord() :LegacyRecordStub {
		return $this->record ??= new LegacyRecordStub();
	}

	public function getQueryInserter() :LegacyInserterStub {
		return $this->inserter ??= new LegacyInserterStub();
	}

	public function getQuerySelector() :LegacySelectorStub {
		return $this->selector ??= new LegacySelectorStub();
	}
}

class LegacyRecordStub {

	public string $scenario = '';

	public string $scope = '';

	public string $value = '';

	public string $milli_at = '0';

	/**
	 * @var array<string,mixed>
	 */
	public array $meta = [];

	/**
	 * @param array<string,mixed> $data
	 */
	public function applyFromArray( array $data ) :self {
		foreach ( $data as $key => $value ) {
			$property = (string)$key;
			if ( \property_exists( $this, $property ) ) {
				$this->{$property} = $value;
			}
		}
		return $this;
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	public function arrayDataWrap( array $data ) :array {
		return $data;
	}
}

class LegacyInserterStub {

	public function insert( $record ) :bool {
		return true;
	}
}

class LegacySelectorStub {

	public function count() :int {
		return 0;
	}
}
