<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @param string $sSectionSlug
	 * @return array
	 * @throws \Exception
	 */
	public function loadStrings_SectionTitles( $sSectionSlug ) {

		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_audit_trail' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMod()
																						 ->getMainFeatureName() );
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

			/*
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
			*/

			default:
				return parent::loadStrings_SectionTitles( $sSectionSlug );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $sOptKey
	 * @return array
	 * @throws \Exception
	 */
	public function loadStrings_Options( $sOptKey ) {
		/** @var \ICWP_WPSF_FeatureHandler_AuditTrail $oMod */
		$oMod = $this->getMod();
		$oCon = $this->getCon();
		$sModName = $oMod->getMainFeatureName();

		switch ( $sOptKey ) {

			case 'enable_audit_trail' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $sModName );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $sModName );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $sModName );
				break;

			case 'audit_trail_max_entries' :
				$sName = __( 'Max Trail Length', 'wp-simple-firewall' );
				$sSummary = __( 'Maximum Audit Trail Length To Keep', 'wp-simple-firewall' );
				$sDescription = __( 'Automatically remove any audit trail entries when this limit is exceeded.', 'wp-simple-firewall' )
								.'<br/>'.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $oMod->getDefaultMaxEntries() );
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
				return parent::loadStrings_Options( $sOptKey );
		}

		return [
			'name'        => $sName,
			'summary'     => $sSummary,
			'description' => $sDescription,
		];
	}
}