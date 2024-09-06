<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;

class ScanFileException extends \Exception {

	private $scanFileData;

	public function __construct( string $file, array $data = [] ) {
		parent::__construct( $file );
		$this->scanFileData = $data;
	}

	public function getScanFileData() :array {
		return $this->scanFileData;
	}
}