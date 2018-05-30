<style type="text/css">
	.icwp_wpsf_site_badge {
		background-color: rgba(255, 255, 255, 0.9);
		box-sizing: content-box;
		color: #000000;
		height: 33px;
		padding: 7px 7px 4px;
		text-align: left;
		z-index: 100;
	}
	.icwp_wpsf_site_badge a {
		border: 0 none;
		box-sizing: inherit;
		color: inherit !important;
		display: block;
		text-decoration: none !important;
	}
	.icwp_wpsf_site_badge a:hover {
		text-decoration: none;
	}
	.icwp_wpsf_site_badge .badge-text {
		font-size: 10px;
		line-height: 17px;
		padding-left: 43px;
	}
	.icwp_wpsf_site_badge img {
		float: left;
	}
</style>
<div id="icwpWpsfSiteBadge" class="icwp_wpsf_site_badge">
	<a href="https://icwp.io/wpsecurityfirewall" target="_blank" title="<?php echo $strings['plugin_name']; ?>">
		<img src="<?php echo $hrefs['img_src']; ?>" alt="<?php echo $strings['plugin_name']; ?> Logo" />
		<div class="badge-text">
			This Site Is Protected By <span style="font-style: italic;">The</span><br/><?php echo $strings['plugin_name']; ?> &rarr;
		</div>
	</a>
</div>