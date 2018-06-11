<h5>What is the Login Guard?</h5>
<p>The goal of the login guard is to protect against attacks on the WordPress user login process.</p>
<p>It protects you against brute force and bots,
   while also providing added layers of user identify verification, e.g. multi-factor authentication.</p>
<dl>
	<dt>What is multi-factor authentication (MFA)?</dt>
	<dd>
		<p>See the link below for a complete explanation of MFA.</p>
		<p><a href="https://icwp.io/ai" target="_blank">
				https://www.icontrolwp.com/blog/security-multi-two-factor-authentication-wordpress/</a> .
		</p>
		<p>Please read this. It really helps.</p>
	</dd>

	<dt>Are 3rd party plugins with custom login pages supported?</dt>
	<dd>
		<p>This is a Pro option and lets Shield interact with 3rd party plugins.</p>
		<p>Unless you're a developer it might be hard to realise that the WordPress plugin landscape is a mess.</p>
		<p>Everyone does everything differently, and so ensuring Shield works with 3rd party systems is nearly
		   a full-time job in itself.</p>
		<p>Systems such as WooCommerce, BuddyPress, etc. all use slightly different login/registration forms
		   and this breaks Shield's Login Guard features since they aren't aligned with WordPress standard
		   API.</p>
		<p>If you use these 3rd party plugins, then you will need to go Pro.</p>
		<p>Currently support systems are:</p>
		<ul>
			WooCommerce - support for login, registration, lost password, and Two-Factor Authentication
			BuddyPress - support for login, registration, lost password, and Two-Factor Authentication
			Easy Digital Downloads - support for login, registration, lost password, and Two-Factor Authentication
		</ul>
	</dd>

	<dt>Login Cooldown - what's that?</dt>
	<dd>
		<p>Great question! This is perhaps one of the coolest, understated features of the entire plugin.</p>
		<p>It elegantly prevents brute force login attacks simply by throttling the WordPress login.</p>
		<p>At its default setting, 10, it will restrict valid WordPress logins to 1 every 10 seconds.</p>
	</dd>
</dl>