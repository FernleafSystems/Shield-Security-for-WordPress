<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Insights extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function doPostConstruction() {
		parent::doPostConstruction();
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oP */
		$oP = $this->getCon()->getModule( 'plugin' );
		$nActivatedAt = $oP->getActivatedAt();
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

		/** @var ICWP_WPSF_FeatureHandler_Traffic $oTrafficMod */
		$oTrafficMod = $oCon->getModule( 'traffic' );
		/** @var ICWP_WPSF_Processor_Traffic $oTrafficPro */
		$oTrafficPro = $oTrafficMod->getProcessor();
		/** @var Shield\Databases\Traffic\Select $oTrafficSelector */
		$oTrafficSelector = $oTrafficPro->getProcessorLogger()
										->getDbHandler()
										->getQuerySelector();

		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oAuditMod */
		$oAuditMod = $oCon->getModule( 'audit_trail' );
		/** @var ICWP_WPSF_Processor_AuditTrail $oAuditPro */
		$oAuditPro = $oAuditMod->getProcessor();
		/** @var Shield\Databases\AuditTrail\Select $oAuditSelect */
		$oAuditSelect = $oAuditPro->getSubProAuditor()->getDbHandler()->getQuerySelector();

		/** @var ICWP_WPSF_FeatureHandler_Ips $oIpMod */
		$oIpMod = $oCon->getModule( 'ips' );

		/** @var ICWP_WPSF_Processor_Sessions $oProSessions */
		$oProSessions = $oCon->getModule( 'sessions' )->getProcessor();
		/** @var Shield\Databases\Session\Select $oSessionSelect */
		$oSessionSelect = $oProSessions->getDbHandler()->getQuerySelector();

		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oModUsers */
		$oModUsers = $oCon->getModule( 'user_management' );
		/** @var ICWP_WPSF_Processor_HackProtect $oProHp */
		$oProHp = $oCon->getModule( 'hack_protect' )->getProcessor();
		/** @var ICWP_WPSF_FeatureHandler_License $oModLicense */
		$oModLicense = $oCon->getModule( 'license' );
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oModPlugin */
		$oModPlugin = $oCon->getModule( 'plugin' );
		/** @var ICWP_WPSF_Processor_Plugin $oProPlugin */
		$oProPlugin = $oModPlugin->getProcessor();

		$bIsPro = $this->isPremium();
		$oCarbon = new \Carbon\Carbon();
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
						'title_filter_form' => __( 'Audit Trail Filters', 'wp-simple-firewall' ),
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
							'Transgressions required for IP block: %s',
							sprintf( '<a href="%s" target="_blank">%s</a>', $oIpMod->getUrl_DirectLinkToOption( 'transgression_limit' ), $oIpMod->getOptTransgressionLimit() )
						),
						'auto_expire'       => sprintf(
							'Black listed IPs auto-expire after: %s',
							sprintf( '<a href="%s" target="_blank">%s</a>',
								$oIpMod->getUrl_DirectLinkToOption( 'auto_expire' ),
								$oCarbon->setTimestamp( $oReq->ts() + $oIpMod->getAutoExpireTime() + 1 )
										->diffForHumans( null, true )
							)
						),
						'title_whitelist'   => __( 'IP Whitelist', 'wp-simple-firewall' ),
						'title_blacklist'   => __( 'IP Blacklist', 'wp-simple-firewall' ),
						'summary_whitelist' => sprintf( __( 'IP addresses that are never blocked by %s.', 'wp-simple-firewall' ), $nPluginName ),
						'summary_blacklist' => sprintf( __( 'IP addresses that have tripped %s defenses.', 'wp-simple-firewall' ), $nPluginName ),
					],
					'vars'    => [],
				];
				break;

			case 'notes':
				$aData = [
					'vars'  => [],
					'ajax'  => [
						'render_table_adminnotes' => $oModPlugin->getAjaxActionData( 'render_table_adminnotes', true ),
						'item_delete'             => $oModPlugin->getAjaxActionData( 'note_delete', true ),
						'item_insert'             => $oModPlugin->getAjaxActionData( 'note_insert', true ),
						'bulk_action'             => $oModPlugin->getAjaxActionData( 'bulk_action', true ),
					],
					'flags' => [
						'can_adminnotes' => $bIsPro,
					]
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
						'title_filter_form' => __( 'Traffic Table Filters', 'wp-simple-firewall' ),
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

			case 'users':
				$aData = [
					'ajax'    => [
						'render_table_sessions' => $oModUsers->getAjaxActionData( 'render_table_sessions', true ),
						'item_delete'           => $oModUsers->getAjaxActionData( 'session_delete', true ),
						'bulk_action'           => $oModUsers->getAjaxActionData( 'bulk_action', true ),

					],
					'flags'   => [],
					'strings' => [
						'title_filter_form' => __( 'Sessions Table Filters', 'wp-simple-firewall' ),
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
					'vars'   => [
						'config_cards'          => $this->getConfigCardsData(),
						'summary'               => $this->getInsightsModsSummary(),
						'insight_events'        => $this->getRecentEvents(),
						'insight_notices'       => $aSecNotices,
						'insight_notices_count' => $nNoticesCount,
						'insight_stats'         => $this->getStats(),
					],
					'inputs' => [
						'license_key' => [
							'name'      => $this->prefixOptionKey( 'license_key' ),
							'maxlength' => $this->getDef( 'license_key_length' ),
						]
					],
					'ajax'   => [],
					'hrefs'  => [
						'shield_pro_url'           => 'https://icwp.io/shieldpro',
						'shield_pro_more_info_url' => 'https://icwp.io/shld1',
					],
					'flags'  => [
						'show_ads'              => false,
						'show_standard_options' => false,
						'show_alt_content'      => true,
						'is_pro'                => $bIsPro,
						'has_notices'           => count( $aSecNotices ) > 0,
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
			'importexport' => sprintf( '%s/%s', __( 'Import', 'wp-simple-firewall' ), __( 'Export', 'wp-simple-firewall' ) ),
		];
		if ( $bIsPro ) {
			unset( $aTopNav[ 'license' ] );
			$aTopNav[ 'license' ] = __( 'Pro', 'wp-simple-firewall' );
		}

		array_walk( $aTopNav, function ( &$sName, $sKey ) use ( $sNavSection ) {
			$sName = [
				'href'   => add_query_arg( [ 'inav' => $sKey ], $this->getUrl_AdminPage() ),
				'name'   => $sName,
				'active' => $sKey === $sNavSection,
				'subnav' => []
			];
		} );

		$aSettingsSubNav = [];
		foreach ( $this->getModulesSummaryData() as $sSlug => $aSubMod ) {
			$aSettingsSubNav[ $sSlug ] = [
				'href' => add_query_arg( [ 'subnav' => $sSlug ], $aTopNav[ 'settings' ][ 'href' ] ),
				'name' => $aSubMod[ 'name' ]
			];
		}
		$aTopNav[ 'settings' ][ 'subnav' ] = $aSettingsSubNav;

//		$aTopNav[ 'full_options' ] = [
//			'href'   => $this->getCon()->getModule( 'plugin' )->getUrl_AdminPage(),
//			'name'   => __( 'Settings', 'wp-simple-firewall' ),
//			'active' => false
//		];

		$oDp = Services::DataManipulation();
		$aData = $oDp->mergeArraysRecursive(
			$this->getBaseDisplayData( false ),
			[
				'classes' => [
					'page_container' => 'page-insights page-'.$sNavSection
				],
				'flags'   => [
					'show_promo'       => !$bIsPro,
					'show_guided_tour' => $oModPlugin->getIfShowIntroVideo(),
				],
				'hrefs'   => [
					'go_pro'     => 'https://icwp.io/shieldgoprofeature',
					'nav_home'   => $this->getUrl_AdminPage(),
					'top_nav'    => $aTopNav,
					'img_banner' => $oCon->getPluginUrl_Image( 'pluginlogo_banner-170x40.png' )
				],
				'strings' => $this->getDisplayStrings(),
				'vars'    => [
					'changelog_id' => $oCon->getPluginSpec()[ 'meta' ][ 'headway_changelog_id' ],
				],
			],
			$aData
		);
		return $this->renderTemplate( sprintf( '/wpadmin_pages/insights_new/%s/index.twig', $sNavSection ), $aData, true );
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
	 * @return array
	 */
	protected function getDisplayStrings() {
		$sName = $this->getCon()->getHumanName();
		return $this->loadDP()->mergeArraysRecursive(
			parent::getDisplayStrings(),
			[
				'page_title'          => sprintf( __( '%s Security Insights', 'wp-simple-firewall' ), $sName ),
				'recommendation'      => ucfirst( __( 'recommendation', 'wp-simple-firewall' ) ),
				'suggestion'          => ucfirst( __( 'suggestion', 'wp-simple-firewall' ) ),
				'box_welcome_title'   => sprintf( __( 'Welcome To %s Security Insights Dashboard', 'wp-simple-firewall' ), $sName ),
				'box_receve_subtitle' => sprintf( __( 'Some of the most recent %s events', 'wp-simple-firewall' ), $sName ),

				'never'          => __( 'Never', 'wp-simple-firewall' ),
				'go_pro'         => 'Go Pro!',
				'options'        => __( 'Options', 'wp-simple-firewall' ),
				'not_available'  => __( 'Sorry, this feature would typically be used by professionals and so is a Pro-only feature.', 'wp-simple-firewall' ),
				'not_enabled'    => __( "This feature isn't currently enabled.", 'wp-simple-firewall' ),
				'please_upgrade' => __( 'You can activate this feature (along with many others) and support development of this plugin for just $12.', 'wp-simple-firewall' ),
				'please_enable'  => __( 'Please turn on this feature in the options.', 'wp-simple-firewall' ),
				'only_1_dollar'  => __( 'for just $1/month', 'wp-simple-firewall' ),
			]
		);
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
		$oSslService = $this->loadSslService();

		$aNotices = [
			'title'    => __( 'Site', 'wp-simple-firewall' ),
			'messages' => []
		];

		// SSL Expires
		$sHomeUrl = $this->loadWp()->getHomeUrl();
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
		$oWpPlugins = $this->loadWpPlugins();
		$aNotices = [
			'title'    => __( 'Plugins', 'wp-simple-firewall' ),
			'messages' => []
		];

		{// Inactive
			$nCount = count( $oWpPlugins->getPlugins() ) - count( $oWpPlugins->getActivePlugins() );
			if ( $nCount > 0 ) {
				$aNotices[ 'messages' ][ 'inactive' ] = [
					'title'   => 'Inactive',
					'message' => sprintf( __( '%s inactive plugin(s)', 'wp-simple-firewall' ), $nCount ),
					'href'    => $this->loadWp()->getAdminUrl_Plugins( true ),
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
					'href'    => $this->loadWp()->getAdminUrl_Updates( true ),
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
		$oWpT = $this->loadWpThemes();
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
					'href'    => $this->loadWp()->getAdminUrl_Themes( true ),
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
					'href'    => $this->loadWp()->getAdminUrl_Updates( true ),
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
		$oWp = $this->loadWp();
		$aNotices = [
			'title'    => __( 'WordPress Core', 'wp-simple-firewall' ),
			'messages' => []
		];

		{// updates
			if ( $oWp->hasCoreUpdate() ) {
				$aNotices[ 'messages' ][ 'updates' ] = [
					'title'   => 'Updates',
					'message' => __( 'WordPress Core has an update available.', 'wp-simple-firewall' ),
					'href'    => $this->loadWp()->getAdminUrl_Updates( true ),
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
		$oConn = $this->getCon();
		/** @var ICWP_WPSF_Processor_Statistics $oStats */
		$oStats = $oConn->getModule( 'statistics' )->getProcessor();

		/** @var ICWP_WPSF_Processor_Ips $oIPs */
		$oIPs = $oConn->getModule( 'ips' )->getProcessor();
		/** @var Shield\Databases\IPs\Select $oSelect */
		$oSelect = $oIPs->getDbHandler()->getQuerySelector();

		$aStats = $oStats->getInsightsStats();
		return [
			'login'          => [
				'title'   => __( 'Login Blocks', 'wp-simple-firewall' ),
				'val'     => $aStats[ 'login.blocked.all' ],
				'tooltip' => __( 'Total login attempts blocked.', 'wp-simple-firewall' )
			],
			'firewall'       => [
				'title'   => __( 'Firewall Blocks', 'wp-simple-firewall' ),
				'val'     => $aStats[ 'firewall.blocked.all' ],
				'tooltip' => __( 'Total requests blocked by firewall rules.', 'wp-simple-firewall' )
			],
			'comments'       => [
				'title'   => __( 'Comment Blocks', 'wp-simple-firewall' ),
				'val'     => $aStats[ 'comments.blocked.all' ],
				'tooltip' => __( 'Total SPAM comments blocked.', 'wp-simple-firewall' )
			],
			//			'sessions'       => array(
			//				'title'   => _wpsf__( 'Active Sessions' ),
			//				'val'     => $oProUsers->getProcessorSessions()->countActiveSessions(),
			//				'tooltip' => _wpsf__( 'Currently active user sessions.' )
			//			),
			'transgressions' => [
				'title'   => __( 'Transgressions', 'wp-simple-firewall' ),
				'val'     => $aStats[ 'ip.transgression.incremented' ],
				'tooltip' => __( 'Total transgression against the site.', 'wp-simple-firewall' )
			],
			'ip_blocks'      => [
				'title'   => __( 'IP Blocks', 'wp-simple-firewall' ),
				'val'     => $aStats[ 'ip.connection.killed' ],
				'tooltip' => __( 'Total connections blocked/killed after too many transgressions.', 'wp-simple-firewall' )
			],
			'blackips'       => [
				'title'   => __( 'Blacklist IPs', 'wp-simple-firewall' ),
				'val'     => $oSelect
					->filterByLists(
						[
							ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK,
							ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_BLACK
						]
					)->count(),
				'tooltip' => __( 'Current IP addresses with transgressions against the site.', 'wp-simple-firewall' )
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

		$aStats = [];
		foreach ( $this->getCon()->getModules() as $oModule ) {
			/** @var ICWP_WPSF_FeatureHandler_BaseWpsf $oModule */
			$aStats = array_merge( $aStats, $oModule->getInsightsOpts() );
		}

		$oWP = Services::WpGeneral();
		$aNames = $this->getInsightStatNames();
		foreach ( $aStats as $sStatKey => $nValue ) {
			$aStats[ $sStatKey ] = [
				'name' => $aNames[ $sStatKey ],
				'val'  => ( $nValue > 0 ) ? $oWP->getTimeStringForDisplay( $nValue ) : __( 'Not yet recorded', 'wp-simple-firewall' ),
			];
		}

		return $aStats;
	}

	/**
	 * @return string[]
	 */
	private function getInsightStatNames() {
		return [
			'insights_test_cron_last_run_at'        => __( 'Simple Test Cron', 'wp-simple-firewall' ),
			'insights_last_scan_ufc_at'             => __( 'Unrecognised Files Scan', 'wp-simple-firewall' ),
			'insights_last_scan_apc_at'             => __( 'Abandoned Plugins Scan', 'wp-simple-firewall' ),
			'insights_last_scan_wcf_at'             => __( 'WordPress Core Files Scan', 'wp-simple-firewall' ),
			'insights_last_scan_ptg_at'             => __( 'Plugin/Themes Guard Scan', 'wp-simple-firewall' ),
			'insights_last_scan_wpv_at'             => __( 'Vulnerabilities Scan', 'wp-simple-firewall' ),
			'insights_last_2fa_login_at'            => __( 'Successful 2-FA Login', 'wp-simple-firewall' ),
			'insights_last_login_block_at'          => __( 'Login Block', 'wp-simple-firewall' ),
			'insights_last_register_block_at'       => __( 'User Registration Block', 'wp-simple-firewall' ),
			'insights_last_reset-password_block_at' => __( 'Reset Password Block', 'wp-simple-firewall' ),
			'insights_last_firewall_block_at'       => __( 'Firewall Block', 'wp-simple-firewall' ),
			'insights_last_idle_logout_at'          => __( 'Idle Logout', 'wp-simple-firewall' ),
			'insights_last_password_block_at'       => __( 'Password Block', 'wp-simple-firewall' ),
			'insights_last_comment_block_at'        => __( 'Comment SPAM Block', 'wp-simple-firewall' ),
			'insights_xml_block_at'                 => __( 'XML-RPC Block', 'wp-simple-firewall' ),
			'insights_restapi_block_at'             => __( 'Anonymous Rest API Block', 'wp-simple-firewall' ),
			'insights_last_transgression_at'        => sprintf( __( '%s Transgression', 'wp-simple-firewall' ), $this->getCon()
																													 ->getHumanName() ),
			'insights_last_ip_block_at'             => __( 'IP Connection Blocked', 'wp-simple-firewall' ),
		];
	}
}