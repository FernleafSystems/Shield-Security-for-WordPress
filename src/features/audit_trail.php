<?php
if ( class_exists( 'ICWP_WPSF_FeatureHandler_AuditTrail', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_AuditTrail extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return bool
	 */
	protected function isReadyToExecute() {
		return parent::isReadyToExecute() && !$this->isVisitorWhitelisted();
	}

	/**
	 */
	public function doPrePluginOptionsSave() {

		if ( $this->getOpt( 'audit_trail_auto_clean' ) < 0 ) {
			$this->getOptionsVo()->resetOptToDefault( 'audit_trail_auto_clean' );
		}
	}

	public function displayAuditTrailViewer() {

		if ( !$this->canDisplayOptionsForm() ) {
			return $this->displayRestrictedPage();
		}

		/** @var ICWP_WPSF_Processor_AuditTrail $oAuditTrail */
		$oAuditTrail = $this->loadFeatureProcessor();

		$aContexts = array(
			'Users',
			'Plugins',
			'Themes',
			'WordPress',
			'Posts',
			'Emails',
			'wpsf'
		);

		$aDisplayData = array(
			'nYourIp'      => $this->loadIpService()->getRequestIp(),
			'sFeatureName' => _wpsf__( 'Audit Trail Viewer' )
		);

		$aAudits = array();
		foreach ( $aContexts as $sContext ) {
			$aAuditContext = array();
			$aAuditContext[ 'title' ] = ( $sContext == 'wpsf' ) ? self::getConn()
																	  ->getHumanName() : _wpsf__( $sContext );

			$aAuditData = $oAuditTrail->getAuditEntriesForContext( strtolower( $sContext ) );
			if ( is_array( $aAuditData ) ) {
				foreach ( $aAuditData as &$aAuditEntry ) {
					$aAuditEntry[ 'event' ] = str_replace( '_', ' ', sanitize_text_field( $aAuditEntry[ 'event' ] ) );
					$aAuditEntry[ 'message' ] = sanitize_text_field( $aAuditEntry[ 'message' ] );
					$aAuditEntry[ 'created_at' ] = $this->loadWp()
														->getTimeStringForDisplay( $aAuditEntry[ 'created_at' ] );
				}
			}
			$aAuditContext[ 'trail' ] = $aAuditData;
			$aAudits[] = $aAuditContext;
		}
		$aDisplayData[ 'aAudits' ] = $aAudits;
		$this->display( $aDisplayData, 'subfeature-audit_trail_viewer' );
//			$this->displayByTemplate( $aDisplayData, 'subfeature-audit_trail_viewer' );
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		return array(
			'at_users'            => _wpsf__( 'Users' ),
			'at_plugins'          => _wpsf__( 'Plugins' ),
			'at_themes'           => _wpsf__( 'Themes' ),
			'at_wordpress'        => _wpsf__( 'WordPress' ),
			'at_posts'            => _wpsf__( 'Posts' ),
			'at_emails'           => _wpsf__( 'Emails' ),
			'at_time'             => _wpsf__( 'Time' ),
			'at_event'            => _wpsf__( 'Event' ),
			'at_message'          => _wpsf__( 'Message' ),
			'at_username'         => _wpsf__( 'Username' ),
			'at_category'         => _wpsf__( 'Category' ),
			'at_ipaddress'        => _wpsf__( 'IP Address' ),
			'at_you'              => _wpsf__( 'You' ),
			'at_no_audit_entries' => _wpsf__( 'There are currently no audit entries this is section.' ),
		);
	}

	/**
	 * @return string
	 */
	public function getAuditTrailTableName() {
		return $this->prefix( $this->getDefinition( 'audit_trail_table_name' ), '_' );
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_audit_trail' :
				$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'The Audit Trail is designed so you can look back on events and analyse what happened and what may have gone wrong.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Audit Trail' ) ) )
				);
				$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_audit_trail_options' :
				$sTitle = _wpsf__( 'Audit Trail Options' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Provides finer control over the audit trail itself.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'These settings are dependent on your requirements.' ) )
				);
				$sTitleShort = _wpsf__( 'Options' );
				break;

			case 'section_enable_audit_contexts' :
				$sTitle = _wpsf__( 'Enable Audit Contexts' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Specify which types of actions on your site are logged.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'These settings are dependent on your requirements.' ) )
				);
				$sTitleShort = _wpsf__( 'Audit Contexts' );
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$oCon = self::getConn();

		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_audit_trail' :
				$sName = sprintf( _wpsf__( 'Enable %s' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), $this->getMainFeatureName() );
				break;

			case 'audit_trail_auto_clean' :
				$sName = _wpsf__( 'Auto Clean' );
				$sSummary = _wpsf__( 'Enable Audit Auto Cleaning' );
				$sDescription = _wpsf__( 'Events older than the number of days specified will be automatically cleaned from the database.' );
				break;

			case 'enable_audit_context_users' :
				$sName = _wpsf__( 'Users And Logins' );
				$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Users And Logins' ) );
				$sDescription = sprintf( _wpsf__( 'When this context is enabled, the audit trail will track activity relating to: %s' ), _wpsf__( 'Users And Logins' ) );
				break;

			case 'enable_audit_context_plugins' :
				$sName = _wpsf__( 'Plugins' );
				$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Plugins' ) );
				$sDescription = sprintf( _wpsf__( 'When this context is enabled, the audit trail will track activity relating to: %s' ), _wpsf__( 'WordPress Plugins' ) );
				break;

			case 'enable_audit_context_themes' :
				$sName = _wpsf__( 'Themes' );
				$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Themes' ) );
				$sDescription = sprintf( _wpsf__( 'When this context is enabled, the audit trail will track activity relating to: %s' ), _wpsf__( 'WordPress Themes' ) );
				break;

			case 'enable_audit_context_posts' :
				$sName = _wpsf__( 'Posts And Pages' );
				$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Posts And Pages' ) );
				$sDescription = sprintf( _wpsf__( 'When this context is enabled, the audit trail will track activity relating to: %s' ), _wpsf__( 'Editing and publishing of posts and pages' ) );
				break;

			case 'enable_audit_context_wordpress' :
				$sName = _wpsf__( 'WordPress And Settings' );
				$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'WordPress And Settings' ) );
				$sDescription = sprintf( _wpsf__( 'When this context is enabled, the audit trail will track activity relating to: %s' ), _wpsf__( 'WordPress upgrades and changes to particular WordPress settings' ) );
				break;

			case 'enable_audit_context_emails' :
				$sName = _wpsf__( 'Emails' );
				$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Emails' ) );
				$sDescription = sprintf( _wpsf__( 'When this context is enabled, the audit trail will track activity relating to: %s' ), _wpsf__( 'Email Sending' ) );
				break;

			case 'enable_audit_context_wpsf' :
				$sName = $oCon->getHumanName();
				$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), $oCon->getHumanName() );
				$sDescription = sprintf( _wpsf__( 'When this context is enabled, the audit trail will track activity relating to: %s' ), $oCon->getHumanName() );
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}