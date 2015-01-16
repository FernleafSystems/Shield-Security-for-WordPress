<h2><?php _wpsf_e( 'Plugin Change Log Summary'); ?></h2>
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
