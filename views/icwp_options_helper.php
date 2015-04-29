<?php

function printOptionsPageHeader( $insSection = '' ) {
	$sLinkedIcwp = '<a href="http://icwp.io/3a" target="_blank">iControlWP</a>';
	echo '<div class="page-header">';
	echo '<h2><a id="pluginlogo_32" class="header-icon32" href="http://icwp.io/2k" target="_blank"></a>';
	$sBaseTitle = sprintf( _wpsf__( 'WordPress Simple Firewall (from %s)' ), $sLinkedIcwp );
	if ( !empty($insSection) ) {
		echo sprintf( '%s :: %s', $insSection, $sBaseTitle );
	}
	else {
		echo $sBaseTitle;
	}
	echo '</h2></div>';
}

function printAllPluginOptionsForm( $inaAllPluginOptions, $insVarPrefix = '', $iOptionsPerRow = 1 ) {
	
	if ( empty($inaAllPluginOptions) ) {
		return;
	}

	$iRowWidth = 8; //8 spans.
	$iOptionWidth = $iRowWidth / $iOptionsPerRow;

	//Take each Options Section in turn
	foreach ( $inaAllPluginOptions as $sOptionSection ) {

		$fIsPrimarySection = isset( $sOptionSection['section_primary'] ) && $sOptionSection['section_primary'];

		$sRowId = str_replace( ' ', '', $sOptionSection['section_title'] );
		//Print the Section Title
		echo '
				<div class="row option_section_row '.( $fIsPrimarySection? 'primary_section' : 'non_primary_section' ).'" id="'.$sRowId.'">
					<div class="span'.( $fIsPrimarySection? '9' : '9' ).'">
						<fieldset>
							<legend>'.$sOptionSection['section_title'].'</legend>
		';
		
		$rowCount = 1;
		$iOptionCount = 0;
		//Print each option in the option section
		foreach ( $sOptionSection['section_options'] as $aOption ) {
			
			$iOptionCount = $iOptionCount % $iOptionsPerRow;

			if ( $iOptionCount == 0 ) {
				echo '
				<div class="row row_number_'.$rowCount.'">';
			}
			
			echo getPluginOptionSpan( $aOption, $iOptionWidth, $insVarPrefix );

			$iOptionCount++;

			if ( $iOptionCount == $iOptionsPerRow ) {
				echo '
				</div> <!-- / options row -->';
				$rowCount++;
			}
	
		}//foreach option
	
		echo '
					</fieldset>
				</div>
			</div>
		';

	}

}

function printAllPluginOptionsFormTabs( $inaAllPluginOptions, $insVarPrefix = '', $iOptionsPerRow = 1 ) {

	if ( empty($inaAllPluginOptions) ) {
		return;
	}


	$iRowWidth = 8; //8 spans.
	$iOptionWidth = $iRowWidth / $iOptionsPerRow;

	$nTabCount = 1;
	//Take each Options Section in turn
	?><ul class="nav nav-tabs"><?php
	foreach ( $inaAllPluginOptions as $sOptionSection ) {
		?>
		<li class="<?php echo ( $nTabCount == 1 )? 'active' : '' ?>">
			<a href="#<?php echo $sOptionSection['section_slug'] ?>" data-toggle="tab" >
				<?php echo $sOptionSection['section_title_short']; ?>
			</a>
		</li>
		<?php
		$nTabCount++;
	}
	?></ul><?php

	$nTabCount = 1;
	?><div class="tab-content"><?php
	foreach ( $inaAllPluginOptions as $sOptionSection ) {

		$aTabClasses = array( 'tab-pane', 'fade' );
		if ( $nTabCount == 1 ) {
			$aTabClasses[] = 'active';
			$aTabClasses[] = 'in';
		}
		if ( isset( $sOptionSection['section_primary'] ) && $sOptionSection['section_primary'] ) {
			$aTabClasses[] = ( isset( $sOptionSection['section_primary'] ) && $sOptionSection['section_primary'] ) ? 'primary_section' : 'non_primary_section';
		}

		?><div class="tab-pane <?php echo implode( ' ', $aTabClasses ); ?>" id="<?php echo $sOptionSection['section_slug'] ?>"><?php

		$fIsPrimarySection = isset( $sOptionSection['section_primary'] ) && $sOptionSection['section_primary'];

		$sRowId = str_replace( array(' ',':'), '', $sOptionSection['section_title'] );
		//Print the Section Title
		echo '
				<div class="row option_section_row '.( $fIsPrimarySection? 'primary_section' : 'non_primary_section' ).'" id="'.$sRowId.'">
					<div class="span'.( $fIsPrimarySection? '9' : '9' ).'">
						<fieldset>
							<legend>'.$sOptionSection['section_title'].'</legend>
		';

		$rowCount = 1;
		$iOptionCount = 0;
		//Print each option in the option section
		foreach ( $sOptionSection['section_options'] as $aOption ) {

			$iOptionCount = $iOptionCount % $iOptionsPerRow;

			if ( $iOptionCount == 0 ) {
				echo '
				<div class="row row_number_'.$rowCount.'">';
			}

			echo getPluginOptionSpan( $aOption, $iOptionWidth, $insVarPrefix );

			$iOptionCount++;

			if ( $iOptionCount == $iOptionsPerRow ) {
				echo '
				</div> <!-- / options row -->';
				$rowCount++;
			}

		}//foreach option

		echo '
					</fieldset>
				</div>
			</div>
		';

		?></div><?php
		$nTabCount++;
	}
	?></div><?php

}

