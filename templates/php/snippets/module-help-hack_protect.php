<h5>What is the Hack Guard module?</h5>
<p>This module is probably one of the most critical modules of the Shield plugin.</p>
<p>Its purpose is to alert you to strange, unusual, unexpected files present on your web host.</p>
<p>These strange files can be something, or nothing, but you need to be aware of them because they can easily
   represent maliciously upload files/scripts that have been put there by hackers.</p>
<dl>
	<dt>What is the core file scanner?</dt>
	<dd>
		<p>WordPress comes with a core set of files in a standard format.</p>
		<p>We also know what those files should look like and the content of them.</p>
		<p>The job of the core file scanner is to look at each WordPress core file and determine whether it is valid.
		   You see, it's quite possible that a hacker will modify one of your core files with malicious code and
		   you'd never know.
		</p>
		<p>Now, with the core file scanner, you will know!</p>
	</dd>

	<dt>What is the unrecognised file scanner?</dt>
	<dd>
		<p>Where the Core File Scanner is preoccupied with finding any official WordPress files that are corrupt,
		   the Unrecognised file scanner is preoccupied with finding all the other files on your web hosting that
		   are not WP Core Files.</p>
		<p>Why are these files important?</p>
		<p>They could perfectly fine, or they could be script files that a hacker uses to access your site or corrupt
		   your WordPress installation.
		</p>
		<p><strong>Important Note: this scanner cannot tell you if you should delete or keep these files, it can
			only tell you that they exist. It is up to you to determine if they're okay.</strong>
			If you're in doubt, talk to your host.
		</p>
	</dd>

	<dt>What is the Vulnerabilities Scanner?</dt>
	<dd>
		<p>This is Pro feature since it uses a commercially available database.</p>
		<p>This scan is designed to alert you to the present of plugins running on your site that have known
		   security vulnerabilities in them.
		</p>
		<p>Half the battle with security is knowing what's good and bad, and running a WordPress site with known
		   security vulnerabilities is asking for trouble, even if you don't know they're vulnerable.
		</p>
		<p>Now you will know if they're vulnerable.</p>
	</dd>
</dl>