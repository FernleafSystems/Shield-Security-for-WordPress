<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ClassDependencyGuard {

	use PluginControllerConsumer;

	/**
	 * @throws Exceptions\LibraryNotFoundException
	 */
	public function ensureAvailable( string $fqcn, string $libraryLabel ) :void {
		$this->includePrefixedVendorIfAvailable();

		if ( !@\class_exists( $fqcn ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed library and class names only: callers log it with error_log() or escape it with esc_html() (RenderService), and the CrowdSec signal-push cron leaves it uncaught for PHP to handle as a fatal error.
			throw new Exceptions\LibraryNotFoundException(
				\sprintf(
					/* translators: %1$s: library name, %2$s: class name */
					__( '%1$s library (%2$s) could not be found.', 'wp-simple-firewall' ),
					$libraryLabel,
					$fqcn
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	private function includePrefixedVendorIfAvailable() :void {
		try {
			self::con()->includePrefixedVendor();
		}
		catch ( Exceptions\LibraryPrefixedAutoloadNotFoundException $e ) {
		}
	}
}
