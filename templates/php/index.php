<?php
$sBaseDirName = dirname( __FILE__ ).DIRECTORY_SEPARATOR;

if ( $flags[ 'wrap_page_content' ] ) : ?>
<div class="wrap">
	<div class="bootstrap-wpadmin1 <?php echo $data[ 'mod_slug' ]; ?> icwp-options-page">
<?php endif;?>

<?php if ( $flags[ 'access_restricted' ] ) : ?>
	<?php include( $sBaseDirName.'access_restricted.php' ); ?>
<?php else : ?>
	<?php include( $sBaseDirName.'index_body.php' ); ?>
<?php endif; ?>

<?php if ( $flags[ 'wrap_page_content' ] ) : ?>
	</div><!-- / bootstrap-wpadmin -->
</div><!-- / wrap -->
<?php endif;?>

<?php include_once( $sBaseDirName.'index_footer.php' ); ?>

<?php
if ( $help_video[ 'show' ] ) {
	include_once( $sBaseDirName.'snippets/help_video_player.php' );
}
