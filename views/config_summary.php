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