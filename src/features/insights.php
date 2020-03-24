<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Insights extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function onModulesLoaded() {
		$this->maybeRedirectToAdmin();
	}

	private function maybeRedirectToAdmin() {
		$oCon = $this->getCon();
		$nActiveFor = $oCon->getModule_Plugin()->getActivateLength();
		if ( !Services::WpGeneral()->isAjax() && is_admin() && !$oCon->isModulePage() && $nActiveFor < 4 ) {
			Services::Response()->redirect( $this->getUrl_AdminPage() );
		}
	}

	/**
	 * @param string $sSubPage
	 * @return string
	 */
	public function getUrl_SubInsightsPage( $sSubPage ) {
		return add_query_arg(
			[ 'inav' => sanitize_key( $sSubPage ) ],
			$this->getCon()->getModule_Insights()->getUrl_AdminPage()
		);
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

		$sNavSection = $oReq->query( 'inav', 'overview' );
		$sSubNavSection = $oReq->query( 'subnav' );

		/** @var \ICWP_WPSF_FeatureHandler_Traffic $oTrafficMod */
		$oTrafficMod = $oCon->getModule( 'traffic' );
		/** @var Shield\Databases\Traffic\Select $oTrafficSelector */
		$oTrafficSelector = $oTrafficMod->getDbHandler_Traffic()->getQuerySelector();

		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oAuditMod */
		$oAuditMod = $oCon->getModule( 'audit_trail' );
		/** @var Shield\Databases\AuditTrail\Select $oAuditSelect */
		$oAuditSelect = $oAuditMod->getDbHandler_AuditTrail()->getQuerySelector();

		/** @var Shield\Modules\Events\Strings $oEventStrings */
		$oEventStrings = $oCon->getModule_Events()->getStrings();
		$aEventsSelect = array_intersect_key( $oEventStrings->getEventNames(), array_flip( $oAuditSelect->getDistinctEvents() ) );
		asort( $aEventsSelect );

		$oIpMod = $oCon->getModule_IPs();
		/** @var Shield\Modules\IPs\Options $oIpOpts */
		$oIpOpts = $oIpMod->getOptions();

		/** @var Shield\Databases\Session\Select $oSessionSelect */
		$oSessionSelect = $this->getDbHandler_Sessions()->getQuerySelector();

		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oModUsers */
		$oModUsers = $oCon->getModule( 'user_management' );
		/** @var ICWP_WPSF_Processor_HackProtect $oProHp */
		$oProHp = $oCon->getModule( 'hack_protect' )->getProcessor();
		/** @var ICWP_WPSF_FeatureHandler_License $oModLicense */
		$oModLicense = $oCon->getModule( 'license' );

		$oModPlugin = $oCon->getModule_Plugin();
		$oTourManager = $oModPlugin->getTourManager();
		if ( !$oTourManager->isCompleted( 'insights_overview' ) && $oModPlugin->getActivateLength() > 600 ) {
			$oTourManager->setCompleted( 'insights_overview' );
		}

		/** @var ICWP_WPSF_Processor_Plugin $oProPlugin */
		$oProPlugin = $oModPlugin->getProcessor();
		$oEvtsMod = $oCon->getModule_Events();

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
						'event'                   => __( 'Event', 'wp-simple-firewall' ),
						'show_after'              => __( 'show results that occurred after', 'wp-simple-firewall' ),
						'show_before'             => __( 'show results that occurred before', 'wp-simple-firewall' ),
					],
					'vars'    => [
						'events_for_select' => $aEventsSelect,
						'unique_ips'        => $oAuditSelect->getDistinctIps(),
						'unique_users'      => $oAuditSelect->getDistinctUsernames(),
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
							sprintf( '<a href="%s" target="_blank">%s</a>', $oIpMod->getUrl_DirectLinkToOption( 'transgression_limit' ), $oIpOpts->getOffenseLimit() )
						),
						'auto_expire'       => sprintf(
							__( 'Black listed IPs auto-expire after: %s', 'wp-simple-firewall' ),
							sprintf( '<a href="%s" target="_blank">%s</a>',
								$oIpMod->getUrl_DirectLinkToOption( 'auto_expire' ),
								$oCarbon->setTimestamp( $oReq->ts() + $oIpOpts->getAutoExpireTime() + 10 )
										->diffForHumans( null, true )
							)
						),
						'title_whitelist'   => __( 'IP Whitelist', 'wp-simple-firewall' ),
						'title_blacklist'   => __( 'IP Blacklist', 'wp-simple-firewall' ),
						'summary_whitelist' => sprintf( __( 'IP addresses that are never blocked by %s.', 'wp-simple-firewall' ), $nPluginName ),
						'summary_blacklist' => sprintf( __( 'IP addresses that have tripped %s defenses.', 'wp-simple-firewall' ), $nPluginName ),
						'enter_ip_block'    => __( 'Enter IP address to block', 'wp-simple-firewall' ),
						'enter_ip_white'    => __( 'Enter IP address to whitelist', 'wp-simple-firewall' ),
						'enter_ip'          => __( 'Enter IP address', 'wp-simple-firewall' ),
						'label_for_ip'      => __( 'Label for IP', 'wp-simple-firewall' ),
						'ip_new'            => __( 'New IP', 'wp-simple-firewall' ),
						'ip_filter'         => __( 'Filter By IP', 'wp-simple-firewall' ),
						'ip_block'          => __( 'Block IP', 'wp-simple-firewall' ),
					],
					'vars'    => [
						'unique_ips_black' => ( new Shield\Modules\IPs\Lib\Ops\RetrieveIpsForLists() )
							->setDbHandler( $oIpMod->getDbHandler_IPs() )
							->black(),
						'unique_ips_white' => ( new Shield\Modules\IPs\Lib\Ops\RetrieveIpsForLists() )
							->setDbHandler( $oIpMod->getDbHandler_IPs() )
							->white()
					],
				];
				break;

			case 'notes':
				$aData = [
					'ajax'    => [
						'render_table_adminnotes' => $oModPlugin->getAjaxActionData( 'render_table_adminnotes', true ),
						'item_action_notes'       => $oModPlugin->getAjaxActionData( 'item_action_notes', true ),
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
						'can_traffic' => true, // since 8.2 it's always available
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
				$aData = $oModPlugin->getImpExpController()->buildInsightsVars();
				break;

			case 'reports':
				$aData = $this->buildVars_Reports();
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

			case 'overview':
			case 'index':
			default:
				$sNavSection = 'overview';
				$aData = [
					'vars'    => [
						'config_cards'          => $this->getConfigCardsData(),
						'summary'               => $this->getInsightsModsSummary(),
						'insight_events'        => $this->getRecentEvents(),
						'insight_notices'       => $aSecNotices,
						'insight_notices_count' => $nNoticesCount,
						'insight_stats'         => $this->getStats(),
					],
					'ajax'    => [
						'render_chart_post' => $oEvtsMod->getAjaxActionData( 'render_chart_post', true ),
					],
					'hrefs'   => [
						'shield_pro_url'           => 'https://shsec.io/shieldpro',
						'shield_pro_more_info_url' => 'https://shsec.io/shld1',
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
			'overview'     => __( 'Overview', 'wp-simple-firewall' ),
			'scans'        => __( 'Scans', 'wp-simple-firewall' ),
			'ips'          => __( 'IP Lists', 'wp-simple-firewall' ),
			'audit'        => __( 'Audit Trail', 'wp-simple-firewall' ),
			'users'        => __( 'Users', 'wp-simple-firewall' ),
			'license'      => __( 'Pro', 'wp-simple-firewall' ),
			'traffic'      => __( 'Traffic', 'wp-simple-firewall' ),
			'notes'        => __( 'Notes', 'wp-simple-firewall' ),
			//			'reports'      => __( 'Reports', 'wp-simple-firewall' ),
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
				'slug'    => $sKey,
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
					'tours'            => [
						'insights_overview' => $oTourManager->canShow( 'insights_overview' )
					]
				],
				'hrefs'   => [
					'go_pro'     => 'https://shsec.io/shieldgoprofeature',
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

			$oCon = $this->getCon();
			$aStdDepsJs = [ $this->prefix( 'plugin' ) ];
			$sNav = Services::Request()->query( 'inav', 'overview' );

			$oModPlugin = $oCon->getModule_Plugin();
			$oTourManager = $oModPlugin->getTourManager();
			switch ( $sNav ) {

				case 'importexport':

					$sAsset = 'shield-import';
					$sUnique = $oCon->prefix( $sAsset );
					wp_register_script(
						$sUnique,
						$oCon->getPluginUrl_Js( $sAsset ),
						$aStdDepsJs,
						$oCon->getVersion(),
						false
					);
					wp_enqueue_script( $sUnique );
					break;

				case 'overview':
				case 'reports':

					$aDeps = $aStdDepsJs;

					$aJsAssets = [ 'chartist.min', 'chartist-plugin-legend', 'charts' ];
					if ( $oTourManager->canShow( 'insights_overview' ) ) {
						array_unshift( $aJsAssets, 'introjs.min.js' );
					}
					foreach ( $aJsAssets as $sAsset ) {
						$sUnique = $oCon->prefix( $sAsset );
						wp_register_script(
							$sUnique,
							$oCon->getPluginUrl_Js( $sAsset ),
							$aDeps,
							$oCon->getVersion(),
							false
						);
						wp_enqueue_script( $sUnique );
						$aDeps[] = $sUnique;
					}

					$aDeps = [];
					$aCssAssets = [ 'chartist.min', 'chartist-plugin-legend' ];
					if ( $oTourManager->canShow( 'insights_overview' ) ) {
						array_unshift( $aCssAssets, 'introjs.min.css' );
					}
					foreach ( $aCssAssets as $sAsset ) {
						$sUnique = $oCon->prefix( $sAsset );
						wp_register_style(
							$sUnique,
							$oCon->getPluginUrl_Css( $sAsset ),
							$aDeps,
							$oCon->getVersion(),
							false
						);
						wp_enqueue_style( $sUnique );
						$aDeps[] = $sUnique;
					}

					$this->includeScriptIpDetect();
					break;

				case 'scans':
				case 'audit':
				case 'ips':
				case 'notes':
				case 'traffic':
				case 'users':

					$sAsset = 'shield-tables';
					$sUnique = $oCon->prefix( $sAsset );
					wp_register_script(
						$sUnique,
						$oCon->getPluginUrl_Js( $sAsset ),
						$aStdDepsJs,
						$oCon->getVersion(),
						false
					);
					wp_enqueue_script( $sUnique );

					$aStdDepsJs[] = $sUnique;
					if ( $sNav == 'scans' ) {
						$sAsset = 'shield-scans';
						$sUnique = $oCon->prefix( $sAsset );
						wp_register_script(
							$sUnique,
							$oCon->getPluginUrl_Js( $sAsset ),
							array_unique( $aStdDepsJs ),
							$oCon->getVersion(),
							false
						);
						wp_enqueue_script( $sUnique );
					}

					if ( $sNav == 'audit' ) {
						$sUnique = $this->prefix( 'datepicker' );
						wp_register_script(
							$sUnique, //TODO: use an includes services for CNDJS
							'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/js/bootstrap-datepicker.min.js',
							array_unique( $aStdDepsJs ),
							$oCon->getVersion(),
							false
						);
						wp_enqueue_script( $sUnique );

						wp_register_style(
							$sUnique,
							'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.min.css',
							[],
							$oCon->getVersion(),
							false
						);
						wp_enqueue_style( $sUnique );
					}

					break;
			}

			wp_localize_script(
				$this->prefix( 'plugin' ),
				'icwp_wpsf_vars_insights',
				[
					'strings' => [
						'downloading_file'         => __( 'Downloading file, please wait...', 'wp-simple-firewall' ),
						'downloading_file_problem' => __( 'There was a problem downloading the file.', 'wp-simple-firewall' ),
						'select_action'            => __( 'Please select an action to perform.', 'wp-simple-firewall' ),
						'are_you_sure'             => __( 'Are you sure?', 'wp-simple-firewall' ),
					],
				]
			);
		}
	}

	private function includeScriptIpDetect() {
		$oCon = $this->getCon();
		/** @var Shield\Modules\Plugin\Options $oOpts */
		$oOpts = $oCon->getModule_Plugin()->getOptions();
		if ( $oOpts->isIpSourceAutoDetect() ) {
			wp_register_script(
				$oCon->prefix( 'ip_detect' ),
				$oCon->getPluginUrl_Js( 'ip_detect.js' ),
				[],
				$oCon->getVersion(),
				true
			);
			wp_enqueue_script( $oCon->prefix( 'ip_detect' ) );

			wp_localize_script(
				$oCon->prefix( 'ip_detect' ),
				'icwp_wpsf_vars_ipdetect',
				[ 'ajax' => $oCon->getModule_Plugin()->getAjaxActionData( 'ipdetect' ) ]
			);
		}
	}

	private function buildVars_Reports() {
		$oEvtsMod = $this->getCon()->getModule_Events();
		/** @var Shield\Modules\Events\Strings $oStrs */
		$oStrs = $oEvtsMod->getStrings();
		$aEvtNames = $oStrs->getEventNames();

		$aData = [
			'ajax'    => [
				'render_chart' => $oEvtsMod->getAjaxActionData( 'render_chart', true ),
			],
			'flags'   => [],
			'strings' => [
			],
			'vars'    => [
				'events_options' => array_intersect_key(
					$aEvtNames,
					array_flip(
						[
							'ip_offense',
							'conn_kill',
							'firewall_block',
						]
					)
				)
			],
		];
		return $aData;
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
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Plugins', 'wp-simple-firewall' ) ),
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
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Updates', 'wp-simple-firewall' ) ),
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
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Themes', 'wp-simple-firewall' ) ),
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
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Updates', 'wp-simple-firewall' ) ),
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
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Updates', 'wp-simple-firewall' ) ),
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
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
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
		$oCon = $this->getCon();
		/** @var Shield\Databases\Events\Select $oSelEvents */
		$oSelEvents = $oCon->getModule_Events()
						   ->getDbHandler_Events()
						   ->getQuerySelector();

		/** @var Shield\Databases\IPs\Select $oSelectIp */
		$oSelectIp = $oCon->getModule_IPs()
						  ->getDbHandler_IPs()
						  ->getQuerySelector();

		$aStatsData = [
			'login'          => [
				'id'        => 'login_block',
				'title'     => __( 'Login Blocks', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					$oSelEvents->clearWheres()->sumEvent( 'login_block' ) ),
				'tooltip_p' => __( 'Total login attempts blocked.', 'wp-simple-firewall' ),
			],
			//			'firewall'       => [
			//				'id'      => 'firewall_block',
			//				'title'   => __( 'Firewall Blocks', 'wp-simple-firewall' ),
			//				'val'     => $oSelEvents->clearWheres()->sumEvent( 'firewall_block' ),
			//				'tooltip' => __( 'Total requests blocked by firewall rules.', 'wp-simple-firewall' )
			//			],
			'bot_blocks'     => [
				'id'        => 'bot_blocks',
				'title'     => __( 'Bot Detection', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					$oSelEvents->clearWheres()->sumEventsLike( 'bottrack_' ) ),
				'tooltip_p' => __( 'Total requests identified as bots.', 'wp-simple-firewall' ),
			],
			'comments'       => [
				'id'        => 'comment_block',
				'title'     => __( 'Comment Blocks', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					$oSelEvents->clearWheres()->sumEvents( [
						'spam_block_bot',
						'spam_block_human',
						'spam_block_recaptcha'
					] ) ),
				'tooltip_p' => __( 'Total SPAM comments blocked.', 'wp-simple-firewall' ),
			],
			'transgressions' => [
				'id'        => 'ip_offense',
				'title'     => __( 'Offenses', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					$oSelEvents->clearWheres()->sumEvent( 'ip_offense' ) ),
				'tooltip_p' => __( 'Total offenses against the site.', 'wp-simple-firewall' ),
			],
			'conn_kills'     => [
				'id'        => 'conn_kill',
				'title'     => __( 'Connection Killed', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					$oSelEvents->clearWheres()->sumEvent( 'conn_kill' ) ),
				'tooltip_p' => __( 'Total connections blocked/killed after too many offenses.', 'wp-simple-firewall' ),
			],
			'ip_blocked'     => [
				'id'        => 'ip_blocked',
				'title'     => __( 'IP Blocked', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Now' ),
					$oSelectIp->filterByBlacklist()->count()
				),
				'tooltip_p' => __( 'IP address exceeds offense limit and is blocked.', 'wp-simple-firewall' ),
			],
		];

		foreach ( $aStatsData as $sKey => $sStatData ) {
			$sSub = sprintf( __( 'previous %s %s', 'wp-simple-firewall' ), 7, __( 'days', 'wp-simple-firewall' ) );
			$aStatsData[ $sKey ][ 'title_sub' ] = $sSub;
			$aStatsData[ $sKey ][ 'tooltip_chart' ] = sprintf( '%s: %s.', __( 'Stats', 'wp-simple-firewall' ), $sSub );
		}

		return $aStatsData;
	}

	/**
	 * @return array
	 */
	protected function getRecentEvents() {
		$oCon = $this->getCon();

		$aTheStats = array_filter(
			$oCon->loadEventsService()->getEvents(),
			function ( $aEvt ) {
				return isset( $aEvt[ 'recent' ] ) && $aEvt[ 'recent' ];
			}
		);

		/** @var Shield\Modules\Insights\Strings $oStrs */
		$oStrs = $this->getStrings();
		$aNames = $oStrs->getInsightStatNames();

		/** @var Shield\Databases\Events\Select $oSel */
		$oSel = $oCon->getModule_Events()
					 ->getDbHandler_Events()
					 ->getQuerySelector();

		$aRecentStats = array_intersect_key(
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
			$aTheStats
		);

		$sNotYetRecorded = __( 'Not yet recorded', 'wp-simple-firewall' );
		foreach ( array_keys( $aTheStats ) as $sStatKey ) {
			if ( !isset( $aRecentStats[ $sStatKey ] ) ) {
				$aRecentStats[ $sStatKey ] = [
					'name' => isset( $aNames[ $sStatKey ] ) ? $aNames[ $sStatKey ] : '*** '.$sStatKey,
					'val'  => $sNotYetRecorded
				];
			}
		}

		return $aRecentStats;
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'Insights';
	}
}