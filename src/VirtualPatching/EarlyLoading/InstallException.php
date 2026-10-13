<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

class InstallException extends \Exception {

	private InstallFailure $reason;

	private string $configFile;

	public function __construct( InstallFailure $reason, string $file = '' ) {
		parent::__construct( $file === '' ? $reason->value : $reason->value.': '.$file );
		$this->reason = $reason;
		$this->configFile = $file;
	}

	public function reason() :InstallFailure {
		return $this->reason;
	}

	/**
	 * The file concerned, when there is one.
	 */
	public function configFile() :string {
		return $this->configFile;
	}
}
