<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * What Shield has installed for early loading. The value is stored with the state.
 */
enum InstallStatus :string {

	/**
	 * Nothing installed.
	 */
	case Off = 'off';

	/**
	 * Configuration files are being edited. Seen by a later request, the install was interrupted.
	 */
	case Installing = 'installing';

	/**
	 * Stub written; the administrator sets auto_prepend_file.
	 */
	case Manual = 'manual';

	/**
	 * Stub and Shield's blocks written.
	 */
	case Installed = 'installed';

	/**
	 * Shield's blocks removed; the stub stays until PHP can no longer be using it.
	 */
	case Removing = 'removing';

	/**
	 * Whether a stub Shield manages is meant to be in place, so it is kept current.
	 */
	public function keepsStub() :bool {
		return $this === self::Installed || $this === self::Manual;
	}
}
