<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

abstract class ScanEnabledFileLockerBase extends Base {

	use Traits\OptConfigBased;

	public const MINIMUM_EDITION = 'business';
	public const SLUG = 'scan_enabled_filelocker_';
	public const FILE_LOCKER_FILE = '';
	public const FILE_LOCKER_FILE_KEY = '';

	protected function getOptConfigKey() :string {
		return 'file_locker';
	}

	protected function testIfProtected() :bool {
		$con = self::con();
		return $con->comps->file_locker->isEnabled()
			   && \in_array( static::FILE_LOCKER_FILE_KEY, $con->comps->file_locker->getFilesToLock() );
	}

	public function slug() :string {
		return static::SLUG.static::FILE_LOCKER_FILE_KEY;
	}

	public function title() :string {
		return sprintf( '%s - %s',
			__( 'Critical File Protection', 'wp-simple-firewall' ), static::FILE_LOCKER_FILE );
	}

	public function descProtected() :string {
		return sprintf( __( '%s is protected against tampering.', 'wp-simple-firewall' ), static::FILE_LOCKER_FILE );
	}

	public function descUnprotected() :string {
		return sprintf( __( "%s isn't protected against tampering.", 'wp-simple-firewall' ), static::FILE_LOCKER_FILE );
	}
}