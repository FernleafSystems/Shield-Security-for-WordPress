<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\TableData\LoadTableDataTheme;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Assets\DetectInstallationDate;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class Themes extends PluginThemesBase {

	public const SLUG = 'scanresults_themes';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/section/themes/index.twig';

	protected function getRenderData() :array {
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

		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
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
		$countGuardFiles = ( new LoadTableDataTheme( $theme ) )->countAll();

		$vulnerabilities = $this->getVulnerabilities()->getItemsForSlug( $theme->stylesheet );

		$flags = [
			'has_update'      => $theme->hasUpdate(),
			'is_abandoned'    => !empty( $abandoned ),
			'has_guard_files' => $countGuardFiles > 0,
			'is_active'       => $theme->active || $theme->is_parent,
			'is_ignored'      => $theme->active || $theme->is_parent,
			'is_vulnerable'   => !empty( $vulnerabilities ),
			'is_wporg'        => $theme->isWpOrg(),
			'is_child'        => $theme->is_child,
			'is_parent'       => $theme->is_parent,
		];

		$isCheckActive = apply_filters( 'shield/scans_check_theme_active', true );
		$isCheckUpdates = apply_filters( 'shield/scans_check_theme_update', true );

		$flags[ 'has_issue' ] = $flags[ 'is_abandoned' ]
								|| $flags[ 'has_guard_files' ]
								|| $flags[ 'is_vulnerable' ];
		$flags[ 'has_warning' ] = !$flags[ 'has_issue' ]
								  && (
									  ( $isCheckActive && !$flags[ 'is_active' ] )
									  ||
									  ( $isCheckUpdates && $flags[ 'has_update' ] )
								  );

		if ( $theme->isWpOrg() && $flags[ 'has_warning' ] && !$flags[ 'has_update' ] ) {
			$wpOrgThemes = implode( '|', array_map( function ( $ver ) {
				return 'twenty'.$ver;
			}, [
				'twentyseven',
				'twentysix',
				'twentyfive',
				'twentyfour',
				'twentythree',
				'twentytwo',
				'twentyone',
				'twenty',
				'nineteen',
				'seventeen',
				'sixteen',
				'fifteen',
				'fourteen',
				'thirteen',
				'twelve',
				'eleven',
				'ten',
			] ) );
			if ( preg_match( sprintf( '#^%s$#', $wpOrgThemes ), strtolower( (string)$theme->slug ) ) ) {
				$flags[ 'has_warning' ] = false;
			}
		}

		return [
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
				'vul_info' => URL::Build( 'https://shsec.io/shieldvulnerabilitylookup', [
					'type'    => $theme->asset_type,
					'slug'    => $theme->stylesheet,
					'version' => $theme->Version,
				] ),
			],
			'flags' => $flags,
			'vars'  => [
				'count_items' => $countGuardFiles + count( $vulnerabilities ) + ( empty( $abandoned ) ? 0 : 1 )
			],
		];
	}
}