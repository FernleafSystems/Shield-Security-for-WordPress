<form action="<?php echo $form_action; ?>" method="post" class="icwpOptionsForm" novalidate="novalidate"
	  autocomplete="off">
	<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo $data[ 'form_nonce' ] ?>">
    <input type="hidden" name="mod_slug" value="<?php echo $data[ 'mod_slug' ]; ?>" />
    <input type="hidden" name="plugin_form_submit" value="Y" />

	<?php foreach ( $ajax[ 'mod_options' ] as $sAjKey => $sAjVal ) : ?>
		<input type="hidden" name="<?php echo $sAjKey; ?>" value="<?php echo $sAjVal; ?>" />
	<?php endforeach; ?>

	<div style="margin-bottom: -1px">
	<div class="row no-gutters">
		<div class="col">
			<div class="module-headline">
				<h4>
					<span class="headline-title"><?php echo $sPageTitle; ?></span>
					<div class="float-right">
						<button type="submit" class="btn btn-primary icwp-form-button"
								name="submit" style="margin-right: 12px">
							<?php echo $strings[ 'btn_save' ]; ?>
						</button>

						<div class="btn-group" role="group" aria-label="Basic example">

							<a aria-disabled="true" class="btn btn-success disabled icwp-carousel-0"
							   href="javascript:void(0)">
								<?php echo $strings[ 'btn_options' ]; ?></a>

							<?php if ( $flags[ 'has_wizard' ] ) : ?>
								<a class="btn btn-outline-dark btn-icwp-wizard icwp-carousel-1"
								   title="Launch Guided Walk-Through Wizards" href="javascript:void(0)">
								<?php echo $strings[ 'btn_wizards' ]; ?></a>
							<?php else : ?>
								<a class="btn btn-outline-dark btn-icwp-wizard disabled"
								   href="javascript:{}"
								   title="No Wizards for this module."
								<?php echo $strings[ 'btn_wizards' ]; ?></a>
							<?php endif; ?>

							<a class="btn btn-outline-info icwp-carousel-2" href="javascript:void(0)">
								<?php echo $strings[ 'btn_help' ]; ?></a>
						</div>
					</div>
					<small class="module-tagline"><?php echo $sTagline; ?></small>
				</h4>
			</div>
		</div>
	</div>

	<div class="row no-gutters">
		<div class="col-2 smoothwidth">

			<ul id="ModuleOptionsNav" class="nav flex-column"
				role="tablist" aria-orientation="vertical">
				<?php foreach ( $data[ 'all_options' ] as $aOptSection ) : ?>
					<li class="nav-item">
					<a class="nav-link <?php echo $aOptSection[ 'primary' ] ? 'active' : '' ?>"
					   id="pills-tab-<?php echo $aOptSection[ 'slug' ]; ?>"
					   data-toggle="pill" href="#pills-<?php echo $aOptSection[ 'slug' ]; ?>"
					   role="tab" aria-controls="pills-<?php echo $aOptSection[ 'slug' ]; ?>"
						<?php echo $aOptSection[ 'primary' ] ? 'aria-selected="true"' : '' ?>
					><?php echo $aOptSection[ 'title_short' ]; ?></a>
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
			<legend><?php echo $aOptSection[ 'title' ]; ?></legend>

			<div class="row_section_summary row">
				<div class="col-8">
					<?php if ( !empty( $aOptSection[ 'summary' ] ) ) : ?>
						<?php foreach ( $aOptSection[ 'summary' ] as $sItem ) : ?>
							<p class="noselect"><?php echo $sItem; ?></p>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<div class="col-4">
					<?php if ( !empty( $aOptSection[ 'help_video' ] ) ) : ?>
						<button class="btn btn-lg btn-outline-info section_help_video" type="button"
								data-toggle="collapse"
								data-target="#sectionVideo<?php echo $aOptSection[ 'help_video' ][ 'id' ]; ?>"
								aria-expanded="false"
								aria-controls="sectionVideo<?php echo $aOptSection[ 'help_video' ][ 'id' ]; ?>">
						  <span class="dashicons dashicons-controls-play"></span> Watch The Video</button>
					<?php endif; ?>
				</div>

				<?php if ( !empty( $aOptSection[ 'help_video' ] ) ) : ?>
					<div class="w-100"></div>
					<div class="col">
						<div class="collapse section_video"
							 id="sectionVideo<?php echo $aOptSection[ 'help_video' ][ 'id' ]; ?>">
							<div class="embed-responsive embed-responsive-16by9">
								<iframe src="<?php echo $aOptSection[ 'help_video' ][ 'embed_url' ]; ?>"
										width="640" height="360" class="embed-responsive-item"
										allowfullscreen></iframe>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( !empty( $aOptSection[ 'warnings' ] ) ) : ?>
				<div class="row">
					<div class="col">
						<?php foreach ( $aOptSection[ 'warnings' ] as $sWarning ) : ?>
							<div class="alert alert-warning text-center"><?php echo $sWarning; ?></div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( !empty( $aOptSection[ 'notices' ] ) ) : ?>
				<div class="row">
					<div class="col">
						<?php foreach ( $aOptSection[ 'notices' ] as $sNotices ) : ?>
							<div class="alert alert-info text-center"><?php echo $sNotices; ?></div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php foreach ( $aOptSection[ 'options' ] as $nKeyRow => $aOption ) :
				$sOptKey = $aOption[ 'key' ];
				$mOptValue = $aOption[ 'value' ];
				$sOptType = $aOption[ 'type' ];
				$bEnabled = $aOption[ 'enabled' ];
				$sDisabledText = $bEnabled ? '' : 'disabled="Disabled"';
				?>
				<div class="form-group row row_number_<?php echo $nKeyRow; ?>">

					<label class="form-label col-3 col-form-label" for="Opt-<?php echo $sOptKey; ?>">
						<div class="form-label-inner text-right">
							<div class="optname"><?php echo $aOption[ 'name' ]; ?></div>
							<?php if ( !empty( $aOption[ 'link_info' ] ) ) : ?>
								<span class="optlinks">[
										<a href="<?php echo $aOption[ 'link_info' ]; ?>"
										   target="_blank"><?php echo $strings[ 'more_info' ]; ?></a>
									<?php if ( !empty( $aOption[ 'link_blog' ] ) ) : ?>
										| <a href="<?php echo $aOption[ 'link_blog' ]; ?>"
											 target="_blank"><?php echo $strings[ 'blog' ]; ?></a>
									<?php endif; ?>
													   ]</span>
							<?php endif; ?>
						</div>
					</label>

					<div class="col-8  option_container
						<?php echo $bEnabled ? 'enabled' : 'disabled overlay_container' ?>">

						<?php if ( !$bEnabled ) : ?>
							<div class="option_overlay">
								<div class="overlay_message">
									<a href="<?php echo $hrefs[ 'go_pro' ]; ?>" target="_blank">
										Sorry, this is a premium-only feature</a>
								</div>
							</div>
						<?php endif; ?>

						<div class="option_section <?php echo ( $mOptValue == 'Y' ) ? 'selected_item' : ''; ?>"
							 id="option_section_<?php echo $sOptKey; ?>">

							<label id="Label-<?php echo $sOptKey ?>" class="for<?php echo $sOptType; ?>"
								   for="Opt-<?php echo $sOptKey; ?>">

								<?php if ( $sOptType == 'checkbox' ) : ?>
									<span class="icwp-switch">
										<input type="checkbox"
											   name="<?php echo $sOptKey; ?>" id="Opt-<?php echo $sOptKey; ?>"
											   value="Y" <?php echo ( $mOptValue == 'Y' ) ? 'checked="checked"' : ''; ?>
											   aria-labelledby="Label-<?php echo $sOptKey ?>"
											<?php echo $sDisabledText; ?> />
										<span class="icwp-slider round"></span>
									</span>
									<span class="summary"><?php echo $aOption[ 'summary' ]; ?></span>

								<?php elseif ( $sOptType == 'text' ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<textarea name="<?php echo $sOptKey; ?>"
											  id="Opt-<?php echo $sOptKey; ?>"
											  placeholder="<?php echo $mOptValue; ?>"
											  rows="<?php echo $aOption[ 'rows' ]; ?>"
										<?php echo $sDisabledText; ?>
									><?php echo $mOptValue; ?></textarea>

								<?php elseif ( $sOptType == 'noneditable_text' ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<input type="text" value="<?php echo $mOptValue; ?>" readonly />

								<?php elseif ( $sOptType == 'password' ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<input type="password" name="<?php echo $sOptKey; ?>" class="col form-control"
										   id="Opt-<?php echo $sOptKey; ?>" value="<?php echo $mOptValue; ?>"
										   placeholder="<?php echo $strings[ 'supply_password' ]; ?>"
										   style="margin-bottom: 5px"
										<?php echo $sDisabledText; ?> />

									<input type="password" name="<?php echo $sOptKey; ?>_confirm"
										   id="Opt-<?php echo $sOptKey; ?>_confirm" class="col form-control"
										   placeholder="<?php echo $strings[ 'confirm_password' ]; ?>"
										<?php echo $sDisabledText; ?> />

								<?php elseif ( $sOptType == 'email' ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<input type="email" name="<?php echo $sOptKey; ?>"
										   id="Opt-<?php echo $sOptKey; ?>"
										   value="<?php echo $mOptValue; ?>"
										   placeholder="<?php echo $mOptValue; ?>"
										<?php echo $sDisabledText; ?> />

								<?php elseif ( $sOptType == 'select' ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<select name="<?php echo $sOptKey; ?>"
											id="Opt-<?php echo $sOptKey; ?>"
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
											id="Opt-<?php echo $sOptKey; ?>"
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

								<?php elseif ( in_array( $sOptType, array( 'comma_separated_lists', 'array' ) ) ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<textarea name="<?php echo $sOptKey; ?>"
											  id="Opt-<?php echo $sOptKey; ?>"
											  placeholder="<?php echo $mOptValue; ?>"
											  rows="<?php echo $aOption[ 'rows' ]; ?>"
										<?php echo $sDisabledText; ?>
									><?php echo $mOptValue; ?></textarea>

								<?php elseif ( $sOptType == 'integer' ) : ?>

									<p><?php echo $aOption[ 'summary' ]; ?></p>
									<input type="text" name="<?php echo $sOptKey; ?>"
										   id="Opt-<?php echo $sOptKey; ?>"
										   value="<?php echo $mOptValue; ?>"
										   placeholder="<?php echo $mOptValue; ?>"
										<?php echo $sDisabledText; ?> />

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