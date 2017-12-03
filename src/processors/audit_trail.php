<?php

if ( class_exists( 'ICWP_WPSF_Processor_AuditTrail', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'basedb.php' );

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
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'audit_trail_auditor_base.php' );
			$this->oAuditor = new ICWP_WPSF_AuditTrail_Auditor_Base();
		}
		return $this->oAuditor;
	}

	/**
	 * @param ICWP_WPSF_FeatureHandler_AuditTrail $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_AuditTrail $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions, $oFeatureOptions->getAuditTrailTableName() );
	}

	/**
	 * Resets the object values to be re-used anew
	 */
	public function init() {
		parent::init();

		// Auto delete db entries
		$nDays = $this->getOption( 'audit_trail_auto_clean' );
		if ( $nDays > 0 ) {
			$this->setAutoExpirePeriod( $nDays*DAY_IN_SECONDS );
		}
	}

	public function action_doFeatureProcessorShutdown() {
		parent::action_doFeatureProcessorShutdown();
		if ( !$this->getFeature()->isPluginDeleting() ) {
			$this->commitAuditTrial();
		}
	}

	/**
	 */
	public function run() {
		if ( !$this->readyToRun() ) {
			return;
		}

		if ( $this->getIsOption( 'enable_audit_context_users', 'Y' ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'audit_trail_users.php' );
			$oUsers = new ICWP_WPSF_Processor_AuditTrail_Users();
			$oUsers->run();
		}

		if ( $this->getIsOption( 'enable_audit_context_plugins', 'Y' ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'audit_trail_plugins.php' );
			$oPlugins = new ICWP_WPSF_Processor_AuditTrail_Plugins();
			$oPlugins->run();
		}

		if ( $this->getIsOption( 'enable_audit_context_themes', 'Y' ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'audit_trail_themes.php' );
			$oThemes = new ICWP_WPSF_Processor_AuditTrail_Themes();
			$oThemes->run();
		}

		if ( $this->getIsOption( 'enable_audit_context_wordpress', 'Y' ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'audit_trail_wordpress.php' );
			$oWp = new ICWP_WPSF_Processor_AuditTrail_Wordpress();
			$oWp->run();
		}

		if ( $this->getIsOption( 'enable_audit_context_posts', 'Y' ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'audit_trail_posts.php' );
			$oPosts = new ICWP_WPSF_Processor_AuditTrail_Posts();
			$oPosts->run();
		}

		if ( $this->getIsOption( 'enable_audit_context_emails', 'Y' ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'audit_trail_emails.php' );
			$oEmails = new ICWP_WPSF_Processor_AuditTrail_Emails();
			$oEmails->run();
		}

		if ( $this->getIsOption( 'enable_audit_context_wpsf', 'Y' ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'audit_trail_wpsf.php' );
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
		$sQuery = "
				SELECT COUNT(*)
				FROM `%s`
				WHERE
					`context`			= '%s'
					AND `deleted_at`	= 0
			";
		return $this->loadDbProcessor()->getVar( sprintf( $sQuery, $this->getTableName(), $sContext ) );
	}

	/**
	 * @param string $sContext
	 * @param int    $nLimit
	 * @return array|bool
	 */
	public function getAuditEntriesForContext( $sContext, $nLimit = 50 ) {
		$sQuery = "
				SELECT *
				FROM `%s`
				WHERE
					`context`			= '%s'
					AND `deleted_at`	= '0'
				ORDER BY `created_at` DESC
				LIMIT %s
			";
		$sQuery = sprintf( $sQuery, $this->getTableName(), $sContext, $nLimit );
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

		foreach ( $aEntries as $aEntry ) {
			if ( empty( $aEntry[ 'ip' ] ) ) {
				$aEntry[ 'ip' ] = $this->ip();
			}
			if ( is_array( $aEntry[ 'message' ] ) ) {
				$aEntry[ 'message' ] = implode( ' ', $aEntry[ 'message' ] );
			}
			$this->insertData( $aEntry );
		}
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		$sSqlTables = "CREATE TABLE %s (
				id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				ip varchar(40) NOT NULL DEFAULT '0',
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
		$aDef = $this->getFeature()->getDefinition( 'audit_trail_table_columns' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}

	/**
	 * override and do not delete
	 */
	public function deleteTable() {
	}
}