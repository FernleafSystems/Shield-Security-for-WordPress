<?php
if ( empty($icwp_aSummaryData) ) {
	return;
}
$sInnerSpanSize = 'span4';

function printFeatureSummaryBlock( $fOn, $sName, $sSettingsHref= '', $sInnerSpanSize = 4 ) {
	$sFeatureSummaryBlockNameTemplates = '%s : <span class="feature-enabled-text">%s</span>';
	$sOn = _wpsf__( 'On' );
	$sOff = _wpsf__( 'Off' );
?>
	<div class="span<?php echo $sInnerSpanSize;?> feature-summary-block state-<?php echo strtolower( $fOn? $sOn : $sOff ); ?>"
		 id="feature-<?php echo str_replace( ' ', '', strtolower($sName) ); ?>"
		>
		<div class="row-fluid">
			<div class="feature-icon span3">
			</div>
				<div class="span8 offset1">
					<a class="btn btn-<?php echo $fOn?'success':'warning';?>"
						<?php echo empty($sSettingsHref)? 'disabled="disabled"': sprintf('href="%s"',$sSettingsHref);?>>
						<?php empty($sSettingsHref)? _wpsf_e('See Below') : _wpsf_e('Go To Settings'); ?>
					</a>
				</div>
		</div>
		<div class="feature-name">
			<?php echo sprintf( $sFeatureSummaryBlockNameTemplates, $sName, $fOn? _wpsf__( 'ON' ) : _wpsf__( 'OFF' ) ); ?>
		</div>
	</div>
<?php
}

?>
<style>
	.feature-summary-blocks {
	}
	.feature-summary-blocks .feature-summary-block {
		background-color: #F6F6F6;
		border: 1px solid rgba(0, 0, 0, 0.3);
		border-radius: 3px;
		margin-bottom: 20px;
		padding: 0;
	}
	.feature-summary-blocks .feature-summary-block.state-on {
		background-color: rgba( 102, 216, 45, 0.2 );
	}
	.feature-summary-blocks .feature-summary-block.state-off {
		background-color: rgba( 239, 141, 49, 0.2 );
	}
	.feature-summary-blocks .feature-summary-block .row-fluid .feature-icon {
		padding: 20px;
		text-align: center;
	}
	.feature-summary-blocks .feature-summary-block .row-fluid div {
		line-height: 46px;
		padding: 20px;
		text-align: center;
	}
	.feature-summary-blocks .feature-summary-block .feature-icon a {
		margin-left: 53px;
	}
	.feature-summary-blocks .feature-summary-block .feature-name {
		border-top: 1px dashed #999999;
		padding: 5px 20px;
	}
	.feature-summary-blocks .feature-summary-block .feature-name .feature-enabled-text {
		float: right;
	}
	.feature-summary-blocks .feature-summary-block .feature-icon:before {
		-webkit-font-smoothing: antialiased;
		display: inline-block;
		font: 48px/1 'dashicons';
		vertical-align: top;
	}
	#feature-dashboard .feature-icon:before,
	#feature-adminaccessrestriction .feature-icon:before {
		content: "\f332";
	}
	#feature-firewall .feature-icon:before {
		content: "\f479";
	}
	#feature-usermanagement .feature-icon:before {
		content: "\f307";
	}
	#feature-loginprotection .feature-icon:before {
		content: "\f112";
	}
	#feature-commentsfilter .feature-icon:before {
		content: "\f125";
	}
	#feature-automaticupdates .feature-icon:before {
		content: "\f463";
	}
	#feature-lockdown .feature-icon:before {
		content: "\f160";
	}
	#feature-audittrail .feature-icon:before {
		content: "\f115";
	}

</style>

<div class="row-fluid">
	<div class="span">
		<h3><?php _wpsf_e('Plugin Activated Features Summary:');?></h3>
	</div>
</div>
<div class="row-fluid feature-summary-blocks">
<?php
foreach( $icwp_aSummaryData as $nKey => $aSummary ) {
	if ( $nKey > 0 && ($nKey % 3 == 0) ) {
		echo '</div><div class="row-fluid feature-summary-blocks">';
	}
	$sPage = isset( $_GET['page'] )? $_GET['page'] : '';
	printFeatureSummaryBlock( $aSummary[0], $aSummary[1], ( $sPage==$aSummary[2])? '' : network_admin_url( 'admin.php?page='.$aSummary[2] ) );
}
?>
</div>
