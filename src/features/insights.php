<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Insights extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function doPostConstruction() {
		parent::doPostConstruction();
		$nActivatedAt = $this->getCon()
							 ->getModule_Plugin()
							 ->getActivatedAt();
		if ( $nActivatedAt > 0 && Services::Request()->ts() - $nActivatedAt < 5 ) {
			Services::Response()->redirect( $this->getUrl_AdminPage() );
		}
	}

	/**
	 * @param array $aData
	 * @return string
	 */
	protected function renderModulePage( $aData = [] ) {
		$oCon = $this->getCon();
		$oReq = Services::Request();
		$aSecNotices = $this->getNotices();

		$nNoticesCount = 0;
		foreach ( $aSecNotices as $aNoticeSection ) {
			$nNoticesCount += isset( $aNoticeSection[ 'count' ] ) ? $aNoticeSection[ 'count' ] : 0;
		}

		$sNavSection = $oReq->query( 'inav' );
		$sSubNavSection = $oReq->query( 'subnav' );

		/** @var ICWP_WPSF_FeatureHandler_Traffic $oTrafficMod */
		$oTrafficMod = $oCon->getModule( 'traffic' );
		/** @var Shield\Databases\Traffic\Select $oTrafficSelector */
		$oTrafficSelector = $oTrafficMod->getDbHandler()
										->getQuerySelector();

		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oAuditMod */
		$oAuditMod = $oCon->getModule( 'audit_trail' );
		/** @var Shield\Databases\AuditTrail\Select $oAuditSelect */
		$oAuditSelect = $oAuditMod->getDbHandler()->getQuerySelector();

		$oIpMod = $oCon->getModule_IPs();

		/** @var Shield\Databases\Session\Select $oSessionSelect */
		$oSessionSelect = $this->getDbHandler_Sessions()->getQuerySelector();

		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oModUsers */
		$oModUsers = $oCon->getModule( 'user_management' );
		/** @var ICWP_WPSF_Processor_HackProtect $oProHp */
		$oProHp = $oCon->getModule( 'hack_protect' )->getProcessor();
		/** @var ICWP_WPSF_FeatureHandler_License $oModLicense */
		$oModLicense = $oCon->getModule( 'license' );
		$oModPlugin = $oCon->getModule_Plugin();
		/** @var ICWP_WPSF_Processor_Plugin $oProPlugin */
		$oProPlugin = $oModPlugin->getProcessor();

		$bIsPro = $this->isPremium();
		$oCarbon = $oReq->carbon();
		$nPluginName = $oCon->getHumanName();
		switch ( $sNavSection ) {

			case 'audit':
				$aData = [
					'ajax'    => [
						'render_table_audittrail' => $oAuditMod->getAjaxActionData( 'render_table_audittrail', true ),
						'item_addparamwhite'      => $oAuditMod->getAjaxActionData( 'item_addparamwhite', true )
					],
					'flags'   => [],
					'strings' => [
						'table_title'             => __( 'Audit Trail', 'wp-simple-firewall' ),
						'title_filter_form'       => __( 'Audit Trail Filters', 'wp-simple-firewall' ),
						'username_ignores'        => __( "Providing a username will cause the 'logged-in' filter to be ignored.", 'wp-simple-firewall' ),
						'exclude_your_ip'         => __( 'Exclude Your Current IP', 'wp-simple-firewall' ),
						'exclude_your_ip_tooltip' => __( 'Exclude Your IP From Results', 'wp-simple-firewall' ),
						'context'                 => __( 'Context', 'wp-simple-firewall' ),
						'show_after'              => __( 'show results that occurred after', 'wp-simple-firewall' ),
						'show_before'             => __( 'show results that occurred before', 'wp-simple-firewall' ),
					],
					'vars'    => [
						'contexts_for_select' => $oAuditMod->getAllContexts(),
						'unique_ips'          => $oAuditSelect->getDistinctIps(),
						'unique_users'        => $oAuditSelect->getDistinctUsernames(),
					],
				];
				break;

			case 'ips':
				$aData = [
					'ajax'    => [
						'render_table_ip' => $oIpMod->getAjaxActionData( 'render_table_ip', true ),
						'item_insert'     => $oIpMod->getAjaxActionData( 'ip_insert', true ),
						'item_delete'     => $oIpMod->getAjaxActionData( 'ip_delete', true ),
					],
					'flags'   => [
						'can_blacklist' => $bIsPro
					],
					'strings' => [
						'trans_limit'       => sprintf(
							__( 'Offenses required for IP block: %s', 'wp-simple-firewall' ),
							sprintf( '<a href="%s" target="_blank">%s</a>', $oIpMod->getUrl_DirectLinkToOption( 'transgression_limit' ), $oIpMod->getOptTransgressionLimit() )
						),
						'auto_expire'       => sprintf(
							__( 'Black listed IPs auto-expire after: %s', 'wp-simple-firewall' ),
							sprintf( '<a href="%s" target="_blank">%s</a>',
								$oIpMod->getUrl_DirectLinkToOption( 'auto_expire' ),
								$oCarbon->setTimestamp( $oReq->ts() + $oIpMod->getAutoExpireTime() + 100 )
										->diffForHumans( null, true )
							)
						),
						'title_whitelist'   => __( 'IP Whitelist', 'wp-simple-firewall' ),
						'title_blacklist'   => __( 'IP Blacklist', 'wp-simple-firewall' ),
						'summary_whitelist' => sprintf( __( 'IP addresses that are never blocked by %s.', 'wp-simple-firewall' ), $nPluginName ),
						'summary_blacklist' => sprintf( __( 'IP addresses that have tripped %s defenses.', 'wp-simple-firewall' ), $nPluginName ),
						'enter_ip_block'    => __( 'Enter IP address to block', 'wp-simple-firewall' ),
						'enter_ip_white'    => __( 'Enter IP address to whitelist', 'wp-simple-firewall' ),
						'label_for_ip'      => __( 'Label for IP', 'wp-simple-firewall' ),
						'ip_new'            => __( 'New IP', 'wp-simple-firewall' ),
						'ip_block'          => __( 'Block IP', 'wp-simple-firewall' ),
					],
					'vars'    => [],
				];
				break;

			case 'notes':
				$aData = [
					'ajax'    => [
						'render_table_adminnotes' => $oModPlugin->getAjaxActionData( 'render_table_adminnotes', true ),
						'item_delete'             => $oModPlugin->getAjaxActionData( 'note_delete', true ),
						'item_insert'             => $oModPlugin->getAjaxActionData( 'note_insert', true ),
						'bulk_action'             => $oModPlugin->getAjaxActionData( 'bulk_action', true ),
					],
					'flags'   => [
						'can_adminnotes' => $bIsPro,
					],
					'strings' => [
						'note_title'    => __( 'Administrator Notes', 'wp-simple-firewall' ),
						'use_this_area' => __( 'Use this feature to make ongoing notes and to-dos', 'wp-simple-firewall' ),
						'note_add'      => __( 'Add Note', 'wp-simple-firewall' ),
						'note_new'      => __( 'New Note', 'wp-simple-firewall' ),
						'note_enter'    => __( 'Enter new note here', 'wp-simple-firewall' ),
					],
				];
				break;

			case 'traffic':
				$aData = [
					'ajax'    => [
						'render_table_traffic' => $oTrafficMod->getAjaxActionData( 'render_table_traffic', true )
					],
					'flags'   => [
						'can_traffic' => $bIsPro,
						'is_enabled'  => $oTrafficMod->isModOptEnabled(),
					],
					'hrefs'   => [
						'please_enable' => $oTrafficMod->getUrl_DirectLinkToOption( 'enable_traffic' ),
					],
					'strings' => [
						'title_filter_form'       => __( 'Traffic Table Filters', 'wp-simple-firewall' ),
						'traffic_title'           => __( 'Traffic Watch', 'wp-simple-firewall' ),
						'traffic_subtitle'        => __( 'Watch and review requests to your site', 'wp-simple-firewall' ),
						'response'                => __( 'Response', 'wp-simple-firewall' ),
						'path_contains'           => __( 'Page/Path Contains', 'wp-simple-firewall' ),
						'exclude_your_ip'         => __( 'Exclude Your Current IP', 'wp-simple-firewall' ),
						'exclude_your_ip_tooltip' => __( 'Exclude Your IP From Results', 'wp-simple-firewall' ),
						'username_ignores'        => __( "Providing a username will cause the 'logged-in' filter to be ignored.", 'wp-simple-firewall' ),
					],
					'vars'    => [
						'unique_ips'       => $oTrafficSelector->getDistinctIps(),
						'unique_responses' => $oTrafficSelector->getDistinctCodes(),
						'unique_users'     => $oTrafficSelector->getDistinctUsernames(),
					],
				];
				break;

			case 'license':
				$aData = $oModLicense->buildInsightsVars();
				break;

			case 'scans':
				$aData = $oProHp->buildInsightsVars();
				break;

			case 'importexport':
				$aData = $oProPlugin->getSubProImportExport()->buildInsightsVars();
				break;

			case 'reports':
				$aData = $oProPlugin->getSubProImportExport()->buildInsightsVars();
				break;

			case 'users':
				$aData = [
					'ajax'    => [
						'render_table_sessions' => $oModUsers->getAjaxActionData( 'render_table_sessions', true ),
						'item_delete'           => $oModUsers->getAjaxActionData( 'session_delete', true ),
						'bulk_action'           => $oModUsers->getAjaxActionData( 'bulk_action', true ),

					],
					'flags'   => [],
					'strings' => [
						'title_filter_form'   => __( 'Sessions Table Filters', 'wp-simple-firewall' ),
						'users_title'         => __( 'User Sessions', 'wp-simple-firewall' ),
						'users_subtitle'      => __( 'Review and manage current user sessions', 'wp-simple-firewall' ),
						'users_maybe_expired' => __( "Some sessions may have expired but haven't been automatically cleaned from the database yet", 'wp-simple-firewall' ),
						'username'            => __( 'Username', 'wp-simple-firewall' ),
					],
					'vars'    => [
						'unique_ips'   => $oSessionSelect->getDistinctIps(),
						'unique_users' => $oSessionSelect->getDistinctUsernames(),
					],
				];
				break;

			case 'settings':
				$aData = [
					'ajax' => [
						'mod_options'          => $oCon->getModule( Services::Request()->query( 'subnav' ) )
													   ->getAjaxActionData( 'mod_options', true ),
						'mod_opts_form_render' => $oCon->getModule( Services::Request()->query( 'subnav' ) )
													   ->getAjaxActionData( 'mod_opts_form_render', true ),
					],
				];
				break;

			case 'insights':
			case 'index':
			default:
				$sNavSection = 'insights';
				$aData = [
					'vars'    => [
						'config_cards'          => $this->getConfigCardsData(),
						'summary'               => $this->getInsightsModsSummary(),
						'insight_events'        => $this->getRecentEvents(),
						'insight_notices'       => $aSecNotices,
						'insight_notices_count' => $nNoticesCount,
						'insight_stats'         => $this->getStats(),
					],
					'inputs'  => [
						'license_key' => [
							'name'      => $this->prefixOptionKey( 'license_key' ),
							'maxlength' => $this->getDef( 'license_key_length' ),
						]
					],
					'ajax'    => [],
					'hrefs'   => [
						'shield_pro_url'           => 'https://icwp.io/shieldpro',
						'shield_pro_more_info_url' => 'https://icwp.io/shld1',
					],
					'flags'   => [
						'show_ads'              => false,
						'show_standard_options' => false,
						'show_alt_content'      => true,
						'is_pro'                => $bIsPro,
						'has_notices'           => count( $aSecNotices ) > 0,
					],
					'strings' => [
						'title_recent'              => __( 'Recent Events Log', 'wp-simple-firewall' ),
						'title_security_notices'    => __( 'Security Notices', 'wp-simple-firewall' ),
						'subtitle_security_notices' => __( 'Potential security issues on your site right now', 'wp-simple-firewall' ),
						'configuration_summary'     => __( 'Plugin Configuration Summary', 'wp-simple-firewall' ),
						'click_to_toggle'           => __( 'click to toggle', 'wp-simple-firewall' ),
						'go_to_options'             => sprintf(
							__( 'Go To %s', 'wp-simple-firewall' ),
							__( 'Options' )
						),
						'key'                       => __( 'Key' ),
						'key_positive'              => __( 'Positive Security', 'wp-simple-firewall' ),
						'key_warning'               => __( 'Potential Warning', 'wp-simple-firewall' ),
						'key_danger'                => __( 'Potential Danger', 'wp-simple-firewall' ),
						'key_information'           => __( 'Information', 'wp-simple-firewall' ),
					],
				];
				break;
		}

		$aTopNav = [
			'settings'     => __( 'Settings', 'wp-simple-firewall' ),
			'insights'     => __( 'Overview', 'wp-simple-firewall' ),
			'scans'        => __( 'Scans', 'wp-simple-firewall' ),
			'ips'          => __( 'IP Lists', 'wp-simple-firewall' ),
			'audit'        => __( 'Audit Trail', 'wp-simple-firewall' ),
			'users'        => __( 'Users', 'wp-simple-firewall' ),
			'license'      => __( 'Pro', 'wp-simple-firewall' ),
			'traffic'      => __( 'Traffic', 'wp-simple-firewall' ),
			'notes'        => __( 'Notes', 'wp-simple-firewall' ),
			'reports'      => __( 'Reports', 'wp-simple-firewall' ),
			'importexport' => sprintf( '%s/%s', __( 'Import', 'wp-simple-firewall' ), __( 'Export', 'wp-simple-firewall' ) ),
		];
		if ( $bIsPro ) {
			unset( $aTopNav[ 'license' ] );
			$aTopNav[ 'license' ] = __( 'Pro', 'wp-simple-firewall' );
		}

		array_walk( $aTopNav, function ( &$sName, $sKey ) use ( $sNavSection ) {
			$sName = [
				'href'    => add_query_arg( [ 'inav' => $sKey ], $this->getUrl_AdminPage() ),
				'name'    => $sName,
				'active'  => $sKey === $sNavSection,
				'subnavs' => []
			];
		} );

		$aSearchSelect = [];
		$aSettingsSubNav = [];
		foreach ( $this->getModulesSummaryData() as $sSlug => $aSubMod ) {
			$aSettingsSubNav[ $sSlug ] = [
				'href'   => add_query_arg( [ 'subnav' => $sSlug ], $aTopNav[ 'settings' ][ 'href' ] ),
				'name'   => $aSubMod[ 'name' ],
				'active' => $sSlug === $sSubNavSection,
				'slug'   => $sSlug
			];

			$aSearchSelect[ $aSubMod[ 'name' ] ] = $aSubMod[ 'options' ];
		}
		$aTopNav[ 'settings' ][ 'subnavs' ] = $aSettingsSubNav;

//		$aTopNav[ 'full_options' ] = [
//			'href'   => $this->getCon()->getModule_Plugin( )->getUrl_AdminPage(),
//			'name'   => __( 'Settings', 'wp-simple-firewall' ),
//			'active' => false
//		];

		$oDp = Services::DataManipulation();
		$aData = $oDp->mergeArraysRecursive(
			$this->getBaseDisplayData(),
			[
				'classes' => [
					'page_container' => 'page-insights page-'.$sNavSection
				],
				'flags'   => [
					'show_promo'       => !$bIsPro && ( $sNavSection != 'settings' ),
					'show_guided_tour' => $oModPlugin->getIfShowIntroVideo(),
				],
				'hrefs'   => [
					'go_pro'     => 'https://icwp.io/shieldgoprofeature',
					'nav_home'   => $this->getUrl_AdminPage(),
					'top_nav'    => $aTopNav,
					'img_banner' => $oCon->getPluginUrl_Image( 'pluginlogo_banner-170x40.png' )
				],
				'strings' => $this->getStrings()->getDisplayStrings(),
				'vars'    => [
					'changelog_id'  => $oCon->getPluginSpec()[ 'meta' ][ 'headway_changelog_id' ],
					'search_select' => $aSearchSelect
				],
			],
			$aData
		);
		return $this->renderTemplate( sprintf( '/wpadmin_pages/insights/%s/index.twig', $sNavSection ), $aData, true );
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		if ( $this->isThisModulePage() ) {

			$oConn = $this->getCon();
			$aStdDeps = [ $this->prefix( 'plugin' ) ];
			$sNav = Services::Request()->query( 'inav' );
			switch ( $sNav ) {

				case 'importexport':

					$sAsset = 'shield-import';
					$sUnique = $this->prefix( $sAsset );
					wp_register_script(
						$sUnique,
						$oConn->getPluginUrl_Js( $sAsset.'.js' ),
						$aStdDeps,
						$oConn->getVersion(),
						false
					);
					wp_enqueue_script( $sUnique );
					break;

				case 'scans':
				case 'audit':
				case 'ips':
				case 'notes':
				case 'traffic':
				case 'users':

					$sAsset = 'shield-tables';
					$sUnique = $this->prefix( $sAsset );
					wp_register_script(
						$sUnique,
						$oConn->getPluginUrl_Js( $sAsset.'.js' ),
						$aStdDeps,
						$oConn->getVersion(),
						false
					);
					wp_enqueue_script( $sUnique );

					$aStdDeps[] = $sUnique;
					if ( $sNav == 'scans' ) {
						$sAsset = 'shield-scans';
						$sUnique = $this->prefix( $sAsset );
						wp_register_script(
							$sUnique,
							$oConn->getPluginUrl_Js( $sAsset.'.js' ),
							array_unique( $aStdDeps ),
							$oConn->getVersion(),
							false
						);
						wp_enqueue_script( $sUnique );
					}

					if ( $sNav == 'audit' ) {
						$sUnique = $this->prefix( 'datepicker' );
						wp_register_script(
							$sUnique, //TODO: use an includes services for CNDJS
							'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/js/bootstrap-datepicker.min.js',
							array_unique( $aStdDeps ),
							$oConn->getVersion(),
							false
						);
						wp_enqueue_script( $sUnique );

						wp_register_style(
							$sUnique,
							'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.min.css',
							[],
							$oConn->getVersion(),
							false
						);
						wp_enqueue_style( $sUnique );
					}

					break;
			}
		}
	}

	/**
	 * @return array[]
	 */
	protected function getInsightsModsSummary() {
		$aMods = [];
		foreach ( $this->getModulesSummaryData() as $aMod ) {
			if ( !in_array( $aMod[ 'slug' ], [ 'insights' ] ) ) {
				$aMods[] = $aMod;
			}
		}
		return $aMods;
	}

	/**
	 * @return array[]
	 */
	protected function getConfigCardsData() {
		return apply_filters( $this->prefix( 'collect_summary' ), [] );
	}

	/**
	 * @return array[]
	 */
	protected function getNotices() {
		$aAll = apply_filters(
			$this->prefix( 'collect_notices' ),
			[
				'plugins' => $this->getNoticesPlugins(),
				'themes'  => $this->getNoticesThemes(),
				'core'    => $this->getNoticesCore(),
			]
		);

		// order and then remove empties
		return array_filter(
			array_merge(
				[
					'site'      => [],
					'sec_admin' => [],
					'scans'     => [],
					'core'      => [],
					'plugins'   => [],
					'themes'    => [],
					'users'     => [],
					'lockdown'  => [],
				],
				$aAll
			),
			function ( $aSection ) {
				return !empty( $aSection[ 'count' ] );
			}
		);
	}

	protected function getNoticesSite() {
		$oSslService = new \FernleafSystems\Wordpress\Services\Utilities\Ssl();

		$aNotices = [
			'title'    => __( 'Site', 'wp-simple-firewall' ),
			'messages' => []
		];

		// SSL Expires
		$sHomeUrl = Services::WpGeneral()->getHomeUrl();
		$bHomeSsl = strpos( $sHomeUrl, 'https://' ) === 0;

		if ( $bHomeSsl && $oSslService->isEnvSupported() ) {

			try {
				// first verify SSL cert:
				$oSslService->getCertDetailsForDomain( $sHomeUrl );

				// If we didn't throw and exception, we got it.
				$nExpiresAt = $oSslService->getExpiresAt( $sHomeUrl );
				if ( $nExpiresAt > 0 ) {
					$nTimeLeft = ( $nExpiresAt - Services::Request()->ts() );
					$bExpired = $nTimeLeft < 0;
					$nDaysLeft = $bExpired ? 0 : (int)round( $nTimeLeft/DAY_IN_SECONDS, 0, PHP_ROUND_HALF_DOWN );

					if ( $nDaysLeft < 15 ) {

						if ( $bExpired ) {
							$sMess = __( 'SSL certificate for this site has expired.', 'wp-simple-firewall' );
						}
						else {
							$sMess = sprintf( __( 'SSL certificate will expire soon (in %s days)', 'wp-simple-firewall' ), $nDaysLeft );
						}

						$aMessage = [
							'title'   => 'SSL Cert Expiration',
							'message' => $sMess,
							'href'    => '',
							'rec'     => __( 'Check or renew your SSL certificate.', 'wp-simple-firewall' )
						];
					}
				}
			}
			catch ( \Exception $oE ) {
				$aMessage = [
					'title'   => 'SSL Cert Expiration',
					'message' => 'Failed to retrieve a valid SSL certificate.',
					'href'    => ''
				];
			}

			if ( !empty( $aMessage ) ) {
				$aNotices[ 'messages' ][ 'ssl_cert' ] = $aMessage;
			}
		}

		{ // db password strength
			$nStrength = ( new \ZxcvbnPhp\Zxcvbn() )->passwordStrength( DB_PASSWORD )[ 'score' ];
			if ( $nStrength < 4 ) {
				$aNotices[ 'messages' ][ 'db_strength' ] = [
					'title'   => 'DB Password',
					'message' => __( 'DB Password appears to be weak.', 'wp-simple-firewall' ),
					'href'    => '',
					'rec'     => __( 'The database password should be strong.', 'wp-simple-firewall' )
				];
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );
		return $aNotices;
	}

	/**
	 * @return array
	 */
	protected function getNoticesPlugins() {
		$oWpPlugins = Services::WpPlugins();
		$aNotices = [
			'title'    => __( 'Plugins' ),
			'messages' => []
		];

		{// Inactive
			$nCount = count( $oWpPlugins->getPlugins() ) - count( $oWpPlugins->getActivePlugins() );
			if ( $nCount > 0 ) {
				$aNotices[ 'messages' ][ 'inactive' ] = [
					'title'   => __( 'Inactive', 'wp-simple-firewall' ),
					'message' => sprintf( __( '%s inactive plugin(s)', 'wp-simple-firewall' ), $nCount ),
					'href'    => Services::WpGeneral()->getAdminUrl_Plugins( true ),
					'action'  => sprintf( 'Go To %s', __( 'Plugins', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Unused plugins should be removed.', 'wp-simple-firewall' )
				];
			}
		}

		{// updates
			$nCount = count( $oWpPlugins->getUpdates() );
			if ( $nCount > 0 ) {
				$aNotices[ 'messages' ][ 'updates' ] = [
					'title'   => 'Updates',
					'message' => sprintf( __( '%s plugin update(s)', 'wp-simple-firewall' ), $nCount ),
					'href'    => Services::WpGeneral()->getAdminUrl_Updates( true ),
					'action'  => sprintf( 'Go To %s', __( 'Updates', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Updates should be applied as early as possible.', 'wp-simple-firewall' )
				];
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );
		return $aNotices;
	}

	/**
	 * @return array
	 */
	protected function getNoticesThemes() {
		$oWpT = Services::WpThemes();
		$aNotices = [
			'title'    => __( 'Themes', 'wp-simple-firewall' ),
			'messages' => []
		];

		{// Inactive
			$nInactive = count( $oWpT->getThemes() ) - ( $oWpT->isActiveThemeAChild() ? 2 : 1 );
			if ( $nInactive > 0 ) {
				$aNotices[ 'messages' ][ 'inactive' ] = [
					'title'   => 'Inactive',
					'message' => sprintf( __( '%s inactive themes(s)', 'wp-simple-firewall' ), $nInactive ),
					'href'    => Services::WpGeneral()->getAdminUrl_Themes( true ),
					'action'  => sprintf( 'Go To %s', __( 'Themes', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Unused themes should be removed.', 'wp-simple-firewall' )
				];
			}
		}

		{// updates
			$nCount = count( $oWpT->getUpdates() );
			if ( $nCount > 0 ) {
				$aNotices[ 'messages' ][ 'updates' ] = [
					'title'   => 'Updates',
					'message' => sprintf( __( '%s theme update(s)', 'wp-simple-firewall' ), $nCount ),
					'href'    => Services::WpGeneral()->getAdminUrl_Updates( true ),
					'action'  => sprintf( 'Go To %s', __( 'Updates', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Updates should be applied as early as possible.', 'wp-simple-firewall' )
				];
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );
		return $aNotices;
	}

	/**
	 * @return array
	 */
	protected function getNoticesCore() {
		$oWp = Services::WpGeneral();
		$aNotices = [
			'title'    => __( 'WordPress Core', 'wp-simple-firewall' ),
			'messages' => []
		];

		{// updates
			if ( $oWp->hasCoreUpdate() ) {
				$aNotices[ 'messages' ][ 'updates' ] = [
					'title'   => 'Updates',
					'message' => __( 'WordPress Core has an update available.', 'wp-simple-firewall' ),
					'href'    => $oWp->getAdminUrl_Updates( true ),
					'action'  => sprintf( 'Go To %s', __( 'Updates', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Updates should be applied as early as possible.', 'wp-simple-firewall' )
				];
			}
		}

		{// autoupdates
			if ( !$oWp->canCoreUpdateAutomatically() ) {
				$aNotices[ 'messages' ][ 'updates_auto' ] = [
					'title'   => 'Auto Updates',
					'message' => __( 'WordPress does not automatically install updates.', 'wp-simple-firewall' ),
					'href'    => $this->getCon()->getModule( 'autoupdates' )->getUrl_AdminPage(),
					'action'  => sprintf( 'Go To %s', __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Minor WordPress upgrades should be applied automatically.', 'wp-simple-firewall' )
				];
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );
		return $aNotices;
	}

	/**
	 * @return array[]
	 */
	protected function getStats() {
		/** @var Shield\Databases\Events\Handler $oDbhEvents */
		$oDbhEvents = $this->getCon()->getModule_Events()->getDbHandler();
		/** @var Shield\Databases\Events\Select $oSelEvents */
		$oSelEvents = $oDbhEvents->getQuerySelector();

		/** @var Shield\Databases\IPs\Select $oSelectIp */
		$oSelectIp = $this->getCon()
						  ->getModule_IPs()
						  ->getDbHandler()
						  ->getQuerySelector();

		return [
			'login'          => [
				'title'   => __( 'Login Blocks', 'wp-simple-firewall' ),
				'val'     => $oSelEvents->sumEvent( 'login_block' ),
				'tooltip' => __( 'Total login attempts blocked.', 'wp-simple-firewall' )
			],
			'firewall'       => [
				'title'   => __( 'Firewall Blocks', 'wp-simple-firewall' ),
				'val'     => $oSelEvents->sumEvent( 'firewall_block' ),
				'tooltip' => __( 'Total requests blocked by firewall rules.', 'wp-simple-firewall' )
			],
			'comments'       => [
				'title'   => __( 'Comment Blocks', 'wp-simple-firewall' ),
				'val'     => $oSelEvents->sumEvents( [ 'spam_block_bot', 'spam_block_human', 'spam_block_recaptcha' ] ),
				'tooltip' => __( 'Total SPAM comments blocked.', 'wp-simple-firewall' )
			],
			//			'sessions'       => array(
			//				'title'   => _wpsf__( 'Active Sessions' ),
			//				'val'     => $oProUsers->getProcessorSessions()->countActiveSessions(),
			//				'tooltip' => _wpsf__( 'Currently active user sessions.' )
			//			),
			'transgressions' => [
				'title'   => __( 'Offenses', 'wp-simple-firewall' ),
				'val'     => $oSelEvents->sumEvent( 'ip_offense' ),
				'tooltip' => __( 'Total offenses against the site.', 'wp-simple-firewall' )
			],
			'ip_blocks'      => [
				'title'   => __( 'IP Blocks', 'wp-simple-firewall' ),
				'val'     => $oSelEvents->sumEvent( 'conn_kill' ),
				'tooltip' => __( 'Total connections blocked/killed after too many offenses.', 'wp-simple-firewall' )
			],
			'blackips'       => [
				'title'   => __( 'Blacklist IPs', 'wp-simple-firewall' ),
				'val'     => $oSelectIp
					->filterByLists(
						[
							ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK,
							ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_BLACK
						]
					)->count(),
				'tooltip' => __( 'Current IP addresses with offenses against the site.', 'wp-simple-firewall' )
			],
			//			'pro'            => array(
			//				'title'   => _wpsf__( 'Pro' ),
			//				'val'     => $this->isPremium() ? _wpsf__( 'Yes' ) : _wpsf__( 'No' ),
			//				'tooltip' => sprintf( _wpsf__( 'Is this site running %s Pro' ), $oConn->getHumanName() )
			//			),
		];
	}

	/**
	 * @return array
	 */
	protected function getRecentEvents() {

		$aEmptyStats = [];
		foreach ( $this->getCon()->getModules() as $oModule ) {
			/** @var ICWP_WPSF_FeatureHandler_BaseWpsf $oModule */
			$aStat = $oModule->getStatEvents_Recent();
			$aEmptyStats = array_merge( $aEmptyStats, $aStat );
		}

		/** @var Shield\Modules\Insights\Strings $oStrs */
		$oStrs = $this->getStrings();
		$aNames = $oStrs->getInsightStatNames();

		/** @var Shield\Databases\Events\Handler $oEventsDbh */
		$oEventsDbh = $this->getCon()
						   ->getModule_Events()
						   ->getDbHandler();
		/** @var Shield\Databases\Events\Select $oSel */
		$oSel = $oEventsDbh->getQuerySelector();

		$aLatestStats = array_intersect_key(
			array_map(
				function ( $oEntryVO ) use ( $aNames ) {
					/** @var Shield\Databases\Events\EntryVO $oEntryVO */
					return [
						'name' => isset( $aNames[ $oEntryVO->event ] ) ? $aNames[ $oEntryVO->event ] : '*** '.$oEntryVO->event,
						'val'  => Services::WpGeneral()->getTimeStringForDisplay( $oEntryVO->created_at )
					];
				},
				$oSel->getLatestForAllEvents()
			),
			$aEmptyStats
		);

		$sNotYetRecorded = __( 'Not yet recorded', 'wp-simple-firewall' );
		foreach ( array_keys( $aEmptyStats ) as $sStatKey ) {
			if ( !isset( $aLatestStats[ $sStatKey ] ) ) {
				$aLatestStats[ $sStatKey ] = [
					'name' => isset( $aNames[ $sStatKey ] ) ? $aNames[ $sStatKey ] : '*** '.$sStatKey,
					'val'  => $sNotYetRecorded
				];
			}
		}

		return $aLatestStats;
	}

	/**
	 * @return Shield\Modules\Insights\Options
	 */
	protected function loadOptions() {
		return new Shield\Modules\Insights\Options();
	}

	/**
	 * @return Shield\Modules\Insights\Strings
	 */
	protected function loadStrings() {
		return new Shield\Modules\Insights\Strings();
	}
}