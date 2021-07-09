<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Render;

use FernleafSystems\Wordpress\Services\Services;

class SectionPlugins extends SectionBase {

	public function render() :string {
		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/scans/results/section/plugins/index.twig',
						$this->buildRenderData()
					);
	}

	protected function buildRenderData() :array {
		return [
			'strings' => [
				'author'                => __( 'Author' ),
				'version'               => __( 'Version' ),
				'name'                  => __( 'Name' ),
				'install_dir'           => __( 'Install Dir', 'wp-simple-firewall' ),
				'file_integrity_status' => __( 'File Integrity Status', 'wp-simple-firewall' ),
				'status_good'           => __( 'Good', 'wp-simple-firewall' ),
				'status_warning'        => __( 'Warning', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'plugins' => array_values( array_map(
					function ( $plugin ) {
						return [
							'name'             => $plugin->Title,
							'slug'             => $plugin->slug,
							'description'      => $plugin->Description,
							'version'          => $plugin->Version,
							'author'           => $plugin->AuthorName,
							'author_url'       => $plugin->AuthorURI,
							'file'             => $plugin->file,
							'dir'              => $plugin->getInstallDir(),
							'is_wporg'         => $plugin->isWpOrg(),
							'integrity_status' => rand( 0, 1 ),
						];
					},
					Services::WpPlugins()->getPluginsAsVo()
				) )
			]
		];
	}
}