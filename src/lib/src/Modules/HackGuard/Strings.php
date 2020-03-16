<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Strings extends Base\Strings {

	/**
	 * @param string $sSlug
	 * @return string|null
	 */
	public function getScanName( $sSlug ) {
		$aN = $this->getScanNames();
		return isset( $aN[ $sSlug ] ) ? $aN[ $sSlug ] : null;
	}

	/**
	 * @return string[]
	 */
	public function getScanNames() {
		return [
			'apc' => __( 'Abandoned Plugins', 'wp-simple-firewall' ),
			'ptg' => __( 'Plugin/Theme Guard', 'wp-simple-firewall' ),
			'mal' => __( 'Malware', 'wp-simple-firewall' ),
			'ufc' => __( 'Unrecognised Files', 'wp-simple-firewall' ),
			'wcf' => __( 'WP Core Files', 'wp-simple-firewall' ),
			'wpv' => __( 'Vulnerabilities', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		$aMessages = [];
		foreach ( $this->getScanNames() as $sSlug => $sScanName ) {
			$aMessages[ $sSlug.'_alert_sent' ] = [
				sprintf( __( '%s scan alert sent.', 'wp-simple-firewall' ), $sScanName )
				.' '.__( 'Alert sent to %s via %s.' )
			];
			$aMessages[ $sSlug.'_scan_found' ] = [
				sprintf( __( '%s scan completed and items were discovered.', 'wp-simple-firewall' ), $sScanName ),
				sprintf( '%s: %s',
					__( 'Note', 'wp-simple-firewall' ),
					__( "These items wont display in results if you've previously marked them as ignored.", 'wp-simple-firewall' )
				)
			];
			$aMessages[ $sSlug.'_item_repair_success' ] = [
				sprintf( __( '%s scan repaired a item found in the scan.', 'wp-simple-firewall' ), $sScanName )
				.' '.__( 'Item repaired: "%s"', 'wp-simple-firewall' ),
			];
			$aMessages[ $sSlug.'_item_repair_fail' ] = [
				sprintf( __( '%s scan could not repair item.', 'wp-simple-firewall' ), $sScanName )
				.' '.__( 'Failed repair item: "%s"', 'wp-simple-firewall' ),
			];
		}
		return $aMessages;
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( $sSectionSlug ) {

		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $sSectionSlug ) {

			case 'section_scan_options' :
				$sTitle = __( 'Scan Options', 'wp-simple-firewall' );
				$sTitleShort = __( 'Scan Options', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Set how frequently the Hack Guard scans will run.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_enable_plugin_feature_hack_protection_tools' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $sModName );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Hack Guard is a set of tools to warn you and protect you against hacks on your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Hack Guard', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_scan_wpv' :
				$sTitleShort = __( 'Vulnerabilities', 'wp-simple-firewall' );
				$sTitle = __( 'Vulnerabilities Scanner', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Regularly scan your WordPress plugins and themes for known security vulnerabilities.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Vulnerabilities Scanner', 'wp-simple-firewall' ) ) ),
					__( 'Ensure this is turned on and you will always know if any of your assets have known security vulnerabilities.', 'wp-simple-firewall' )
				];
				break;

			case 'section_file_guard' :
				$sTitleShort = __( 'File Scans and Malware', 'wp-simple-firewall' );
				$sTitle = __( 'File Scanning and Malware Protection', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ),
						__( 'Monitor WordPress files and protect against malicious intrusion and hacking.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), $sTitle ) )
				];
				break;

			case 'section_scan_ufc' :
				$sTitleShort = __( 'Unrecognised Files', 'wp-simple-firewall' );
				$sTitle = __( 'Unrecognised Files Scanner', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( "Regularly scan your WordPress core folders for files that don't belong.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), $sTitle ) )
				];
				break;

			case 'section_scan_apc' :
				$sTitle = __( 'Enable The Abandoned Plugin Scanner', 'wp-simple-firewall' );
				$sTitleShort = __( 'Abandoned Plugins', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ),
						__( 'Monitor your site for plugins that have been abandoned by their authors and are no longer maintained.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enable this to alert you to your site running unmaintained code.', 'wp-simple-firewall' ) )
				];
				break;

			//REMOVED:
			case 'section_scan_wcf' :
				$sTitleShort = __( 'Core Files', 'wp-simple-firewall' );
				$sTitle = __( 'WordPress Core File Scanner', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Regularly scan your WordPress core files for changes compared to official WordPress files.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), $sTitle ) )
				];
				break;

			case 'section_scan_mal' :
				$sTitleShort = __( 'Malware', 'wp-simple-firewall' );
				$sTitle = __( 'Malware Scanner', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Monitor and detect presence of Malware signatures.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enable this scanner to automatically detect infected files.', 'wp-simple-firewall' ) )
				];
				break;

			default:
				return parent::getSectionStrings( $sSectionSlug );
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
	public function getOptionStrings( $sOptKey ) {

		$oMod = $this->getMod();
		$sModName = $oMod->getMainFeatureName();

		switch ( $sOptKey ) {

			case 'enable_hack_protect' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $sModName );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $sModName );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $sModName );
				break;

			case 'scan_frequency' :
				$sName = __( 'Daily Scan Frequency', 'wp-simple-firewall' );
				$sSummary = __( 'Number Of Times To Run All Scans Each Day', 'wp-simple-firewall' );
				$sDescription = [
					sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), __( 'Once every 24hrs.', 'wp-simple-firewall' ) ),
					__( 'To improve security, increase the number of scans per day.', 'wp-simple-firewall' )
				];
				break;

			case 'enable_plugin_vulnerabilities_scan' :
				$sName = __( 'Vulnerabilities Scanner', 'wp-simple-firewall' );
				$sSummary = sprintf( __( 'Daily Cron - %s', 'wp-simple-firewall' ), __( 'Scans Plugins For Known Vulnerabilities', 'wp-simple-firewall' ) );
				$sDescription = __( 'Runs a scan of all your plugins against a database of known WordPress plugin vulnerabilities.', 'wp-simple-firewall' );
				break;

			case 'enable_wpvuln_scan' :
				$sName = __( 'Vulnerability Scanner', 'wp-simple-firewall' );
				$sSummary = __( 'Enable The Vulnerability Scanner', 'wp-simple-firewall' );
				$sDescription = __( 'Runs a scan of all your plugins against a database of known WordPress vulnerabilities.', 'wp-simple-firewall' );
				break;

			case 'wpvuln_scan_autoupdate' :
				$sName = __( 'Automatic Updates', 'wp-simple-firewall' );
				$sSummary = __( 'Apply Updates Automatically To Vulnerable Plugins', 'wp-simple-firewall' );
				$sDescription = __( 'When an update becomes available, automatically apply updates to items with known vulnerabilities.', 'wp-simple-firewall' );
				break;

			case 'enable_core_file_integrity_scan' :
				$sName = sprintf( __( '%s Core Files', 'wp-simple-firewall' ),
					Services::WpGeneral()->isClassicPress() ? 'ClassicPress' : 'WordPress'
				);
				$sSummary = sprintf( __( 'Scan And Monitor %s Core Files For Changes', 'wp-simple-firewall' ),
					Services::WpGeneral()->isClassicPress() ? 'ClassicPress' : 'WordPress'
				);
				$sDescription = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Regularly scan your WordPress core files for changes compared to official WordPress files.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep this feature turned on, at all times.', 'wp-simple-firewall' ) )
				];
				break;

			case 'mal_scan_enable' :
				$sName = __( 'Malware', 'wp-simple-firewall' );
				$sSummary = __( 'Scan And Monitor All Files For Malware Infections', 'wp-simple-firewall' );
				$sDescription = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Monitor and detect presence of Malware signatures.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enable this scanner to automatically detect infected files.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep this feature turned on, at all times.', 'wp-simple-firewall' ) )
				];
				break;

			case 'ptg_enable' :
				$sName = __( 'Plugins & Themes', 'wp-simple-firewall' );
				$sSummary = __( 'Scan And Monitor Plugin & Theme Files For Changes', 'wp-simple-firewall' );

				$sDescription = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Detect malicious changes to your themes and plugins.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep this feature turned on, at all times.', 'wp-simple-firewall' ) )
				];
				break;

			case 'file_repair_areas' :
				$sName = __( 'Automatic File Repair', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Repair Files That Have Changes Or Malware Infection', 'wp-simple-firewall' );
				$sDescription = [
					__( 'Will attempt to automatically repair files that are detected to have been changed or infected with malware.', 'wp-simple-firewall' ),
					__( 'In the case of WordPress, original files will be downloaded from WordPress.org to repair any broken files.', 'wp-simple-firewall' ),
					__( 'In the case of plugins & themes, only those installed from WordPress.org can be repaired.', 'wp-simple-firewall' ),
				];
				break;

			case 'enable_unrecognised_file_cleaner_scan' :
				$sName = __( 'Unrecognised Files Scanner', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Scans For Unrecognised Files In Core Directories', 'wp-simple-firewall' );
				$sDescription = __( 'Scans for, and automatically deletes, any files in your core WordPress folders that are not part of your WordPress installation.', 'wp-simple-firewall' );
				break;

			case 'ufc_scan_uploads' :
				$sName = __( 'Scan Uploads', 'wp-simple-firewall' );
				$sSummary = __( 'Scan Uploads Folder For PHP and Javascript', 'wp-simple-firewall' );
				$sDescription = sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Take care when turning on this option - if you are unsure, leave it disabled.', 'wp-simple-firewall' ) )
								.'<br />'.__( 'The Uploads folder is primarily for media, but could be used to store nefarious files.', 'wp-simple-firewall' );
				break;

			case 'ufc_exclusions' :
				$sName = __( 'File Exclusions', 'wp-simple-firewall' );
				$sSummary = __( 'Provide A List Of Files To Be Excluded From The Scan', 'wp-simple-firewall' );
				$sDefaults = implode( ', ', $oMod->getOptions()->getOptDefault( 'ufc_exclusions' ) );
				$sDescription = __( 'Take a new line for each file you wish to exclude from the scan.', 'wp-simple-firewall' )
								.'<br/><strong>'.__( 'No commas are necessary.', 'wp-simple-firewall' ).'</strong>'
								.'<br/>'.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $sDefaults );
				break;

			case 'ic_enabled' :
				$sName = __( 'Enable Integrity Scan', 'wp-simple-firewall' );
				$sSummary = __( 'Scans For Critical Changes Made To Your WordPress Site', 'wp-simple-firewall' );
				$sDescription = __( 'Detects changes made to your WordPress site outside of WordPress.', 'wp-simple-firewall' );
				break;

			case 'ic_users' :
				$sName = __( 'Monitor User Accounts', 'wp-simple-firewall' );
				$sSummary = __( 'Scans For Critical Changes Made To User Accounts', 'wp-simple-firewall' );
				$sDescription = sprintf( __( 'Detects changes made to critical user account information that were made directly on the database and outside of the WordPress system.', 'wp-simple-firewall' ), 'author=' )
								.'<br />'.__( 'An example of this might be some form of SQL Injection attack.', 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Enabling this option for every page low may slow down your site with large numbers of users.', 'wp-simple-firewall' ) )
								.'<br />'.sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), __( 'This option may cause critical problem with 3rd party plugins that manage user accounts.', 'wp-simple-firewall' ) );
				break;

			case 'ptg_depth' : /* DELETED */
				$sName = __( 'Guard/Scan Depth', 'wp-simple-firewall' );
				$sSummary = __( 'How Deep Into The Plugin Directories To Scan And Guard', 'wp-simple-firewall' );
				$sDescription = __( 'The Guard normally scans only the top level of a folder. Increasing depth will increase scan times.', 'wp-simple-firewall' )
								.'<br/>'.sprintf( __( 'Setting it to %s will remove this limit and all sub-folders will be scanned - not recommended', 'wp-simple-firewall' ), 0 );
				break;

			case 'ptg_reinstall_links' :
				$sName = __( 'Show Re-Install Links', 'wp-simple-firewall' );
				$sSummary = __( 'Show Re-Install Links For Plugins', 'wp-simple-firewall' );
				$sDescription = __( "Show links to re-install plugins and offer re-install when activating plugins.", 'wp-simple-firewall' );
				break;

			case 'enabled_scan_apc' :
				$sName = __( 'Abandoned Plugin Scanner', 'wp-simple-firewall' );
				$sSummary = __( 'Enable The Abandoned Plugin Scanner', 'wp-simple-firewall' );
				$sDescription = __( "Scan your WordPress.org assets for whether they've been abandoned.", 'wp-simple-firewall' );
				break;

			case 'display_apc' :
				$sName = __( 'Highlight Plugins', 'wp-simple-firewall' );
				$sSummary = __( 'Highlight Abandoned Plugins', 'wp-simple-firewall' );
				$sDescription = __( "Abandoned plugins will be highlighted on the main plugins page.", 'wp-simple-firewall' );
				break;

			case 'mal_autorepair_core' :
				$sName = __( 'Auto-Repair WP Core', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Repair WordPress Core Files', 'wp-simple-firewall' );
				$sDescription = __( "Automatically reinstall any core files found to have potential malware.", 'wp-simple-firewall' );
				break;

			case 'mal_autorepair_plugins' :
				$sName = __( 'Auto-Repair WP Plugins', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Repair WordPress.org Plugins', 'wp-simple-firewall' );
				$sDescription = __( "Automatically repair any plugin files found to have potential malware.", 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'Only compatible with plugins installed from WordPress.org.', 'wp-simple-firewall' ) )
								.'<br />'.sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( "Also deletes suspected files if they weren't originally distributed with the plugin.", 'wp-simple-firewall' ) );
				break;

			case 'autorepair_themes' :
				$sName = __( 'Auto-Repair WP Themes', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Repair WordPress.org Themes', 'wp-simple-firewall' );
				$sDescription = __( "Automatically repair any theme files found to have potential malware.", 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'Only compatible with themes installed from WordPress.org.', 'wp-simple-firewall' ) )
								.'<br />'.sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( "Also deletes suspected files if they weren't originally distributed with the theme.", 'wp-simple-firewall' ) );
				break;

			case 'mal_autorepair_surgical' :
				$sName = __( 'Surgical Auto-Repair', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Attempt To Surgically Remove Malware Code', 'wp-simple-firewall' );
				$sDescription = __( "Attempts to automatically remove code from infected files.", 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), __( 'This could break your site if code removal leaves remaining code in an inconsistent state.', 'wp-simple-firewall' ) )
								.'<br />'.sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( "Only applies to files that don't fall under the other categories for automatic repair.", 'wp-simple-firewall' ) );
				break;

			case 'rt_file_wpconfig' :
				$sName = __( 'WP Config', 'wp-simple-firewall' );
				$sSummary = __( 'Realtime Protection For WP Config File', 'wp-simple-firewall' );
				$sDescription = __( "Realtime protection for the wp-config.php file.", 'wp-simple-firewall' );
				break;

			// REMOVED:
			case 'wpvuln_scan_display' :
				$sName = __( 'Highlight Plugins', 'wp-simple-firewall' );
				$sSummary = __( 'Highlight Vulnerable Plugins Upon Display', 'wp-simple-firewall' );
				$sDescription = __( 'Vulnerable plugins will be highlighted on the main plugins page.', 'wp-simple-firewall' );
				break;
			case 'email_files_list' :
				$sName = __( 'Email Files List', 'wp-simple-firewall' );
				$sSummary = __( 'Scan Notification Emails Should Include Full Listing Of Files', 'wp-simple-firewall' );
				$sDescription = __( 'Scanner notification emails will include a summary list of all affected files.', 'wp-simple-firewall' );
				break;
			case 'mal_fp_confidence' :
				$sName = __( 'Ignore False Positives Threshold', 'wp-simple-firewall' );
				$sSummary = __( 'Ignore False Positives In Scan Results Automatically', 'wp-simple-firewall' );
				$sDescription = __( "You can choose to ignore files with potential malware, depending on whether the confidence that it's a 'false positive' meets your minimum threshold.", 'wp-simple-firewall' )
								.'<br />'.__( "A false positive happens when a file appears to contain malware and shows up in scan results, but it's actually clean.", 'wp-simple-firewall' )
								.' ('.__( "A false positive is similar to when an anti-virus alerts to a file that doesnt have a virus.", 'wp-simple-firewall' ).')'
								.'<br />'.__( "The higher the confidence level, the more likely a result is a false positive.", 'wp-simple-firewall' )
								.' '.__( "A low level means it's less likely to be a false positive.", 'wp-simple-firewall' )
								.'<br />'.__( "The scan will automatically ignore results whose 'false positive' confidence level is greater than your chosen threshold.", 'wp-simple-firewall' )
								.'<br />'.__( "The higher the confidence threshold you select, the more likely that 'false positives' will appears in your scan results.", 'wp-simple-firewall' )
								.'<br />'.__( "Disabling network intelligence turns off 'false positive confidence' levels.", 'wp-simple-firewall' )
								.' '.__( 'You will no longer benefit from the intelligence gathered from the entire network.', 'wp-simple-firewall' )
								.' '.__( 'All data shared is completely anonymous.', 'wp-simple-firewall' )
								.' '.' [<a href="https://shsec.io/moreinfomalnetwork">'.__( 'More Info', 'wp-simple-firewall' ).'</a>]'
								.'<br />'.__( 'The more sites that share this information, the stronger and smarter the network becomes.', 'wp-simple-firewall' );
				break;
			case 'notification_interval' :
				$sName = __( 'Repeat Notifications', 'wp-simple-firewall' );
				$sSummary = __( 'Item Repeat Notifications Suppression Interval', 'wp-simple-firewall' );
				$sDescription = __( 'How long the automated scans should wait before repeating a notification about an item.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Specify the number of days to suppress repeat notifications.', 'wp-simple-firewall' )
								.'<br/>'.sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'This is per discovered item or file, not per scan.', 'wp-simple-firewall' ) );
				break;
			case 'ptg_extensions' :
				$sName = __( 'Included File Types', 'wp-simple-firewall' );
				$sSummary = __( 'The File Types (by File Extension) Included In The Scan', 'wp-simple-firewall' );
				$sDescription = __( 'Take a new line for each file extension.', 'wp-simple-firewall' )
								.'<br/>'.__( 'No commas(,) or periods(.) necessary.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Remove all extensions to scan all file type (not recommended).', 'wp-simple-firewall' );
				break;
			default:
				return parent::getOptionStrings( $sOptKey );
		}

		return [
			'name'        => $sName,
			'summary'     => $sSummary,
			'description' => $sDescription,
		];
	}
}