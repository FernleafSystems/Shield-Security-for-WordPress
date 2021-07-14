<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Render\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SectionBase {

	use ModConsumer;

	protected function buildRenderData() :array {
		return [];
	}

	protected function getCommonRenderData() :array {
		return [
			'ajax'    => [
				'scantable_action' => $this->getMod()->getAjaxActionData( 'scantable_action', true ),
			],
			'strings' => [
				'author'                => __( 'Author' ),
				'version'               => __( 'Version' ),
				'name'                  => __( 'Name' ),
				'install_dir'           => __( 'Install Dir', 'wp-simple-firewall' ),
				'file_integrity_status' => __( 'File Integrity Status', 'wp-simple-firewall' ),
				'status_good'           => __( 'Good', 'wp-simple-firewall' ),
				'status_warning'        => __( 'Warning', 'wp-simple-firewall' ),
				'abandoned'             => __( 'Abandoned', 'wp-simple-firewall' ),
				'vulnerable'            => __( 'Vulnerable', 'wp-simple-firewall' ),
				'vulnerable_known'      => __( 'Known vulnerabilities are present.', 'wp-simple-firewall' ),
				'vulnerable_update'     => __( "You should upgrade to the latest available version or remove it if none are available.", 'wp-simple-firewall' ),
				'update_available'      => __( 'Update Available', 'wp-simple-firewall' ),
			],
			'hrefs'   => [
				'upgrade' => Services::WpGeneral()->getAdminUrl_Updates()
			],
		];
	}
}