<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

/**
 * @deprecated 19.1
 */
class Strings extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Strings {

	public function getScanName( string $slug ) :string {
		return $this->getScanStrings()[ $slug ][ 'name' ];
	}

	/**
	 * @return string[]
	 */
	public function getScanStrings() :array {
		return [
			'afs' => [
				'name'     => __( 'WordPress Filesystem Scan', 'wp-simple-firewall' ),
				'subtitle' => __( 'Filesystem Scan looking for modified, missing and unrecognised files (use config to adjust scan areas)', 'wp-simple-firewall' ),
			],
			'apc' => [
				'name'     => __( 'Abandoned Plugins', 'wp-simple-firewall' ),
				'subtitle' => __( "Discover plugins that may have been abandoned by their authors", 'wp-simple-firewall' ),
			],
			'wpv' => [
				'name'     => __( 'Vulnerabilities', 'wp-simple-firewall' ),
				'subtitle' => __( "Be alerted to plugins and themes with known security vulnerabilities", 'wp-simple-firewall' ),
			],
		];
	}
}