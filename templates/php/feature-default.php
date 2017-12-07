<div class="row icwpTopLevelRow">
	<div class="icwpTopLevelSpan <?php echo $flags[ 'show_ads' ] ? 'span10' : 'span10'; ?>" id="icwpOptionsTopPill">

		<ul class="nav nav-pills">
			<li <?php echo $flags[ 'show_content_actions' ] ? '' : 'class="active"'; ?>>
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
				<li <?php echo $flags[ 'show_content_help' ] ? '' : 'class="active"'; ?>>
				<a href="#icwpPillHelp" data-toggle="pill">
					<span class="dashicons dashicons-editor-help">&nbsp;</span>
					<div class="title"><?php echo $strings[ 'help_title' ]; ?></div>
					<p class="summary"><?php echo $strings[ 'help_summary' ]; ?></p>
				</a>
			</li>
			<?php endif; ?>
		</ul>
		<div class="tab-content">
			<div class="tab-pane <?php echo $flags[ 'show_content_actions' ] ? '' : 'active'; ?>"
				 id="icwpPillOptions">
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
			<div class="tab-pane active" id="icwpPillSelect">
				<h3 style="text-align: center">&uarr; <?php echo 'Select Desired Section Above'; ?> &uarr;</h3>
			</div>
		</div>

		<?php if ( $flags[ 'show_ads' ] ) : ?>
			<div class="row-fluid">
				<div class="span12">
					<?php echo getWidgetIframeHtml( 'dashboard-widget-worpit-wtb' ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div><!-- / span9 -->

		<div class="span2" id="side_widgets">

		<?php if ( isset( $flags[ 'show_summary' ] ) && $flags[ 'show_summary' ] ) : ?>
			<?php include_once( $sBaseDirName.'snippets'.DIRECTORY_SEPARATOR.'state_summary.php' ); ?>
		<?php endif; ?>
		</div>

		  <!--	--><?php //if ( $flags[ 'show_ads' ] ) : ?>
		  <!--		<div class="span3" id="side_widgets">-->
		  <!--			--><?php //echo getWidgetIframeHtml( 'side-widgets-wtb' ); ?>
		  <!--		</div>-->
		  <!--	--><?php //endif; ?>
</div>