<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

abstract class ScanEnabledFileLockerBase extends Base {

	public const SLUG = 'scan_enabled_filelocker_';
	public const FILE_LOCKER_FILE = '';
	public const FILE_LOCKER_FILE_KEY = '';

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_HackGuard();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled()
			   && $mod->getFileLocker()->isEnabled()
			   && in_array( static::FILE_LOCKER_FILE_KEY, $opts->getFilesToLock() );
	}

	public function href() :string {
		return $this->getCon()->getModule_HackGuard()->isModOptEnabled() ?
			$this->link( 'file_locker' ) : $this->link( 'enable_hack_protect' );
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