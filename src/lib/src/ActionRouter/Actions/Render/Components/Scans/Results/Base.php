<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Services\Services;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\BaseScans {

	protected function getRenderData() :array {
		return [
			'strings' => [
				'author'            => __( 'Author' ),
				'version'           => __( 'Version' ),
				'name'              => __( 'Name' ),
				'install_dir'       => __( 'Installation Directory', 'wp-simple-firewall' ),
				'file_integrity'    => __( 'File Integrity', 'wp-simple-firewall' ),
				'status_good'       => __( 'Good', 'wp-simple-firewall' ),
				'status_warning'    => __( 'Warning', 'wp-simple-firewall' ),
				'abandoned'         => __( 'Abandoned', 'wp-simple-firewall' ),
				'vulnerable'        => __( 'Vulnerable', 'wp-simple-firewall' ),
				'vulnerable_known'  => __( 'Vulnerability Discovered', 'wp-simple-firewall' ),
				'vulnerable_update' => __( "You should upgrade to the latest version or remove it if no updates are available.", 'wp-simple-firewall' ),
				'update_available'  => __( 'Update Available', 'wp-simple-firewall' ),
				'installed_at'      => __( 'Installed', 'wp-simple-firewall' ),
				'estimated'         => __( 'estimated', 'wp-simple-firewall' ),
				'child_theme'       => __( 'Linked To Child Theme', 'wp-simple-firewall' ),
				'parent_theme'      => __( 'Linked To Parent Theme', 'wp-simple-firewall' ),
			],
			'hrefs'   => [
				'upgrade' => Services::WpGeneral()->getAdminUrl_Updates()
			],
		];
	}
}