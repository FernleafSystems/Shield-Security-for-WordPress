<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Render\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\LoadRawTableData;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Assets\DetectInstallationDate;

class SectionPlugins extends SectionPluginThemesBase {

	public function render() :string {
		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/scans/results/section/plugins/index.twig',
						$this->buildRenderData()
					);
	}

	protected function buildRenderData() :array {
		$items = $this->buildPluginsData();
		ksort( $items );

		$hashes = [];
		$abandoned = [];
		$vulnerable = [];
		$problems = [];
		$inactive = [];
		$updates = [];
		foreach ( $items as $key => $item ) {
			if ( $item[ 'flags' ][ 'has_guard_files' ] ) {
				unset( $items[ $key ] );
				$hashes[] = $item;
			}
			elseif ( $item[ 'flags' ][ 'is_vulnerable' ] ) {
				unset( $items[ $key ] );
				$vulnerable[] = $item;
			}
			elseif ( $item[ 'flags' ][ 'is_abandoned' ] ) {
				unset( $items[ $key ] );
				$abandoned[] = $item;
			}
			elseif ( $item[ 'flags' ][ 'has_issue' ] ) {
				unset( $items[ $key ] );
				$problems[] = $item;
			}
			elseif ( $item[ 'flags' ][ 'has_update' ] ) {
				unset( $items[ $key ] );
				$updates[] = $item;
			}
			elseif ( !$item[ 'info' ][ 'active' ] ) {
				unset( $items[ $key ] );
				$inactive[] = $item;
			}
		}

		$items = array_merge( $vulnerable, $hashes, $abandoned, $problems, $updates, $inactive, $items );

		return Services::DataManipulation()
					   ->mergeArraysRecursive( $this->getCommonRenderData(), [
						   'strings' => [
							   'no_items'    => __( "Previous scans didn't detect any modified or missing files in any plugin directories.", 'wp-simple-firewall' ),
							   'no_files'    => __( "Previous scans didn't detect any modified or missing files in the plugin directory.", 'wp-simple-firewall' ),
							   'files_found' => __( "Previous scans detected 1 or more modified or missing files in the plugin directory.", 'wp-simple-firewall' ),
							   'not_active'  => __( "This plugin isn't active and should be uninstalled.", 'wp-simple-firewall' ),
						   ],
						   'vars'    => [
							   'count_items' => count( $problems ) + count( $updates ),
							   'plugins'     => array_values( $items ),
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
				'installed_at' => $carbon->setTimestamp( ( new DetectInstallationDate() )->plugin( $plugin ) )
										 ->diffForHumans(),
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
			],
			'vars'  => [
				'abandoned_rid' => empty( $abandoned ) ? -1 : $abandoned->VO->id,
				'count_items'   => count( $guardFilesData ) + count( $vulnerabilities )
								   + ( empty( $abandoned ) ? 0 : 1 ) + ( $plugin->hasUpdate() ? 1 : 0 )
			],
		];
		$data[ 'flags' ][ 'has_issue' ] = $data[ 'flags' ][ 'is_abandoned' ]
										  || $data[ 'flags' ][ 'has_guard_files' ]
										  || $data[ 'flags' ][ 'is_vulnerable' ];
		return $data;
	}
}