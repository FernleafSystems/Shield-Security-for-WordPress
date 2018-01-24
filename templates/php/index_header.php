<?php
$sBaseDirName = dirname( __FILE__ ).DIRECTORY_SEPARATOR;
include_once( $sBaseDirName.'widgets/icwp_widgets.php' ); ?>

<style>
	#wpbody {
		background-color: #ffffff;
	}
	#wpbody-content {
	}
	#ModulePageTopRow {
		min-width: 760px; /** prevents col breaking **/
	}
	#TopPluginIcon {
		height: 62px;
		background-position: 48%;
	}
	#ColumnOptions {
		background-color: #f6f6f6;
		box-shadow: 2px 2px 3px rgba(0, 0, 0, 0.2);
		min-width: 590px; /** prevents col breaking **/
	}
	.module-headline {
		font-size: 14px;
		padding: 14px 16px 10px;
	}
	.module-tagline {
		font-size: 14px;
		display: block;
	}
	.modules a.module {
		font-size: 14px;
		padding: 1.1rem 0.5rem;
		color: #666666;
	}
	.modules a.module:hover,
	.modules a.module:focus {
		background-color: #f6f6f6;
		box-shadow: none;
	}
	.modules a.module.active {
		background-color: #f6f6f6;
		box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
	}
	.icwp-options-page .tab-content {
		background-color: #FCFFFC;
		border: 1px solid #dddddd;
	}
	#ModuleOptionsNav {
		margin-top: 20px;
	}
	#ModuleOptionsNav li a {
		border: 1px solid transparent;
		font-size: 14px;
		height: 60px;
		border-radius: 2px 0 0 2px;
		color: #006100;
		letter-spacing: -0.5px;
	}
	#ModuleOptionsNav li a.active {
		background-color: #FCFFFC;
		border: 1px solid #dddddd;
		border-right-color: transparent;
		margin-right: -1px;
		z-index: 2;
		position: relative;
		box-shadow: -2px 2px 2px rgba(0, 0, 0, 0.03);
	}
	#ModuleOptionsNav li a:active,
	#ModuleOptionsNav li a:focus {
		box-shadow: none;
	}

	.smoothwidth {
		transition: width 4.5s;
	}
</style>

<div class="wrap">
	<div class="bootstrap-wpadmin1 <?php echo $data[ 'mod_slug' ]; ?> icwp-options-page">

<div class="">

  <div class="row no-gutters" id="ModulePageTopRow">

    <div class="col-2 modules smoothwidth" id="ColumnModules">
		<div id="TopPluginIcon" class="pluginlogo_32">&nbsp;</div>
		<div class="nav flex-column">
		<?php foreach ( $aSummaryData as $nKey => $aSummary ) : ?>
			<a class="nav-link module <?php echo $aSummary[ 'active' ] ? 'active' : ''; ?>"
			   id="tab-<?php echo $aSummary[ 'slug' ]; ?>"
			   href="<?php echo $aSummary[ 'href' ]; ?>" role="tab">
				<?php echo $aSummary[ 'name' ]; ?>
			</a>
		<?php endforeach; ?>
		</div>
	</div>

    <div class="col" id="ColumnOptions">
<!--		<div class="page-header">-->
<!--			<h2>-->
<!--				--><?php //if ( $help_video[ 'show' ] ) : ?>
<!--					<a href="#" class="btn btn-success"-->
<!--					   data-featherlight="#--><?php //echo $help_video[ 'display_id' ]; ?><!--">Help Video</a>-->
<!--				--><?php //endif; ?>
<!--			</h2>-->
<!--		</div>-->
		<?php
		if ( empty( $sFeatureInclude ) ) {
			$sFeatureInclude = 'feature-default';
		}
		include( $sBaseDirName.$sFeatureInclude ); ?>
	</div>
  </div>





</div>
