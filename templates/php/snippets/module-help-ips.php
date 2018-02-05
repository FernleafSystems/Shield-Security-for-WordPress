<h5>What is the IP Manager?</h5>
<p>The IP Manager is dedicated to ensuring that bad actors get blocked, and good actors have no trouble accessing your site.</p>
<dl>
	<dt>How does the automatic black list work?</dt>
	<dd>
		<p>You'll find black lists option on many security systems, but they're are fundamentally flawed.</p>
		<p>Permanently blocking visitors by IP address is completely useless. It's great for marketing because it makes
		   customer <em>think</em> they have "control", but really, none of us have control when we use IP addresses.</p>
		<p>So why do we have a blacklist? We have a <em>temporary, completely automated blacklist</em>. It's true that a given
		   bad actor might try a number of attacks on your site from the same IP address, but if the bot is normal, it'll
		   change its IP address a lot, or originate from a bot-net, meaning many different IPs. Having a huge IP black list
		   will slow down your site and make it terribly inefficient for all your normal visitors - the majority.
		</p>
		<p>This is why our black list is temporary. When we spot a bad actor, we block it. But we block it temporarily so
		   that we can keep your black list lean and fast. You also don't need to ever worry about it. It's all handled for you
		   without any effort on your part. That is true security. You should never, ever, need to manually maintain block lists,
		   and if you're currently doing this, you need to rethink your security strategy.
		</p>
	</dd>

	<dt>What is a transgression?</dt>
	<dd>
		<p>This is how we spot bad actors. There are many actions which visitors can take that indicate the presence
		   of a bot or malicious visitor.</p>
		<p>These can include posting spam comments, failed login attempts, firewall triggers etc.</p>
		<p>1 of these events in isolation doesn't make a visitor malicious, but more than a few tell us they're not
		   friendly. You decide how many of these sorts of events should be triggered before Shield considers
		   them malicious.</p>
		<p>They will be added to the black list with any single transgression. But only after they've reached
		   your transgression limit, will they be blocked from accessing the site.
		   This block period will last according to 'Auto Block Expiration'.
		</p>
	</dd>

	<dt>What does it really mean if an IP Address is on the White List?</dt>
	<dd>
		<p>Any visitor accessing this site while their IP address is on the white list will not be subject to ANY
		   processing by the <?php echo $sPluginName; ?> plugin.</p>
		<p>That includes absolutely everything. Shield will completely ignore that visitor.</p>
		<p>Note: This also includes renaming of the login page URL. Remember: absolutely everything.</p>
	</dd>

	<dt>Can I manage the white and black lists?</dt>
	<dd>
		<p>Yes. Please see the tab above 'Manage IP Lists'</p>
		<p>You can do the following:</p>
		<ul>
			<li>Add an IP address to the white list.</li>
			<li>Remove an IP address from the white list.</li>
			<li>Remove an IP address from the black list.</li>
		</ul>
		<p>You can't add an IP address to the black list - remember, it's completely automated for you.</p>
	</dd>
</dl>