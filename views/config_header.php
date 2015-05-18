<?php
$sBaseDirName = dirname(__FILE__).ICWP_DS;
include_once( $sBaseDirName . 'icwp_options_helper.php' );
include_once( $sBaseDirName.'widgets'.ICWP_DS.'icwp_widgets.php' );

$sPluginName = _wpsf__( 'WordPress Simple Firewall' );
//$fAdminAccessOn = $aMainOptions['enable_admin_access_restriction'] == 'Y';
//$fFirewallOn = $aMainOptions['enable_firewall'] == 'Y';
//$fLoginProtectOn = $aMainOptions['enable_login_protect'] == 'Y';
//$fCommentsFilteringOn = $aMainOptions['enable_comments_filter'] == 'Y';
//$fLockdownOn = $aMainOptions['enable_lockdown'] == 'Y';
//$fAutoupdatesOn = $aMainOptions['enable_autoupdates'] == 'Y';

$sLatestVersionBranch = '2.x.x';
$sOn = _wpsf__( 'On' );
$sOff = _wpsf__( 'Off' );
?>

<div class="wrap">
	<div class="bootstrap-wpadmin <?php echo isset($sFeatureSlug) ? $sFeatureSlug : ''; ?> icwp-options-page">
		<div class="row">
			<div class="span12">
				<?php include_once( $sBaseDirName.'snippets'.ICWP_DS.'state_summary.php' ); ?>
			</div>
		</div>
<?php
printOptionsPageHeader( $sFeatureName );
