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
			throw new Exceptions\LibraryNotFoundException(
				\sprintf(
					__( '%1$s library (%2$s) could not be found.', 'wp-simple-firewall' ),
					$libraryLabel,
					$fqcn
				)
			);
		}
	}

	private function includePrefixedVendorIfAvailable() :void {
		if ( \method_exists( self::con(), 'includePrefixedVendor' ) ) {
			try {
				self::con()->includePrefixedVendor();
			}
			catch ( Exceptions\LibraryPrefixedAutoloadNotFoundException $e ) {
			}
		}
	}
}
