<?php

if ( class_exists( 'ICWP_WPSF_Processor_AuditTrail', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/basedb.php' );

class ICWP_WPSF_Processor_AuditTrail extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @var ICWP_WPSF_AuditTrail_Auditor_Base
	 */
	protected $oAuditor;

	/**
	 * @return ICWP_WPSF_AuditTrail_Auditor_Base
	 */
	public function getBaseAuditor() {
		if ( !isset( $this->oAuditor ) ) {
			require_once( dirname( __FILE__ ).'/audit_trail_auditor_base.php' );
			$this->oAuditor = new ICWP_WPSF_AuditTrail_Auditor_Base();
		}
		return $this->oAuditor;
	}

	/**
	 * @param ICWP_WPSF_FeatureHandler_AuditTrail $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_AuditTrail $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getAuditTrailTableName() );
	}

	public function onModuleShutdown() {
		parent::onModuleShutdown();
		if ( !$this->getFeature()->isPluginDeleting() ) {
			$this->commitAuditTrial();
		}
	}

	/**
	 */
	public function run() {
		if ( !$this->isReadyToRun() ) {
			return;
		}

		if ( $this->getIsOption( 'enable_audit_context_users', 'Y' ) ) {
			require_once( dirname( __FILE__ ).'/audit_trail_users.php' );
			$oUsers = new ICWP_WPSF_Processor_AuditTrail_Users();
			$oUsers->run();
		}

		if ( $this->getIsOption( 'enable_audit_context_plugins', 'Y' ) ) {
			require_once( dirname( __FILE__ ).'/audit_trail_plugins.php' );
			$oPlugins = new ICWP_WPSF_Processor_AuditTrail_Plugins();
			$oPlugins->run();
		}

		if ( $this->getIsOption( 'enable_audit_context_themes', 'Y' ) ) {
			require_once( dirname( __FILE__ ).'/audit_trail_themes.php' );
			$oThemes = new ICWP_WPSF_Processor_AuditTrail_Themes();
			$oThemes->run();
		}

		if ( $this->getIsOption( 'enable_audit_context_wordpress', 'Y' ) ) {
			require_once( dirname( __FILE__ ).'/audit_trail_wordpress.php' );
			$oWp = new ICWP_WPSF_Processor_AuditTrail_Wordpress();
			$oWp->run();
		}

		if ( $this->getIsOption( 'enable_audit_context_posts', 'Y' ) ) {
			require_once( dirname( __FILE__ ).'/audit_trail_posts.php' );
			$oPosts = new ICWP_WPSF_Processor_AuditTrail_Posts();
			$oPosts->run();
		}

		if ( $this->getIsOption( 'enable_audit_context_emails', 'Y' ) ) {
			require_once( dirname( __FILE__ ).'/audit_trail_emails.php' );
			$oEmails = new ICWP_WPSF_Processor_AuditTrail_Emails();
			$oEmails->run();
		}

		if ( $this->getIsOption( 'enable_audit_context_wpsf', 'Y' ) ) {
			require_once( dirname( __FILE__ ).'/audit_trail_wpsf.php' );
			$oWpsf = new ICWP_WPSF_Processor_AuditTrail_Wpsf();
			$oWpsf->run();
		}
	}

	/**
	 * @return array|bool
	 */
	public function getAllAuditEntries() {
		return array_reverse( $this->selectAll() );
	}

	/**
	 * @param string $sContext
	 * @return array|bool
	 */
	public function countAuditEntriesForContext( $sContext ) {
		$sContext = ( $sContext == 'all' ) ? '' : sprintf( "`context`= '%s' AND", $sContext );
		$sQuery = "
				SELECT COUNT(*)
				FROM `%s`
				WHERE
					%s `deleted_at`	= 0
			";
		return $this->loadDbProcessor()->getVar( sprintf( $sQuery, $this->getTableName(), $sContext ) );
	}

	/**
	 * @param string $sContext
	 * @param string $sOrderBy
	 * @param string $sOrder
	 * @param int    $nPage
	 * @param int    $nLimit
	 * @return array|bool
	 */
	public function getAuditEntriesForContext( $sContext, $sOrderBy = 'created_at', $sOrder = 'DESC', $nPage = 1, $nLimit = 50 ) {
		$sOffset = ( $nPage - 1 )*$nLimit;
		$sQuery = "
				SELECT *
				FROM `%s`
				WHERE
					%s `deleted_at`	= 0
				ORDER BY `%s` %s
				LIMIT %s OFFSET %s
			";

		$sContext = ( $sContext == 'all' ) ? '' : sprintf( "`context`= '%s' AND", $sContext );

		$sQuery = sprintf( $sQuery, $this->getTableName(), $sContext, $sOrderBy, $sOrder, $nLimit, $sOffset );
		return $this->selectCustom( $sQuery );
	}

	/**
	 */
	protected function commitAuditTrial() {
		$aEntries = apply_filters(
			$this->getFeature()->prefix( 'collect_audit_trail' ),
			$this->getBaseAuditor()->getAuditTrailEntries( true )
		);
		if ( empty( $aEntries ) || !is_array( $aEntries ) ) {
			return;
		}

		$sReqId = $this->getController()->getShortRequestId();
		foreach ( $aEntries as $aEntry ) {
			if ( empty( $aEntry[ 'ip' ] ) ) {
				$aEntry[ 'ip' ] = $this->ip();
			}
			if ( is_array( $aEntry[ 'message' ] ) ) {
				$aEntry[ 'message' ] = implode( ' ', $aEntry[ 'message' ] );
			}
			$aEntry[ 'rid' ] = $sReqId;
			$this->insertData( $aEntry );
		}
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		$sSqlTables = "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			rid varchar(10) NOT NULL DEFAULT '',
			ip varchar(40) NOT NULL DEFAULT 0,
			wp_username varchar(255) NOT NULL DEFAULT 'none',
			context varchar(32) NOT NULL DEFAULT 'none',
			event varchar(50) NOT NULL DEFAULT 'none',
			category int(3) UNSIGNED NOT NULL DEFAULT 0,
			message text,
			immutable tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
		) %s;";
		return sprintf( $sSqlTables, $this->getTableName(), $this->loadDbProcessor()->getCharCollate() );
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getFeature()->getDef( 'audit_trail_table_columns' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}

	/**
	 * override and do not delete
	 */
	public function deleteTable() {
	}

	/**
	 * @return int|null
	 */
	protected function getAutoExpirePeriod() {
		// Auto delete db entries
		$nDays = (int)$this->getOption( 'audit_trail_auto_clean' );
		return ( $nDays > 0 ) ? ( $nDays*DAY_IN_SECONDS ) : parent::getAutoExpirePeriod();
	}

	/**
	 * @return ICWP_WPSF_Query_AuditTrail_Find
	 */
	public function getAuditTrailFinder() {
		require_once( dirname( dirname( __FILE__ ) ).'/query/audittrail_find.php' );
		$oSearch = new ICWP_WPSF_Query_AuditTrail_Find();
		return $oSearch->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_AuditTrail_Delete
	 */
	public function getAuditTrailDelete() {
		require_once( dirname( dirname( __FILE__ ) ).'/query/audittrail_delete.php' );
		$oSearch = new ICWP_WPSF_Query_AuditTrail_Delete();
		return $oSearch->setTable( $this->getTableName() );
	}
}