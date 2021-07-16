<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Render\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\LoadRawTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Assets\DetectInstallationDate;

class SectionThemes extends SectionPluginThemesBase {

	public function render() :string {
		$renderData = $this->buildRenderData();
		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/scans/results/section/themes/index.twig',
						$renderData
					);
	}

	protected function buildRenderData() :array {
		$items = $this->buildThemesData();
		ksort( $items );

		$problems = [];
		$active = [];
		$updates = [];
		foreach ( $items as $key => $item ) {
			if ( $item[ 'flags' ][ 'has_issue' ] ) {
				unset( $items[ $key ] );
				$problems[] = $item;
			}
			elseif ( $item[ 'flags' ][ 'has_update' ] ) {
				unset( $items[ $key ] );
				$updates[] = $item;
			}
			elseif ( $item[ 'info' ][ 'active' ] ) {
				unset( $items[ $key ] );
				$active[] = $item;
			}
		}

		$items = array_merge( $problems, $updates, $active, $items );

		return Services::DataManipulation()
					   ->mergeArraysRecursive( $this->getCommonRenderData(), [
						   'strings' => [
							   'no_items'    => __( "Previous scans didn't detect any modified or missing files in any Theme directories.", 'wp-simple-firewall' ),
							   'no_files'    => __( "Previous scans didn't detect any modified or missing files in the Theme directories.", 'wp-simple-firewall' ),
							   'files_found' => __( "Previous scans detected 1 or more modified or missing files in the theme directory.", 'wp-simple-firewall' ),
							   'not_active'  => __( "This theme isn't active and should be uninstalled.", 'wp-simple-firewall' ),
						   ],
						   'vars'    => [
							   'count_items' => count( $problems ) + count( $updates ),
							   'themes'      => array_values( $items ),
						   ]
					   ] );
	}

	private function buildThemesData() :array {
		return array_map(
			function ( $item ) {
				return $this->buildThemeData( $item );
			},
			Services::WpThemes()->getThemesAsVo()
		);
	}

	private function buildThemeData( WpThemeVo $theme ) :array {
		$carbon = Services::Request()->carbon();

		$abandoned = $this->getAbandoned()->getItemForSlug( $theme->stylesheet );
		$guardFilesData = ( new LoadRawTableData() )
			->setMod( $this->getMod() )
			->loadForTheme( $theme );

		$vulnerabilities = $this->getVulnerabilities()->getItemsForSlug( $theme->file );

		$data = [
			'info'  => [
				'type'         => 'theme',
				'active'       => $theme->active || $theme->is_parent,
				'name'         => $theme->Name,
				'slug'         => $theme->slug,
				'description'  => $theme->Description,
				'version'      => $theme->Version,
				'author'       => $theme->Author,
				'author_url'   => $theme->AuthorURI,
				'file'         => $theme->stylesheet,
				'dir'          => '/'.str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $theme->getInstallDir() ) ),
				'abandoned_at' => empty( $abandoned ) ? 0
					: $carbon->setTimestamp( $abandoned->last_updated_at )->diffForHumans(),
				'installed_at' => $carbon->setTimestamp( ( new DetectInstallationDate() )->theme( $theme ) )
										 ->diffForHumans(),
				'child_theme'  => $theme->is_parent ? $theme->child_theme->Name : '',
				'parent_theme' => $theme->is_child ? $theme->parent_theme->Name : '',
			],
			'flags' => [
				'has_update'      => $theme->hasUpdate(),
				'is_abandoned'    => !empty( $abandoned ),
				'has_guard_files' => !empty( $guardFilesData ),
				'is_vulnerable'   => !empty( $vulnerabilities ),
				'is_wporg'        => $theme->isWpOrg(),
				'is_child'        => $theme->is_child,
				'is_parent'       => $theme->is_parent,
			],
			'vars'  => [
				'count_items' => count( $guardFilesData ) + count( $vulnerabilities ) + ( empty( $abandoned ) ? 0 : 1 )
			],
		];
		$data[ 'flags' ][ 'has_issue' ] = $data[ 'flags' ][ 'is_abandoned' ]
										  || $data[ 'flags' ][ 'has_guard_files' ]
										  || $data[ 'flags' ][ 'is_vulnerable' ];
		return $data;
	}
}