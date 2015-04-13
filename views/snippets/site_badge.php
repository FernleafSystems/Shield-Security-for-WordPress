<style type="text/css">
	#icwpWpsfSiteBadge {
		background-color: rgba(255, 255, 255, 0.9);
		border-radius: 3px 3px 0 0;
		bottom: 0;
		box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.47);
		color: #000000;
		height: 33px;
		left: 20px;
		padding: 7px 7px 4px;
		position: fixed;
		text-align: left;
		width: 189px;
		z-index: 100;
	}
	#icwpWpsfSiteBadge a {
		border: 0 none;
		color: inherit !important;
		display: block;
	}
	#icwpWpsfSiteBadge a:hover {
		text-decoration: none;
	}
	#icwpWpsfSiteBadge .badge-text {
		font-size: 10px;
		line-height: 17px;
		padding-left: 43px;
	}
	#icwpWpsfSiteBadge img {
		float: left;
	}
	#icwpWpsfCloseButton {
		background-color: #ffffff;
		border: 1px solid #aaaaaa;
		border-radius: 5px;
		1px 2px 4px rgba(0, 0, 0, 0.30);
		color: #666666;
		font-size: 9px;
		height: 10px;
		left: -5px;
		line-height: 6px;
		padding: 3px;
		position: absolute;
		text-align: center;
		top: -5px;
		width: 10px;
		z-index: 1001;
	}
	#icwpWpsfCloseButton:hover {
		cursor: pointer;
	}
</style>
<div id="icwpWpsfSiteBadge">
	<a id="icwpWpsfCloseButton" onclick="getElementById('icwpWpsfSiteBadge').remove();">x</a>
	<a href="http://icwp.io/wpsecurityfirewall" target="_blank" title="WordPress Security Firewall">
		<img src="%s" alt="WordPress Security Firewall Logo"/> <div class="badge-text">This Site Is Protected By <span style="font-style: italic;">The</span><br/>WordPress Security Firewall &rarr;</div>
	</a>
</div>