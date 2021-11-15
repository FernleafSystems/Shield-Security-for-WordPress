<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Render\ScanResults;

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

		$hashes = [];
		$abandoned = [];
		$vulnerable = [];
		$problems = [];
		$inactive = [];
		$warning = [];
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
			elseif ( $item[ 'flags' ][ 'has_warning' ] ) {
				unset( $items[ $key ] );
				$warning[] = $item;
			}
		}

		$items = array_merge( $vulnerable, $hashes, $abandoned, $problems, $warning, $inactive, $items );

		return Services::DataManipulation()
					   ->mergeArraysRecursive( $this->getCommonRenderData(), [
						   'strings' => [
							   'no_items'     => __( "Previous scans didn't detect any modified or unrecognised files in any Theme directories.", 'wp-simple-firewall' ),
							   'no_files'     => __( "Previous scans didn't detect any modified or unrecognised files in the Theme directories.", 'wp-simple-firewall' ),
							   'files_found'  => __( "Previous scans detected 1 or more modified or unrecognised files in the theme directory.", 'wp-simple-firewall' ),
							   'not_active'   => __( "This theme isn't active and should be uninstalled.", 'wp-simple-firewall' ),
							   'go_to_themes' => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Themes' ) ),
						   ],
						   'hrefs'   => [
							   'page_themes' => Services::WpGeneral()->getAdminUrl_Themes()
						   ],
						   'vars'    => [
							   'count_items' => count( $vulnerable ) + count( $hashes )
												+ count( $abandoned ) + count( $problems ),
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

		$vulnerabilities = $this->getVulnerabilities()->getItemsForSlug( $theme->stylesheet );

		$data = [
			'info'  => [
				'type'         => 'theme',
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
			'hrefs' => [
				'vul_info' => add_query_arg(
					[
						'type'    => $theme->asset_type,
						'slug'    => $theme->stylesheet,
						'version' => $theme->Version,
					],
					'https://shsec.io/shieldvulnerabilitylookup'
				),
			],
			'flags' => [
				'has_update'      => $theme->hasUpdate(),
				'is_abandoned'    => !empty( $abandoned ),
				'has_guard_files' => !empty( $guardFilesData ),
				'is_active'       => $theme->active || $theme->is_parent,
				'is_vulnerable'   => !empty( $vulnerabilities ),
				'is_wporg'        => $theme->isWpOrg(),
				'is_child'        => $theme->is_child,
				'is_parent'       => $theme->is_parent,
			],
			'vars'  => [
				'count_items' => count( $guardFilesData ) + count( $vulnerabilities )
								 + ( empty( $abandoned ) ? 0 : 1 )
			],
		];
		$data[ 'flags' ][ 'has_issue' ] = $data[ 'flags' ][ 'is_abandoned' ]
										  || $data[ 'flags' ][ 'has_guard_files' ]
										  || $data[ 'flags' ][ 'is_vulnerable' ];
		$data[ 'flags' ][ 'has_warning' ] = !$data[ 'flags' ][ 'has_issue' ]
											&& (
												!$data[ 'flags' ][ 'is_active' ]
												|| $data[ 'flags' ][ 'has_update' ]
											);
		return $data;
	}
}