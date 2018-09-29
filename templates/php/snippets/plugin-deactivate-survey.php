<div id="icwpWpsfSurvey" class="hidden icwp-wpsf-dialog">
	<h3>Would You Care To Share?</h3>
	<p>Deactivating Shield makes us sad, but to help us improve we'd love to know why.</p>
	<p>This is optional - will you take a second to tell us why you're deactivating Shield?</p>
	<form>
		<ul>
			<?php foreach ( $inputs[ 'checkboxes' ] as $sKey => $sOpt ) : ?>
				<li><label><input name="<?php echo $sKey; ?>" type="checkbox" value="Y">
						<?php echo $sOpt; ?></label></li>
			<?php endforeach; ?>
		</ul>
		<textarea style="width: 360px;" rows="3" placeholder="Any other specific details or comments?"></textarea>
	</form>
</div>
