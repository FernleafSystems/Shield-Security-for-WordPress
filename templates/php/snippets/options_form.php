<form action="<?php echo $form_action; ?>" method="post" class=" icwpOptionsForm">
	<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo $data[ 'form_nonce' ] ?>">
    <input type="hidden" name="mod_slug" value="<?php echo $data[ 'mod_slug' ]; ?>" />
    <input type="hidden" name="all_options_input" value="<?php echo $data[ 'all_options_input' ]; ?>" />
    <input type="hidden" name="plugin_form_submit" value="Y" />


<div style="margin-bottom: -1px">
	<div class="row no-gutters">
		<div class="col">
			<div class="module-headline">
				<h4><a id="pluginlogo_32" class="header-icon32" href="http://icwp.io/2k" target="_blank"></a>
					<?php echo $sPageTitle; ?>
					<div class="float-right">
						<div class="btn-group" role="group" aria-label="Basic example">
							<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 1 );}"
							   class="btn btn-info">Go To Help</a>
							<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 2 );}"
							   class="btn btn-secondary">Go To Actions</a>
						</div>
						<button type="submit" class="float-right btn btn-primary icwp-form-button"
								name="submit" style="margin-left: 8px">
							<?php _wpsf_e( 'Save Settings' ); ?>
						</button>
					</div>
					<small class="module-tagline"><?php echo $sTagline; ?></small>
				</h4>
			</div>
		</div>
	</div>
	<div class="row no-gutters">
		<div class="col-2 smoothwidth">

			<ul id="ModuleOptionsNav" class="nav flex-column" id="v-pills-tab"
				role="tablist" aria-orientation="vertical">
				<?php foreach ( $data[ 'all_options' ] as $aOptSection ) : ?>
					<li class="nav-item">
					<a class="nav-link <?php echo $aOptSection[ 'primary' ] ? 'active' : '' ?>"
					   id="pills-tab-<?php echo $aOptSection[ 'slug' ]; ?>"
					   data-toggle="pill" href="#pills-<?php echo $aOptSection[ 'slug' ]; ?>"
					   role="tab" aria-controls="pills-<?php echo $aOptSection[ 'slug' ]; ?>"
						<?php echo $aOptSection[ 'primary' ] ? 'aria-selected="true"' : '' ?>>
						<?php echo $aOptSection[ 'title_short' ]; ?>
					</a>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<div class="col" style="margin: 0 4px 5px 0;">
			<div class="tab-content" id="pills-tabContent">
				<?php foreach ( $data[ 'all_options' ] as $aOptSection ) : ?>
					<div class="tab-pane <?php echo $aOptSection[ 'primary' ] ? 'active' : '' ?>"
						 id="pills-<?php echo $aOptSection[ 'slug' ]; ?>"
						 role="tabpanel" aria-labelledby="pills-tab-<?php echo $aOptSection[ 'slug' ]; ?>">

