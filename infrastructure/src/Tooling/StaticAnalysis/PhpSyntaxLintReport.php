<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis;

class PhpSyntaxLintReport {

	private int $checkedFileCount;

	/** @var array<int,array{path:string,output:string,exit_code:int}> */
	private array $failures;

	/**
	 * @param array<int,array{path:string,output:string,exit_code:int}> $failures
	 */
	public function __construct( int $checkedFileCount, array $failures ) {
		$this->checkedFileCount = $checkedFileCount;
		$this->failures = $failures;
	}

	public function getCheckedFileCount() :int {
		return $this->checkedFileCount;
	}

	public function hasFailures() :bool {
		return !empty( $this->failures );
	}

	/**
	 * @return array<int,array{path:string,output:string,exit_code:int}>
	 */
	public function getFailures() :array {
		return $this->failures;
	}
}
