<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

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
		$mod = $this->con()->getModule_HackGuard();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled()
			   && $mod->getFileLocker()->isEnabled()
			   && in_array( static::FILE_LOCKER_FILE_KEY, $opts->getFilesToLock() );
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