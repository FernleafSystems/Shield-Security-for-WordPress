<style>
	#icwpOptionsTopPill > .nav-pills li#icwpWizardPill a {
		background-image: url("<?php echo $hrefs['img_wizard_wand'];?>");
	}
</style>
<div class="row icwpTopLevelRow">
	<div class="icwpTopLevelSpan <?php echo $flags[ 'show_ads' ] ? 'span11' : 'span11'; ?>" id="icwpOptionsTopPill">

		<ul class="nav nav-pills">
			<li class="active">
				<a href="#icwpPillOptions" data-toggle="pill">
					<span class="dashicons dashicons-admin-settings">&nbsp;</span>
					<div class="title"><?php echo $strings[ 'options_title' ]; ?></div>
					<p class="summary"><?php echo $strings[ 'options_summary' ]; ?></p>
				</a>
			</li>
			<li>
			<?php if ( $flags[ 'show_content_actions' ] ) : ?>
				<a href="#icwpPillActions" data-toggle="pill">
					<span class="dashicons dashicons-hammer">&nbsp;</span>
					<div class="title"><?php echo $strings[ 'actions_title' ]; ?></div>
					<p class="summary"><?php echo $strings[ 'actions_summary' ]; ?></p>
				</a>
			<?php endif; ?>
			</li>
			<?php if ( $flags[ 'show_content_help' ] ) : ?>
				<li>
					<a href="#icwpPillHelp" data-toggle="pill">
						<span class="dashicons dashicons-editor-help">&nbsp;</span>
						<div class="title"><?php echo $strings[ 'help_title' ]; ?></div>
						<p class="summary"><?php echo $strings[ 'help_summary' ]; ?></p>
					</a>
				</li>
			<?php endif; ?>
			<?php if ( $flags[ 'has_wizard' ] ) : ?>
				<?php if ( $flags[ 'can_wizard' ] ) : ?>
					<li id="icwpWizardPill">
						<a href="<?php echo $hrefs[ 'wizard_link' ]; ?>"
						   title="Launch Guided Walk-Through Wizards" target="_blank">&nbsp;</a>
					</li>
				<?php else: ?>
					<li id="icwpWizardPill">
						<a href="#" title="Wizards are not available as your PHP version is too old.">&nbsp;</a>
					</li>
				<?php endif; ?>
			<?php endif; ?>
		</ul>
		<div class="tab-content">
			<div class="tab-pane active" id="icwpPillOptions">
				<?php echo $flags[ 'show_standard_options' ] ? $options_form : ''; ?>
				<?php echo $flags[ 'show_alt_content' ] ? $content[ 'alt' ] : ''; ?>
			</div>

			<?php if ( $flags[ 'show_content_actions' ] ) : ?>
				<div class="tab-pane" id="icwpPillActions">
					<?php echo $content[ 'actions' ]; ?>
				</div>
			<?php endif; ?>

			<?php if ( $flags[ 'show_content_help' ] ) : ?>
				<div class="tab-pane" id="icwpPillHelp">
					<div class="content-help"><?php echo $content[ 'help' ]; ?></div>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $flags[ 'show_ads' ] ) : ?>
			<div class="row-fluid">
				<div class="span12">
					<?php echo getWidgetIframeHtml( 'dashboard-widget-worpit-wtb' ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div><!-- / span9 -->

		<div class="span1" id="side_widgets">
		</div>
</div>