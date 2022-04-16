<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Services\Services;

class Strings extends Base\Strings {

	/**
	 * @inheritDoc
	 */
	public function getEventStrings() :array {
		return [
			'scan_run'                 => [
				'name'  => __( 'Scan Completed', 'wp-simple-firewall' ),
				'audit' => [
					sprintf( '%s: {{scan}}', __( 'Scan Completed', 'wp-simple-firewall' ) ),
				],
			],
			'scan_item_delete_success' => [
				'name'  => __( 'Scan Item Delete Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Item found in the scan was deleted.', 'wp-simple-firewall' ),
					__( 'Item deleted: "{{path_full}}"', 'wp-simple-firewall' ),
				],
			],
			'scan_item_repair_success' => [
				'name'  => __( 'Scan Item Repair Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Repaired item found in the scan.', 'wp-simple-firewall' ),
					__( 'Item repaired: "{{path_full}}"', 'wp-simple-firewall' ),
				],
			],
			'scan_item_repair_fail'    => [
				'name'  => __( 'Scan Item Repair Failure', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Failed to repair scan item.', 'wp-simple-firewall' ),
					__( 'Failed item: "{{path_full}}"', 'wp-simple-firewall' ),
				],
			],
			'scan_items_found'         => [
				'name'  => __( 'Items Found In Scan', 'wp-simple-firewall' ),
				'audit' => [
					__( '{{scan}}: scan completed and items were discovered.', 'wp-simple-firewall' ),
					sprintf( '%s: %s {{items}}',
						__( 'Note', 'wp-simple-firewall' ),
						__( "These items wont display in results if you've previously marked them as ignored.", 'wp-simple-firewall' )
					),
				],
			],
		];
	}

	public function getScanName( string $slug ) :string {
		return $this->getScanStrings()[ $slug ][ 'name' ];
	}

	/**
	 * @return string[]
	 */
	public function getScanStrings() :array {
		return [
			'afs' => [
				'name'     => __( 'WordPress Filesystem Scan', 'wp-simple-firewall' ),
				'subtitle' => __( "WordPress Filesystem Scan looking for modified, missing and unrecognised files throughout the entire site", 'wp-simple-firewall' ),
			],
			'apc' => [
				'name'     => __( 'Abandoned Plugins', 'wp-simple-firewall' ),
				'subtitle' => __( "Discover plugins that may have been abandoned by their authors", 'wp-simple-firewall' ),
			],
			'ptg' => [
				'name'     => __( 'Plugin/Theme Guard', 'wp-simple-firewall' ),
				'subtitle' => __( "Be alerted to file changes for all your plugins and themes", 'wp-simple-firewall' ),
			],
			'mal' => [
				'name'     => __( 'Malware', 'wp-simple-firewall' ),
				'subtitle' => __( "Detect files that may be infected with malware", 'wp-simple-firewall' ),
			],
			'ufc' => [
				'name'     => __( 'Unrecognised Files', 'wp-simple-firewall' ),
				'subtitle' => __( "Detect files which aren't part of the official WordPress.org distribution", 'wp-simple-firewall' ),
			],
			'wcf' => [
				'name'     => __( 'WordPress Core Files', 'wp-simple-firewall' ),
				'subtitle' => __( "Detect changes to core WordPress files when compared to the official distribution", 'wp-simple-firewall' ),
			],
			'wpv' => [
				'name'     => __( 'Vulnerabilities', 'wp-simple-firewall' ),
				'subtitle' => __( "Be alerted to plugins and themes with known security vulnerabilities", 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {

		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $section ) {

			case 'section_scan_options' :
				$title = __( 'Scan Options', 'wp-simple-firewall' );
				$shortTitle = __( 'Scan Options', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Set how frequently the Hack Guard scans will run.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_enable_plugin_feature_hack_protection_tools' :
				$shortTitle = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $sModName );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Hack Guard is a set of tools to warn you and protect you against hacks on your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Hack Guard', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_scan_wpv' :
				$shortTitle = sprintf( '%s, %s, %s', __( 'Vulnerabilities', 'wp-simple-firewall' ),
					__( 'Plugins', 'wp-simple-firewall' ), __( 'Themes', 'wp-simple-firewall' ) );
				$title = __( 'Vulnerabilities Scanner', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Regularly scan your WordPress plugins and themes for known security vulnerabilities.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Vulnerabilities Scanner', 'wp-simple-firewall' ) ) ),
					__( 'Ensure this is turned on and you will always know if any of your assets have known security vulnerabilities.', 'wp-simple-firewall' )
				];
				break;

			case 'section_file_guard' :
				$shortTitle = __( 'File Scans and Malware', 'wp-simple-firewall' );
				$title = __( 'File Scanning and Malware Protection', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ),
						__( 'Monitor WordPress files and protect against malicious intrusion and hacking.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), $title ) )
				];
				break;

			case 'section_realtime' :
				$shortTitle = __( 'Realtime Change Detection', 'wp-simple-firewall' );
				$title = __( 'Realtime Change Detection', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ),
						__( 'Monitor Your WordPress Site For Changes To Critical Components In Realtime.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						sprintf( __( 'Enable The Realtime Change Detection Features.', 'wp-simple-firewall' ), $title ) )
				];
				break;

			case 'section_scan_apc' :
				$title = __( 'Enable The Abandoned Plugin Scanner', 'wp-simple-firewall' );
				$shortTitle = __( 'Abandoned Plugins', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ),
						__( 'Monitor your site for plugins that have been abandoned by their authors and are no longer maintained.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enable this to alert you to your site running unmaintained code.', 'wp-simple-firewall' ) )
				];
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $title,
			'title_short' => $shortTitle,
			'summary'     => $summary,
		];
	}

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
				$name = sprintf( __( 'Automatic %s File Scanner', 'wp-simple-firewall' ),
					Services::WpGeneral()->isClassicPress() ? 'ClassicPress' : 'WordPress'
				);
				$summary = sprintf( __( 'Scan And Monitor %s Files For Changes', 'wp-simple-firewall' ),
					Services::WpGeneral()->isClassicPress() ? 'ClassicPress' : 'WordPress'
				);
				$desc = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Regularly scan all files on your site for changes.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep this feature turned on, at all times.', 'wp-simple-firewall' ) ),
				];
				if ( $mod->isPremium() ) {
					$desc[] = sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ),
						sprintf( __( 'Scan areas include: WordPress Core Files, Plugin and Themes, and PHP Malware.', 'wp-simple-firewall' ), 'ShieldPRO' ) );
				}
				else {
					$desc[] = sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ),
						__( 'Scan areas include: WordPress Core Files.', 'wp-simple-firewall' ) );
					$desc[] = sprintf( '%s - %s', __( 'Important', 'wp-simple-firewall' ),
						sprintf( __( 'To also include scanning Plugins and Themes, and for PHP Malware, please upgrade to %s.', 'wp-simple-firewall' ), 'ShieldPRO' ) );
				}
				break;

			case 'file_repair_areas' :
				$name = __( 'Automatic File Repair', 'wp-simple-firewall' );
				$summary = __( 'Automatically Repair Files That Have Changes Or Malware Infection', 'wp-simple-firewall' );
				$desc = [
					__( 'Will attempt to automatically repair files that have been changed, or infected with malware.', 'wp-simple-firewall' ),
					'- '.__( 'In the case of WordPress, original files will be downloaded from WordPress.org to repair any broken files.', 'wp-simple-firewall' ),
					'- '.__( 'In the case of plugins & themes, only those installed from WordPress.org can be repaired.', 'wp-simple-firewall' ),
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