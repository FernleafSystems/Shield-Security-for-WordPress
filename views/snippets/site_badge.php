<style type="text/css">
	#icwpWpsfSiteBadge {
		background-color: rgba(255, 255, 255, 0.9);
		border-radius: 3px 3px 0 0;
		bottom: 0;
		color: #000000;
		left: 20px;
		padding: 7px 7px 4px;
		position: fixed;
		width: 189px;
		z-index: 100;
	}
	#icwpWpsfSiteBadge a {
		color: inherit !important;
		display: block;
	}
	#icwpWpsfSiteBadge .badge-text {
		font-size: 11px;
		line-height: 17px;
		padding-left: 43px;
	}
	#icwpWpsfSiteBadge a:hover {
		text-decoration: none;
	}
	#icwpWpsfSiteBadge img {
		float: left;
	}
	#icwpWpsfCloseButton {
		background-color: #ffffff;
		border: 1px solid #aaaaaa;
		border-radius: 5px;
		box-shadow: 1px 1px 1px rgba(0, 0, 0, 0.11);
		color: #666666;
		font-size: 9px;
		height: 13px;
		left: -5px;
		line-height: 2px;
		padding: 3px;
		position: absolute;
		top: -5px;
		width: 13px;
	}
	#icwpWpsfCloseButton:hover {
		cursor: pointer;
	}
</style>
<div id="icwpWpsfSiteBadge">
	<a id="icwpWpsfCloseButton" onclick="getElementById('icwpWpsfSiteBadge').remove();">x</a>
	<a href="http://icwp.io/wpsecurityfirewall" target="_blank" alt="WordPress Simple Security Firewall">
		<img src="%s" /> <div class="badge-text">Site Protected By The Simple Security Firewall &rarr;</div>
	</a>
</div>