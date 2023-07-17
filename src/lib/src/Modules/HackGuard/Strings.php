<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Services\Services;

class Strings extends Base\Strings {

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

		$sModName = $this->mod()->getMainFeatureName();

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
		$caps = $this->con()->caps;
		/** @var ModCon $mod */
		$mod = $this->mod();
		$modName = $mod->getMainFeatureName();

		switch ( $key ) {

			case 'enable_hack_protect' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$desc = [ sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName ) ];
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
				$desc = [ __( 'Runs a scan of all your plugins against a database of known WordPress plugin vulnerabilities.', 'wp-simple-firewall' ) ];
				break;

			case 'enable_wpvuln_scan' :
				$name = __( 'Vulnerability Scanner', 'wp-simple-firewall' );
				$summary = __( 'Enable The Vulnerability Scanner', 'wp-simple-firewall' );
				$desc = [ __( 'Runs a scan of all your plugins against a database of known WordPress vulnerabilities.', 'wp-simple-firewall' ) ];
				break;

			case 'wpvuln_scan_autoupdate' :
				$name = __( 'Automatic Updates', 'wp-simple-firewall' );
				$summary = __( 'Apply Updates Automatically To Vulnerable Plugins', 'wp-simple-firewall' );
				$desc = [ __( 'When an update becomes available, automatically apply updates to items with known vulnerabilities.', 'wp-simple-firewall' ) ];
				break;

