<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Render\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\LoadRawTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Apc;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;

class SectionPlugins extends SectionBase {

	/**
	 * @var Scans\Apc\ResultsSet
	 */
	private $abandonedPlugins;

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

		return [
			'ajax'    => [
				'scantable_action' => $mod->getAjaxActionData( 'scantable_action', true ),
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
				'update_available'      => __( 'Update Available', 'wp-simple-firewall' ),
			],
			'hrefs'   => [
				'upgrade' => Services::WpGeneral()->getAdminUrl_Updates()
			],
			'vars'    => [
				'plugins' => array_values( $plugins )
			]
		];
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

		$abandoned = $this->getAbandonedPluginsResults()->getItemForSlug( $plugin->file );
		$guardFilesData = ( new LoadRawTableData() )
			->setMod( $this->getMod() )
			->loadForPlugin( $plugin );

		$data = [
			'info'  => [
				'active'           => $plugin->active,
				'name'             => $plugin->Title,
				'type'             => 'plugin',
				'slug'             => $plugin->slug,
				'description'      => $plugin->Description,
				'version'          => $plugin->Version,
				'author'           => $plugin->AuthorName,
				'author_url'       => $plugin->AuthorURI,
				'file'             => $plugin->file,
				'dir'              => '/'.str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $plugin->getInstallDir() ) ),
				'abandoned_at'     => empty( $abandoned ) ? 0
					: $carbon->setTimestamp( $abandoned->last_updated_at )->diffForHumans(),
			],
			'flags' => [
				'has_update'      => $plugin->hasUpdate(),
				'is_abandoned'    => !empty( $abandoned ),
				'has_guard_files' => !empty( $guardFilesData ),
				'is_wporg'        => $plugin->isWpOrg(),
			]
		];
		$data[ 'flags' ][ 'has_issue' ] = $data[ 'flags' ][ 'is_abandoned' ]
										  || $data[ 'flags' ][ 'has_guard_files' ];
		return $data;
	}

	private function getAbandonedPluginsResults() :Scans\Apc\ResultsSet {
		if ( !isset( $this->abandonedPlugins ) ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			try {
				$this->abandonedPlugins = $mod->getScanCon( Apc::SCAN_SLUG )->getAllResults();
			}
			catch ( \Exception $e ) {
				$this->abandonedPlugins = new Scans\Apc\ResultsSet();
			}
		}
		return $this->abandonedPlugins;
	}
}