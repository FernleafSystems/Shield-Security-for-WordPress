<?php
include_once( 'icwp-wpsf-config_header.php' );
?>
<div class="wrap">
	<div class="bootstrap-wpadmin">
		<?php echo printOptionsPageHeader( 'Dashboard' ); ?>

		<?php if ( $icwp_fShowAds ) : ?>
			<div class="row" id="worpit_promo">
				<div class="span12">
					<?php echo getWidgetIframeHtml( 'dashboard-widget-worpit-wtb' ); ?>
				</div>
			</div><!-- / row -->

		<?php endif; ?>

		<div class="row">
			<div class="<?php echo $icwp_fShowAds? 'span9' : 'span12'; ?>">
				<form action="<?php echo $icwp_form_action; ?>" method="post" class="form-horizontal">
				<?php
					wp_nonce_field( $icwp_nonce_field );
					printAllPluginOptionsForm( $icwp_aAllOptions, $icwp_var_prefix, 1 );
				?>
					<div class="form-actions">
						<input type="hidden" name="<?php echo $icwp_var_prefix; ?>all_options_input" value="<?php echo $icwp_all_options_input; ?>" />
						<input type="hidden" name="<?php echo $icwp_var_prefix; ?>plugin_form_submit" value="Y" />
						<button type="submit" class="btn btn-primary" name="submit"><?php _wpsf_e( 'Save All Settings' ); ?></button>
					</div>
				</form>
				
			</div><!-- / span9 -->
		
			<?php if ( $icwp_fShowAds ) : ?>
			<div class="span3" id="side_widgets">
		  		<?php echo getWidgetIframeHtml('side-widgets-wtb'); ?>
			</div>
			<?php endif; ?>
		</div><!-- / row -->

		<?php include_once( dirname(__FILE__).'/widgets/icwp_common_widgets.php' ); ?>

		<?php if ( $icwp_fShowAds ) : ?>
			<div class="row" id="developer_channel_promo">
				<div class="span12">
					<?php echo getWidgetIframeHtml('dashboard-widget-developerchannel-wtb'); ?>
				</div>
			</div><!-- / row -->
		<?php endif; ?>
		
		<div class="row" id="tbs_docs">
			<h2><?php _wpsf_e( 'Plugin Configuration Summary'); ?></h2>
			<div class="span6" id="tbs_docs_shortcodes">
			  <div class="well">
				<h4 style="margin-top:20px;">
				<?php printf( _wpsf__('Firewall is %s'), $fFirewallOn ? $sOn : $sOff ); ?>
				[ <a href="admin.php?page=icwp-wpsf-firewall"><?php _wpsf_e('Configure Now'); ?></a> ]</h4>
				<?php if ( $fFirewallOn ) : ?>
					<ul>
						<li><?php printf( _wpsf__('Firewall logging is %s'), ($icwp_aFirewallOptions['enable_firewall_log'] == 'Y') ? $sOn : $sOff ); ?></li>
						<li><?php _wpsf_e( 'When the firewall blocks a visit, it will:'); ?>
							<?php
							if( $icwp_aFirewallOptions['block_response'] == 'redirect_die' ) {
								_wpsf_e( 'Die' );
							}
							else if ( $icwp_aFirewallOptions['block_response'] == 'redirect_die_message' ) {
								_wpsf_e( 'Die with a message' );
							}
							else if ( $icwp_aFirewallOptions['block_response'] == 'redirect_home' ) {
								_wpsf_e( 'Redirect to home page' );
							}
							else if ( $icwp_aFirewallOptions['block_response'] == 'redirect_404' ) {
								_wpsf_e( 'Redirect to 404 page' );
							}
							else {
								_wpsf_e( 'Unknown' );
							}
						?>
						</li>
						<?php if ( isset($icwp_aFirewallOptions['ips_whitelist']['ips']) ) : ?>
							<li>
								<?php printf( _wpsf__('You have %s whitelisted IP addresses'), count( $icwp_aFirewallOptions['ips_whitelist']['ips'] ) ); ?>
								<?php foreach( $icwp_aFirewallOptions['ips_whitelist']['ips'] as $sIp ) : ?>
									<br />
									<?php printf( _wpsf__('%s labelled as %s'), long2ip($sIp), $icwp_aFirewallOptions['ips_whitelist']['meta'][md5( $sIp )] ); ?>
								<?php endforeach; ?>
							</li>
						<?php endif; ?>

						<?php if ( isset($icwp_aFirewallOptions['ips_blacklist']['ips']) ) : ?>
							<li>
								<?php printf( _wpsf__('You have %s blacklisted IP addresses'), count( $icwp_aFirewallOptions['ips_blacklist']['ips'] ) ); ?>
								<?php foreach( $icwp_aFirewallOptions['ips_blacklist']['ips'] as $sIp ) : ?>
									<br />
									<?php printf( _wpsf__('%s labelled as %s'), long2ip($sIp), $icwp_aFirewallOptions['ips_blacklist']['meta'][md5( $sIp )] ); ?>
								<?php endforeach; ?>
							</li>
						<?php endif; ?>
						
						<li><?php printf( _wpsf__('Firewall blocks Directory Traversals: %s'), ($icwp_aFirewallOptions['block_dir_traversal'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Firewall blocks SQL Queries: %s'), ($icwp_aFirewallOptions['block_sql_queries'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Firewall blocks WordPress Specific Terms: %s'), ($icwp_aFirewallOptions['block_wordpress_terms'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Firewall blocks Field Truncation Attacks: %s'), ($icwp_aFirewallOptions['block_field_truncation'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Firewall blocks Directory Traversals: %s'), ($icwp_aFirewallOptions['block_dir_traversal'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Firewall blocks Executable File Uploads: %s'), ($icwp_aFirewallOptions['block_exe_file_uploads'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Firewall blocks Leading Schemas (HTTPS / HTTP): %s'), ($icwp_aFirewallOptions['block_leading_schema'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Firewall Logging is %s'), ($icwp_aFirewallOptions['enable_firewall_log'] == 'Y')? $sOn : $sOff ); ?></li>
					</ul>
				<?php endif; ?>
				<hr/>
				<h4 style="margin-top:20px;">
					<?php printf( _wpsf__('Login Protection is %s'), $fLoginProtectOn ? $sOn : $sOff ); ?>
					[ <a href="admin.php?page=icwp-wpsf-login_protect"><?php _wpsf_e('Configure Now'); ?></a> ]</h4>
				<?php if ( $fLoginProtectOn ) : ?>
					<ul>
						<?php if ( isset($icwp_aLoginProtectOptions['ips_whitelist']['ips']) ) : ?>
							<li>
								<?php printf( _wpsf__('You have %s whitelisted IP addresses'), count( $icwp_aLoginProtectOptions['ips_whitelist']['ips'] ) ); ?>
								<?php foreach( $icwp_aLoginProtectOptions['ips_whitelist']['ips'] as $sIp ) : ?>
									<br />
									<?php printf( _wpsf__('%s labelled as %s'), long2ip($sIp), $icwp_aLoginProtectOptions['ips_whitelist']['meta'][md5( $sIp )] ); ?>
								<?php endforeach; ?>
							</li>
						<?php endif; ?>
						<li><?php printf( _wpsf__('Two Factor Login Authentication: %s'), ($icwp_aLoginProtectOptions['enable_two_factor_auth_by_ip'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Two Factor Login By Pass: %s'), ($icwp_aLoginProtectOptions['enable_two_factor_bypass_on_email_fail'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Login Cooldown Interval: %s'), ($icwp_aLoginProtectOptions['login_limit_interval'] == '0')? $sOff : sprintf( _wpsf__('%s seconds'), $icwp_aLoginProtectOptions['login_limit_interval'] ) ); ?></li>
						<li><?php printf( _wpsf__('Login Form GASP Protection: %s'), ($icwp_aLoginProtectOptions['enable_login_gasp_check'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Login Protect Logging: %s'), ($icwp_aLoginProtectOptions['enable_login_protect_log'] == 'Y')? $sOn : $sOff ); ?></li>
					</ul>
				<?php endif; ?>
				<hr/>
				<h4 style="margin-top:20px;">
					<?php printf( _wpsf__('Comments Filtering is %s'), $fCommentsFilteringOn ? $sOn : $sOff ); ?>
					[ <a href="admin.php?page=icwp-wpsf-comments_filter"><?php _wpsf_e('Configure Now'); ?></a> ]</h4>
				<?php if ( $fCommentsFilteringOn ) : ?>
					<ul>
						<li><?php printf( _wpsf__('Enchanced GASP Protection: %s'), ($icwp_aCommentsFilterOptions['enable_comments_gasp_protection'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Comments Cooldown Interval: %s'), ($icwp_aCommentsFilterOptions['comments_cooldown_interval'] == '0')? $sOff : sprintf( _wpsf__('%s seconds'), $icwp_aCommentsFilterOptions['comments_cooldown_interval'] ) ); ?></li>
						<li><?php printf( _wpsf__('Comments Token Expire: %s'), ($icwp_aCommentsFilterOptions['comments_token_expire_interval'] == '0')? $sOff : sprintf( _wpsf__('%s seconds'), $icwp_aCommentsFilterOptions['comments_token_expire_interval'] ) ); ?></li>
					</ul>
				<?php endif; ?>
				<hr/>
				<h4 style="margin-top:20px;">
					<?php printf( _wpsf__('WordPress Lockdown is %s'), $fLockdownOn ? $sOn : $sOff ); ?>
					[ <a href="admin.php?page=icwp-wpsf-lockdown"><?php _wpsf_e('Configure Now'); ?></a> ]</h4>
				<?php if ( $fLockdownOn ) : ?>
					<ul>
						<li><?php printf( _wpsf__('Disable File Editing: %s'), ($icwp_aLockdownOptions['disable_file_editing'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Mask WordPress Version: %s'), empty($icwp_aLockdownOptions['mask_wordpress_version'])? $sOff : $icwp_aLockdownOptions['mask_wordpress_version'] ); ?></li>
					</ul>
				<?php endif; ?>
				<hr/>
				<h4 style="margin-top:20px;">
					<?php printf( _wpsf__('Auto Updates is %s'), $fAutoupdatesOn ? $sOn : $sOff ); ?>
					[ <a href="admin.php?page=icwp-wpsf-autoupdates"><?php _wpsf_e('Configure Now'); ?></a> ]</h4>
				<?php if ( $fAutoupdatesOn ) :
					
					if ( $icwp_aAutoupdatesOptions['autoupdate_core'] == 'core_never' ) {
						$sAutoCoreUpdateOption = $sOff;
					}
					else if ( $icwp_aAutoupdatesOptions['autoupdate_core'] == 'core_minor' )  {
						$sAutoCoreUpdateOption = _wpsf__('Minor Versions Only');
					}
					else {
						$sAutoCoreUpdateOption = _wpsf__('Major and Minor Versions');
					}
				?>
					<ul>
						<li><?php printf( _wpsf__('Automatically Update WordPress Simple Firewall Plugin: %s'), ($icwp_aAutoupdatesOptions['autoupdate_plugin_self'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Automatically Update WordPress Core: %s'), $sAutoCoreUpdateOption ); ?></li>
						<li><?php printf( _wpsf__('Automatically Update Plugins: %s'), ($icwp_aAutoupdatesOptions['enable_autoupdate_plugins'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Automatically Update Themes: %s'), ($icwp_aAutoupdatesOptions['enable_autoupdate_themes'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Automatically Update Translations: %s'), ($icwp_aAutoupdatesOptions['enable_autoupdate_translations'] == 'Y')? $sOn : $sOff ); ?></li>
						<li><?php printf( _wpsf__('Ignore Version Control Systems: %s'), ($icwp_aAutoupdatesOptions['enable_autoupdate_ignore_vcs'] == 'Y')? $sOn : $sOff ); ?></li>
					</ul>
				<?php endif; ?>
			  </div>
		  </div><!-- / span6 -->
		  <div class="span6" id="tbs_docs_examples">
			  <div class="well">
				<h3><?php printf( _wpsf__('Release v%s'), $sLatestVersionBranch ) ; ?></h3>
				<p><?php printf( _wpsf__('The following summarises the main changes to the plugin in the v%s release'), $sLatestVersionBranch ) ; ?></p>
				<p><?php printf( _wpsf__('%snew%s refers to the absolute latest release.'), '<span class="label">', '</span>' ) ; ?></p>
				<?php
				$aNewLog = array(
					'ADDED: Options to automatic updates to control where and whether email notifications are sent.',
					'ADDED: Various fixes and verification of WordPress 3.8 compatibility.',
					'ADDED: Integration with iControlWP and the automatic updates system.',
					'ADDED: Better filesystem handling methods.',
					'ADDED: Better firewall logic for whitelisting rules.',
					'ADDED: Some new firewall white listing parameters to help with post editing.',
					'ADDED: Option to run automatic updates upon demand according to your settings',
					'ADDED: Localization capabilities. All we need now are translators.',
					'ADDED: Option to mask the WordPress version so the real version is never publicly visible.'
				);
				?>
				<ul>
				<?php foreach( $aNewLog as $sItem ) : ?>
					<li><span class="label"><?php _wpsf_e('new'); ?></span> <?php echo $sItem; ?></li>
				<?php endforeach; ?>
				</ul>
				<?php
				$aLog = array(
				);
				?>
				<ul>
				<?php foreach( $aLog as $sItem ) : ?>
					<li><?php echo $sItem; ?></li>
				<?php endforeach; ?>
				</ul>
			</div>
			<div class="well">
				<?php
				$aLog = array(

					'1.9.x' => array(
						'ADDED: Block deactivation of plugin if admin access restriction is on.',
						'ADDED: New feature to manage WordPress Automatic Updates.',
						'FIXED: Several small bugs and streamlined codebase.',
					),
					'1.8.x'	=> array(
						'ADDED: Admin Access Key Restriction feature.',
						'ADDED: WordPress Lockdown feature.'
					),
					'1.7.x'	=> array(
						'ADDED: Support for WPMU sites (only manageable as Super Admin).',
						'CHANGE: Serious performance optimizations and a few bug fixes.',
					),
					'1.6.x'	=> array(
						'ADDED: GASP-based, and further enhanced, SPAM comments filtering functionality.',
					),
					'1.5.x'	=> array(
						'IMPROVED: Whitelisting/Blacklisting operations and options',
						'NEW Option: Login Protect Dedicated IP Whitelist.',
						'REMOVED Option: Firewall wp-login.php blocking'
					),
					'1.4.x'	=> array(
						'NEW Option: Plugin will automatically upgrade itself when an update is detected - ensures plugin always remains current.',
						'Now displays an admin notice when a plugin upgrade is available with a link to immediately update.',
						'Plugin collision protection: removes collision with All In One WordPress Security.',
						'Improved Login Cooldown Feature- works more like email throttling as it now uses an extra filesystem-based level of protection.',
						"Fix - Login Cooldown Feature didn't take effect in certain circumstances.",
						'Brand new plugin options system making them more efficient, easier to manage/update, using fewer WordPress database options',
						'Huge improvements on database calls and efficiency in loading plugin options'
					),
					'1.3.x'	=> array(
						"New Feature - Email Throttle. It will prevent you getting bombarded by 1000s of emails in case you're hit by a bot.",
						"Another Firewall die() option. New option will print a message and uses the wp_die() function instead.",
						"Option to separately log Login Protect features.",
						"Refactored and improved the logging system.",
						"Option to by-pass 2-factor authentication in the case sending the verification email fails.",
						"Login Protect checking now better logs out users immediately with a redirect.",
						"We now escape the log data being printed - just in case there's any HTML/JS etc in there we don't want.",
						"Optimized and cleaned a lot of the option caching code to improve reliability and performance (more to come).",
					),
					
					'1.2.x'	=> array(
						'New Feature - Ability to import settings from WordPress Firewall 2 Plugin.',
						'New Feature - Login Form GASP-based Anti-Bot Protection.',
						'New Feature - Login Cooldown Interval.',
						'Performance optimizations.',
						'UI Cleanup and code improvements.',
						'Added new Login Protect feature where you can add 2-Factor Authentication to your WordPress user logins.',
						'Improved method for processing the IP address lists to be more cross-platform reliable.',
						'Improved .htaccess rules (thanks MickeyRoush).',
						'Mailing method now uses WP_MAIL.'
					),
					
					'1.1.x'	=> array(
						'Option to check Cookies values in firewall testing.',
						'Ability to whitelist particular pages and their parameters.',
						'Quite a few improvements made to the reliability of the firewall processing.',
						'Option to completely ignore logged-in Administrators from the Firewall processing (they wont even trigger logging etc).',
						'Ability to (un)blacklist and (un)whitelist IP addresses directly from within the log.',
						'Helpful link to IP WHOIS from within the log.',
						'Firewall logging now has its own dedicated database table.',
						'Fix: Block email not showing the IPv4 friendly address.',
						'You can now specify IP ranges in whitelists and blacklists.',
						'You can now specify which email address to send the notification emails.',
						"You can now add a comment to IP addresses in the whitelist/blacklist. To do this, write your IP address then type a SPACE and write whatever you want (don't take a new line').",
						'You can now set to delete ALL firewall settings when you deactivate the plugin.',
						'Improved formatting of the firewall log.'
					)
				);
				?>
				<?php foreach( $aLog as $sVersion => $aItems ) : ?>
				<h3><?php printf( _wpsf__('Change log for the v%s release'), $sVersion ); ?></h3>
				<ul>
					<?php foreach( $aItems as $sItem ) : ?>
						<li><?php echo $sItem; ?></li>
					<?php endforeach; ?>
				</ul>
				<?php endforeach; ?>
			  </div>
		  </div><!-- / span6 -->
		</div><!-- / row -->
		
		<div class="row">
		  <div class="span6">
		  </div><!-- / span6 -->
		  <div class="span6">
		  	<p></p>
		  </div><!-- / span6 -->
		</div><!-- / row -->
		
	</div><!-- / bootstrap-wpadmin -->
	<?php include_once( dirname(__FILE__).'/include_js.php' ); ?>
</div><!-- / wrap -->