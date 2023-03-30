<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\LoadFileScanResultsTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\TableData\LoadFileScanResultsTableDataPlugin;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveBase;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Assets\DetectInstallationDate;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class Plugins extends PluginThemesBase {

	public const SLUG = 'scanresults_plugins';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/section/plugins/index.twig';

	protected function getRenderData() :array {
		$items = $this->buildPluginsData();
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
				'no_items'      => __( "Previous scans didn't detect any modified or unrecognised files in any plugin directories.", 'wp-simple-firewall' ),
				'no_files'      => __( "Previous scans didn't detect any modified or unrecognised files in the plugin directory.", 'wp-simple-firewall' ),
				'files_found'   => __( "Previous scans detected 1 or more modified or unrecognised files in the plugin directory.", 'wp-simple-firewall' ),
				'not_active'    => __( "This plugin isn't active and should be uninstalled.", 'wp-simple-firewall' ),
				'wporg_ok'      => __( "This plugin is installed from WordPress.org so actions such as file repair and file diff are available.", 'wp-simple-firewall' ),
				'not_wporg'     => __( "This plugin isn't installed from WordPress.org so actions such as file repair and file diff aren't available.", 'wp-simple-firewall' ),
				'no_tags'       => __( "The plugin developer chose not to use SVN tags for this version, so actions such as file repair and file diff aren't available.", 'wp-simple-firewall' ),
				'go_to_plugins' => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Plugins' ) ),
			],
			'hrefs'   => [
				'page_plugins' => Services::WpGeneral()->getAdminUrl_Plugins()
			],
			'vars'    => [
				'count_items' => count( $vulnerable ) + count( $hashes )
								 + count( $abandoned ) + count( $problems ),
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

		$resultsLoader = new LoadFileScanResultsTableData();
		$resultsLoader->custom_record_retriever_wheres = [
			sprintf( "%s.`meta_key`='ptg_slug'", RetrieveBase::ABBR_RESULTITEMMETA ),
			sprintf( "%s.`meta_value`='%s'", RetrieveBase::ABBR_RESULTITEMMETA, $plugin->file ),
		];
		$countGuardFiles = $resultsLoader->countAll();

		$vulnerabilities = $this->getVulnerabilities()->getItemsForSlug( $plugin->file );

		$isCheckActive = apply_filters( 'shield/scans_check_plugin_active', true );
		$isCheckUpdates = apply_filters( 'shield/scans_check_plugin_update', true );

		$flags = [
			'has_update'      => $plugin->hasUpdate(),
			'has_guard_files' => $countGuardFiles > 0,
			'is_abandoned'    => !empty( $abandoned ),
			'is_active'       => $plugin->active,
			'is_vulnerable'   => !empty( $vulnerabilities ),
			'is_wporg'        => $plugin->isWpOrg(),
			'has_tag'         => $plugin->isWpOrg() && $plugin->svn_uses_tags,
		];
		$flags[ 'has_issue' ] = $flags[ 'is_abandoned' ]
								|| $flags[ 'has_guard_files' ]
								|| $flags[ 'is_vulnerable' ];
		$flags[ 'has_warning' ] = !$flags[ 'has_issue' ]
								  && (
									  ( $isCheckActive && !$flags[ 'is_active' ] )
									  ||
									  ( $isCheckUpdates && $flags[ 'has_update' ] )
								  );

		return [
			'info'  => [
				'type'         => 'plugin',
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
			'hrefs' => [
				'vul_info' => URL::Build( 'https://shsec.io/shieldvulnerabilitylookup', [
					'type'    => $plugin->asset_type,
					'slug'    => $plugin->slug,
					'version' => $plugin->Version,
				] ),
			],
			'flags' => $flags,
			'vars'  => [
				'abandoned_rid' => empty( $abandoned ) ? -1 : $abandoned->VO->scanresult_id,
				'count_items'   => $countGuardFiles + count( $vulnerabilities )
								   + ( empty( $abandoned ) ? 0 : 1 )
			],
		];
	}
}