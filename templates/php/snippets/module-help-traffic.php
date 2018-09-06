<h5>What is the Traffic Watcher?</h5>
<p>You can think of the Traffic Watcher as nothing more than a window into web requests to your WordPress site.</p>
<dl>
	<dt>Why would I need the Traffic Watcher?</dt>
	<dd>
		<p>In everyday use, you wouldn't need the watcher to be active. It'll use resources that you otherwise
		   don't need to use.</p>
		<p>But, if you're concerned about a sudden performance drop, or feel that your site is subject to some
		   sort of attack, you can enable it to get a more informed view on your traffic.</p>
	</dd>

	<dt>Does the Traffic Watcher use a lot of resources?</dt>
	<dd>
		<p>No. But it will perform an database insert (write) on each page load. It's a tiny execution and not
		   one that will impact your page loading.</p>
		<p>Shield performs this database write at the very end of execution so any page loading for the visitor
		   will complete 99.99% and then the database execution starts.</p>
		<p>In this way there'll be no noticeable performance impact from it if, for example, your SQL server is having
		   trouble at the time.</p>
	</dd>

	<dt>What if my traffic watcher log gets very large?</dt>
	<dd>
		<p>You can limit the size of the log in the settings to ensure it will be trimmed to your desired size regularly.</p>
		<p>You can also set a setting which will automatically turn off the logging after 1 week, in-case you're likely
		   to forget about it :)</p>
	</dd>

	<dt>What is the Traffic Log Exclusions option?</dt>
	<dd>
		<p>Use the exclusions system to automatically skip the logging of web requests you know to be legitimate.</p>
		<p>This reduces the size of your log and the speed at which it fills up, and also prevents your logs
		   from filling up with "noise" (information you just don't need to have logged).</p>
		<p>We recommend that you take a moment to consider which traffic you should exclude as there's no point
		   in logging traffic that you don't need.</p>
	</dd>
	<dt>How does the Traffic Log Exclusions work?</dt>
	<dd>
		<p>When a web request reaches your site, Shield examines it against each exclusion category you have selected.</p>
		<p>If the request matches ANY single one (or more) of the exclusions, it will not be logged.</p>
		<p>Here's an example. Imagine you selected to only exclude 'AJAX' requests from the logs,
		   but you haven't selected to exclude 'Logged-In Users'. What happens if you're logged-in and you're in
		   the WP admin area and your page sends off an AJAX-request?  You might think that since you're not
		   excluding Logged-In Users that this should be logged. However, it wont appear in your logs because
		   you're excluding AJAX requests.</p>
		<p style="font-weight: bolder;">
			Remember: if a request matches any single exclusion category it will not be logged.</p>

	</dd>
</dl>