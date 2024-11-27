<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveBase;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans\ForPluginTheme;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\LoadFileScanResultsTableData;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Assets\DetectInstallationDate;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;
use FernleafSystems\Wordpress\Services\Utilities\URL;

abstract class PluginThemesBase extends Base {

	private static $wpOrgDataCache = false;

	protected function getRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
			'strings' => [
				'ptg_name'          => __( 'Plugin/Theme Guard', 'wp-simple-firewall' ),
				'ptg_not_available' => __( 'Scanning Plugin & Theme Files is only available with ShieldPRO.', 'wp-simple-firewall' ),
			],
			'flags'   => [
				'ptg_is_restricted' => !self::con()->isPremiumActive(),
			],
			'vars'    => [
				'datatables_init' => ( new ForPluginTheme() )->build()
			]
		] );
	}

	protected function getVulnerabilities() :Scans\Wpv\ResultsSet {
		try {
			$vulnerable = self::con()->comps->scans->WPV()->getResultsForDisplay();
		}
		catch ( \Exception $e ) {
			$vulnerable = new Scans\Wpv\ResultsSet();
		}
		return $vulnerable;
	}

	protected function getAbandoned() :Scans\Apc\ResultsSet {
		try {
			$abandoned = self::con()->comps->scans->APC()->getResultsForDisplay();
		}
		catch ( \Exception $e ) {
			$abandoned = new Scans\Apc\ResultsSet();
		}
		return $abandoned;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $item
	 */
	protected function getCachedFlags( $item ) :array {
		if ( !\is_array( self::$wpOrgDataCache ) ) {
			self::$wpOrgDataCache = Transient::Get( 'apto-shield-plugintheme-flags-cache' );
			if ( !\is_array( self::$wpOrgDataCache ) ) {
				self::$wpOrgDataCache = [];
			}
		}

		if ( !isset( self::$wpOrgDataCache[ $item->unique_id ] ) ) {
			self::$wpOrgDataCache[ $item->unique_id ] = [
				'is_wporg' => $item->isWpOrg(),
				'has_tag'  => $item->isWpOrg() && ( $item->svn_uses_tags || \is_a( $item, WpThemeVo::class ) ),
			];
			Transient::Set( 'apto-shield-plugintheme-flags-cache', self::$wpOrgDataCache, \HOUR_IN_SECONDS );
		}

		return self::$wpOrgDataCache[ $item->unique_id ];
	}

	protected function buildPluginData( WpPluginVo $plugin, bool $queryWpOrgData = false ) :array {
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

		$flags = \array_merge( [
			'has_update'      => $plugin->hasUpdate(),
			'has_guard_files' => $countGuardFiles > 0,
			'is_abandoned'    => !empty( $abandoned ),
			'is_active'       => $plugin->active,
			'is_vulnerable'   => !empty( $vulnerabilities ),
		], $queryWpOrgData ? $this->getCachedFlags( $plugin ) : [] );

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
				'unique_id'    => $plugin->unique_id,
				'description'  => $plugin->Description,
				'version'      => $plugin->Version,
				'author'       => $plugin->AuthorName,
				'author_url'   => $plugin->AuthorURI,
				'file'         => $plugin->file,
				'installed_at' => $carbon->setTimestamp( ( new DetectInstallationDate() )->plugin( $plugin ) )
										 ->diffForHumans(),
				'dir'          => '/'.\str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $plugin->getInstallDir() ) ),
				'abandoned_at' => empty( $abandoned ) ? 0
					: $carbon->setTimestamp( $abandoned->last_updated_at )->diffForHumans(),
			],
			'hrefs' => [
				'vul_info' => URL::Build( 'https://clk.shldscrty.com/shieldvulnerabilitylookup', [
					'type'    => $plugin->asset_type,
					'slug'    => $plugin->slug,
					'version' => $plugin->Version,
				] ),
			],
			'flags' => $flags,
			'vars'  => [
				'abandoned_rid' => empty( $abandoned ) ? -1 : $abandoned->VO->scanresult_id,
				'count_items'   => $countGuardFiles + \count( $vulnerabilities ) + ( empty( $abandoned ) ? 0 : 1 )
			],
		];
	}

	protected function buildThemeData( WpThemeVo $theme, bool $queryWpOrgData = false ) :array {
		$carbon = Services::Request()->carbon();

		$abandoned = $this->getAbandoned()->getItemForSlug( $theme->stylesheet );

		$resultsLoader = new LoadFileScanResultsTableData();
		$resultsLoader->custom_record_retriever_wheres = [
			sprintf( "%s.`meta_key`='ptg_slug'", RetrieveBase::ABBR_RESULTITEMMETA ),
			sprintf( "%s.`meta_value`='%s'", RetrieveBase::ABBR_RESULTITEMMETA, $theme->stylesheet ),
		];
		$countGuardFiles = $resultsLoader->countAll();

		$vulnerabilities = $this->getVulnerabilities()->getItemsForSlug( $theme->stylesheet );

		$flags = \array_merge( [
			'has_update'      => $theme->hasUpdate(),
			'is_abandoned'    => !empty( $abandoned ),
			'has_guard_files' => $countGuardFiles > 0,
			'is_active'       => $theme->active || $theme->is_parent,
			'is_ignored'      => $theme->active || $theme->is_parent,
			'is_vulnerable'   => !empty( $vulnerabilities ),
			'is_child'        => $theme->is_child,
			'is_parent'       => $theme->is_parent,
		], $queryWpOrgData ? $this->getCachedFlags( $theme ) : [] );

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

		// We only run the API check for WordPress.org themes if certain conditions are met
		if ( $flags[ 'has_warning' ] && !$flags[ 'has_update' ] ) {
			$wpOrgThemes = \implode( '|', \array_map( function ( $ver ) {
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
			if ( \preg_match( sprintf( '#^%s$#', $wpOrgThemes ), \strtolower( (string)$theme->slug ) ) ) {
				$flags = \array_merge( $flags, $this->getCachedFlags( $theme ) );
				if ( $flags[ 'is_wporg' ] ) {
					$flags[ 'has_warning' ] = false;
				}
			}
		}

		return [
			'info'  => [
				'type'         => 'theme',
				'name'         => $theme->Name,
				'slug'         => $theme->slug,
				'unique_id'    => $theme->unique_id,
				'description'  => $theme->Description,
				'version'      => $theme->Version,
				'author'       => $theme->Author,
				'author_url'   => $theme->AuthorURI,
				'file'         => $theme->stylesheet,
				'dir'          => '/'.\str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $theme->getInstallDir() ) ),
				'abandoned_at' => empty( $abandoned ) ? 0
					: $carbon->setTimestamp( $abandoned->last_updated_at )->diffForHumans(),
				'installed_at' => $carbon->setTimestamp( ( new DetectInstallationDate() )->theme( $theme ) )
										 ->diffForHumans(),
				'child_theme'  => $theme->is_parent ? $theme->child_theme->Name : '',
				'parent_theme' => $theme->is_child ? $theme->parent_theme->Name : '',
			],
			'hrefs' => [
				'vul_info' => URL::Build( 'https://clk.shldscrty.com/shieldvulnerabilitylookup', [
					'type'    => $theme->asset_type,
					'slug'    => $theme->stylesheet,
					'version' => $theme->Version,
				] ),
			],
			'flags' => $flags,
			'vars'  => [
				'count_items' => $countGuardFiles + \count( $vulnerabilities ) + ( empty( $abandoned ) ? 0 : 1 )
			],
		];
	}
}