<div class="option_section_row <?php echo $aOptSection[ 'primary' ] ? 'primary_section' : 'non_primary_section'; ?>"
	 id="row-<?php echo $aOptSection[ 'slug' ]; ?>">
		<div class="options-body">
			<legend>
				<?php echo $aOptSection[ 'title' ]; ?>
				<?php if ( !empty( $aOptSection[ 'help_video_url' ] ) ) : ?>
					<div style="float:right;">

						<a href="<?php echo $aOptSection[ 'help_video_url' ]; ?>"
						   class="btn"
						   data-featherlight-iframe-height="454"
						   data-featherlight-iframe-width="772"
						   data-featherlight="iframe">
							<span class="dashicons dashicons-controls-play"></span> Help Video
						</a>
					</div>
				<?php endif; ?>
			</legend>

			<?php if ( !empty( $aOptSection[ 'summary' ] ) ) : ?>
				<div class="row_section_summary">
					<?php foreach ( $aOptSection[ 'summary' ] as $sItem ) : ?>
						<p class="noselect"><?php echo $sItem; ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php foreach ( $aOptSection[ 'options' ] as $nKeyRow => $aOption ) :
				$sOptKey = $aOption[ 'key' ];
				$mOptValue = $aOption[ 'value' ];
				$sOptType = $aOption[ 'type' ];
				$bEnabled = $aOption[ 'enabled' ];
				$sDisabledText = $bEnabled ? '' : 'disabled="Disabled"';
				?>
				<div class="form-group row no-gutters row_number_<?php echo $nKeyRow; ?>">

					<label class="form-label col-3 col-form-label" for="<?php echo $sOptKey; ?>">
						<span class="optname"><?php echo $aOption[ 'name' ]; ?></span>
						<?php if ( !empty( $aOption[ 'link_info' ] ) ) : ?>
							<br /><span class="optlinks">
							[
							<a href="<?php echo $aOption[ 'link_info' ]; ?>"
							   target="_blank"><?php echo $strings[ 'more_info' ]; ?></a>
								<?php if ( !empty( $aOption[ 'link_blog' ] ) ) : ?>
									| <a href="<?php echo $aOption[ 'link_blog' ]; ?>"
										 target="_blank"><?php echo $strings[ 'blog' ]; ?></a>
								<?php endif; ?>
							]
						</span>
						<?php endif; ?>
					</label>

					<div class="col-8 col-xs-offset-1 option_container
						<?php echo $bEnabled ? 'enabled' : 'disabled overlay_container' ?>">

						<?php if ( !$bEnabled ) : ?>
							<div class="option_overlay">
								<div class="overlay_message">
									<a href="<?php echo $hrefs[ 'go_pro' ]; ?>" target="_blank">
										Go Pro!</a>
								</div>
							</div>
						<?php endif; ?>

						<div class="option_section <?php echo ( $mOptValue == 'Y' ) ? 'selected_item' : ''; ?>"
							 id="option_section_<?php echo $sOptKey; ?>">

							<label class="for<?php echo $sOptType; ?>">

								<?php if ( $sOptType == 'checkbox' ) : ?>
									<span class="icwp-switch">
										<input type="checkbox"
											   name="<?php echo $sOptKey; ?>"
											   id="<?php echo $sOptKey; ?>"
											   value="Y" <?php echo ( $mOptValue == 'Y' ) ? 'checked="checked"' : ''; ?>
											<?php echo $sDisabledText; ?> />
										<span class="icwp-slider round"></span>
									</span>
									<span class="summary"><?php echo $aOption[ 'summary' ]; ?></span>

								<?php elseif ( $sOptType == 'text' ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<textarea name="<?php echo $sOptKey; ?>"
											  id="<?php echo $sOptKey; ?>"
											  placeholder="<?php echo $mOptValue; ?>"
											  rows="<?php echo $aOption[ 'rows' ]; ?>"
											  class="span7" <?php echo $sDisabledText; ?>><?php echo $mOptValue; ?></textarea>

								<?php elseif ( $sOptType == 'noneditable_text' ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<input type="text" readonly class="span8"
										   value="<?php echo $mOptValue; ?>" />

								<?php elseif ( $sOptType == 'password' ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<input type="password" name="<?php echo $sOptKey; ?>"
										   id="<?php echo $sOptKey; ?>"
										   value="<?php echo $mOptValue; ?>"
										   placeholder="<?php echo $mOptValue; ?>"
										   class="span7" <?php echo $sDisabledText; ?> />

								<?php elseif ( $sOptType == 'email' ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<input type="email" name="<?php echo $sOptKey; ?>"
										   id="<?php echo $sOptKey; ?>"
										   value="<?php echo $mOptValue; ?>"
										   placeholder="<?php echo $mOptValue; ?>"
										   class="span7" <?php echo $sDisabledText; ?> />

								<?php elseif ( $sOptType == 'select' ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<select name="<?php echo $sOptKey; ?>"
											id="<?php echo $sOptKey; ?>"
										<?php echo $sDisabledText; ?> >
										<?php foreach ( $aOption[ 'value_options' ] as $sOptionValue => $sOptionValueName ) : ?>
											<option value="<?php echo $sOptionValue; ?>"
													id="<?php echo $sOptKey; ?>_<?php echo $sOptionValue; ?>"
												<?php echo ( $sOptionValue == $mOptValue ) ? 'selected="selected"' : ''; ?>
											><?php echo $sOptionValueName; ?></option>
										<?php endforeach; ?>
									</select>

								<?php elseif ( $sOptType == 'multiple_select' ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<select name="<?php echo $sOptKey; ?>[]"
											id="<?php echo $sOptKey; ?>"
											multiple="multiple" multiple
											size="<?php echo count( $aOption[ 'value_options' ] ); ?>"
										<?php echo $sDisabledText; ?> >
										<?php foreach ( $aOption[ 'value_options' ] as $sOptionValue => $sOptionValueName ) : ?>
											<option value="<?php echo $sOptionValue; ?>"
													id="<?php echo $sOptKey; ?>_<?php echo $sOptionValue; ?>"
												<?php echo in_array( $sOptionValue, $mOptValue ) ? 'selected="selected"' : ''; ?>
											><?php echo $sOptionValueName; ?></option>
										<?php endforeach; ?>
									</select>

								<?php elseif ( $sOptType == 'array' ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<textarea name="<?php echo $sOptKey; ?>"
											  id="<?php echo $sOptKey; ?>"
											  placeholder="<?php echo $mOptValue; ?>"
											  rows="<?php echo $aOption[ 'rows' ]; ?>"
											  class="span7" <?php echo $sDisabledText; ?>><?php echo $mOptValue; ?></textarea>

								<?php elseif ( $sOptType == 'comma_separated_lists' ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<textarea name="<?php echo $sOptKey; ?>"
											  id="<?php echo $sOptKey; ?>"
											  placeholder="<?php echo $mOptValue; ?>"
											  rows="<?php echo $aOption[ 'rows' ]; ?>"
											  class="span7" <?php echo $sDisabledText; ?> ><?php echo $mOptValue; ?></textarea>

								<?php elseif ( $sOptType == 'integer' ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<input type="text" name="<?php echo $sOptKey; ?>"
										   id="<?php echo $sOptKey; ?>"
										   value="<?php echo $mOptValue; ?>"
										   placeholder="<?php echo $mOptValue; ?>"
										   class="span7" <?php echo $sDisabledText; ?> />

								<?php else : ?>
									ERROR: Should never reach this point.
								<?php endif; ?>

							</label>
							<p class="help-block"><?php echo $aOption[ 'description' ]; ?></p>
							<div style="clear:both"></div>
						</div>
					</div><!-- controls -->
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>
</form>





<!--<div class="pull-right well">-->
<!--	<h5 style="margin-bottom: 10px;">Options Legend</h5>-->
<!--	<label class="forcheckbox">-->
<!--		<span class="switch">-->
<!--			<input type="checkbox" name="legend" id="legend" value="Y" checked="checked" disabled="disabled">-->
<!--			<span class="icwp-slider round"></span>-->
<!--		</span>-->
<!--		<span class="summary">Option is turned on / enabled</span>-->
<!--	</label>-->
<!--	<label class="forcheckbox">-->
<!--		<span class="switch">-->
<!--			<input type="checkbox" name="legend" id="legend" value="Y" disabled="disabled">-->
<!--			<span class="icwp-slider round"></span>-->
<!--		</span>-->
<!--		<span class="summary">Option is turned off / disabled</span>-->
<!--	</label>-->
<!--</div>-->