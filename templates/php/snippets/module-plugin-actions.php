<h2>Plugin Walk-Through Wizards</h2>
<p>Click the buttons below to launch the respective walk-through wizard.</p>

<?php if ( !$flags[ 'can_php54' ] ) : ?>
	<div class="alert alert-danger">
		<h3>Running Wizards is not supported on your web hosting.</h3>
		<p>You're site is running PHP Version <strong><?php echo $data[ 'phpversion' ]; ?></strong>.</p>
		<p>Running Shield Wizards requires at least <strong>PHP 5.4</strong>.
		   <br />PHP 5.4 was released around 6 years ago and by now,
		   any <strong>decent webhost</strong> will have provisioned support for <em>at least</em> this
		   version of PHP. Check with your webhost or site developer to see about switching up your PHP version
		   because you're running your website on old, outdated, non-maintained software. And if you're PHP
		   is this far out-of-date, you gotta wonder what else on your webhost needs some important upgrades.
		</p>
		<p>Website security is covered in many areas, and your webhost/server is a critical one of these.</p>
		<p>Note: As Shield Security is further developed, more and more features will require higher versions of PHP.</p>
	</div>
<?php endif; ?>

<div class="well">
	<h3>Original Welcome Wizard</h3>
	<p>Use this to re-run the original welcome wizard that gets you started with Shield Security.</p>
	<p><a href="<?php echo $hrefs[ 'wizard_welcome' ]; ?>" title="Shield Welcome Wizard" target="_blank"
			<?php echo $flags[ 'can_welcome' ] ? '' : 'disabled="disabled" onclick="event.preventDefault();"'; ?>
		  class="btn btn-default btn-large">Shield Welcome Wizard</a></p>
</div>

<hr />

<div class="well">
	<h3>Import Options From Another Site</h3>
	<p>Use this to import all the options from a remote site directly into this site.</p>
	<p><a href="<?php echo $hrefs[ 'wizard_import' ]; ?>" title="Import Options Wizard" target="_blank"
			<?php echo $flags[ 'can_import' ] ? '' : 'disabled="disabled" onclick="event.preventDefault();"'; ?>
		  class="btn btn-default btn-large">Import Options Wizard</a></p>
	<p class="text-warning">Warning: Use of this feature will overwrite all Shield settings on this site.</p>
</div>

<hr />