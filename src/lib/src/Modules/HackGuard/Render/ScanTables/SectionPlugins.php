<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Render\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\LoadRawTableData;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;

class SectionPlugins extends SectionPluginThemesBase {

	public function render() :string {
		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/scans/results/section/plugins/index.twig',
						$this->buildRenderData()
					);
	}

	protected function buildRenderData() :array {
		$mod = $this->getMod();

		$plugins = $this->buildPluginsData();
		ksort( $plugins );

		$problems = [];
		$active = [];
		foreach ( $plugins as $key => $plugin ) {
			if ( $plugin[ 'flags' ][ 'has_issue' ] ) {
				unset( $plugins[ $key ] );
				$problems[] = $plugin;
			}
			elseif ( $plugin[ 'info' ][ 'active' ] ) {
				unset( $plugins[ $key ] );
				$active[] = $plugin;
			}
		}

		$plugins = array_merge( $problems, $active, $plugins );

		return Services::DataManipulation()
					   ->mergeArraysRecursive( $this->getCommonRenderData(), [
						   'vars' => [
							   'plugins' => array_values( $problems )
						   ]
					   ] );
	}

	private function buildPluginsData() :array {
		return array_map(
			function ( $plugin ) {
				return $this->buildPluginData( $plugin );
			},
			Services::WpPlugins()->getPluginsAsVo()
		);
	}

	private function buildPluginData( WpPluginVo $plugin ) :array {
		$carbon = Services::Request()->carbon();

		$abandoned = $this->getAbandoned()->getItemForSlug( $plugin->file );
		$guardFilesData = ( new LoadRawTableData() )
			->setMod( $this->getMod() )
			->loadForPlugin( $plugin );

		$vulnerabilities = $this->getVulnerabilities()->getItemsForSlug( $plugin->file );

		$data = [
			'info'  => [
				'type'         => 'plugin',
				'active'       => $plugin->active,
				'name'         => $plugin->Title,
				'slug'         => $plugin->slug,
				'description'  => $plugin->Description,
				'version'      => $plugin->Version,
				'author'       => $plugin->AuthorName,
				'author_url'   => $plugin->AuthorURI,
				'file'         => $plugin->file,
				'dir'          => '/'.str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $plugin->getInstallDir() ) ),
				'abandoned_at' => empty( $abandoned ) ? 0
					: $carbon->setTimestamp( $abandoned->last_updated_at )->diffForHumans(),
			],
			'flags' => [
				'has_update'      => $plugin->hasUpdate(),
				'is_abandoned'    => !empty( $abandoned ),
				'has_guard_files' => !empty( $guardFilesData ),
				'is_vulnerable'   => !empty( $vulnerabilities ),
				'is_wporg'        => $plugin->isWpOrg(),
			]
		];
		$data[ 'flags' ][ 'has_issue' ] = $data[ 'flags' ][ 'is_abandoned' ]
										  || $data[ 'flags' ][ 'has_guard_files' ]
										  || $data[ 'flags' ][ 'is_vulnerable' ];
		return $data;
	}
}