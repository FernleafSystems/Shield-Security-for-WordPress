<div id="icwpOptionsFormContainer">
<form action="<?php echo $form_action; ?>" method="post" class="form-horizontal icwpOptionsForm">
	<?php echo $nonce_field; ?>

	<ul class="nav nav-tabs">
		<?php foreach ( $aAllOptions as $sOptionSection ) : ?>
			<li class="<?php echo $sOptionSection['primary'] ? 'active' : '' ?>">
				<a href="#<?php echo $sOptionSection['slug'] ?>" data-toggle="tab" >
					<?php echo $sOptionSection['title_short']; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="tab-content">
		<?php foreach ( $aAllOptions as $sOptionSection ) : ?>

			<div class="tab-pane fade <?php echo $sOptionSection['primary'] ? 'active in primary_section' : 'non_primary_section'; ?>"
				 id="<?php echo $sOptionSection['slug'] ?>">
				<div class="row-fluid option_section_row <?php echo $sOptionSection['primary'] ? 'primary_section' : 'non_primary_section'; ?>"
					 id="row-<?php echo $sOptionSection['slug']; ?>">
					<div class="span12 options-body">
							<legend>
                                <?php echo $sOptionSection['title']; ?>
                                <?php if ( !empty( $sOptionSection['help_video_url'] ) ) : ?>
                                    <div style="float:right;">

                                        <a href="<?php echo $sOptionSection['help_video_url']; ?>"
                                           class="btn"
                                           data-featherlight-iframe-height="454"
                                           data-featherlight-iframe-width="772"
                                           data-featherlight="iframe">
                                            <span class="dashicons dashicons-controls-play"></span> Help Video
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </legend>

							<?php if ( !empty( $sOptionSection['summary'] ) ) : ?>
								<div class="row-fluid row_section_summary">
									<div class="span12">
										<?php foreach( $sOptionSection['summary'] as $sItem ) : ?>
											<p class="noselect"><?php echo $sItem; ?></p>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>

							<?php foreach( $sOptionSection['options'] as $nKeyRow => $aOption ) :
								$sOptionKey = $aOption['key'];
								$sOptionType = $aOption['type'];
								$bEnabled = $aOption[ 'enabled' ];
								$sDisabledText = $bEnabled ? '' : 'disabled="Disabled"';
								?>
								<div class="row-fluid option_row row_number_<?php echo $nKeyRow; ?>">
									<div class="item_group span12
												<?php echo $bEnabled ? 'enabled' : 'disabled overlay_container' ?>
												<?php echo ( $aOption['value'] == 'Y' || $aOption['value'] != $aOption['default'] ) ? 'selected_item_group':''; ?>"
										 id="span_<?php echo $var_prefix.$sOptionKey; ?>">

										<?php if ( !$bEnabled ) : ?>
											<div class="option_overlay">
												<div class="overlay_message">
													<a href="<?php echo $hrefs['go_pro']; ?>" target="_blank">
														This is premium feature</a>
												</div>
											</div>
										<?php endif; ?>

										<div class="control-group">
											<label class="control-label" for="<?php echo $var_prefix.$sOptionKey; ?>">
												<span class="optname"><?php echo $aOption['name']; ?></span>
												<?php if ( !empty( $aOption['link_info'] ) ) : ?>
												<span class="optlinks">
													[
													<a href="<?php echo $aOption['link_info']; ?>" target="_blank"><?php echo $strings['more_info']; ?></a>
													<?php if ( !empty( $aOption['link_blog'] ) ) : ?>
														| <a href="<?php echo $aOption['link_blog']; ?>" target="_blank"><?php echo $strings['blog']; ?></a>
													<?php endif; ?>
													]
												</span>
												<?php endif; ?>
											</label>
											<div class="controls">
												<div class="option_section <?php echo ( $aOption['value'] == 'Y' ) ? 'selected_item':''; ?>"
													 id="option_section_<?php echo $var_prefix.$sOptionKey; ?>">
													<label>
														<?php if ( $sOptionType == 'checkbox' ) : ?>

															<input type="checkbox" name="<?php echo $var_prefix.$sOptionKey; ?>" id="<?php echo $var_prefix.$sOptionKey; ?>"
																   value="Y" <?php echo ( $aOption['value'] == 'Y' ) ? 'checked="checked"':''; ?>
																	<?php echo $sDisabledText; ?> />
															<?php echo $aOption['summary']; ?>

														<?php elseif ( $sOptionType == 'text' ) : ?>

															<p><?php echo $aOption['summary']; ?></p>
															<textarea name="<?php echo $var_prefix.$sOptionKey; ?>" id="<?php echo $var_prefix.$sOptionKey; ?>"
																	  placeholder="<?php echo $aOption['value']; ?>" rows="<?php echo $aOption['rows']; ?>"
																	  class="span5" <?php echo $sDisabledText; ?>><?php echo $aOption['value']; ?></textarea>

														<?php elseif ( $sOptionType == 'noneditable_text' ) : ?>

															<p><?php echo $aOption['summary']; ?></p>
															<input type="text" readonly value="<?php echo $aOption['value']; ?>" class="span5" />

														<?php elseif ( $sOptionType == 'password' ) : ?>

															<p><?php echo $aOption['summary']; ?></p>
															<input type="password" name="<?php echo $var_prefix.$sOptionKey; ?>" id="<?php echo $var_prefix.$sOptionKey; ?>"
																   value="<?php echo $aOption['value']; ?>" placeholder="<?php echo $aOption['value']; ?>"
																   class="span5" <?php echo $sDisabledText; ?> />

														<?php elseif ( $sOptionType == 'email' ) : ?>

															<p><?php echo $aOption['summary']; ?></p>
															<input type="email" name="<?php echo $var_prefix.$sOptionKey; ?>" id="<?php echo $var_prefix.$sOptionKey; ?>"
																   value="<?php echo $aOption['value']; ?>" placeholder="<?php echo $aOption['value']; ?>"
																   class="span5" <?php echo $sDisabledText; ?> />

														<?php elseif ( $sOptionType == 'select' ) : ?>

															<p><?php echo $aOption['summary']; ?></p>
															<select name="<?php echo $var_prefix.$sOptionKey; ?>" id="<?php echo $var_prefix.$sOptionKey; ?>"
																<?php echo $sDisabledText; ?> >
																<?php foreach( $aOption['value_options'] as $sOptionValue => $sOptionValueName ) : ?>
																	<option value="<?php echo $sOptionValue; ?>" id="<?php echo $var_prefix.$sOptionKey; ?>_<?php echo $sOptionValue; ?>"
																		<?php echo ( $sOptionValue == $aOption['value'] ) ? 'selected="selected"' : ''; ?>
																		><?php echo $sOptionValueName; ?></option>
																<?php endforeach; ?>
															</select>

														<?php elseif ( $sOptionType == 'multiple_select' ) : ?>

															<p><?php echo $aOption['summary']; ?></p>
															<select name="<?php echo $var_prefix.$sOptionKey; ?>[]" id="<?php echo $var_prefix.$sOptionKey; ?>"
																	multiple="multiple" multiple size="<?php echo count( $aOption['value_options'] ); ?>"
																<?php echo $sDisabledText; ?> >
																<?php foreach( $aOption['value_options'] as $sOptionValue => $sOptionValueName ) : ?>
																	<option value="<?php echo $sOptionValue; ?>" id="<?php echo $var_prefix.$sOptionKey; ?>_<?php echo $sOptionValue; ?>"
																		<?php echo in_array( $sOptionValue, $aOption['value'] ) ? 'selected="selected"' : ''; ?>
																		><?php echo $sOptionValueName; ?></option>
																<?php endforeach; ?>
															</select>

														<?php elseif ( $sOptionType == 'array' ) : ?>

															<p><?php echo $aOption['summary']; ?></p>
															<textarea name="<?php echo $var_prefix.$sOptionKey; ?>" id="<?php echo $var_prefix.$sOptionKey; ?>"
																	  placeholder="<?php echo $aOption['value']; ?>" rows="<?php echo $aOption['rows']; ?>"
																	  class="span5" <?php echo $sDisabledText; ?>><?php echo $aOption['value']; ?></textarea>

														<?php elseif ( $sOptionType == 'comma_separated_lists' ) : ?>

															<p><?php echo $aOption['summary']; ?></p>
															<textarea name="<?php echo $var_prefix.$sOptionKey; ?>" id="<?php echo $var_prefix.$sOptionKey; ?>"
																	  placeholder="<?php echo $aOption['value']; ?>" rows="<?php echo $aOption['rows']; ?>"
																	  class="span5" <?php echo $sDisabledText; ?> ><?php echo $aOption['value']; ?></textarea>

														<?php elseif ( $sOptionType == 'integer' ) : ?>

															<p><?php echo $aOption['summary']; ?></p>
															<input type="text" name="<?php echo $var_prefix.$sOptionKey; ?>" id="<?php echo $var_prefix.$sOptionKey; ?>"
																   value="<?php echo $aOption['value']; ?>" placeholder="<?php echo $aOption['value']; ?>"
																   class="span5" <?php echo $sDisabledText; ?> />

														<?php else : ?>
															ERROR: Should never reach this point.
														<?php endif; ?>

													</label>
													<p class="help-block"><?php echo  $aOption['description']; ?></p>
													<div style="clear:both"></div>
												</div>
											</div><!-- controls -->
										</div><!-- control-group -->
									</div>
								</div>
							<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="form-actions">
		<input type="hidden" name="<?php echo $var_prefix; ?>feature_slug" value="<?php echo $feature_slug; ?>" />
		<input type="hidden" name="<?php echo $var_prefix; ?>all_options_input" value="<?php echo $all_options_input; ?>" />
		<input type="hidden" name="<?php echo $var_prefix; ?>plugin_form_submit" value="Y" />
		<button type="submit" class="btn btn-success btn-large icwp-form-button" name="submit"><?php _wpsf_e( 'Save All Settings' ); ?></button>
	</div>
</form>
</div>