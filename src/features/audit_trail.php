<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_AuditTrail extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return int
	 */
	public function getAutoCleanDays() {
		return (int)$this->getOpt( 'audit_trail_auto_clean' );
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( Services::Request()->request( 'exec' ) ) {

				case 'render_table_audittrail':
					$aAjaxResponse = $this->ajaxExec_BuildTableAuditTrail();
					break;

				case 'item_addparamwhite':
					$aAjaxResponse = $this->ajaxExec_AddParamToFirewallWhitelist();
					break;

				default:
					break;
			}
		}
		return parent::handleAuthAjax( $aAjaxResponse );
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_AddParamToFirewallWhitelist() {
		$bSuccess = false;

		$nId = Services::Request()->post( 'rid' );
		if ( empty( $nId ) || !is_numeric( $nId ) || $nId < 1 ) {
			$sMessage = __( 'Invalid audit entry selected for this action', 'wp-simple-firewall' );
		}
		else {
			/** @var ICWP_WPSF_Processor_AuditTrail $oPro */
			$oPro = $this->getProcessor();
			/** @var Shield\Databases\AuditTrail\EntryVO $oEntry */
			$oEntry = $oPro->getSubProAuditor()
						   ->getDbHandler()
						   ->getQuerySelector()
						   ->byId( $nId );

			if ( empty( $oEntry ) ) {
				$sMessage = __( 'Audit entry could not be loaded.', 'wp-simple-firewall' );
			}
			else {
				$aData = $oEntry->meta;
				$sParam = isset( $aData[ 'param' ] ) ? $aData[ 'param' ] : '';
				$sUri = isset( $aData[ 'uri' ] ) ? $aData[ 'uri' ] : '*';
				if ( empty( $sParam ) ) {
					$sMessage = __( 'Parameter associated with this audit entry could not be found.', 'wp-simple-firewall' );
				}
				else {
					/** @var ICWP_WPSF_FeatureHandler_Firewall $oModFire */
					$oModFire = $this->getCon()->getModule( 'firewall' );
					$oModFire->addParamToWhitelist( $sParam, $sUri );
					$sMessage = sprintf( __( 'Parameter "%s" whitelisted successfully', 'wp-simple-firewall' ), $sParam );
					$bSuccess = true;
				}
			}
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage
		];
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_BuildTableAuditTrail() {
		/** @var ICWP_WPSF_Processor_AuditTrail $oPro */
		$oPro = $this->getProcessor();

		$oTableBuilder = ( new Shield\Tables\Build\AuditTrail() )
			->setMod( $this )
			->setDbHandler( $oPro->getSubProAuditor()->getDbHandler() );

		return [
			'success' => true,
			'html'    => $oTableBuilder->buildTable()
		];
	}

	/**
	 * @return int
	 */
	protected function getDefaultMaxEntries() {
		return $this->getDef( 'audit_trail_default_max_entries' );
	}

	/**
	 * @return int
	 */
	public function getMaxEntries() {
		return $this->isPremium() ? (int)$this->getOpt( 'audit_trail_max_entries' ) : $this->getDefaultMaxEntries();
	}

	/**
	 * @return bool
	 */
	public function isEnabledAuditing() {
		return $this->isAuditEmails()
			   || $this->isAuditPlugins()
			   || $this->isAuditThemes()
			   || $this->isAuditPosts()
			   || $this->isAuditShield()
			   || $this->isAuditUsers()
			   || $this->isAuditWp();
	}

	/**
	 * @return bool
	 */
	public function isAuditEmails() {
		return $this->isOpt( 'enable_audit_context_emails', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditPlugins() {
		return $this->isOpt( 'enable_audit_context_plugins', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditPosts() {
		return $this->isOpt( 'enable_audit_context_posts', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditShield() {
		return $this->isOpt( 'enable_audit_context_wpsf', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditThemes() {
		return $this->isOpt( 'enable_audit_context_themes', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditUsers() {
		return $this->isOpt( 'enable_audit_context_users', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditWp() {
		return $this->isOpt( 'enable_audit_context_wordpress', 'Y' );
	}

	/**
	 * @return array
	 */
	public function getAllContexts() {
		return [
			'all'       => 'All', //special
			'wpsf'      => $this->getCon()->getHumanName(),
			'wordpress' => 'WordPress',
			'users'     => 'Users',
			'posts'     => 'Posts',
			'plugins'   => 'Plugins',
			'themes'    => 'Themes',
			'emails'    => 'Emails',
		];
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		return $this->loadDP()->mergeArraysRecursive(
			parent::getDisplayStrings(),
			[
				'at_users'            => __( 'Users', 'wp-simple-firewall' ),
				'at_plugins'          => __( 'Plugins', 'wp-simple-firewall' ),
				'at_themes'           => __( 'Themes', 'wp-simple-firewall' ),
				'at_wordpress'        => __( 'WordPress', 'wp-simple-firewall' ),
				'at_posts'            => __( 'Posts', 'wp-simple-firewall' ),
				'at_emails'           => __( 'Emails', 'wp-simple-firewall' ),
				'at_time'             => __( 'Time', 'wp-simple-firewall' ),
				'at_event'            => __( 'Event', 'wp-simple-firewall' ),
				'at_message'          => __( 'Message', 'wp-simple-firewall' ),
				'at_username'         => __( 'Username', 'wp-simple-firewall' ),
				'at_category'         => __( 'Category', 'wp-simple-firewall' ),
				'at_ipaddress'        => __( 'IP Address', 'wp-simple-firewall' ),
				'at_you'              => __( 'You', 'wp-simple-firewall' ),
				'at_no_audit_entries' => __( 'There are currently no audit entries this is section.', 'wp-simple-firewall' ),
			]
		);
	}

	/**
	 * @return bool
	 */
	public function isEnabledChangeTracking() {
		return !$this->isOpt( 'enable_change_tracking', 'disabled' );
	}

	/**
	 * @return int
	 */
	public function getCTSnapshotsPerWeek() {
		return (int)$this->getOpt( 'ct_snapshots_per_week', 7 );
	}

	/**
	 * @return int
	 */
	public function getCTMaxSnapshots() {
		return (int)$this->getOpt( 'ct_max_snapshots', 28 );
	}

	/**
	 * @return int
	 */
	public function getCTSnapshotInterval() {
		return WEEK_IN_SECONDS/$this->getCTSnapshotsPerWeek();
	}

	/**
	 * @return int
	 */
	public function getCTLastSnapshotAt() {
		return $this->getOpt( 'ct_last_snapshot_at' );
	}

	/**
	 * @return bool
	 */
	public function isCTSnapshotDue() {
		return ( Services::Request()->ts() - $this->getCTLastSnapshotAt() > $this->getCTSnapshotInterval() );
	}

	/**
	 * @return ICWP_WPSF_FeatureHandler_AuditTrail
	 */
	public function updateCTLastSnapshotAt() {
		return $this->setOptAt( 'ct_last_snapshot_at' );
	}

	/**
	 * See plugin controller for the nature of $aData wpPrivacyExport()
	 *
	 * @param array  $aExportItems
	 * @param string $sEmail
	 * @param int    $nPage
	 * @return array
	 */
	public function onWpPrivacyExport( $aExportItems, $sEmail, $nPage = 1 ) {
		/** @var ICWP_WPSF_Processor_AuditTrail $oProc */
		$oProc = $this->getProcessor();

		$oUser = Services::WpUsers()->getUserByEmail( $sEmail );

		$aExportItem = [
			'group_id'    => $this->prefix(),
			'group_label' => sprintf( __( '[%s] Audit Trail Entries', 'wp-simple-firewall' ), $this->getCon()
																								   ->getHumanName() ),
			'item_id'     => $this->prefix( 'audit-trail' ),
			'data'        => [],
		];

		try {
			$oFinder = $oProc->getSubProAuditor()
							 ->getDbHandler()
							 ->getQuerySelector()
							 ->addWhereSearch( 'wp_username', $oUser->user_login )
							 ->setResultsAsVo( true );

			$oWp = Services::WpGeneral();
			/** @var Shield\Databases\AuditTrail\EntryVO $oEntry */
			foreach ( $oFinder->query() as $oEntry ) {
				$aExportItem[ 'data' ][] = [
					$sTimeStamp = $oWp->getTimeStringForDisplay( $oEntry->getCreatedAt() ),
					'name'  => sprintf( '[%s] Audit Trail Entry', $sTimeStamp ),
					'value' => sprintf( '[IP:%s] %s', $oEntry->ip, $oEntry->message )
				];
			}

			if ( !empty( $aExportItem[ 'data' ] ) ) {
				$aExportItems[] = $aExportItem;
			}
		}
		catch ( \Exception $oE ) {
		}

		return $aExportItems;
	}

	/**
	 * See plugin controller for the nature of $aData wpPrivacyErase()
	 *
	 * @param array  $aData
	 * @param string $sEmail
	 * @param int    $nPage
	 * @return array
	 */
	public function onWpPrivacyErase( $aData, $sEmail, $nPage = 1 ) {

		/** @var ICWP_WPSF_Processor_AuditTrail $oProc */
		$oProc = $this->getProcessor();

		try {
			$oThisUsername = Services::WpUsers()->getUserByEmail( $sEmail )->user_login;
			$oProc->getSubProAuditor()
				  ->getDbHandler()
				  ->getQueryDeleter()
				  ->addWhereSearch( 'wp_username', $oThisUsername )
				  ->all();
			$aData[ 'messages' ][] = sprintf( '%s Audit Entries deleted', $this->getCon()->getHumanName() );
		}
		catch ( \Exception $oE ) {
		}
		return $aData;
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		$aThis = [
			'strings'      => [
				'title' => __( 'Activity Audit Log', 'wp-simple-firewall' ),
				'sub'   => __( 'Track Activity: What, Who, When, Where', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $this->getUrl_AdminPage()
		];

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$aAudit = [];
			$aNonAudit = [];
			$this->isAuditShield() ? $aAudit[] = 'Shield' : $aNonAudit[] = 'Shield';
			$this->isAuditUsers() ? $aAudit[] = __( 'users', 'wp-simple-firewall' ) : $aNonAudit[] = __( 'users', 'wp-simple-firewall' );
			$this->isAuditPlugins() ? $aAudit[] = __( 'plugins', 'wp-simple-firewall' ) : $aNonAudit[] = __( 'plugins', 'wp-simple-firewall' );
			$this->isAuditThemes() ? $aAudit[] = __( 'themes', 'wp-simple-firewall' ) : $aNonAudit[] = __( 'themes', 'wp-simple-firewall' );
			$this->isAuditPosts() ? $aAudit[] = __( 'posts', 'wp-simple-firewall' ) : $aNonAudit[] = __( 'posts', 'wp-simple-firewall' );
			$this->isAuditEmails() ? $aAudit[] = __( 'emails', 'wp-simple-firewall' ) : $aNonAudit[] = __( 'emails', 'wp-simple-firewall' );
			$this->isAuditWp() ? $aAudit[] = 'WP' : $aNonAudit[] = 'WP';

			if ( empty( $aNonAudit ) ) {
				$aThis[ 'key_opts' ][ 'audit' ] = [
					'name'    => __( 'Audit Areas', 'wp-simple-firewall' ),
					'enabled' => true,
					'summary' => __( 'All important events on your site are being logged', 'wp-simple-firewall' ),
					'weight'  => 2,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
				];
			}
			else if ( empty( $aAudit ) ) {
				$aThis[ 'key_opts' ][ 'audit' ] = [
					'name'    => __( 'Audit Areas', 'wp-simple-firewall' ),
					'enabled' => false,
					'summary' => sprintf( __( 'No areas are set to be audited: %s', 'wp-simple-firewall' ), implode( ', ', $aAudit ) ),
					'weight'  => 2,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
				];
			}
			else {
//				$aThis[ 'key_opts' ][ 'audit' ] = array(
//					'name'    => _wpsf__( 'Audit Areas' ),
//					'enabled' => true,
//					'summary' => sprintf( _wpsf__( 'Important areas are being audited: %s' ), implode( ', ', $aAudit ) ),
//					'weight'  => 2,
//					'href'    => $this->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
//				);
				$aThis[ 'key_opts' ][ 'nonaudit' ] = [
					'name'    => __( 'Audit Events', 'wp-simple-firewall' ),
					'enabled' => false,
					'summary' => sprintf( __( "Important events aren't being audited: %s", 'wp-simple-firewall' ), implode( ', ', $aNonAudit ) ),
					'weight'  => 2,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
				];
			}

			$aThis[ 'key_opts' ][ 'length' ] = [
				'name'    => __( 'Audit Trail', 'wp-simple-firewall' ),
				'enabled' => true,
				'summary' => sprintf( __( 'Maximum Audit Trail entries limited to %s', 'wp-simple-firewall' ), $this->getMaxEntries() ),
				'weight'  => 0,
				'href'    => $this->getUrl_DirectLinkToOption( 'audit_trail_max_entries' ),
			];
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_audit_trail' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The Audit Trail is designed so you can look back on events and analyse what happened and what may have gone wrong.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Audit Trail', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_audit_trail_options' :
				$sTitle = __( 'Audit Trail Options', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides finer control over the audit trail itself.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'These settings are dependent on your requirements.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Audit Trail Options', 'wp-simple-firewall' );
				break;

			case 'section_enable_audit_contexts' :
				$sTitle = __( 'Enable Audit Areas', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Specify which types of actions on your site are logged.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'These settings are dependent on your requirements.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Audit Areas', 'wp-simple-firewall' );
				break;

			case 'section_change_tracking' :
				$sTitle = __( 'Track All Major Changes To Your Site', 'wp-simple-firewall' );
				$sTitleShort = __( 'Change Tracking', 'wp-simple-firewall' );
				$aData = ( new Shield\ChangeTrack\Snapshot\Collate() )->run();
				$sResult = (int)( strlen( base64_encode( WP_Http_Encoding::compress( json_encode( $aData ) ) ) )/1024 );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Track significant changes to your site.', 'wp-simple-firewall' ) )
					.' '.sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ), __( 'This is separate from the Audit Trail.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Considerations', 'wp-simple-firewall' ),
						__( 'Change Tracking uses snapshots that may use take up  lot of data.', 'wp-simple-firewall' )
						.' '.sprintf( 'Each snapshot will consume ~%sKB in your database', $sResult )
					),
				];
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [];
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {
		$oCon = $this->getCon();

		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_audit_trail' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				break;

			case 'audit_trail_max_entries' :
				$sName = __( 'Max Trail Length', 'wp-simple-firewall' );
				$sSummary = __( 'Maximum Audit Trail Length To Keep', 'wp-simple-firewall' );
				$sDescription = __( 'Automatically remove any audit trail entries when this limit is exceeded.', 'wp-simple-firewall' )
								.'<br/>'.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $this->getDefaultMaxEntries() );
				break;

			case 'audit_trail_auto_clean' :
				$sName = __( 'Auto Clean', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Purge Audit Log Entries Older Than The Set Number Of Days', 'wp-simple-firewall' );
				$sDescription = __( 'Events older than the number of days specified will be automatically cleaned from the database.', 'wp-simple-firewall' );
				break;

			case 'enable_audit_context_users' :
				$sName = __( 'Users And Logins', 'wp-simple-firewall' );
				$sSummary = sprintf( __( 'Enable Audit Context - %s', 'wp-simple-firewall' ), __( 'Users And Logins', 'wp-simple-firewall' ) );
				$sDescription = sprintf( __( 'When this context is enabled, the audit trail will track activity relating to: %s', 'wp-simple-firewall' ), __( 'Users And Logins', 'wp-simple-firewall' ) );
				break;

			case 'enable_audit_context_plugins' :
				$sName = __( 'Plugins', 'wp-simple-firewall' );
				$sSummary = sprintf( __( 'Enable Audit Context - %s', 'wp-simple-firewall' ), __( 'Plugins', 'wp-simple-firewall' ) );
				$sDescription = sprintf( __( 'When this context is enabled, the audit trail will track activity relating to: %s', 'wp-simple-firewall' ), __( 'WordPress Plugins', 'wp-simple-firewall' ) );
				break;

			case 'enable_audit_context_themes' :
				$sName = __( 'Themes', 'wp-simple-firewall' );
				$sSummary = sprintf( __( 'Enable Audit Context - %s', 'wp-simple-firewall' ), __( 'Themes', 'wp-simple-firewall' ) );
				$sDescription = sprintf( __( 'When this context is enabled, the audit trail will track activity relating to: %s', 'wp-simple-firewall' ), __( 'WordPress Themes', 'wp-simple-firewall' ) );
				break;

			case 'enable_audit_context_posts' :
				$sName = __( 'Posts And Pages', 'wp-simple-firewall' );
				$sSummary = sprintf( __( 'Enable Audit Context - %s', 'wp-simple-firewall' ), __( 'Posts And Pages', 'wp-simple-firewall' ) );
				$sDescription = sprintf( __( 'When this context is enabled, the audit trail will track activity relating to: %s', 'wp-simple-firewall' ), __( 'Editing and publishing of posts and pages', 'wp-simple-firewall' ) );
				break;

			case 'enable_audit_context_wordpress' :
				$sName = __( 'WordPress And Settings', 'wp-simple-firewall' );
				$sSummary = sprintf( __( 'Enable Audit Context - %s', 'wp-simple-firewall' ), __( 'WordPress And Settings', 'wp-simple-firewall' ) );
				$sDescription = sprintf( __( 'When this context is enabled, the audit trail will track activity relating to: %s', 'wp-simple-firewall' ), __( 'WordPress upgrades and changes to particular WordPress settings', 'wp-simple-firewall' ) );
				break;

			case 'enable_audit_context_emails' :
				$sName = __( 'Emails', 'wp-simple-firewall' );
				$sSummary = sprintf( __( 'Enable Audit Context - %s', 'wp-simple-firewall' ), __( 'Emails', 'wp-simple-firewall' ) );
				$sDescription = sprintf( __( 'When this context is enabled, the audit trail will track activity relating to: %s', 'wp-simple-firewall' ), __( 'Email Sending', 'wp-simple-firewall' ) );
				break;

			case 'enable_audit_context_wpsf' :
				$sName = $oCon->getHumanName();
				$sSummary = sprintf( __( 'Enable Audit Context - %s', 'wp-simple-firewall' ), $oCon->getHumanName() );
				$sDescription = sprintf( __( 'When this context is enabled, the audit trail will track activity relating to: %s', 'wp-simple-firewall' ), $oCon->getHumanName() );
				break;

			case 'enable_change_tracking' :
				$sName = __( 'Site Change Tracking', 'wp-simple-firewall' );
				$sSummary = __( 'Track Major Changes To Your Site', 'wp-simple-firewall' );
				$sDescription = __( 'Tracking major changes to your site will help you monitor and catch malicious damage.', 'wp-simple-firewall' );
				break;

			case 'ct_snapshots_per_week' :
				$sName = __( 'Snapshot Per Week', 'wp-simple-firewall' );
				$sSummary = __( 'Number Of Snapshots To Take Per Week', 'wp-simple-firewall' );
				$sDescription = __( 'The number of snapshots to take per week. For daily snapshots, select 7.', 'wp-simple-firewall' )
								.'<br />'.__( 'Data storage in your database increases with the number of snapshots.', 'wp-simple-firewall' )
								.'<br />'.__( 'However, increased snapshots provide more granular information on when major site changes occurred.', 'wp-simple-firewall' );
				break;

			case 'ct_max_snapshots' :
				$sName = __( 'Max Snapshots', 'wp-simple-firewall' );
				$sSummary = __( 'Maximum Number Of Snapshots To Retain', 'wp-simple-firewall' );
				$sDescription = __( 'The more snapshots you retain, the further back you can look at changes over your site.', 'wp-simple-firewall' )
								.'<br />'.__( 'You will need to consider the implications to database storage requirements.', 'wp-simple-firewall' );
				break;

			default:
				throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}