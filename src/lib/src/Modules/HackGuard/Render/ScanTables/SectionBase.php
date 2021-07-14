<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Render\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SectionBase {

	use ModConsumer;

	protected $renderData = [];

	protected function buildRenderData() :array {
		return [];
	}

	public function getRenderData() :array {
		if ( empty( $this->renderData ) ) {
			$this->renderData = $this->buildRenderData();
		}
		return $this->renderData;
	}

	protected function getCommonRenderData() :array {
		return [
			'ajax'    => [
				'scantable_action' => $this->getMod()->getAjaxActionData( 'scantable_action', true ),
			],
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
				'vulnerable_known'  => __( 'Known vulnerabilities are present.', 'wp-simple-firewall' ),
				'vulnerable_update' => __( "You should upgrade to the latest available version or remove it if no updates are available.", 'wp-simple-firewall' ),
				'update_available'  => __( 'Update Available', 'wp-simple-firewall' ),
			],
			'hrefs'   => [
				'upgrade' => Services::WpGeneral()->getAdminUrl_Updates()
			],
		];
	}
}