<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Services\Services;

class Strings extends Base\Strings {

	public function getScanName( string $slug ) :string {
		return $this->getScanNames()[ $slug ];
	}

	/**
	 * @return string[]
	 */
	public function getScanNames() :array {
		return [
			'apc' => __( 'Abandoned Plugins', 'wp-simple-firewall' ),
			'ptg' => __( 'Plugin/Theme Guard', 'wp-simple-firewall' ),
			'mal' => __( 'Malware', 'wp-simple-firewall' ),
			'ufc' => __( 'Unrecognised Files', 'wp-simple-firewall' ),
			'wcf' => __( 'WordPress Core Files', 'wp-simple-firewall' ),
			'wpv' => __( 'Vulnerabilities', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() :array {
		$messages = [];
		foreach ( $this->getScanNames() as $slug => $scanName ) {
			$messages[ $slug.'_alert_sent' ] = [
				sprintf( __( '%s scan alert sent.', 'wp-simple-firewall' ), $scanName )
				.' '.__( 'Alert sent to %s via %s.' )
			];
			$messages[ $slug.'_scan_found' ] = [
				sprintf( __( '%s scan completed and items were discovered.', 'wp-simple-firewall' ), $scanName ),
				sprintf( '%s: %s',
					__( 'Note', 'wp-simple-firewall' ),
					__( "These items wont display in results if you've previously marked them as ignored.", 'wp-simple-firewall' )
				)
			];
			$messages[ 'scan_item_delete_success' ] = [
				__( 'Deleted item found in the scan.', 'wp-simple-firewall' )
				.' '.__( 'Item deleted: "%s"', 'wp-simple-firewall' ),
			];
			$messages[ 'scan_item_repair_success' ] = [
				__( 'Repaired item found in the scan.', 'wp-simple-firewall' )
				.' '.__( 'Item repaired: "%s"', 'wp-simple-firewall' ),
			];
			$messages[ 'scan_item_repair_fail' ] = [
				__( 'Failed to repair scan item.', 'wp-simple-firewall' )
				.' '.__( 'Failed item: "%s"', 'wp-simple-firewall' ),
			];
			$messages[ $slug.'_item_repair_success' ] = [
				sprintf( __( '%s scan repaired a item found in the scan.', 'wp-simple-firewall' ), $scanName )
				.' '.__( 'Item repaired: "%s"', 'wp-simple-firewall' ),
			];
			$messages[ $slug.'_item_repair_fail' ] = [
				sprintf( __( '%s scan could not repair item.', 'wp-simple-firewall' ), $scanName )
				.' '.__( 'Failed repair item: "%s"', 'wp-simple-firewall' ),
			];
		}
		return $messages;
	}

	/**
	 * @param string $section
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {

		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $section ) {

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

			case 'section_realtime' :
				$sTitleShort = __( 'Realtime Change Detection', 'wp-simple-firewall' );
				$sTitle = __( 'Realtime Change Detection', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ),
						__( 'Monitor Your WordPress Site For Changes To Critical Components In Realtime.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						sprintf( __( 'Enable The Realtime Change Detection Features.', 'wp-simple-firewall' ), $sTitle ) )
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
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $key
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( string $key ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$modName = $mod->getMainFeatureName();

		switch ( $key ) {

			case 'enable_hack_protect' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$desc = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName );
				break;

			case 'scan_frequency' :
				$name = __( 'Daily Scan Frequency', 'wp-simple-firewall' );
				$summary = __( 'Number Of Times To Run All Scans Each Day', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), __( 'Once every 24hrs.', 'wp-simple-firewall' ) ),
					__( 'To improve security, increase the number of scans per day.', 'wp-simple-firewall' )
				];
				break;

			case 'enable_plugin_vulnerabilities_scan' :
				$name = __( 'Vulnerabilities Scanner', 'wp-simple-firewall' );
				$summary = sprintf( __( 'Daily Cron - %s', 'wp-simple-firewall' ), __( 'Scans Plugins For Known Vulnerabilities', 'wp-simple-firewall' ) );
				$desc = __( 'Runs a scan of all your plugins against a database of known WordPress plugin vulnerabilities.', 'wp-simple-firewall' );
				break;

			case 'enable_wpvuln_scan' :
				$name = __( 'Vulnerability Scanner', 'wp-simple-firewall' );
				$summary = __( 'Enable The Vulnerability Scanner', 'wp-simple-firewall' );
				$desc = __( 'Runs a scan of all your plugins against a database of known WordPress vulnerabilities.', 'wp-simple-firewall' );
				break;

			case 'wpvuln_scan_autoupdate' :
				$name = __( 'Automatic Updates', 'wp-simple-firewall' );
				$summary = __( 'Apply Updates Automatically To Vulnerable Plugins', 'wp-simple-firewall' );
				$desc = __( 'When an update becomes available, automatically apply updates to items with known vulnerabilities.', 'wp-simple-firewall' );
				break;

			case 'enable_core_file_integrity_scan' :
				$name = sprintf( __( '%s Core Files', 'wp-simple-firewall' ),
					Services::WpGeneral()->isClassicPress() ? 'ClassicPress' : 'WordPress'
				);
				$summary = sprintf( __( 'Scan And Monitor %s Core Files For Changes', 'wp-simple-firewall' ),
					Services::WpGeneral()->isClassicPress() ? 'ClassicPress' : 'WordPress'
				);
				$desc = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Regularly scan your WordPress core files for changes compared to official WordPress files.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep this feature turned on, at all times.', 'wp-simple-firewall' ) )
				];
				break;

			case 'mal_scan_enable' :
				$name = __( 'Malware', 'wp-simple-firewall' );
				$summary = __( 'Scan And Monitor Files For Malware Infections', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Monitor and detect presence of Malware signatures.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep this feature turned on, at all times.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ), __( 'Currently files of the following types are supported:', 'wp-simple-firewall' ) )
					.' '.implode( ', ', [ 'PHP' ] )
				];
				break;

			case 'ptg_enable' :
				$name = __( 'Plugins & Themes', 'wp-simple-firewall' );
				$summary = __( 'Scan And Monitor Plugin & Theme Files For Changes', 'wp-simple-firewall' );

				$desc = [
					__( "Looks for new files added to plugins or themes, and also for changes to existing files.", 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Important', 'wp-simple-firewall' ), __( "Doesn't currently detect missing files.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep this feature turned on, at all times.', 'wp-simple-firewall' ) )
				];
				if ( !$this->getCon()->hasCacheDir() ) {
					$desc[] = sprintf( __( 'Sorry, this feature is not available because we cannot write to disk at this location: %s', 'wp-simple-firewall' ),
						'<code>'.$mod->getPtgSnapsBaseDir().'</code>' );
				}
				break;

			case 'file_repair_areas' :
				$name = __( 'Automatic File Repair', 'wp-simple-firewall' );
				$summary = __( 'Automatically Repair Files That Have Changes Or Malware Infection', 'wp-simple-firewall' );
				$desc = [
					__( 'Will attempt to automatically repair files that have been changed or infected with malware.', 'wp-simple-firewall' ),
					'- '.__( 'In the case of WordPress, original files will be downloaded from WordPress.org to repair any broken files.', 'wp-simple-firewall' ),
					'- '.__( 'In the case of plugins & themes, only those installed from WordPress.org may be repaired.', 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Important', 'wp-simple-firewall' ), __( "Auto-Repair will never automatically delete new or unrecognised files.", 'wp-simple-firewall' ) )
					.' '.__( "Unrecognised files will need to be manually deleted.", 'wp-simple-firewall' ),
				];
				break;

			case 'file_locker' :
				$name = __( 'File Locker', 'wp-simple-firewall' );
				$summary = __( 'Lock Files Against Tampering And Changes', 'wp-simple-firewall' );
				$desc = [
					__( 'Detects changes to the files, then lets you examine contents and revert as required.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'Web.Config is only available for Windows/IIS.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'After saving, it may take up to 60 seconds before a new lock is stored.', 'wp-simple-firewall' ) )
					.' '.__( "It will be displayed below when it's ready.", 'wp-simple-firewall' )
				];

				$locks = ( new LoadFileLocks() )
					->setMod( $this->getMod() )
					->loadLocks();
				if ( !empty( $locks ) ) {
					$desc[] = __( 'Locked Files', 'wp-simple-firewall' ).':';
					foreach ( $locks as $lock ) {
						$desc[] = sprintf( '<code>%s</code>', $lock->file );
					}
				}
				break;

			case 'enable_unrecognised_file_cleaner_scan' :
				$name = __( 'Unrecognised Files Scanner', 'wp-simple-firewall' );
				$summary = __( 'Automatically Scans For Unrecognised Files In Core Directories', 'wp-simple-firewall' );
				$desc = __( 'Scans for, and automatically deletes, any files in your core WordPress folders that are not part of your WordPress installation.', 'wp-simple-firewall' );
				break;

			case 'ufc_scan_uploads' :
				$name = __( 'Scan Uploads', 'wp-simple-firewall' );
				$summary = __( 'Scan Uploads Folder For PHP and Javascript', 'wp-simple-firewall' );
				$desc = sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Take care when turning on this option - if you are unsure, leave it disabled.', 'wp-simple-firewall' ) )
						.'<br />'.__( 'The Uploads folder is primarily for media, but could be used to store nefarious files.', 'wp-simple-firewall' );
				break;

			case 'ufc_exclusions' :
				$name = __( 'File Exclusions', 'wp-simple-firewall' );
				$summary = __( 'Provide A List Of Files To Be Excluded From The Scan', 'wp-simple-firewall' );
				$sDefaults = implode( ', ', $this->getOptions()->getOptDefault( 'ufc_exclusions' ) );
				$desc = __( 'Take a new line for each file you wish to exclude from the scan.', 'wp-simple-firewall' )
						.'<br/><strong>'.__( 'No commas are necessary.', 'wp-simple-firewall' ).'</strong>'
						.'<br/>'.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $sDefaults );
				break;

			case 'ic_enabled' :
				$name = __( 'Enable Integrity Scan', 'wp-simple-firewall' );
				$summary = __( 'Scans For Critical Changes Made To Your WordPress Site', 'wp-simple-firewall' );
				$desc = __( 'Detects changes made to your WordPress site outside of WordPress.', 'wp-simple-firewall' );
				break;

			case 'ic_users' :
				$name = __( 'Monitor User Accounts', 'wp-simple-firewall' );
				$summary = __( 'Scans For Critical Changes Made To User Accounts', 'wp-simple-firewall' );
				$desc = sprintf( __( 'Detects changes made to critical user account information that were made directly on the database and outside of the WordPress system.', 'wp-simple-firewall' ), 'author=' )
						.'<br />'.__( 'An example of this might be some form of SQL Injection attack.', 'wp-simple-firewall' )
						.'<br />'.sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Enabling this option for every page low may slow down your site with large numbers of users.', 'wp-simple-firewall' ) )
						.'<br />'.sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), __( 'This option may cause critical problem with 3rd party plugins that manage user accounts.', 'wp-simple-firewall' ) );
				break;

			case 'ptg_depth' : /* DELETED */
				$name = __( 'Guard/Scan Depth', 'wp-simple-firewall' );
				$summary = __( 'How Deep Into The Plugin Directories To Scan And Guard', 'wp-simple-firewall' );
				$desc = __( 'The Guard normally scans only the top level of a folder. Increasing depth will increase scan times.', 'wp-simple-firewall' )
						.'<br/>'.sprintf( __( 'Setting it to %s will remove this limit and all sub-folders will be scanned - not recommended', 'wp-simple-firewall' ), 0 );
				break;

			case 'ptg_reinstall_links' :
				$name = __( 'Show Re-Install Links', 'wp-simple-firewall' );
				$summary = __( 'Show Re-Install Links For Plugins', 'wp-simple-firewall' );
				$desc = __( "Show links to re-install plugins and offer re-install when activating plugins.", 'wp-simple-firewall' );
				break;

			case 'auto_filter_results' :
				$name = __( 'Auto-Filter Results', 'wp-simple-firewall' );
				$summary = __( 'Automatically Filter Results Of Irrelevant Items', 'wp-simple-firewall' );
				$desc = [
					__( 'Automatically remove items from results that are irrelevant.', 'wp-simple-firewall' ),
					__( "An example of this is filtering out results for PHP files that don't have any executable code.", 'wp-simple-firewall' ),
				];
				break;

			case 'scan_path_exclusions' :
				$name = __( 'Scan Exclusions', 'wp-simple-firewall' );
				$summary = __( 'Scan File And Folder Exclusions', 'wp-simple-firewall' );
				$desc = [
					__( 'A list of file/folder paths that will never be scanned.', 'wp-simple-firewall' ),
					__( 'All paths are relative to your WordPress installation directory.', 'wp-simple-firewall' ),
					__( 'This is an advanced option and should be used with great care.', 'wp-simple-firewall' ),
					__( 'Take a new line for each whitelisted path.', 'wp-simple-firewall' ),
					__( 'All characters will be treated as case-insensitive.', 'wp-simple-firewall' ),
					__( 'Directories should be provided with a trailing slash (/).', 'wp-simple-firewall' ),
					__( "If a path matches any core WordPress directories, it'll be removed automatically.", 'wp-simple-firewall' ),
					__( "These aren't regular expression, but you can use asterisk (*) as a wildcard.", 'wp-simple-firewall' ),
					sprintf( '%s: <code>%s</code>', __( 'WordPress Installation Directory', 'wp-simple-firewall' ), ABSPATH ),
				];
				break;

			case 'enabled_scan_apc' :
				$name = __( 'Abandoned Plugin Scanner', 'wp-simple-firewall' );
				$summary = __( 'Enable The Abandoned Plugin Scanner', 'wp-simple-firewall' );
				$desc = __( "Scan your WordPress.org assets for whether they've been abandoned.", 'wp-simple-firewall' );
				break;

			case 'mal_autorepair_surgical' :
				$name = __( 'Surgical Auto-Repair', 'wp-simple-firewall' );
				$summary = __( 'Automatically Attempt To Surgically Remove Malware Code', 'wp-simple-firewall' );
				$desc = __( "Attempts to automatically remove code from infected files.", 'wp-simple-firewall' )
						.'<br />'.sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), __( 'This could break your site if code removal leaves remaining code in an inconsistent state.', 'wp-simple-firewall' ) )
						.'<br />'.sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( "Only applies to files that don't fall under the other categories for automatic repair.", 'wp-simple-firewall' ) );
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $name,
			'summary'     => $summary,
			'description' => $desc,
		];
	}

	private function deprecated_strings() {
		// scan
		__( "Detect changes to core WordPress files when compared to the official distribution", 'wp-simple-firewall' );
		__( "Detect files which aren't part of the official WordPress.org distribution", 'wp-simple-firewall' );
		__( "Detect files that may be infected with malware", 'wp-simple-firewall' );
		__( '%s has detected abandoned plugins installed on your site.', 'wp-simple-firewall' );
		__( "Running code that hasn't seen any updates for over 2 years is far from ideal.", 'wp-simple-firewall' );
		__( 'Details for the items(s) are below:', 'wp-simple-firewall' );
		__( 'Abandoned Plugin(s) Discovered On Your Site.', 'wp-simple-firewall' );
		__( 'Unrecognised WordPress Files Detected', 'wp-simple-firewall' );
		__( 'The %s Unrecognised File Scanner found files which you need to review.', 'wp-simple-firewall' );
		__( '%s has attempted to delete these files based on your current settings.', 'wp-simple-firewall' );
		__( 'We recommend you run the scanner to review your site', 'wp-simple-firewall' );
		sprintf( '[ <a href="https://shsec.io/moreinfoufc">%s</a> ]', __( 'More Info On This Scanner', 'wp-simple-firewall' ) );

		__( 'Modified Core WordPress Files Discovered', 'wp-simple-firewall' );
		sprintf( __( "The %s Core File Scanner found files with potential problems.", 'wp-simple-firewall' ), $sName );

		$aContent[] = '<strong>'.sprintf( __( "%s has already attempted to repair the files.", 'wp-simple-firewall' ), $sName ).'</strong>'
					  .' '.__( 'But, you should always check these files to ensure everything is as you expect.', 'wp-simple-firewall' );
		$aContent[] = __( 'You should review these files and replace them with official versions if required.', 'wp-simple-firewall' );
		$aContent[] = __( 'Alternatively you can have the plugin attempt to repair/replace these files automatically.', 'wp-simple-firewall' )
					  .' [<a href="https://shsec.io/moreinfochecksum">'.__( 'More Info', 'wp-simple-firewall' ).'</a>]';
		__( "The following files have different content:", 'wp-simple-firewall' );
		__( 'The following files are missing:', 'wp-simple-firewall' );

		$aContent = [
			sprintf( __( "The %s Malware Scanner found files with potential malware.", 'wp-simple-firewall' ), $sName ),
			sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
				__( "You must examine the file(s) carefully to determine whether suspicious code is really present.", 'wp-simple-firewall' ) ),
			sprintf( __( "The %s Malware Scanner searches for common malware patterns and so false positives (detection errors) are to be expected sometimes.", 'wp-simple-firewall' ), $sName ),
			sprintf( '%s: %s', __( 'Site URL', 'wp-simple-firewall' ), sprintf( '<a href="%s" target="_blank">%s</a>', $sHomeUrl, $sHomeUrl ) ),
		];
		__( 'The following files contain suspected malware:', 'wp-simple-firewall' );

		$aContent = [
			sprintf( __( '%s has detected at least 1 Plugins/Themes have been modified on your site.', 'wp-simple-firewall' ), $sName ),
			__( 'You will receive only 1 email notification about these changes in a 1 week period.', 'wp-simple-firewall' ),
			__( 'Details of the problem items are below:', 'wp-simple-firewall' ),
		];
		__( 'Modified Themes:', 'wp-simple-firewall' );
		__( 'Modified Plugins:', 'wp-simple-firewall' );
		__( 'Plugins/Themes Have Been Altered', 'wp-simple-firewall' );

		$aContent = [
			sprintf( __( '%s has detected items with known security vulnerabilities.', 'wp-simple-firewall' ), $oCon->getHumanName() ),
			__( 'You should update or remove these items at your earliest convenience.', 'wp-simple-firewall' ),
			__( 'Details for the items(s) are below:', 'wp-simple-firewall' ),
		];

		__( 'Item', 'wp-simple-firewall' );
		__( 'Vulnerability Title: %s', 'wp-simple-firewall' );
		__( 'Vulnerability Type: %s', 'wp-simple-firewall' );
		__( 'Fixed Version: %s', 'wp-simple-firewall' );
		__( 'Further Information: %s', 'wp-simple-firewall' );

		__( 'Run Scanner', 'wp-simple-firewall' );
	}
}