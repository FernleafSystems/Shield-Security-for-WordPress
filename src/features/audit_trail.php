<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_AuditTrail extends ICWP_WPSF_FeatureHandler_BaseWpsf {

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
		$oTableBuilder = ( new Shield\Tables\Build\AuditTrail() )
			->setMod( $this )
			->setDbHandler( $this->getDbHandler() );

		return [
			'success' => true,
			'html'    => $oTableBuilder->buildTable()
		];
	}

	/**
	 * @return int
	 */
	public function getMaxEntries() {
		return $this->isPremium() ? (int)$this->getOpt( 'audit_trail_max_entries' ) : $this->getDefaultMaxEntries();
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
	 * @return Shield\Databases\AuditTrail\Handler
	 */
	protected function loadDbHandler() {
		return new Shield\Databases\AuditTrail\Handler();
	}

	/**
	 * @return Shield\Modules\AuditTrail\Options
	 */
	protected function loadOptions() {
		return new Shield\Modules\AuditTrail\Options();
	}

	/**
	 * @return Shield\Modules\AuditTrail\Strings
	 */
	protected function loadStrings() {
		return new Shield\Modules\AuditTrail\Strings();
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function isEnabledChangeTracking() {
		return !$this->isOpt( 'enable_change_tracking', 'disabled' );
	}

	/**
	 * @return int
	 * @deprecated
	 */
	public function getCTSnapshotsPerWeek() {
		return (int)$this->getOpt( 'ct_snapshots_per_week', 7 );
	}

	/**
	 * @return int
	 * @deprecated
	 */
	public function getCTMaxSnapshots() {
		return (int)$this->getOpt( 'ct_max_snapshots', 28 );
	}

	/**
	 * @return int
	 * @deprecated
	 */
	public function getCTSnapshotInterval() {
		return WEEK_IN_SECONDS/$this->getCTSnapshotsPerWeek();
	}

	/**
	 * @return int
	 * @deprecated
	 */
	public function getCTLastSnapshotAt() {
		return $this->getOpt( 'ct_last_snapshot_at' );
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function isCTSnapshotDue() {
		return ( Services::Request()->ts() - $this->getCTLastSnapshotAt() > $this->getCTSnapshotInterval() );
	}

	/**
	 * @return bool
	 * @deprecated
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
	 * @deprecated
	 */
	public function isAuditEmails() {
		return $this->isOpt( 'enable_audit_context_emails', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function isAuditPlugins() {
		return $this->isOpt( 'enable_audit_context_plugins', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function isAuditPosts() {
		return $this->isOpt( 'enable_audit_context_posts', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function isAuditShield() {
		return $this->isOpt( 'enable_audit_context_wpsf', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function isAuditThemes() {
		return $this->isOpt( 'enable_audit_context_themes', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function isAuditUsers() {
		return $this->isOpt( 'enable_audit_context_users', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function isAuditWp() {
		return $this->isOpt( 'enable_audit_context_wordpress', 'Y' );
	}

	/**
	 * @return int
	 * @deprecated
	 */
	public function getDefaultMaxEntries() {
		return $this->getDef( 'audit_trail_default_max_entries' );
	}

	/**
	 * @return int
	 * @deprecated
	 */
	public function getAutoCleanDays() {
		return (int)$this->getOpt( 'audit_trail_auto_clean' );
	}
}