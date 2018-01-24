<?php
$sBaseDirName = dirname( __FILE__ ).DIRECTORY_SEPARATOR;
include_once( $sBaseDirName.'widgets/icwp_widgets.php' ); ?>

<style>
	#wpbody {
		background-color: #eeeeee;
	}
	#wpbody-content {
	}
	#ColumnOptions {
		background-color: #ffffff;
		box-shadow: 2px 2px 3px rgba(0,0,0,0.2);
	}
	.module-headline {
		font-size: 14px;
		padding-left: 16px;
	}
	.module-tagline {
		font-size: 14px;
		display: inline-block;
	}
	.modules a.module {
		font-size: 14px;
		padding: 1.1rem 0.5rem;
		color: #666666;
	}
	.modules a.module.active {
		background-color: #ffffff;
		box-shadow: 2px 2px 4px rgba(0,0,0,0.2);
	}
	.icwp-options-page .tab-content {
		background-color: #f8fbf8;
		border: 1px solid #dddddd;

	}

	#ModuleOptionsNav {
		margin-top: 20px;
	}

	#ModuleOptionsNav li a {
		font-size: 14px;
		height: 60px;
		border-radius: 2px 0 0 2px;
	}
	#ModuleOptionsNav li a.active {
		background-color: #f8fbf8;
		margin-left: 1rem;
		border: 1px solid #dddddd;
		border-right-color: transparent;
		margin-right: -1px;
		z-index: 2;
		position: relative;
	}
	#ModuleOptionsNav li a:active,
	#ModuleOptionsNav li a:focus {
		box-shadow: none;
	}
</style>

<div class="wrap">
	<div class="bootstrap-wpadmin1 <?php echo $data[ 'mod_slug' ]; ?> icwp-options-page">

<div class="">

  <div class="row no-gutters" id="ModulePageTopRow">
    <div class="col-2 col-xs-6 modules" id="ColumnModules">
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
		<div class="page-header">
			<h2>
				<?php if ( $help_video[ 'show' ] ) : ?>
					<a href="#" class="btn btn-success"
					   data-featherlight="#<?php echo $help_video[ 'display_id' ]; ?>">Help Video</a>
				<?php endif; ?>
				<?php if ( !empty( $sTagline ) ) : ?>

				<?php endif; ?>
			</h2>
		</div>
		<?php
		if ( empty( $sFeatureInclude ) ) {
			$sFeatureInclude = 'feature-default';
		}
		include( $sBaseDirName.$sFeatureInclude ); ?>
	</div>
  </div>





</div>