function getPluginOptionSpan( $aOption, $nSpanSize, $insVarPrefix = '' ) {
	
	$sOptionKey = $aOption['key'];
	$sOptionSaved = $aOption['value'];
	$sOptionDefault = $aOption['default'];
	$sOptionType = $aOption['type'];
	$aPossibleOptions = $aOption['value_options'];
	$sHelpLink = $aOption['info_link'];
	$sBlogLink = $aOption['blog_link'];
	$sOptionHumanName = $aOption['name'];
	$sOptionTitle = $aOption['summary'];
	$sOptionHelpText = $aOption['description'];

	if ( $sOptionKey == 'spacer' ) {
		$sHtml = '
			<div class="span'.$nSpanSize.'">
			</div>
		';
	}
	else {

		$sLink = '';
		$sLinkTemplate = '<br /><span>[%s]</span>';
		if ( !empty($sHelpLink) ) {
			$sLink = sprintf( $sLinkTemplate, '<a href="'.$sHelpLink.'" target="_blank">'._wpsf__('More Info').'</a>%s' );
			if ( !empty( $sBlogLink ) ) {
				$sLink = sprintf( $sLink, ' | <a href="'.$sBlogLink.'" target="_blank">'._wpsf__('Blog').'</a>' );
			}
			else {
				$sLink = sprintf( $sLink, '' );
			}
		}

		$sSpanId = 'span_'.$insVarPrefix.$sOptionKey;
		$sHtml = '
			<div class="item_group span'.$nSpanSize.' '.( ($sOptionSaved === 'Y' || $sOptionSaved != $sOptionDefault )? ' selected_item_group':'' ).'" id="'.$sSpanId.'">
				<div class="control-group">
					<label class="control-label" for="'.$insVarPrefix.$sOptionKey.'">'.$sOptionHumanName.$sLink.'</label>
					<div class="controls">
					  <div class="option_section'.( ($sOptionSaved == 'Y')? ' selected_item':'' ).'" id="option_section_'.$insVarPrefix.$sOptionKey.'">
						<label>
		';
		$sAdditionalClass = '';
		$sHelpSection = '';
		
		if ( $sOptionType === 'checkbox' ) {
			
			$sChecked = ( $sOptionSaved == 'Y' )? 'checked="checked"' : '';
			
			$sHtml .= '
				<input '.$sChecked.'
						type="checkbox"
						name="'.$insVarPrefix.$sOptionKey.'"
						value="Y"
						class="'.$sAdditionalClass.'"
						id="'.$insVarPrefix.$sOptionKey.'" />
						'.$sOptionTitle;

		}
		else if ( $sOptionType === 'text' ) {
			$sTextInput = esc_attr( stripslashes( $sOptionSaved ) );
			$sHtml .= '
				<p>'.$sOptionTitle.'</p>
				<input type="text"
						name="'.$insVarPrefix.$sOptionKey.'"
						value="'.$sTextInput.'"
						placeholder="'.$sTextInput.'"
						id="'.$insVarPrefix.$sOptionKey.'"
						class="span5" />';
		}
		else if ( $sOptionType === 'password' ) {
			$sTextInput = esc_attr( $sOptionSaved );
			$sHtml .= '
				<p>'.$sOptionTitle.'</p>
				<input type="password"
						name="'.$insVarPrefix.$sOptionKey.'"
						value="'.$sTextInput.'"
						placeholder="'.$sTextInput.'"
						id="'.$insVarPrefix.$sOptionKey.'"
						class="span5" />';
		}
		else if ( $sOptionType === 'email' ) {
			$sTextInput = esc_attr( $sOptionSaved );
			$sHtml .= '
				<p>'.$sOptionTitle.'</p>
				<input type="text"
						name="'.$insVarPrefix.$sOptionKey.'"
						value="'.$sTextInput.'"
						placeholder="'.$sTextInput.'"
						id="'.$insVarPrefix.$sOptionKey.'"
						class="span5" />';

		}
		else if ( $sOptionType === 'select' ) {

			$sFragment = '<p>'.$sOptionTitle.'</p>
				<select
				id="'.$insVarPrefix.$sOptionKey.'"
				name="'.$insVarPrefix.$sOptionKey.'">';

			foreach( $aPossibleOptions as $mOptionValue => $sOptionName ) {

				$fSelected = $sOptionSaved == $mOptionValue;
				$sFragment .= '
					<option
					value="'.$mOptionValue.'"
					id="'.$insVarPrefix.$sOptionKey.'_'.$mOptionValue.'"'
					.( $fSelected? ' selected="selected"' : '') .'>'. $sOptionName.'</option>';
			}
			$sFragment .= '</select>';
			$sHtml .= $sFragment;
		}
		else if ( $sOptionType === 'multiple_select' ) {

			$sFragment = '<p>'.$sOptionTitle.'</p>
				<select
				id="'.$insVarPrefix.$sOptionKey.'"
				name="'.$insVarPrefix.$sOptionKey.'[]" multiple multiple="multiple" size="'.count( $aPossibleOptions ).'">';

			foreach( $aPossibleOptions as $mOptionValue => $sOptionName ) {

				$fSelected = in_array( $mOptionValue, $sOptionSaved );
				$sFragment .= '<option
					value="'.$mOptionValue.'"
					id="'.$insVarPrefix.$sOptionKey.'_'.$mOptionValue.'"'
					.( $fSelected? ' selected="selected"' : '') .'>'. $sOptionName.'</option>';
			}
			$sFragment .= '</select>';
			$sHtml .= $sFragment;
		}
		else if ( $sOptionType === 'ip_addresses' ) {
			$sTextInput = esc_attr( $sOptionSaved );
			$nRows = substr_count( $sTextInput, "\n" ) + 1;
			$sHtml .= '
				<p>'.$sOptionTitle.'</p>
				<textarea type="text"
						name="'.$insVarPrefix.$sOptionKey.'"
						placeholder="'.$sTextInput.'"
						id="'.$insVarPrefix.$sOptionKey.'"
						rows="'.$nRows.'"
						class="span5">'.$sTextInput.'</textarea>';

			$sOptionHelpText = '<p class="help-block">'.$sOptionHelpText.'</p>';

		}
		else if ( $sOptionType === 'array' ) {
			$sTextInput = esc_attr( $sOptionSaved );
			$nRows = substr_count( $sTextInput, "\n" ) + 1;
			$sHtml .= '
				<p>'.$sOptionTitle.'</p>
				<textarea type="text"
						name="'.$insVarPrefix.$sOptionKey.'"
						placeholder="'.$sTextInput.'"
						id="'.$insVarPrefix.$sOptionKey.'"
						rows="'.$nRows.'"
						class="span5">'.$sTextInput.'</textarea>';

			$sOptionHelpText = '<p class="help-block">'.$sOptionHelpText.'</p>';

		}
		else if ( $sOptionType === 'yubikey_unique_keys' ) {
			$sTextInput = esc_attr( $sOptionSaved );
			$nRows = substr_count( $sTextInput, "\n" ) + 1;
			$sHtml .= '
				<p>'.$sOptionTitle.'</p>
				<textarea type="text"
						name="'.$insVarPrefix.$sOptionKey.'"
						placeholder="'.$sTextInput.'"
						id="'.$insVarPrefix.$sOptionKey.'"
						rows="'.$nRows.'"
						class="span5">'.$sTextInput.'</textarea>';

			$sOptionHelpText = '<p class="help-block">'.$sOptionHelpText.'</p>';

		}
		else if ( $sOptionType === 'comma_separated_lists' ) {
			$sTextInput = esc_attr( $sOptionSaved );
			$nRows = substr_count( $sTextInput, "\n" ) + 1;
			$sHtml .= '
				<p>'.$sOptionTitle.'</p>
				<textarea type="text"
						name="'.$insVarPrefix.$sOptionKey.'"
						placeholder="'.$sTextInput.'"
						id="'.$insVarPrefix.$sOptionKey.'"
						rows="'.$nRows.'"
						class="span5">'.$sTextInput.'</textarea>';
		}
		else if ( $sOptionType === 'integer' ) {
			$sTextInput = esc_attr( $sOptionSaved );
			$sHtml .= '
				<p>'.$sOptionTitle.'</p>
				<input type="text"
						name="'.$insVarPrefix.$sOptionKey.'"
						value="'.$sTextInput.'"
						placeholder="'.$sTextInput.'"
						id="'.$insVarPrefix.$sOptionKey.'"
						class="span5" />';
		}
		else {
			$sHtml .= 'we should never reach this point';
		}

//		$sOptionHelpText = '<p class="help-block">'
//			.$sOptionHelpText
//			.( isset($sHelpLink)? '<br /><span class="help-link">['.$sHelpLink.']</span>':'' )
//			.'</p>';
		
		$sOptionHelpText = '<p class="help-block">'.$sOptionHelpText.'</p>';

		$sHtml .= '
						</label>
						'.$sOptionHelpText.'
						<div style="clear:both"></div>
					  </div>
					</div><!-- controls -->'
					.$sHelpSection.'
				</div><!-- control-group -->
			</div>
		';
	}
	
	return $sHtml;
}
