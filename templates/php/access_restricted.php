<div class="container-fluid">
<div class="row">
	<div class="col-md-8 offset-md-2 col-xs-10 offset-xs-1 col-xl-6 offset-xl-3">
		<div class="options-body" id="IcwpWpsfSecurityAdmin">

		<h3><?php echo $strings[ 'aar_title' ]; ?></h3>
		<p><?php echo $strings[ 'aar_what_should_you_enter' ]; ?>
			<br /><?php echo $strings[ 'aar_must_supply_key_first' ]; ?>
		</p>

		<form action="<?php echo $form_action; ?>" method="post" class="form-horizontal" id="SecurityAdminForm">

			<?php foreach ( $ajax[ 'sec_admin_login' ] as $sName => $sVal ) : ?>
				<input type="hidden" value="<?php echo $sVal; ?>" name="<?php echo $sName; ?>" />
			<?php endforeach; ?>

			<div class="form-group row no-gutters">

				<label class="form-label col-3 col-form-label" for="admin_access_key_request">
					<span class="optname"><?php echo $strings[ 'aar_enter_access_key' ]; ?></span>
				</label>

				<div class="col-8 col-xs-offset-1 option_container">

					<div class="option_section">
						<label class="admin_access_key_request">
							<input type="password" name="admin_access_key_request"
								   id="admin_access_key_request" value="" autocomplete="off" autofocus />
						</label>
						<p class="help-block"><?php echo $strings[ 'aar_to_manage_must_enter_key' ]; ?></p>
					</div>
				</div>
			</div>
			<div class="form-group row no-gutters">
				<div class="col-6 order-2 text-right">
					<button type="submit" class="btn btn-primary" name="submit">
						<?php echo $strings[ 'aar_submit_access_key' ]; ?></button>
				</div>
				<div class="col-5 order-1 text-left">
					<a class="btn btn-link "
					   href="<?php echo $hrefs[ 'aar_forget_key' ]; ?>" target="_blank">
						<?php echo $strings[ 'aar_forget_key' ]; ?></a>
				</div>

			</div>
		</form>
		</div>
	</div>
</div>
</div>