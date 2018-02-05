<h5>What is the Audit Trail?</h5>
<p>You can think of the audit trail as an ongoing log of important events that happen on your site.</p>
<dl>
	<dt>Why would I need an audit trail?</dt>
	<dd>
		<p>Often when something bad happens, it's good to be able to know why, so as to help prevent it in the future.</p>
		<p>The audit trail assists us in finding the series of events that led to a problem.</p>
		<p>e.g. WordPress displays only a blank screen. What events happened recently? Perhaps a plugin upgrade/installation caused it?
		You wont know unless you can track the series of events that led up to it.</p>
	</dd>

	<dt>What are the audit trail contexts?</dt>
	<dd>
		<p>This split up the log into logical areas so you can more easily track related events.</p>
		<p>e.g. If you want to view events relating to plugins, enable this context.</p>
		<p>We recommend keeping the Shield Plugin context enabled as this keeps a log of security-related events.</p>
	</dd>

	<dt>What if my audit trail gets very large?</dt>
	<dd>
		<p>We store audit trail events in its own independent database table, so performance on your site will never be impacted.</p>
		<p>You can keep the database table lean by automatically pruning old events. The default for "old" is 14 days.</p>
	</dd>

	<dt>What's the difference between the options 'Auto-Clean' and 'Max Trial Length'?</dt>
	<dd>
		<p>Auto-cleaning keeps your audit trail table lean - usually there's no need to retain events older than a couple of weeks.</p>
		<p>Max Trial Length is a Pro feature. If you have a busy site you'll need a bigger audit trail.</p>
		<p>Free users will be limited to 50 events, Pro subscribers will be unlimited.</p>
	</dd>
</dl>