<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * Why the early loader could not be installed or removed. The reason is a stable key; the admin
 * interface turns it into a message.
 */
class InstallException extends \Exception {

	public const MULTISITE = 'multisite';
	public const FILESYSTEM_NOT_DIRECT = 'filesystem_not_direct';
	public const UNSAFE_PATH = 'unsafe_path';
	public const HOST_PREPEND_NOT_ABSOLUTE = 'host_prepend_not_absolute';
	public const CONFLICT = 'conflict';
	public const MALFORMED_MARKERS = 'malformed_markers';
	public const LOOPBACK_UNAVAILABLE = 'loopback_unavailable';
	public const STUB_WRITE_FAILED = 'stub_write_failed';
	public const STUB_SMOKE_FAILED = 'stub_smoke_failed';
	public const CONFIG_WRITE_FAILED = 'config_write_failed';
	public const SITE_FAILED_REVERTED = 'site_failed_reverted';

	private string $reason;

	private string $configFile;

	public function __construct( string $reason, string $file = '' ) {
		parent::__construct( $file === '' ? $reason : $reason.': '.$file );
		$this->reason = $reason;
		$this->configFile = $file;
	}

	public function reason() :string {
		return $this->reason;
	}

	/**
	 * The configuration file concerned, when there is one.
	 */
	public function configFile() :string {
		return $this->configFile;
	}
}
