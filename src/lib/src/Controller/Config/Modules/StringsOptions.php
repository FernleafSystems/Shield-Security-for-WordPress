<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginDumpTelemetry;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class StringsOptions {

	use PluginControllerConsumer;

	/**
	 * @return array{name:string, summary:string, description:array}
	 */
	public function getFor( string $key ) :array {
		$con = self::con();
		$caps = $con->caps;
		$opts = $con->comps->opts_lookup;
		$pluginName = $con->labels->Name;
		$modStrings = new StringsModules();

		switch ( $key ) {

			case 'enable_audit_trail':
				$modName = $modStrings->getFor( EnumModules::ACTIVITY )[ 'name' ];
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$desc = [ sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName ) ];
				break;
			case 'log_level_db':
				$name = __( 'Logging Level', 'wp-simple-firewall' );
				$summary = __( 'Logging Level For DB-Based Logs', 'wp-simple-firewall' );
				$desc = [
					__( 'Specify the logging levels when using the local database.', 'wp-simple-firewall' ),
					__( "Debug and Info logging should only be enabled when investigating specific problems.", 'wp-simple-firewall' ),
					sprintf( '<a href="%s" target="_blank">%s</a>',
						$con->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_DOCS ),
						__( 'View all event details and their assigned levels', 'wp-simple-firewall' )
					)
				];
				break;
			case 'audit_trail_auto_clean':
				$name = __( 'Log Retention', 'wp-simple-firewall' );
				$summary = __( 'Automatically Purge Activity Logs Older Than The Set Number Of Days', 'wp-simple-firewall' );
				$desc = [
					__( 'Events older than the number of days specified will be automatically cleaned from the database.', 'wp-simple-firewall' )
				];
				if ( !$con->caps->hasCap( 'logs_retention_unlimited' ) ) {
					$desc[] = sprintf(
						__( 'The maximum log retention limit (%s) may be increased by upgrading your ShieldPRO plan.', 'wp-simple-firewall' ),
						$con->caps->getMaxLogRetentionDays()
					);
				}
				break;

			case 'autoupdate_plugin_self':
				$name = __( 'Self AutoUpdate', 'wp-simple-firewall' );
				$summary = __( 'Always Automatically Update This Plugin', 'wp-simple-firewall' );
				$desc = [
					sprintf( __( 'Automatically update the "%s" plugin.', 'wp-simple-firewall' ), $pluginName ),
					__( 'The plugin will normally automatically update after approximately 5 days, if left to decide.', 'wp-simple-firewall' )
				];
				break;
			case 'update_delay':
				$name = __( 'Update Delay', 'wp-simple-firewall' );
				$summary = __( 'Delay Automatic Updates For Period Of Stability', 'wp-simple-firewall' );
				$desc = [
					sprintf( __( '%s will delay upgrades until the new update has been available for the set number of days.', 'wp-simple-firewall' ), $pluginName ),
					__( "This helps ensure updates are more stable before they're automatically applied to your site.", 'wp-simple-firewall' )
				];
				break;

			case 'enable_comments_filter':
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modStrings->getFor( EnumModules::COMMENTS )[ 'name' ] );
				$summary = __( 'Enable (or Disable) The Comment SPAM Protection Feature', 'wp-simple-firewall' );
				$desc = [ sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), __( 'Comment SPAM Protection', 'wp-simple-firewall' ) ) ];
				break;
			case 'comments_cooldown':
				$name = __( 'Comments Cooldown', 'wp-simple-firewall' );
				$summary = __( 'Minimum Time Interval Between Comments', 'wp-simple-firewall' ).' ('.__( 'seconds' ).')';
				$desc = [
					__( 'Prevents comment floods and ensures a minimum period before any further comments are accepted on the site.', 'wp-simple-firewall' ),
					__( 'Set to zero (0) to disable.', 'wp-simple-firewall' ),
				];
				break;
			case 'trusted_commenter_minimum':
				$name = __( 'Trusted Commenter Minimum', 'wp-simple-firewall' );
				$summary = __( 'Minimum Number Of Approved Comments Before Commenter Is Trusted', 'wp-simple-firewall' );
				$desc = [
					__( 'Specify how many approved comments must exist before a commenter is trusted and their comments are no longer scanned.', 'wp-simple-firewall' ),
					__( 'Normally WordPress will trust after 1 comment.', 'wp-simple-firewall' )
				];
				break;
			case 'trusted_user_roles':
				$name = __( 'Trusted User Roles', 'wp-simple-firewall' );
				$summary = __( "Comments From Users With These Roles Will Never Be Scanned", 'wp-simple-firewall' );
				$desc = [
					__( "Shield doesn't normally scan comments from logged-in or registered users.", 'wp-simple-firewall' ),
					__( "Specify user roles here that shouldn't be scanned.", 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'Take a new line for each user role.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Available Roles', 'wp-simple-firewall' ),
						\implode( ', ', Services::WpUsers()->getAvailableUserRoles() ) )
				];
				break;
			case 'enable_antibot_comments':
				$name = __( 'Block Bot SPAM', 'wp-simple-firewall' );
				$summary = __( 'Use silentCAPTCHA To Block Bot Comment SPAM', 'wp-simple-firewall' );
				$desc = [
					sprintf( __( 'Block 99.9%% of all WordPress comment SPAM using silentCAPTCHA.', 'wp-simple-firewall' ), $pluginName ),
					sprintf( __( "silentCAPTCHA is %s's exclusive bot-detection technology that removes the needs for visible CAPTCHAs and similar visitor challenges.", 'wp-simple-firewall' ), $pluginName ),
				];
				break;
			case 'enable_comments_human_spam_filter':
				$name = __( 'Block Human SPAM', 'wp-simple-firewall' );
				$summary = __( 'Block Comment SPAM Posted By Humans', 'wp-simple-firewall' );
				$desc = [
					__( 'Most SPAM is automatic, by bots, but sometimes Humans also post comments to your site and these bypass Bot Detection rules.', 'wp-simple-firewall' ),
					__( 'When this happens, you can scan the content for keywords that are typical of SPAM.', 'wp-simple-firewall' ),
				];
				break;
			case 'comments_default_action_human_spam':
				$name = __( 'SPAM Action', 'wp-simple-firewall' );
				$summary = __( 'How To Categorise Comments When Identified To Be SPAM', 'wp-simple-firewall' );
				$desc = [ sprintf( __( 'When a comment is detected as being SPAM from %s, the comment will be categorised based on this setting.', 'wp-simple-firewall' ), '<span style"text-decoration:underline;">'.__( 'a human commenter', 'wp-simple-firewall' ).'</span>' ) ];
				break;
			case 'comments_default_action_spam_bot':
				$name = __( 'Bot SPAM Action', 'wp-simple-firewall' );
				$summary = __( 'Where To Put Bot SPAM Comments', 'wp-simple-firewall' );
				$desc = [
					sprintf( __( 'When a comment is detected as being bot SPAM, %s will move the comment to this folder.', 'wp-simple-firewall' ), $pluginName )
				];
				break;

			case 'enable_firewall':
				$modName = $modStrings->getFor( EnumModules::FIREWALL )[ 'name' ];
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$desc = [
					sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName )
				];
				break;
			case 'include_cookie_checks':
				$name = __( 'Include Cookies', 'wp-simple-firewall' );
				$summary = __( 'Also Test Cookie Values In Firewall Tests', 'wp-simple-firewall' );
				$desc = [ __( 'The firewall tests GET and POST, but with this option checked it will also check COOKIE values.', 'wp-simple-firewall' ) ];
				break;
			case 'block_dir_traversal':
				$name = __( 'Directory Traversals', 'wp-simple-firewall' );
				$summary = __( 'Block Directory Traversals', 'wp-simple-firewall' );
				$desc = [ sprintf( __( 'This will block directory traversal paths in in application parameters (e.g. %s, etc).', 'wp-simple-firewall' ), \base64_decode( 'Li4vLCAuLi8uLi9ldGMvcGFzc3dk' ) ) ];
				break;
			case 'block_sql_queries':
				$name = __( 'SQL Queries', 'wp-simple-firewall' );
				$summary = __( 'Block SQL Queries', 'wp-simple-firewall' );
				$desc = [ sprintf( __( 'This will block sql in application parameters (e.g. %s, etc).', 'wp-simple-firewall' ), \base64_decode( 'dW5pb24gc2VsZWN0LCBjb25jYXQoLCAvKiovLCAuLik=' ) ) ];
				break;
			case 'block_field_truncation':
				$name = __( 'Field Truncation', 'wp-simple-firewall' );
				$summary = __( 'Block Field Truncation Attacks', 'wp-simple-firewall' );
				$desc = [ __( 'This will block field truncation attacks in application parameters.', 'wp-simple-firewall' ) ];
				break;
			case 'block_php_code':
				$name = __( 'PHP Code', 'wp-simple-firewall' );
				$summary = sprintf( __( 'Block %s', 'wp-simple-firewall' ), __( 'PHP Code Includes', 'wp-simple-firewall' ) );
				$desc = [
					__( 'This will block any data that appears to try and include PHP files.', 'wp-simple-firewall' ),
					__( 'Will probably block saving within the Plugin/Theme file editors.', 'wp-simple-firewall' )
				];
				break;
			case 'block_aggressive':
				$name = __( 'Aggressive Scan', 'wp-simple-firewall' );
				$summary = __( 'Aggressively Block Data', 'wp-simple-firewall' );
				$desc = [
					__( 'Employs a set of aggressive rules to detect and block malicious data submitted to your site.', 'wp-simple-firewall' ),
					sprintf( '<strong>%s</strong> - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'May cause an increase in false-positive firewall blocks.', 'wp-simple-firewall' ) )
				];
				break;
			case 'block_send_email':
				$name = __( 'Send Email Report', 'wp-simple-firewall' );
				$summary = __( 'Send Firewall Trigger Report Email', 'wp-simple-firewall' );
				$desc = [ __( 'Use with caution - if you get hit by automated bots you may send out too many emails and you could get blocked by your host', 'wp-simple-firewall' ) ];
				break;
			case 'page_params_whitelist':
				$name = __( 'Whitelist Parameters', 'wp-simple-firewall' );
				$summary = __( 'Detail pages and parameters that are whitelisted (ignored by the firewall)', 'wp-simple-firewall' );
				$desc = [ __( 'This should be used with caution and you should only provide parameter names that you must have excluded', 'wp-simple-firewall' ) ];
				break;

			case 'enable_hack_protect':
				$modName = $modStrings->getFor( EnumModules::SCANS )[ 'name' ];
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$desc = [ sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName ) ];
				break;
			case 'scan_frequency':
				$name = __( 'Daily Scan Frequency', 'wp-simple-firewall' );
				$summary = __( 'Number Of Times To Run All Scans Each Day', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), __( 'Once every 24hrs.', 'wp-simple-firewall' ) ),
					__( 'To improve security, increase the number of scans per day.', 'wp-simple-firewall' )
				];
				break;
			case 'enable_plugin_vulnerabilities_scan':
				$name = __( 'Vulnerabilities Scanner', 'wp-simple-firewall' );
				$summary = sprintf( __( 'Daily Cron - %s', 'wp-simple-firewall' ), __( 'Scans Plugins For Known Vulnerabilities', 'wp-simple-firewall' ) );
				$desc = [ __( 'Runs a scan of all your plugins against a database of known WordPress plugin vulnerabilities.', 'wp-simple-firewall' ) ];
				break;
			case 'enable_wpvuln_scan':
				$name = __( 'Vulnerability Scanner', 'wp-simple-firewall' );
				$summary = __( 'Scan For Vulnerabilities In Plugins & Themes', 'wp-simple-firewall' );
				$desc = [ __( 'Runs a scan of all your assets against a database of known WordPress vulnerabilities.', 'wp-simple-firewall' ) ];
				break;
			case 'wpvuln_scan_autoupdate':
				$name = __( 'Automatic Updates', 'wp-simple-firewall' );
				$summary = __( 'Automatically Install Updates To Vulnerable Plugins', 'wp-simple-firewall' );
				$desc = [ __( 'When an update becomes available, automatically apply updates to items with known vulnerabilities.', 'wp-simple-firewall' ) ];
				break;
			case 'enable_core_file_integrity_scan':
				$name = sprintf( __( 'WordPress File Scanner', 'wp-simple-firewall' ), 'WordPress' );
				$summary = __( 'Automatically Scan WordPress Files For Changes', 'wp-simple-firewall' );
				$desc = [
					__( "It's critical to regularly scan WordPress files for signs of intrusion.", 'wp-simple-firewall' )
					.' '.__( 'This is one of the fastest ways to detect malicious activity on the site.', 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep this feature turned on, at all times.', 'wp-simple-firewall' ) ),
				];
				$desc[] = sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ),
					$con->isPremiumActive() ?
						sprintf( __( "See the 'File Scan Areas' option to specify which files should be scanned.", 'wp-simple-firewall' ), 'ShieldPRO' )
						: __( 'Only core WordPress files are scans in the free version of Shield.', 'wp-simple-firewall' )
				);
				break;
			case 'file_scan_areas':
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
			case 'file_repair_areas':
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
			case 'file_locker':
				$name = __( 'File Locker', 'wp-simple-firewall' );
				$summary = __( 'Lock Files Against Tampering And Changes', 'wp-simple-firewall' );
				$desc = [
					__( 'Detects changes to the files, then lets you examine contents and revert as required.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'Web.Config is only available for Windows/IIS.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'After saving, it may take up to 60 seconds before a new lock is stored.', 'wp-simple-firewall' ) )
					.' '.__( "It will be displayed below when it's ready.", 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( "The PHP OpenSSL Extension is required, along with the RC4 Cipher.", 'wp-simple-firewall' ) ),
				];

				$locks = self::con()->comps->file_locker->getLocks();
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
			case 'scan_path_exclusions':
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
			case 'enabled_scan_apc':
				$name = __( 'Abandoned Plugins', 'wp-simple-firewall' );
				$summary = __( 'Scan For Plugins Abandoned By The Developer', 'wp-simple-firewall' );
				$desc = [ __( "Scan your WordPress.org assets for whether they've been abandoned.", 'wp-simple-firewall' ) ];
				break;

			case 'x_frame':
				$name = __( 'Block iFrames', 'wp-simple-firewall' );
				$summary = __( 'Block Remote iFrames Of This Site', 'wp-simple-firewall' );
				$desc = [
					__( 'The setting prevents any external website from embedding your site in an iFrame.', 'wp-simple-firewall' ),
					__( 'This is useful for preventing so-called "ClickJack attacks".', 'wp-simple-firewall' )
				];
				break;
			case 'x_referrer_policy':
				$name = __( 'Referrer Policy', 'wp-simple-firewall' );
				$summary = __( 'Referrer Policy Header', 'wp-simple-firewall' );
				$desc = [ __( 'The Referrer Policy Header allows you to control when and what referral information a browser may pass along with links clicked on your site.', 'wp-simple-firewall' ) ];
				break;
			case 'x_xss_protect':
				$name = __( 'XSS Protection', 'wp-simple-firewall' );
				$summary = __( 'Employ Built-In Browser XSS Protection', 'wp-simple-firewall' );
				$desc = [ __( 'Directs compatible browsers to block what they detect as Reflective XSS attacks.', 'wp-simple-firewall' ) ];
				break;
			case 'x_content_type':
				$name = __( 'Prevent Mime-Sniff', 'wp-simple-firewall' );
				$summary = __( 'Turn-Off Browser Mime-Sniff', 'wp-simple-firewall' );
				$desc = [ __( 'Reduces visitor exposure to malicious user-uploaded content.', 'wp-simple-firewall' ) ];
				break;
			case 'enable_x_content_security_policy':
				$name = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), 'CSP' );
				$summary = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Content Security Policy', 'wp-simple-firewall' ) );
				$desc = [
					__( 'Use this option to toggle on/off whether your custom CSP Rules are applied.', 'wp-simple-firewall' ),
				];
				break;
			case 'xcsp_custom':
				$name = __( 'CSP Rules', 'wp-simple-firewall' );
				$summary = __( 'Content Security Policy (CSP) Rules', 'wp-simple-firewall' );
				$desc = [
					\implode( ' ', [
						__( 'CSP Rules allow you to provide granular control over your site content and how assets are served.', 'wp-simple-firewall' ),
						__( "It's a complex area and if used incorrectly, can easily break your front-end user experience.", 'wp-simple-firewall' ),
						__( "We recommend seeking out expertise in this area, and ensure you have full testing in your deployments.", 'wp-simple-firewall' ),
					] ),
					__( "Please note that WP Page Caching plugins typically ignore HTTP Headers and if you're not seeing them reflected in your tests, you should disable your caching plugin.", 'wp-simple-firewall' ),
					'- '.__( 'Take a new line per rule.', 'wp-simple-firewall' ),
					'- '.__( 'We provide this feature as-is: to allow you to add custom CSP rules to your site.', 'wp-simple-firewall' ),
					'- '.__( "We don't provide support for creating CSP rules and whether they're correct for your site.", 'wp-simple-firewall' ),
					'- '.__( "Many WordPress caching plugins ignore HTTP Headers - if they're not showing up, disable page caching.", 'wp-simple-firewall' )
				];
				break;

			case 'enable_ips':
				$modName = $modStrings->getFor( EnumModules::IPS )[ 'name' ];
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$desc = [ sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName ) ];
				break;
			case 'transgression_limit':
				$name = __( 'Offense Limit', 'wp-simple-firewall' );
				$summary = __( 'The number of permitted offenses before an IP address will be blocked', 'wp-simple-firewall' );
				$desc = [
					sprintf( __( 'An offense is registered against an IP address each time a visitor trips the defenses of the %s plugin.', 'wp-simple-firewall' ), $pluginName ),
					__( 'When the number of these offenses exceeds the limit, they are automatically blocked from accessing the site.', 'wp-simple-firewall' ),
					sprintf( __( 'Set this to "0" to turn off the %s feature.', 'wp-simple-firewall' ), __( 'Automatic IP Black List', 'wp-simple-firewall' ) )
				];
				break;
			case 'auto_expire':
				$name = __( 'Auto Block Expiration', 'wp-simple-firewall' );
				$summary = __( 'After 1 "X" a black listed IP will be removed from the black list', 'wp-simple-firewall' );
				$desc = [
					__( 'This option lets you choose how long blocked IP addresses should stay blocked.', 'wp-simple-firewall' ),
					__( 'Performance of your block lists is optimised by automatically removing stale IP addresses, keeping the list small and fast.', 'wp-simple-firewall' )
				];
				break;
			case 'user_auto_recover':
				$name = __( 'User Auto Unblock', 'wp-simple-firewall' );
				$summary = __( 'Allow Visitors To Unblock Their IP', 'wp-simple-firewall' );
				$desc = [ __( 'Allow visitors blocked by the plugin to automatically unblock themselves.', 'wp-simple-firewall' ) ];
				break;
			case 'request_whitelist':
				$name = __( 'Request Path Whitelist', 'wp-simple-firewall' );
				$summary = __( 'Request Path Whitelist', 'wp-simple-firewall' );
				$desc = [
					__( 'A list of request paths that will never trigger an offense.', 'wp-simple-firewall' ),
					__( 'This is an advanced option and should be used with great care.', 'wp-simple-firewall' )
					.'<br />- '.__( 'Take a new line for each whitelisted path.', 'wp-simple-firewall' )
					.'<br />- '.__( "All characters will be treated as case-insensitive.", 'wp-simple-firewall' )
					.'<br />- '.__( "The paths are compared against only the request path, not the query portion.", 'wp-simple-firewall' )
					.'<br />- '.__( "If a path you add matches your website root (/), it'll be removed automatically.", 'wp-simple-firewall' )
				];

				break;
			case 'silentcaptcha_complexity':
				$name = __( 'silentCAPTCHA Complexity', 'wp-simple-firewall' );
				$summary = __( 'Adjust silentCAPTCHA Challenge Complexity', 'wp-simple-firewall' );
				$desc = [
					__( "Shield's silentCAPTCHA system uses ALTCHA, that challenges bots to perform complex work.", 'wp-simple-firewall' ),
					__( "This complex work is compute intensive and inflicts a processing cost on bots. Bots typically won't do the work, and this helps to discriminate between bots and humans.", 'wp-simple-firewall' ),
					__( "More complexity in the challenge is a bigger cost to bots, but may present a burden for legitimate visitors that use slower devices.", 'wp-simple-firewall' ),
					__( "Adaptive complexity will try to present the most suitable challenge depending on the type of visitor.", 'wp-simple-firewall' ),
				];
				break;
			case 'antibot_minimum':
				$name = __( 'silentCAPTCHA Bot Minimum Score', 'wp-simple-firewall' );
				$summary = __( 'silentCAPTCHA Bot Score (Percentage)', 'wp-simple-firewall' );
				$desc = [
					__( "Every IP address accessing your site gets its own unique visitor score - the higher the score, the better the visitor i.e. the more likely it's human.", 'wp-simple-firewall' ),
					__( "A score of '100' would mean it's almost certainly good, a score of '0' means it's highly likely to be a bad bot.", 'wp-simple-firewall' ),
					__( 'When a bot tries to login, or post a comment, we test its visitor score.', 'wp-simple-firewall' )
					.' '.__( 'If the visitor score fails to meet your Minimum silentCAPTCHA Score, we may prevent the request (such as login, WP comment etc.). If its higher, we allow it.', 'wp-simple-firewall' ),
					__( "This means: choose a higher minimum score to be more strict and capture more bots (but potentially block someone that appears to be a bot, but isn't).", 'wp-simple-firewall' )
					.' '.__( "Or choose a lower minimum score to perhaps allow through more bots (but reduce the chances of accidentally blocking legitimate visitors).", 'wp-simple-firewall' ),
				];
				break;
			case 'antibot_high_reputation_minimum':
				$name = __( 'High Reputation Bypass', 'wp-simple-firewall' );
				$summary = __( 'Prevent Visitors With A High Reputation Scores From Being Blocked', 'wp-simple-firewall' );
				$desc = [
					__( "Visitors that have accumulated a high IP reputation score should ideally never be blocked.", 'wp-simple-firewall' ),
					__( "This option ensures that visitors with a high reputation score won't be blocked by Shield automatically.", 'wp-simple-firewall' ),
					sprintf( '%s: %s',
						__( 'Note', 'wp-simple-firewall' ),
						sprintf( __( 'Your current IP Reputation score is %s.', 'wp-simple-firewall' ), sprintf( '<code>%s</code>',
							( new CalculateVisitorBotScores() )
								->setIP( $con->this_req->ip )
								->total()
						) )
					),
				];
				break;
			case 'cs_block':
				$name = __( 'CrowdSec IP Blocking', 'wp-simple-firewall' );
				$summary = __( 'How To Handle Requests From IPs Found On CrowdSec Blocklist', 'wp-simple-firewall' );
				$desc = [
					__( "How should Shield block requests from IP addresses found on CrowdSec's list of malicious IP addresses?", 'wp-simple-firewall' ),
					__( "To provide the greatest flexibility for your visitors in the case of false positives, select the option to block but with the ability for visitors to automatically unblock themselves.", 'wp-simple-firewall' ),
				];
				break;
			case 'cs_enroll_id':
				try {
					$machID = $con->comps->crowdsec->getCApiStore()->retrieveMachineId();
				}
				catch ( \Exception $e ) {
					$machID = '';
				}
				$name = __( 'CrowdSec Enroll ID', 'wp-simple-firewall' );
				$summary = __( 'CrowdSec Instance Enroll ID', 'wp-simple-firewall' );
				$desc = [
					__( 'CrowdSec Instance Enroll ID.', 'wp-simple-firewall' ),
					__( 'You can link this WordPress site to your CrowdSec console by providing your Enroll ID.', 'wp-simple-firewall' ),
					sprintf( '%s: <a href="%s" target="_blank">%s</a>', __( 'Login or Signup for your free CrowdSec console', 'wp-simple-firewall' ),
						'https://clk.shldscrty.com/crowdsecapp', 'https://app.crowdsec.net' ),
					empty( $machID ) ? __( "Your site isn't registered with CrowdSec yet.", 'wp-simple-firewall' )
						: sprintf( __( "Your registered machine ID with CrowdSec is: %s", 'wp-simple-firewall' ), '<code>'.$machID.'</code>' ),
				];
				break;
			case 'track_loginfailed':
				$name = __( 'Failed Login', 'wp-simple-firewall' );
				$summary = __( 'Detect Failed Login Attempts For Users That Exist', 'wp-simple-firewall' );
				$desc = [ __( "Penalise a visitor when they try to login using a valid username but with the wrong password.", 'wp-simple-firewall' ) ];
				break;
			case 'track_xmlrpc':
				$name = __( 'XML-RPC Access', 'wp-simple-firewall' );
				$summary = __( 'Identify A Bot When It Accesses XML-RPC', 'wp-simple-firewall' );
				$desc = [
					__( "If you don't use XML-RPC, there's no reason anything should be accessing it.", 'wp-simple-firewall' ),
					__( "Be careful to ensure you don't block legitimate XML-RPC traffic if your site needs it.", 'wp-simple-firewall' ),
					__( "We recommend to start with logging here, in-case you're unsure.", 'wp-simple-firewall' )
					.' '.__( "You can monitor the Activity Log and when you're happy you won't block valid requests, you can switch to blocking.", 'wp-simple-firewall' )
				];
				break;
			case 'track_404':
				$name = __( '404 Detect', 'wp-simple-firewall' );
				$summary = __( 'Identify A Bot When It Hits A 404', 'wp-simple-firewall' );
				$desc = [
					__( 'Detect when a visitor tries to load a non-existent page.', 'wp-simple-firewall' ),
					__( "Care should be taken to ensure that your website doesn't generate 404 errors for normal visitors.", 'wp-simple-firewall' ),
					sprintf( '%s: <br/><strong>%s</strong>',
						__( "404 errors generated for the following file types won't trigger an offense", 'wp-simple-firewall' ),
						\implode( ', ', $con->comps->bot_signals->getAllowableExt404s() )
					),
					$con->caps->canBotsAdvancedBlocking() ? '' : $this->getNoteForBots()
				];
				break;
			case 'track_linkcheese':
				$name = __( 'Link Cheese', 'wp-simple-firewall' );
				$summary = __( 'Tempt A Bot With A Fake Link To Follow', 'wp-simple-firewall' );
				$desc = [
					__( "Detect a bot when it follows a fake 'no-follow' link.", 'wp-simple-firewall' ),
					__( "This works because legitimate web crawlers respect 'robots.txt' and 'nofollow' directives.", 'wp-simple-firewall' )
				];
				break;
			case 'track_logininvalid':
				$name = __( 'Invalid Usernames', 'wp-simple-firewall' );
				$summary = __( "Detect Failed Login Attempts With Usernames That Don't Exist", 'wp-simple-firewall' );
				$desc = [
					__( "Identify a Bot when it tries to login with a non-existent username.", 'wp-simple-firewall' ),
					sprintf( __( "This includes the WordPress default %s username, if you've removed that account.", 'wp-simple-firewall' ),
						'<code>admin</code>' ),
					$con->caps->canBotsAdvancedBlocking() ? '' : $this->getNoteForBots()
				];
				break;
			case 'track_invalidscript':
				$name = __( 'Invalid Script Load', 'wp-simple-firewall' );
				$summary = __( 'Identify Bot Attempts To Load WordPress In A Non-Standard Way', 'wp-simple-firewall' );
				$desc = [
					__( "Detect when a bot tries to load WordPress directly from a file that isn't normally used to load WordPress.", 'wp-simple-firewall' ),
					__( "WordPress is normally loaded in a limited number of ways and when it's loaded in other ways it may point to probing by a malicious bot.", 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						sprintf( __( 'Set this option to "%s" and monitor the Activity Log, since some plugins, themes, or custom integrations may trigger this under normal circumstances.', 'wp-simple-firewall' ), __( 'Activity Log Only', 'wp-simple-firewall' ) ) ),
					sprintf( '%s: %s',
						__( "Currently permitted scripts", 'wp-simple-firewall' ),
						sprintf( '<ul><li><code>%s</code></li></ul>',
							\implode( '</code></li><li><code>', $con->comps->bot_signals->getAllowableScripts() ) )
					),
					$con->caps->canBotsAdvancedBlocking() ? '' : $this->getNoteForBots()
				];
				break;
			case 'track_fakewebcrawler':
				$name = __( 'Fake Web Crawler', 'wp-simple-firewall' );
				$summary = __( 'Detect Fake Search Engine Crawlers', 'wp-simple-firewall' );
				$desc = [
					__( "Identify a visitor as a Bot when it presents as an official web crawler, but analysis shows it's fake.", 'wp-simple-firewall' ),
					__( "Many bots pretend to be a Google Bot, or any other branded crawler.", 'wp-simple-firewall' )
					.'<br/>'.__( "We will immediately know that a particular bot isn't here for anything good.", 'wp-simple-firewall' ),
					$con->caps->canBotsAdvancedBlocking() ? '' : $this->getNoteForBots(),
				];
				break;
			case 'track_useragent':
				$name = __( 'Empty User Agents', 'wp-simple-firewall' );
				$summary = __( 'Detect Requests With Empty User Agents', 'wp-simple-firewall' );
				$desc = [
					__( "Identify a bot when the user agent is not provided.", 'wp-simple-firewall' ),
					sprintf( '%s:<br/><code>%s</code>',
						__( 'For example, your browser user agent is', 'wp-simple-firewall' ),
						esc_html( Services::Request()->getUserAgent() ) ),
					$con->caps->canBotsAdvancedBlocking() ? '' : $this->getNoteForBots(),
				];
				break;

			case 'enable_auto_integrations':
				$name = __( 'Auto-Integrations', 'wp-simple-firewall' );
				$summary = __( "Automatically Switch-On Built-in Integrations As They're Detected", 'wp-simple-firewall' );
				$desc = [
					__( 'Shield will automatically scan your 3rd party plugins to check whether a built-in integration is available.', 'wp-simple-firewall' ),
					__( "As soon as a compatible plugin is detected, Shield will automatically switch-on the built-in integration.", 'wp-simple-firewall' ),
				];
				break;

			case 'enable_mainwp':
				$name = __( 'MainWP Integration', 'wp-simple-firewall' );
				$summary = __( "Turn-On Shield's Built-In Extension For MainWP Server And Client Installations", 'wp-simple-firewall' );
				$desc = [
					__( 'Easily integrate Shield Security to help you manage your site security from within MainWP.', 'wp-simple-firewall' ),
					__( "You don't need to install a separate extension for MainWP.", 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
						__( "If this is a MainWP client site, you should add your MainWP Admin Server's IP address to your IP bypass list.", 'wp-simple-firewall' ) )
				];
				if ( !$con->caps->canMainwpLevel1() ) {
					$desc[] = __( 'Please upgrade your plan if you need to run the MainWP integration.', 'wp-simple-firewall' );
				}
				break;

			case 'user_form_providers':
				$name = __( 'User Forms Bot Detection', 'wp-simple-firewall' );
				$summary = __( "Select The User Forms Provider That You Use", 'wp-simple-firewall' );
				$desc = [
					__( 'Many 3rd party plugins provide custom user login, registration, and lost password forms.', 'wp-simple-firewall' )
					.' '.__( "They aren't normally checked for Bots since they require a custom integration.", 'wp-simple-firewall' ),
					__( "Select your 3rd party providers to have Shield automatically detect Bot requests to these forms.", 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
						__( "Only form types (login, registration, lost password), that you've selected will be monitored.", 'wp-simple-firewall' ) ),
				];
				if ( !$con->caps->canThirdPartyScanUsers() ) {
					$desc[] = __( 'Please upgrade your plan if you need to protect and integrate with 3rd party user login forms.', 'wp-simple-firewall' );
				}
				break;
			case 'form_spam_providers':
				$name = __( 'Contact Form SPAM', 'wp-simple-firewall' );
				$summary = __( 'Select The Form Providers That You Use', 'wp-simple-firewall' );
				$desc = [
					__( 'Just like WordPress comments, Contact Forms (or any type of form at all) is normally a victim of SPAM.', 'wp-simple-firewall' )
					.' '.__( "Some form developers provide CAPTCHAs to try and block SPAM, but these are often clunky and ineffective.", 'wp-simple-firewall' ),
					__( "If you want Shield to block SPAM from suspected bots without your users having to click CAPTCHAs, select the form providers you want to protect.", 'wp-simple-firewall' ),
					__( "If the provider you want isn't on the list, contact our support team and we'll see if an integration can be built.", 'wp-simple-firewall' ),
				];
				if ( !$con->caps->canThirdPartyScanSpam() ) {
					$desc[] = __( 'Please upgrade your plan if you need to protect and integrate with 3rd party form providers.', 'wp-simple-firewall' );
				}
				break;
			case 'suresend_emails':
				$name = __( 'SureSend Emails', 'wp-simple-firewall' );
				$summary = __( 'Select Which Shield Emails Should Be Sent Using SureSend', 'wp-simple-firewall' );
				$desc = [
					__( 'SureSend is a dedicated email delivery service from Shield Security.', 'wp-simple-firewall' ),
					__( 'The purpose is the improve WordPress email reliability for critical emails.', 'wp-simple-firewall' ),
					__( "If you're not using a dedicated email service provider to send WordPress emails, you should enable SureSend for these important emails.", 'wp-simple-firewall' ),
					__( "This isn't a replacement for a dedicated email service.", 'wp-simple-firewall' ),
					__( "Please read the information and blog links below to fully understand this service and its limitations.", 'wp-simple-firewall' ),
				];
				break;
			case 'disable_xmlrpc':
				$name = sprintf( __( 'Disable %s', 'wp-simple-firewall' ), 'XML-RPC' );
				$summary = sprintf( __( 'Disable The %s System', 'wp-simple-firewall' ), 'XML-RPC' );
				$desc = [ sprintf( __( 'Checking this option will completely turn off the whole %s system.', 'wp-simple-firewall' ), 'XML-RPC' ) ];
				break;
			case 'disable_anonymous_restapi':
				$name = __( 'Anonymous Rest API', 'wp-simple-firewall' );
				$summary = sprintf( __( 'Disable The %s System', 'wp-simple-firewall' ), __( 'Anonymous Rest API', 'wp-simple-firewall' ) );
				$desc = [
					__( 'You can completely disable anonymous access to the REST API.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'Disabling anonymous access may break plugins that use the REST API for your site visitors.', 'wp-simple-firewall' ) ),
					__( 'Use the exclusions option to allow anonymous access to specific API endpoints.', 'wp-simple-firewall' ),
				];
				break;
			case 'api_namespace_exclusions':
				$name = __( 'Rest API Exclusions', 'wp-simple-firewall' );
				$summary = __( 'Anonymous REST API Exclusions', 'wp-simple-firewall' );
				$desc = [
					__( 'These REST API namespaces will be excluded from the Anonymous API restriction.', 'wp-simple-firewall' ),
					sprintf( __( 'Some plugins (e.g. %s) use the REST API anonymously so you need to provide exclusions for them to work correctly.', 'wp-simple-firewall' ),
						'Contact Form 7' ),
					__( "Please contact the developer of a plugin to ask them for their REST API namespace if you need it." ),
					__( 'Some common namespaces' ).':',
				];

				$defaultEx = [
					'contact-form-7' => 'Contact Form 7',
					'tribe'          => 'The Events Calendar',
					'jetpack'        => 'JetPack',
					'woocommerce'    => 'WooCommerce',
					'wpstatistics'   => 'WP Statistics',
				];
				foreach ( $defaultEx as $defNamespace => $defName ) {
					$desc[] = sprintf( '<code>%s</code> - %s', $defNamespace, $defName );
				}
				break;
			case 'disable_file_editing':
				$name = __( 'Disable File Editing', 'wp-simple-firewall' );
				$summary = __( 'Disable Ability To Edit Files From Within WordPress', 'wp-simple-firewall' );
				$desc = [
					__( 'Removes the option to directly edit any files from within the WordPress admin area.', 'wp-simple-firewall' ),
					__( 'Equivalent to setting "DISALLOW_FILE_EDIT" to TRUE.', 'wp-simple-firewall' )
				];
				break;
			case 'hide_wordpress_generator_tag':
				$name = __( 'WP Generator Tag', 'wp-simple-firewall' );
				$summary = __( 'Remove WP Generator Meta Tag', 'wp-simple-firewall' );
				$desc = [ __( 'Remove a meta tag from your WordPress pages that publicly displays that your site is WordPress and its current version.', 'wp-simple-firewall' ) ];
				break;
			case 'clean_wp_rubbish':
				$name = __( 'Clean WP Files', 'wp-simple-firewall' );
				$summary = __( 'Automatically Delete Unnecessary WP Files', 'wp-simple-firewall' );
				$desc = [
					__( "Automatically delete WordPress files that aren't necessary.", 'wp-simple-firewall' ),
					__( "The cleanup process runs once each day.", 'wp-simple-firewall' ),
					sprintf( '%s: <code>%s</code>', __( 'Files Deleted', 'wp-simple-firewall' ),
						\implode( '</code><code>', [ 'wp-config-sample.php', 'readme.html', 'license.txt' ] ) )
				];
				break;
			case 'block_author_discovery':
				$name = __( 'Block Username Fishing', 'wp-simple-firewall' );
				$summary = __( 'Block the ability to discover WordPress usernames based on author IDs', 'wp-simple-firewall' );
				$desc = [
					sprintf( __( 'When enabled, any URL requests containing "%s" will be killed.', 'wp-simple-firewall' ), 'author=' ),
					sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Enabling this option may interfere with expected operations of your site.', 'wp-simple-firewall' ) )
				];
				break;

			case 'enable_login_protect':
				$modName = $modStrings->getFor( EnumModules::LOGIN )[ 'name' ];
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$desc = [ sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName ) ];
				break;
			case 'rename_wplogin_path':
				$name = __( 'Hide WP Login & Admin', 'wp-simple-firewall' );
				$summary = __( 'Hide The WordPress Login And Admin Areas', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s: %s',
						__( 'Important', 'wp-simple-firewall' ),
						sprintf( __( "This will cause %s and %s URLs to return HTTP 404 errors while you're not logged-in.", 'wp-simple-firewall' ),
							'<code>/wp-admin/</code>',
							'<code>/wp-login.php</code>'
						)
					),
					sprintf( __( 'Only letters and numbers are permitted: %s', 'wp-simple-firewall' ), '<strong>abc123</strong>' ),
					sprintf( __( 'Your current login URL is: %s', 'wp-simple-firewall' ), '<br /><strong>&nbsp;&nbsp;'.wp_login_url().'</strong>' )
				];
				break;
			case 'rename_wplogin_redirect':
				$name = __( 'WP Login & Admin Redirect', 'wp-simple-firewall' );
				$summary = __( 'Automatic Redirect URL For Hidden Pages', 'wp-simple-firewall' );
				$desc = [
					__( 'Automatically redirect here for any requests made to hidden pages.', 'wp-simple-firewall' ),
					sprintf( '%s: %s',
						__( 'Note', 'wp-simple-firewall' ),
						sprintf( __( 'Leave this blank to serve a standard "%s" error page.', 'wp-simple-firewall' ), 'HTTP 404 Not Found' )
					),
					sprintf( '%s: %s',
						__( 'Important', 'wp-simple-firewall' ),
						sprintf( __( 'Use relative paths from your homepage URL e.g. %s redirects to your homepage (%s).', 'wp-simple-firewall' ),
							'<code>/</code>',
							sprintf( '<code>%s</code>', Services::WpGeneral()->getHomeUrl() )
						)
					),
				];
				break;

			case 'enable_chained_authentication':
				$name = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Multi-Factor Authentication', 'wp-simple-firewall' ) );
				$summary = __( 'Require All Active Authentication Factors', 'wp-simple-firewall' );
				$desc = [ __( 'When enabled, all multi-factor authentication methods will be applied to a user login. Disable to require only one to login.', 'wp-simple-firewall' ) ];
				break;
			case 'mfa_verify_page':
				$name = __( '2FA Verification Page', 'wp-simple-firewall' );
				$summary = __( 'Type Of 2FA Verification Page', 'wp-simple-firewall' );
				$desc = [
					__( 'Choose the type of page provided to users for MFA verification.', 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ),
						__( 'Choose the Custom Shield page if there are conflicts or issues with the WP Login page for 2FA.', 'wp-simple-firewall' ) ),
				];
				break;
			case 'mfa_user_setup_pages':
				$name = __( '2FA User Config Page', 'wp-simple-firewall' );
				$summary = __( 'Config Pages For User 2FA Setup', 'wp-simple-firewall' );
				$desc = [
					__( 'Specify pages available to users to configure 2FA on their account.', 'wp-simple-firewall' ),
					__( 'At least 1 option must be provided and defaults to the user profile page within the WP admin area.', 'wp-simple-firewall' )
				];
				break;
			case 'mfa_skip':
				$name = __( '2FA Remember Me', 'wp-simple-firewall' );
				$summary = __( 'A User Can Bypass 2FA For The Set Number Of Days', 'wp-simple-firewall' );
				$desc = [ __( 'The number of days a user can bypass 2FA after a successful 2FA. 0 to disable.', 'wp-simple-firewall' ) ];
				break;

			case 'allow_backupcodes':
				$name = __( 'Allow Backup Codes', 'wp-simple-firewall' );
				$summary = __( 'Allow Users To Generate A Backup Code', 'wp-simple-firewall' );
				$desc = [
					__( "Allow users to generate a backup 2FA login code.", 'wp-simple-firewall' ),
					__( "These may be used by the user when they don't have access to their normal 2FA methods.", 'wp-simple-firewall' )
				];
				break;
			case 'enable_google_authenticator':
				$name = __( 'Google Authenticator', 'wp-simple-firewall' );
				$summary = __( 'Allow Users To Use Google Authenticator', 'wp-simple-firewall' );
				$desc = [
					__( 'When enabled, users will have the option to add Google Authenticator to their WordPress user profile', 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ),
						sprintf( __( "Google Authenticator, LastPass Authenticator & Microsoft Authenticator are all supported by this option, but it is referred to as Google Authenticator for simplicity.", 'wp-simple-firewall' ), 'WooCommerce' ) ),
				];
				break;
			case 'enable_email_authentication':
				$name = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Email Authentication', 'wp-simple-firewall' ) );
				$summary = sprintf( __( 'Two-Factor Login Authentication By %s', 'wp-simple-firewall' ), __( 'Email', 'wp-simple-firewall' ) );
				$desc = [ __( 'All users will be required to verify their login by email-based two-factor authentication.', 'wp-simple-firewall' ) ];
				break;
			case 'enable_email_auto_login':
				$name = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Auto-Login Links', 'wp-simple-firewall' ) );
				$summary = __( 'Provide Auto-Login Links For Simple Email 2FA', 'wp-simple-firewall' );
				$desc = [
					__( 'When active, 2FA emails will contain a link that will automatically login the user without the need to enter 2FA Codes.', 'wp-simple-firewall' ),
					__( "Links may only be used once.", 'wp-simple-firewall' ),
					__( "If you believe, for whatever reason, you or your users' mailboxes are compromised, you should disable this option.", 'wp-simple-firewall' ),
				];
				break;
			case 'email_any_user_set':
				$name = __( 'Allow Any User', 'wp-simple-firewall' );
				$summary = __( 'Allow Any User To Turn-On Two-Factor Authentication By Email.', 'wp-simple-firewall' );
				$desc = [ __( 'Any user can turn on two-factor authentication by email from their profile.', 'wp-simple-firewall' ) ];
				break;
			case 'two_factor_auth_user_roles':
				$name = sprintf( '%s - %s', __( 'Enforce', 'wp-simple-firewall' ), __( 'Email Authentication', 'wp-simple-firewall' ) );
				$summary = __( 'All User Roles Subject To Email Authentication', 'wp-simple-firewall' );
				$desc = [
					sprintf( '<strong>%s</strong>: %s', __( 'Important', 'wp-simple-firewall' ), sprintf( __( 'This setting only applies to %s.', 'wp-simple-firewall' ), __( 'Email Authentication', 'wp-simple-firewall' ) ) ),
					__( 'Enforces email-based authentication on all users with the selected roles.', 'wp-simple-firewall' ),
					__( 'If a user has multiple roles assigned to it, all roles will be checked against this list.', 'wp-simple-firewall' ),
					sprintf( '%s:<br /><code>%s</code>', __( 'All User Roles Available On This Site', 'wp-simple-firewall' ),
						\implode( '</code>, <code>', Services::WpUsers()->getAvailableUserRoles() ) )
				];
				break;
			case 'bot_protection_locations':
				$name = __( 'Protected Forms', 'wp-simple-firewall' );
				$summary = __( 'Which WordPress Forms Should Be Protected From Bots', 'wp-simple-firewall' );
				$desc = [
					sprintf( __( '%s will protect WordPress forms from bots by limiting attempts against them using our silentCAPTCH technology.', 'wp-simple-firewall' ), $pluginName ),
					__( 'Choose the forms for which bot protection measures will be deployed.', 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ), sprintf( __( "Use with 3rd party systems such as %s, requires a Pro license.", 'wp-simple-firewall' ), 'WooCommerce' ) ),
				];
				break;
			case 'login_limit_interval':
				$name = __( 'Cooldown Period', 'wp-simple-firewall' );
				$summary = __( 'Limit account access requests to every X seconds', 'wp-simple-firewall' );
				$desc = [
					__( 'WordPress will process only ONE account access attempt per number of seconds specified.', 'wp-simple-firewall' ),
					__( 'Zero (0) turns this off.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $con->opts->optDefault( 'login_limit_interval' ) )
				];
				break;
			case 'enable_user_register_checking':
				$name = __( 'User Registration', 'wp-simple-firewall' );
				$summary = __( 'Apply Brute Force Protection To User Registration And Lost Passwords', 'wp-simple-firewall' );
				$desc = [ __( 'When enabled, settings in this section will also apply to new user registration and users trying to reset passwords.', 'wp-simple-firewall' ) ];
				break;
			case 'enable_passkeys':
				$name = __( 'Allow Passkeys', 'wp-simple-firewall' );
				$summary = __( 'Allow Users To Register Passkeys', 'wp-simple-firewall' );
				$desc = [
					__( 'Allow users to register Passkeys & FIDO2-compatible devices to complete their WordPress login.', 'wp-simple-firewall' ),
				];

				$desc[] = __( 'Passkeys include any FIDO2-compatible devices, such as:', 'wp-simple-firewall' );
				foreach (
					[
						'Windows Hello',
						'Apple Face ID',
						'Apple Touch ID',
						'Compatible fingerprint readers',
						'FIDO2 Yubikeys',
						'FIDO2 Google Titan keys',
						'1Password, BitWarden, etc.',
					] as $type
				) {
					$desc[] = sprintf( '- %s', $type );
				}
				break;
			case 'enable_yubikey':
				$name = __( 'Allow Yubikey OTP', 'wp-simple-firewall' );
				$summary = __( 'Allow Yubikey Registration For One Time Passwords', 'wp-simple-firewall' );
				$desc = [ __( 'Combined with your Yubikey API details this will form the basis of your Yubikey Authentication', 'wp-simple-firewall' ) ];
				break;
			case 'yubikey_app_id':
				$name = __( 'Yubikey App ID', 'wp-simple-firewall' );
				$summary = __( 'Your Unique Yubikey App ID', 'wp-simple-firewall' );
				$desc = [
					__( 'Combined with your Yubikey API Key this will form the basis of your Yubikey Authentication', 'wp-simple-firewall' ),
					__( 'Please review the info link on how to obtain your own Yubikey App ID and API Key.', 'wp-simple-firewall' )
				];
				break;
			case 'yubikey_api_key':
				$name = __( 'Yubikey API Key', 'wp-simple-firewall' );
				$summary = __( 'Your Unique Yubikey App API Key', 'wp-simple-firewall' );
				$desc = [
					__( 'Combined with your Yubikey App ID this will form the basis of your Yubikey Authentication.', 'wp-simple-firewall' ),
					__( 'Please review the info link on how to get your own Yubikey App ID and API Key.', 'wp-simple-firewall' )
				];
				break;
			case 'yubikey_unique_keys':
				$name = __( 'Yubikey Unique Keys', 'wp-simple-firewall' );
				$summary = __( 'This method for Yubikeys is no longer supported. Please see your user profile', 'wp-simple-firewall' );
				$desc = [
					sprintf( '<strong>%s: %s</strong>', __( 'Format', 'wp-simple-firewall' ), 'Username,Yubikey' ),
					__( 'Provide Username<->Yubikey Pairs that are usable for this site.', 'wp-simple-firewall' ),
					__( 'If a Username is not assigned a Yubikey, Yubikey Authentication is OFF for that user.', 'wp-simple-firewall' ),
					__( 'Each [Username,Key] pair should be separated by a new line: you only need to provide the first 12 characters of the yubikey.', 'wp-simple-firewall' ),
				];
				break;

			case 'global_enable_plugin_features':
				$name = sprintf( __( 'Enable %s Protection', 'wp-simple-firewall' ), $pluginName );
				$summary = __( 'Switch Off To Disable All Security Protection', 'wp-simple-firewall' );
				$desc = [
					sprintf( __( "You can keep the security plugin activated, but temporarily disable all protection it provides.", 'wp-simple-firewall' ), $pluginName ),
					sprintf( '<a href="%s">%s</a>',
						$con->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_DEBUG ),
						'Launch Debug Info Page'
					)
				];
				break;

			case 'enable_tracking':
				$name = __( 'Anonymous Usage Statistics', 'wp-simple-firewall' );
				$summary = __( 'Permit Anonymous Telemetry Reports', 'wp-simple-firewall' );
				$desc = [
					__( 'Allows us to gather information on statistics and features in-use across our client installations.', 'wp-simple-firewall' )
					.' '.__( 'This information is strictly anonymous and contains no personally, or otherwise, identifiable data.', 'wp-simple-firewall' ),
					sprintf( '<a href="%s" target="_blank">%s</a>',
						self::con()->plugin_urls->noncedPluginAction( PluginDumpTelemetry::class ),
						__( 'Click to see the exact data that would be sent.', 'wp-simple-firewall' )
					)
				];
				break;

			case 'enable_beta':
				$name = __( 'Beta Access', 'wp-simple-firewall' );
				$summary = __( 'Enable Access To Beta Versions', 'wp-simple-firewall' );
				$desc = [
					__( 'Enable this option to allow shield to upgrade to beta and pre-release versions.', 'wp-simple-firewall' ),
					__( "Please only enable this on non-critical sites, and if you're comfortable with bugs arising.", 'wp-simple-firewall' ),
				];
				break;

			case 'visitor_address_source':
				$name = __( 'IP Source', 'wp-simple-firewall' );
				$summary = __( 'Which IP Address Is Yours', 'wp-simple-firewall' );
				$desc = [
					\implode( ' ', [
						__( "It's crucial that we can detect the correct IP address for each visitor to the site.", 'wp-simple-firewall' ),
						__( "We rely on the PHP server configuration, but some hosts aren't correctly setup to let us find it easily.", 'wp-simple-firewall' ),
						sprintf( __( "The preferred source is %s since this can't be spoofed.", 'wp-simple-firewall' ),
							sprintf( '<code>%s</code>', 'REMOTE_ADDR' ) )
					] ),
					\implode( ' ', [
						__( "You can help us detect the best IP address for your server by using the link below to tell you your current IP address and then select the option from the list that contains it.", 'wp-simple-firewall' ),
						sprintf(
							'<p class="mt-2 text-center"><a href="%s" class="btn btn-secondary btn-sm" target="_blank">%s</a></p>',
							'https://clk.shldscrty.com/shieldwhatismyip',
							__( 'What Is My IP Address?', 'wp-simple-firewall' )
						),
						sprintf( __( "If the correct setting is not %s, we recommend contacting your hosting provider to request that they configure your hosting so that %s provides the actual visitor IP address.", 'wp-simple-firewall' ),
							sprintf( '<code>%s</code>', 'REMOTE_ADDR' ), sprintf( '<code>%s</code>', 'REMOTE_ADDR' ) )
					] ),
				];
				break;

			case 'block_send_email_address':
				$name = __( 'Report Email', 'wp-simple-firewall' );
				$summary = __( 'Email For All Reports and Plugin Notifications', 'wp-simple-firewall' );
				$desc = [
					__( "This lets you customise the default email address for all emails sent by the plugin.", 'wp-simple-firewall' ),
					sprintf( __( "The plugin defaults to the site administration email address, which is: %s", 'wp-simple-firewall' ),
						sprintf( '<a href="%s" target="_blank" title="%s"><code>'.get_bloginfo( 'admin_email' ).'</code></a>',
							Services::WpGeneral()->getAdminUrl( 'options-general.php' ),
							__( 'Review site settings', 'wp-simple-firewall' ) )
					)
				];
				break;

			case 'enable_upgrade_admin_notice':
				$name = __( 'In-Plugin Notices', 'wp-simple-firewall' );
				$summary = __( 'Display Non-Essential Plugin Notices And Admin Bar Menu', 'wp-simple-firewall' );
				$desc = [
					__( 'By default Shield displays non-essential notices in the admin area and admin bar.', 'wp-simple-firewall' ),
					__( 'These notices can be hidden by switching off this option.', 'wp-simple-firewall' ),
				];
				break;

			case 'display_plugin_badge':
				$name = __( 'Show Plugin Badge', 'wp-simple-firewall' );
				$summary = __( 'Display Plugin Security Badge To Your Visitors', 'wp-simple-firewall' );
				$desc = [
					__( 'Enabling this option helps support the plugin by spreading the word about it on your website.', 'wp-simple-firewall' )
					.' '.__( 'The plugin badge also lets visitors know your are taking your website security seriously.', 'wp-simple-firewall' ),
					__( "This also acts as an affiliate link if you're running ShieldPRO so you can earn rewards for each referral.", 'wp-simple-firewall' ),
					sprintf( '<strong><a href="%s" target="_blank">%s</a></strong>', 'https://clk.shldscrty.com/wpsf20', __( 'Read this carefully before enabling this option.', 'wp-simple-firewall' ) ),
				];
				break;
			case 'delete_on_deactivate':
				$name = __( 'Delete Plugin Settings', 'wp-simple-firewall' );
				$summary = __( 'Delete All Plugin Settings Upon Plugin Deactivation', 'wp-simple-firewall' );
				$desc = [ __( 'Careful: Removes all plugin options when you deactivate the plugin', 'wp-simple-firewall' ) ];
				break;

			case 'preferred_temp_dir':
				$name = __( 'Temp Dir', 'wp-simple-firewall' );
				$summary = __( 'Preferred Temporary Directory', 'wp-simple-firewall' );
				$tmpDir = $con->cache_dir_handler->dir();
				if ( empty( $tmpDir ) ) {
					$desc = [
						sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ),
							sprintf( __( "%s currently can't locate a temporary directory, so you'll need to provide one here.", 'wp-simple-firewall' ), $pluginName ) ),
						sprintf( __( '%s needs to store data temporarily to disk.', 'wp-simple-firewall' ), $pluginName )
						.' '.__( "It'll find a suitable location automatically, but if this fails, you may see PHP warnings on your site and certain Shield functionality won't be available.", 'wp-simple-firewall' ),
						sprintf( __( "If you use the %s directive, this automatic process may fail and you'll need to use this option to specify a directory that WordPress can write to.", 'wp-simple-firewall' ),
							sprintf( '<code>%s</code>', 'open_basedir' ) ),
						sprintf( '<code>%s</code>: <code>%s</code>', __( 'ABSPATH', 'wp-simple-firewall' ), ABSPATH ),
					];
				}
				else {
					$desc = [
						sprintf( __( '%s needs to store data temporarily to disk.', 'wp-simple-firewall' ), $pluginName )
						.' '.__( "It'll find a suitable location automatically, but if this fails, you may see PHP warnings on your site and certain Shield functionality won't be available.", 'wp-simple-firewall' ),
						sprintf( __( "%s has successfully chosen the following location to create its temporary directory: %s", 'wp-simple-firewall' ), $pluginName,
							sprintf( '<code>%s</code>', \dirname( $tmpDir ) ) ),
						sprintf( '%s - %s', __( 'Important', 'wp-simple-firewall' ), __( 'You should only provide a value for this configuration option if you experience any trouble.', 'wp-simple-firewall' ) ),
					];
				}
				break;

			case 'importexport_enable':
				$name = __( 'Automatic Import/Export', 'wp-simple-firewall' );
				$summary = __( 'Allow Automated Import And Export Of Options On This Site', 'wp-simple-firewall' );
				$desc = [
					__( 'Enable this option to allow automatic import and export of options between WordPress sites.', 'wp-simple-firewall' ),
				];
				if ( !$con->caps->canImportExportSync() ) {
					$desc[] = sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'You will need to upgrade your plan to use the Automatic Import/Export feature.', 'wp-simple-firewall' ) );
				}
				break;

			case 'importexport_whitelist':
				$name = __( 'Export Whitelist', 'wp-simple-firewall' );
				$summary = __( 'Whitelisted Sites To Export Options From This Site', 'wp-simple-firewall' );
				$desc = [
					__( 'Whitelisted sites may export options from this site without the key.', 'wp-simple-firewall' ),
					__( 'List each site URL on a new line.', 'wp-simple-firewall' ),
					__( 'This is to be used in conjunction with the Master Import Site feature.', 'wp-simple-firewall' )
				];
				break;

			case 'importexport_masterurl':
				$name = __( 'Master Import Site', 'wp-simple-firewall' );
				$summary = __( 'Automatically Import Options From This Site URL', 'wp-simple-firewall' );
				$desc = [
					__( "Supplying a site URL here will make this site an 'Options Slave'.", 'wp-simple-firewall' ),
					__( 'Options will be automatically exported from the Master site each day.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Use of this feature will overwrite existing options and replace them with those from the Master Import Site.', 'wp-simple-firewall' ) )
				];
				break;

			case 'importexport_whitelist_notify':
				$name = __( 'Notify Whitelist', 'wp-simple-firewall' );
				$summary = __( 'Notify Sites On The Whitelist To Update Options From Master', 'wp-simple-firewall' );
				$desc = [ __( "When enabled, manual options saving will notify sites on the whitelist to export options from the Master site.", 'wp-simple-firewall' ) ];
				break;

			case 'importexport_secretkey':
				$name = __( 'Secret Key', 'wp-simple-firewall' );
				$summary = __( 'Import/Export Secret Key', 'wp-simple-firewall' );
				$desc = [
					__( 'Keep this Secret Key private as it will allow export of options from your site.', 'wp-simple-firewall' ),
					sprintf( '%s: %s %s',
						__( 'Note', 'wp-simple-firewall' ),
						__( 'This key is automatically regenerated every 24hrs.', 'wp-simple-firewall' ),
						sprintf( 'Key set to expire: %s', Services::Request()
																  ->carbon()
																  ->timestamp( $con->opts->optGet( 'importexport_secretkey_expires_at' ) )
																  ->diffForHumans() )
					)
				];
				break;

			case 'frequency_alert':
				$name = __( 'Alert Reports Frequency', 'wp-simple-firewall' );
				$summary = __( 'How Often Should You Be Sent Reports With Important Alerts', 'wp-simple-firewall' );
				$desc = [
					__( 'Choose when you should be sent reports containing important critical alerts about your site security.', 'wp-simple-firewall' ),
					__( 'Critical alerts are typically results from your most recent site scans.', 'wp-simple-firewall' )
				];
				break;

			case 'frequency_info':
				$name = __( 'Info Reports Frequency', 'wp-simple-firewall' );
				$summary = __( 'How Often Informational Reports Will Be Sent To You', 'wp-simple-firewall' );
				$desc = [
					__( 'Choose when you should be sent non-critical information and reports about your site security.', 'wp-simple-firewall' ),
					__( 'Information and reports are typically statistics.', 'wp-simple-firewall' )
				];
				break;

			case 'instant_alert_admins':
				$name = __( 'Admin Accounts', 'wp-simple-firewall' );
				$summary = __( 'Be alerted to important changes on any admin account', 'wp-simple-firewall' );
				$desc = [
					__( "Be alerted to any important change on any admin account.", 'wp-simple-firewall' ),
					__( "Using Shield's exclusive Snapshot technology, changes to admins that are made directly on the WP database will also be detected!", 'wp-simple-firewall' ),
				];
				break;

			case 'instant_alert_shield_deactivated':
				$name = __( 'Shield Deactivated', 'wp-simple-firewall' );
				$summary = __( 'Receive an alert upon plugin deactivation', 'wp-simple-firewall' );
				$desc = [
					__( "Be alerted to any important change on any admin account.", 'wp-simple-firewall' ),
					__( "Be alerted when the Shield plugin is deactivated", 'wp-simple-firewall' ),
				];
				break;
			case 'instant_alert_vulnerabilities':
				$name = __( 'Vulnerabilities', 'wp-simple-firewall' );
				$summary = __( 'Be alerted to discovery of any vulnerable plugin/theme', 'wp-simple-firewall' );
				$desc = [
					__( "Be alerted to discovery of any vulnerable plugin/theme.", 'wp-simple-firewall' ),
					__( "Vulnerability scanning must be active to enable this option.", 'wp-simple-firewall' ),
				];
				break;
			case 'instant_alert_filelocker':
				$name = __( 'FileLocker Changes', 'wp-simple-firewall' );
				$summary = __( 'Be alerted to any changes to FileLocker items', 'wp-simple-firewall' );
				$desc = [
					__( "Be alerted to any changes to FileLocker items.", 'wp-simple-firewall' ),
					__( "FileLocker must be active to enable this option.", 'wp-simple-firewall' ),
				];
				break;

			case 'enable_admin_access_restriction':
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), __( 'Security Admin', 'wp-simple-firewall' ) );
				$summary = __( 'Enforce Security Admin Access Restriction', 'wp-simple-firewall' );
				$desc = [ __( "Enable this with great care and consideration. Ensure that you set an Security PIN that you'll remember.", 'wp-simple-firewall' ) ];
				break;
			case 'admin_access_key':
				$name = __( 'Security Admin PIN', 'wp-simple-firewall' );
				$summary = __( 'Provide/Update Security Admin PIN', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'If you forget this, you could potentially lock yourself out from using this plugin.', 'wp-simple-firewall' ) ),
					'<strong>'.( empty( $opts->getSecAdminPIN() ) ? __( 'Security PIN NOT Currently Set', 'wp-simple-firewall' ) : __( 'Security PIN Currently Set', 'wp-simple-firewall' ) ).'</strong>',
				];
				break;
			case 'sec_admin_users':
				$name = __( 'Security Admins', 'wp-simple-firewall' );
				$summary = __( 'Persistent Security Admins', 'wp-simple-firewall' );
				$desc = [
					__( "Users provided will be security admins automatically, without needing the security PIN.", 'wp-simple-firewall' ),
					__( 'Enter admin username, email or ID.', 'wp-simple-firewall' ).' '.__( '1 entry per-line.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'Verified users will be converted to usernames.', 'wp-simple-firewall' ) )
				];
				break;
			case 'admin_access_timeout':
				$name = __( 'Security Admin Timeout', 'wp-simple-firewall' );
				$summary = __( 'Specify An Automatic Timeout Interval For Security Admin Access', 'wp-simple-firewall' );
				$desc = [
					__( 'This will automatically expire your Security Admin Session.', 'wp-simple-firewall' ),
					sprintf(
						'%s: %s',
						__( 'Default', 'wp-simple-firewall' ),
						sprintf( '%s minutes', $con->opts->optDefault( 'admin_access_timeout' ) )
					)
				];
				break;
			case 'allow_email_override':
				$name = __( 'Allow Email Override', 'wp-simple-firewall' );
				$summary = __( 'Allow Email Override Of Admin Access Restrictions', 'wp-simple-firewall' );
				$desc = [
					__( 'Allow the use of verification emails to override and switch off the Security Admin restrictions.', 'wp-simple-firewall' ),
					sprintf( __( "The email address specified in %s's General settings will be used.", 'wp-simple-firewall' ), $pluginName )
				];
				break;
			case 'enable_mu':
				$name = __( 'Run In MU Mode', 'wp-simple-firewall' );
				$summary = __( 'Run Plugin In Must-Use (MU) Mode', 'wp-simple-firewall' );
				$desc = [
					__( "Setup the plugin to run as an MU-plugin to prevent accidental deactivation.", 'wp-simple-firewall' ),
					__( "You should fully understand the implications of this, including the inability to deactivate the plugin from the WordPress dashboard while this setting is active.", 'wp-simple-firewall' ),
					sprintf( '<strong>%s</strong>: %s', __( 'Important', 'wp-simple-firewall' ),
						sprintf( __( 'WordPress must be at least version %s to activate this option.', 'wp-simple-firewall' ), '<code>5.6</code>' ) ),
				];
				break;
			case 'admin_access_restrict_posts':
				$name = __( 'Pages', 'wp-simple-firewall' );
				$summary = __( 'Restrict Access To Key WordPress Posts And Pages Actions', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict access to page/post creation, editing and deletion.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), sprintf( __( 'Selecting "%s" will also restrict all other options.', 'wp-simple-firewall' ), __( 'Edit', 'wp-simple-firewall' ) ) )
				];
				break;
			case 'admin_access_restrict_plugins':
				$name = __( 'Plugins', 'wp-simple-firewall' );
				$summary = __( 'Restrict Access To Key WordPress Plugin Actions', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict access to plugin installation, update, activation and deletion.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), sprintf( __( 'Selecting "%s" will also restrict all other options.', 'wp-simple-firewall' ), __( 'Activate', 'wp-simple-firewall' ) ) )
				];
				break;
			case 'admin_access_restrict_options':
				$name = __( 'WordPress Options', 'wp-simple-firewall' );
				$summary = __( 'Restrict Access To Certain WordPress Admin Options', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict the ability of WordPress administrators from changing key WordPress settings.', 'wp-simple-firewall' ) ),
					__( 'The following options will be restricted:', 'wp-simple-firewall' ),
					sprintf( '<ul style="list-style-type: square"><li>%s</li></ul>', \implode( '</li><li>', [
						sprintf( '%s %s', __( 'New User Default Role' ), '<span class="badge bg-success">new</span>' ),
						sprintf( '%s %s', __( 'Permalink structure' ), '<span class="badge bg-success">new</span>' ),
						__( 'Site Title' ),
						__( 'Tagline' ),
						__( 'WordPress Address (URL)' ),
						__( 'Site Address (URL)' ),
						__( 'Administration Email Address' ),
						sprintf( '%s (%s)', __( 'Membership' ), __( 'Anyone can register' ) ),
						__( 'Email notifications for new comments', 'wp-simple-firewall' ),
						__( 'Comments must be manually approved' ),
						__( 'Search engine visibility' ),
					] ) )
				];
				break;
			case 'admin_access_restrict_admin_users':
				$name = __( 'Admin Users', 'wp-simple-firewall' );
				$summary = __( 'Restrict Access To Create/Delete/Modify Other Admin Users', 'wp-simple-firewall' );
				$desc = [ sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict the ability of WordPress administrators from creating, modifying or promoting other administrators.', 'wp-simple-firewall' ) ) ];
				break;
			case 'admin_access_restrict_themes':
				$name = __( 'Themes', 'wp-simple-firewall' );
				$summary = __( 'Restrict Access To WordPress Theme Actions', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ),
						__( 'This will restrict access to theme installation, update, activation and deletion.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ),
						sprintf(
							__( 'Selecting "%s" will also restrict all other options.', 'wp-simple-firewall' ),
							sprintf(
								__( '%s and %s', 'wp-simple-firewall' ),
								__( 'Activate', 'wp-simple-firewall' ),
								__( 'Edit Theme Options', 'wp-simple-firewall' )
							)
						)
					)
				];
				break;
			case 'whitelabel_enable':
				$name = sprintf( '%s: %s', __( 'Enable', 'wp-simple-firewall' ), __( 'White Label', 'wp-simple-firewall' ) );
				$summary = __( 'Activate Your White Label Settings', 'wp-simple-firewall' );
				$desc = [ __( 'Turn your White Label settings on/off.', 'wp-simple-firewall' ) ];
				break;
			case 'wl_pluginnamemain':
				$name = __( 'Plugin Name', 'wp-simple-firewall' );
				$summary = __( 'The Name Of The Plugin', 'wp-simple-firewall' );
				$desc = [ __( 'The name of the plugin that will be displayed to your site users.', 'wp-simple-firewall' ) ];
				break;
			case 'wl_namemenu':
				$name = __( 'Menu Title', 'wp-simple-firewall' );
				$summary = __( 'The Main Menu Title Of The Plugin', 'wp-simple-firewall' );
				$desc = [ sprintf( __( 'The Main Menu Title Of The Plugin. If left empty, the "%s" will be used.', 'wp-simple-firewall' ), __( 'Plugin Name', 'wp-simple-firewall' ) ) ];
				break;
			case 'wl_companyname':
				$name = __( 'Company Name', 'wp-simple-firewall' );
				$summary = __( 'The Name Of Your Company', 'wp-simple-firewall' );
				$desc = [ __( 'Provide the name of your company.', 'wp-simple-firewall' ) ];
				break;
			case 'wl_description':
				$name = __( 'Description', 'wp-simple-firewall' );
				$summary = __( 'The Description Of The Plugin', 'wp-simple-firewall' );
				$desc = [ __( 'The description of the plugin displayed on the plugins page.', 'wp-simple-firewall' ) ];
				break;
			case 'wl_homeurl':
				$name = __( 'Home URL', 'wp-simple-firewall' );
				$summary = __( 'Plugin Home Page URL', 'wp-simple-firewall' );
				$desc = [ __( "When a user clicks the home link for this plugin, this is where they'll be directed.", 'wp-simple-firewall' ) ];
				break;
			case 'wl_menuiconurl':
				$name = __( 'Menu Icon', 'wp-simple-firewall' );
				$summary = __( 'Menu Icon URL', 'wp-simple-firewall' );
				$desc = [
					__( 'The URL of the icon to display in the menu.', 'wp-simple-firewall' ),
					sprintf( __( 'The %s should measure %s.', 'wp-simple-firewall' ), __( 'icon', 'wp-simple-firewall' ), '16px x 16px' )
				];
				break;
			case 'wl_dashboardlogourl':
				$name = __( 'Plugin Badge Logo', 'wp-simple-firewall' );
				$summary = __( 'Plugin Badge Logo URL', 'wp-simple-firewall' );
				$desc = [
					__( 'The URL of the logo to display in the plugin badge.', 'wp-simple-firewall' ),
					sprintf( __( 'The %s should measure %s.', 'wp-simple-firewall' ), __( 'logo', 'wp-simple-firewall' ), '128px x 128px' )
				];
				break;
			case 'wl_login2fa_logourl':
				$name = __( 'Dashboard and 2FA Login Logo URL', 'wp-simple-firewall' );
				$summary = __( 'Dashboard and 2FA Login Logo URL', 'wp-simple-firewall' );
				$desc = [ __( 'The URL of the logo to display on the Dashboard and the Two-Factor Authentication login page.', 'wp-simple-firewall' ) ];
				break;
			case 'enable_logger':
				$name = __( 'Enable Request Logging', 'wp-simple-firewall' );
				$summary = __( 'Log Requests To Your WordPress Site', 'wp-simple-firewall' );
				$desc = [ __( 'Monitor web requests sent to your WordPress site.', 'wp-simple-firewall' ) ];
				break;
			case 'type_exclusions':
				$name = __( 'Request Log Exclusions', 'wp-simple-firewall' );
				$summary = __( 'Select Which Types Of Requests To Exclude', 'wp-simple-firewall' );
				$desc = [
					__( "There's no need to have unnecessary traffic noise in your logs, so we automatically exclude certain types of requests.", 'wp-simple-firewall' ),
					__( "Select request types that you don't want to appear in the traffic viewer.", 'wp-simple-firewall' ),
					__( 'If a request matches any exclusion rule, it wont show in the traffic logs.', 'wp-simple-firewall' )
				];
				break;
			case 'enable_live_log':
				$max = \round( $opts->getTrafficLiveLogDuration()/\MINUTE_IN_SECONDS );

				$name = __( 'Live Traffic', 'wp-simple-firewall' );
				$summary = __( 'Temporarily Log All Traffic', 'wp-simple-firewall' );
				$desc = [
					__( "Requires standard traffic logging to be switched-on and logs all requests to the site (nothing is excluded).", 'wp-simple-firewall' ),
					__( "For high-traffic sites, this option can cause your database to become quite large and isn't recommend unless required.", 'wp-simple-firewall' ),
					sprintf( __( 'This setting will automatically be disabled after %s and all requests logged during that period that would normally have been excluded will also be deleted.', 'wp-simple-firewall' ),
						sprintf( _n( '%s minute', '%s minutes', $max ), $max ) ),
					sprintf( '<a href="%s">%s &rarr;</a>',
						$con->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE ),
						__( 'Live Logs Viewer', 'wp-simple-firewall' )
					),

				];

				$remaining = $opts->getTrafficLiveLogTimeRemaining();
				if ( $remaining > 0 ) {
					$desc[] = sprintf(
						__( 'Live logging will be automatically disabled: %s', 'wp-simple-firewall' ),
						sprintf( '<code>%s</code>', Services::Request()
															->carbon()
															->addSeconds( $remaining )
															->diffForHumans()
						)
					);
				}
				break;
			case 'custom_exclusions':
				$name = __( 'Custom Exclusions', 'wp-simple-firewall' );
				$summary = __( 'Provide Custom Traffic Exclusions', 'wp-simple-firewall' );
				$desc = [
					__( "For each entry, if the text is present in either the User Agent or request Path, it will be excluded.", 'wp-simple-firewall' ),
					__( 'Take a new line for each entry.', 'wp-simple-firewall' ),
					__( 'Comparisons are case-insensitive.', 'wp-simple-firewall' )
				];
				break;
			case 'auto_clean':
				$name = __( 'Log Retention', 'wp-simple-firewall' );
				$summary = __( 'Traffic Log Retention Policy (Days)', 'wp-simple-firewall' );
				$desc = [
					__( 'Traffic logs older than this maximum number of days will be automatically deleted.', 'wp-simple-firewall' ),
					sprintf( '%s: %s',
						__( 'Note', 'wp-simple-firewall' ),
						__( 'Activity logs depend on these traffic logs so if they have a longer retention period, some traffic logs will be retained longer.', 'wp-simple-firewall' )
					),
				];
				if ( !$con->caps->hasCap( 'logs_retention_unlimited' ) ) {
					$desc[] = sprintf(
						__( 'The maximum log retention limit (%s) may be increased by upgrading your ShieldPRO plan.', 'wp-simple-firewall' ),
						$con->caps->getMaxLogRetentionDays()
					);
				}
				break;
			case 'enable_limiter':
				$name = __( 'Enable Rate Limiting', 'wp-simple-firewall' );
				$summary = __( 'Turn On The Rate Limiting Feature', 'wp-simple-firewall' );
				$desc = [
					__( 'Limit requests to your site based on the your rate-limiting settings.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'Enabling this option automatically switches-on request logging.', 'wp-simple-firewall' ) ),
				];
				break;
			case 'limit_requests':
				$name = __( 'Max Request Limit', 'wp-simple-firewall' );
				$summary = __( 'Maximum Number Of Requests Allowed In Time Limit', 'wp-simple-firewall' );
				$desc = [
					__( 'The maximum number of requests that are allowed within the given request time limit.', 'wp-simple-firewall' ),
					__( 'Any visitor that exceeds this number of requests in the given time period will register an offense against their IP address.', 'wp-simple-firewall' ),
					__( 'Enough offenses will result in a ban of the IP address.', 'wp-simple-firewall' ),
					__( 'Use a larger maximum request limit to reduce the risk of blocking legitimate visitors.', 'wp-simple-firewall' )
				];
				break;
			case 'limit_time_span':
				$name = __( 'Request Limit Time Interval', 'wp-simple-firewall' );
				$summary = __( 'The Time Interval To Test For Excessive Requests', 'wp-simple-firewall' );
				$desc = [
					__( 'The time period within which to monitor for multiple requests that exceed the max request limit.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'Interval is measured in seconds.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Example', 'wp-simple-firewall' ),
						sprintf( __( 'Use %s to test for excessive requests within a %s minutes interval.', 'wp-simple-firewall' ), '<code>300</code>', 5 ) ),
					sprintf( '%s: %s', __( 'Example', 'wp-simple-firewall' ),
						sprintf( __( 'Use %s to test for excessive requests within a %s minutes interval.', 'wp-simple-firewall' ), '<code>3600</code>', 60 ) ),
					__( 'Use a smaller interval to reduce the risk of blocking legitimate visitors.', 'wp-simple-firewall' )
				];
				break;

			case 'enable_user_management':
				$modName = $modStrings->getFor( EnumModules::USERS )[ 'name' ];
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$desc = [ sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName ) ];
				break;
			case 'enable_admin_login_email_notification':
				$name = __( 'Admin Login Notification Email', 'wp-simple-firewall' );
				$summary = __( 'Send An Notification Email When Administrator Logs In', 'wp-simple-firewall' );
				$desc = [
					__( 'If you would like to be notified every time an administrator user logs into this WordPress site, enter a notification email address.', 'wp-simple-firewall' ),
					__( 'No email address - No Notification.', 'wp-simple-firewall' ),
				];
				$desc[] = self::con()->isPremiumActive() ?
					__( 'Multiple email addresses may be supplied, separated by a comma.', 'wp-simple-firewall' ) :
					__( 'Please upgrade your plan if you need to notify multiple email addresses.', 'wp-simple-firewall' );
				break;
			case 'enable_user_login_email_notification':
				$name = __( 'User Login Notification Email', 'wp-simple-firewall' );
				$summary = __( 'Send Email Notification To Each User Upon Successful Login', 'wp-simple-firewall' );
				$desc = [ __( 'A notification is sent to each user when a successful login occurs for their account.', 'wp-simple-firewall' ) ];
				break;
			case 'session_timeout_interval':
				$name = __( 'Session Lifetime Override', 'wp-simple-firewall' );
				$summary = __( 'Override Maximum Session Lifetime', 'wp-simple-firewall' );
				$desc = [
					__( 'WordPress default is 2 days, or 14 days when you check the "Remember Me" box.', 'wp-simple-firewall' ),
					__( 'Think of this as an absolute maximum possible session length.', 'wp-simple-firewall' ),
					sprintf( __( 'This cannot be less than %s.', 'wp-simple-firewall' ), '<strong>1</strong>' ),
					sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), '<strong>'.$con->opts->optDefault( 'session_timeout_interval' ).'</strong>' )
				];
				break;
			case 'session_idle_timeout_interval':
				$name = __( 'Idle Timeout', 'wp-simple-firewall' );
				$summary = __( 'Specify How Many Hours After Inactivity To Automatically Logout User', 'wp-simple-firewall' );
				$desc = [
					__( 'If the user is inactive for the number of hours specified, they will be forcefully logged out next time they return.', 'wp-simple-firewall' ),
					sprintf( __( 'Set to %s to turn off this option.', 'wp-simple-firewall' ), '"<strong>0</strong>"' )
				];
				break;
			case 'session_lock':
				$name = __( 'User Session Lock', 'wp-simple-firewall' );
				$summary = __( 'Locks A User Session To Prevent Theft', 'wp-simple-firewall' );
				$desc = [
					__( 'Protects against user compromise by preventing user session theft/hijacking.', 'wp-simple-firewall' ),
				];
				break;
			case 'reg_email_validate':
				$name = __( 'Validate Email Addresses', 'wp-simple-firewall' );
				$summary = __( 'Validate Email Addresses When User Attempts To Register', 'wp-simple-firewall' );
				$desc = [
					__( 'Validate Email Addresses When User Attempts To Register.', 'wp-simple-firewall' ),
					__( 'To validate an email your site sends a request to the ShieldNET API and may cause a small delay during the user registration request.', 'wp-simple-firewall' ),
				];
				break;
			case 'email_checks':
				$name = __( 'Email Validation Checks', 'wp-simple-firewall' );
				$summary = __( 'The Email Address Properties That Will Be Tested', 'wp-simple-firewall' );
				$desc = [ __( 'Select the properties that should be tested during email address validation.', 'wp-simple-firewall' ) ];
				break;
			case 'enable_password_policies':
				$name = __( 'Enforce Password Policies', 'wp-simple-firewall' );
				$summary = __( 'Enforce Any Configured Password Policies', 'wp-simple-firewall' );
				$desc = [ __( 'Apply any configured password policies.', 'wp-simple-firewall' ) ];
				break;
			case 'pass_prevent_pwned':
				$name = __( 'Prevent Pwned Passwords', 'wp-simple-firewall' );
				$summary = __( 'Prevent Use Of "Pwned" Passwords', 'wp-simple-firewall' );
				$desc = [ __( 'Prevents users from using any passwords found on the public available list of "pwned" passwords.', 'wp-simple-firewall' ) ];
				break;
			case 'pass_min_strength':
				$name = __( 'Minimum Strength', 'wp-simple-firewall' );
				$summary = __( 'Minimum Password Strength', 'wp-simple-firewall' );
				$desc = [ __( 'All passwords that a user sets must meet this minimum strength.', 'wp-simple-firewall' ) ];
				break;
			case 'pass_force_existing':
				$name = __( 'Apply To Existing Users', 'wp-simple-firewall' );
				$summary = __( 'Apply Password Policies To Existing Users and Their Passwords', 'wp-simple-firewall' );
				$desc = [
					__( "Forces existing users to update their passwords if they don't meet requirements, after they next login.", 'wp-simple-firewall' ),
					__( 'Note: You may want to warn users prior to enabling this option.', 'wp-simple-firewall' )
				];
				break;
			case 'pass_expire':
				$name = __( 'Password Expiration', 'wp-simple-firewall' );
				$summary = __( 'Passwords Expire After This Many Days', 'wp-simple-firewall' );
				$desc = [
					__( 'Users will be forced to reset their passwords after the number of days specified.', 'wp-simple-firewall' ),
					__( 'Set to Zero(0) to disable.', 'wp-simple-firewall' )
				];
				break;
			case 'manual_suspend':
				$name = __( 'Allow Manual User Suspension', 'wp-simple-firewall' );
				$summary = __( 'Manually Suspend User Accounts To Prevent Login', 'wp-simple-firewall' );
				$desc = [ __( 'Users may be suspended by administrators to prevent future login.', 'wp-simple-firewall' ) ];
				break;
			case 'auto_password':
				$name = __( 'Auto-Suspend Expired Passwords', 'wp-simple-firewall' );
				$summary = __( 'Automatically Suspend Users With Expired Passwords', 'wp-simple-firewall' );
				$desc = [
					__( 'Automatically suspends login by users and requires password reset to unsuspend.', 'wp-simple-firewall' ),
					sprintf(
						'<strong>%s</strong> - %s',
						__( 'Important', 'wp-simple-firewall' ),
						__( 'Requires password expiration policy to be set.', 'wp-simple-firewall' )
					)
				];
				break;
			case 'auto_idle_days':
				$name = __( 'Auto-Suspend Idle Users', 'wp-simple-firewall' );
				$summary = __( 'Automatically Suspend Idle User Accounts', 'wp-simple-firewall' );
				$desc = [
					__( 'Automatically suspends login for idle accounts and requires password reset to unsuspend.', 'wp-simple-firewall' ),
					__( 'Specify the number of days since last login to consider a user as idle.', 'wp-simple-firewall' ),
					__( 'Set to Zero(0) to disable.', 'wp-simple-firewall' )
				];
				break;
			case 'auto_idle_roles':
				$name = __( 'Auto-Suspend Idle User Roles', 'wp-simple-firewall' );
				$summary = __( 'Apply Automatic Suspension To Accounts With These Roles', 'wp-simple-firewall' );
				$desc = [
					__( 'Automatic suspension for idle accounts applies only to the roles you specify.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'Take a new line for each user role.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Available Roles', 'wp-simple-firewall' ),
						\implode( ', ', Services::WpUsers()->getAvailableUserRoles() ) ),
					sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), \implode( ', ', $con->opts->optDefault( 'auto_idle_roles' ) ) )
				];
				break;

			default:
				$def = $con->opts->optDef( $key );
				$name = __( $def[ 'name' ] ?? '', 'wp-simple-firewall' );
				$summary = __( $def[ 'summary' ] ?? '', 'wp-simple-firewall' );
				$desc = $def[ 'description' ] ?? [];
				break;
		}

		return [
			'name'        => $name,
			'summary'     => $summary,
			'description' => $desc,
		];
	}

	private function getNoteForBots() :string {
		return sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ),
			__( "You'll need to upgrade your plan to trigger offenses for these events.", 'wp-simple-firewall' ) );
	}
}