			case 'enable_core_file_integrity_scan' :
				$name = sprintf( __( 'Automatic %s File Scanner', 'wp-simple-firewall' ),
					Services::WpGeneral()->isClassicPress() ? 'ClassicPress' : 'WordPress'
				);
				$summary = sprintf( __( 'Scan And Monitor %s Files For Changes', 'wp-simple-firewall' ),
					Services::WpGeneral()->isClassicPress() ? 'ClassicPress' : 'WordPress'
				);
				$desc = [
					__( 'It is important to regularly scan your WordPress files for signs of intrusion.', 'wp-simple-firewall' )
					.' '.__( 'This is one of the fastest ways to detect malicious activity on the site.', 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep this feature turned on, at all times.', 'wp-simple-firewall' ) ),
				];
				$desc[] = sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ),
					sprintf( __( "See the 'File Scan Areas' option to direct how and where the file scanner will operate.", 'wp-simple-firewall' ), 'ShieldPRO' ) );
				break;

			case 'file_scan_areas' :
				$name = __( 'File Scan Areas', 'wp-simple-firewall' );
				$summary = __( 'Select Which Areas Should Be Scanned', 'wp-simple-firewall' );
				$desc = [
					__( 'Each scan area performs a specific task, as follows:', 'wp-simple-firewall' ),
					sprintf( '- <strong>%s</strong>: %s', __( 'WP core files', 'wp-simple-firewall' ),
						\implode( ' ', [
							__( "Scans all WP files for your current WordPress version.", 'wp-simple-firewall' ),
							sprintf( __( "It also looks for files that shouldn't be in core WP directories (%s).", 'wp-simple-firewall' ),
								'<code>/wp-admin/</code>, <code>/wp-includes/</code>' ),
							sprintf( __( "Doesn't scan within the %s directory.", 'wp-simple-firewall' ), '<code>/wp-content/</code>' )
						] )
					),
					sprintf( '- <strong>%s</strong>: %s', __( 'PHP Malware', 'wp-simple-firewall' ),
						\implode( ' ', [
							__( "Scans all PHP files for malware patterns.", 'wp-simple-firewall' ),
						] )
					),
				];

				if ( $caps->canScanPluginsThemesRemote() ) {
					$additional = __( 'Premium plugins are also supported on your plan.', 'wp-simple-firewall' );
				}
				elseif ( $caps->canScanPluginsThemesLocal() ) {
					$additional = __( 'Scanning uses local snapshots. Upgrade your plan to use crowd-sourced snapshots and add support for premium plugins.', 'wp-simple-firewall' );
				}
				else {
					$additional = __( 'Please upgrade to support scanning of all plugin files for tampering.', 'wp-simple-firewall' );
				}
				$desc[] = sprintf( '- <strong>%s</strong>: %s', __( 'Plugins' ),
					\implode( ' ', [
						__( "Scans for file tampering within plugin directories.", 'wp-simple-firewall' ),
						$additional
					] )
				);

				if ( $caps->canScanPluginsThemesRemote() ) {
					$additional = __( 'Premium themes are also supported on your plan.', 'wp-simple-firewall' );
				}
				elseif ( $caps->canScanPluginsThemesLocal() ) {
					$additional = __( 'Scanning uses local snapshots. Upgrade your plan to use crowd-sourced snapshots and add support for premium themes.', 'wp-simple-firewall' );
				}
				else {
					$additional = __( 'Upgrade your plan to support scanning of theme files for tampering.', 'wp-simple-firewall' );
				}
				$desc[] = sprintf( '- <strong>%s</strong>: %s', __( 'Themes' ),
					\implode( ' ', [
						__( "Scans for file tampering within the active theme.", 'wp-simple-firewall' ),
						$additional
					] )
				);

				if ( !$caps->canScanAllFiles() ) {
					$additional = __( 'Upgrade your plan to support this scanning area.', 'wp-simple-firewall' );
				}
				else {
					$additional = '';
				}
				$desc[] = sprintf( '- <strong>%s</strong>: %s', sprintf( __( '%s directory', 'wp-simple-firewall' ), '<code>/wp-content/</code>' ),
					\implode( ' ', [
						sprintf( __( "The %s directory is the wild-west and many plugins and themes use it to store working files.", 'wp-simple-firewall' ), '<code>wp-content</code>' ),
						__( "It's practically impossible to tell which files should and shouldn't be there.", 'wp-simple-firewall' ),
						sprintf( __( "This scan area currently focuses on only %s files.", 'wp-simple-firewall' ),
							'<code>'.\implode( '</code>, <code>', [ '.php', '.js', '.ico' ] ).'</code>'
						),
						$additional
					] )
				);

				if ( !$caps->canScanAllFiles() ) {
					$additional = __( 'Upgrade your plan to support this scanning area.', 'wp-simple-firewall' );
				}
				else {
					$additional = '';
				}
				$desc[] = sprintf( '- <strong>%s</strong>: %s', __( 'WP root directory' ),
					\implode( ' ', [
						sprintf( __( "The %s directory is like the %s directory and many non-WordPress files are kept there.", 'wp-simple-firewall' ), 'WP root', '<code>/wp-content/</code>' ),
						__( "Since it's normally messy, it's the perfect place to hide malicious files in plain sight.", 'wp-simple-firewall' ),
						__( "We have rules to detect unidentified files, but you'll probably see false positive results.", 'wp-simple-firewall' ),
						$additional,
					] )
				);

				$desc[] = __( 'The more areas that are selected, the longer the file scan will take to complete.', 'wp-simple-firewall' );
				break;

			case 'file_repair_areas' :
				$name = __( 'Automatic File Repair', 'wp-simple-firewall' );
				$summary = __( 'Automatically Repair Files That Have Changes Or Malware Infection', 'wp-simple-firewall' );
				$desc = [
					__( 'Attempts to automatically repair files that have been changed, or infected with malware.', 'wp-simple-firewall' ),
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
					.' '.__( "It will be displayed below when it's ready.", 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( "The PHP OpenSSL Extension is required, along with the RC4 Cipher.", 'wp-simple-firewall' ) ),
				];

				$locks = ( new LoadFileLocks() )->loadLocks();
				if ( !empty( $locks ) ) {
					$desc[] = __( 'Locked Files', 'wp-simple-firewall' ).':';
					foreach ( $locks as $lock ) {
						$desc[] = sprintf( '<code>%s</code>', $lock->path );
					}
				}
				break;

			case 'ptg_reinstall_links':
				$name = __( 'Show Re-Install Links', 'wp-simple-firewall' );
				$summary = __( 'Show Re-Install Links For Plugins', 'wp-simple-firewall' );
				$desc = [ __( "Show links to re-install plugins and offer re-install when activating plugins.", 'wp-simple-firewall' ) ];
				break;

			case 'optimise_scan_speed':
				$name = __( 'Optimise File Scans', 'wp-simple-firewall' );
				$summary = __( 'Optimise File Scans', 'wp-simple-firewall' );
				$desc = [
					__( 'Optimise file scans to run much faster.', 'wp-simple-firewall' ),
					__( 'If you experience any errors in your logs or strange scanning behaviour, disable this option.', 'wp-simple-firewall' )
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
				$desc = [ __( "Scan your WordPress.org assets for whether they've been abandoned.", 'wp-simple-firewall' ) ];
				break;

			case 'mal_autorepair_surgical' :
				$name = __( 'Surgical Auto-Repair', 'wp-simple-firewall' );
				$summary = __( 'Automatically Attempt To Surgically Remove Malware Code', 'wp-simple-firewall' );
				$desc = [
					__( "Attempts to automatically remove code from infected files.", 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), __( 'This could break your site if code removal leaves remaining code in an inconsistent state.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( "Only applies to files that don't fall under the other categories for automatic repair.", 'wp-simple-firewall' ) )
				];
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
}