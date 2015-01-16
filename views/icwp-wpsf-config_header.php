<?php
include_once( dirname(__FILE__).ICWP_DS.'icwp_options_helper.php' );
include_once( dirname(__FILE__).ICWP_DS.'widgets'.ICWP_DS.'icwp_widgets.php' );

$sPluginName = _wpsf__( 'WordPress Simple Firewall' );
//$fAdminAccessOn = $icwp_aMainOptions['enable_admin_access_restriction'] == 'Y';
//$fFirewallOn = $icwp_aMainOptions['enable_firewall'] == 'Y';
//$fLoginProtectOn = $icwp_aMainOptions['enable_login_protect'] == 'Y';
//$fCommentsFilteringOn = $icwp_aMainOptions['enable_comments_filter'] == 'Y';
//$fLockdownOn = $icwp_aMainOptions['enable_lockdown'] == 'Y';
//$fAutoupdatesOn = $icwp_aMainOptions['enable_autoupdates'] == 'Y';

$sLatestVersionBranch = '2.x.x';
$sOn = _wpsf__( 'On' );
$sOff = _wpsf__( 'Off' );
?>

<div class="wrap">
	<div class="bootstrap-wpadmin <?php echo isset($icwp_sFeatureSlug) ? $icwp_sFeatureSlug : ''; ?>">
		<div class="row">
			<div class="span12">
				<?php include_once( 'icwp-wpsf-state_summary.php' ); ?>
			</div>
		</div>
<?php echo printOptionsPageHeader( $icwp_sFeatureName );
