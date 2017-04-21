<div class="featherlight embed-container shield-help-video" id="<?php echo $help_video[ 'display_id' ]; ?>">
        <iframe width="<?php echo $help_video[ 'width' ] ?>"
                height="<?php echo $help_video[ 'height' ] ?>"
                src="<?php echo $help_video[ 'iframe_url' ] ?>"
                frameborder="0"
                webkitAllowFullScreen
                mozallowfullscreen
                allowfullscreen
        ></iframe>
</div>
<style type="text/css">
    .shield-help-video {
        margin-top: 100px;
    }
</style>

<?php if ( $help_video[ 'auto_show' ] ) : ?>
    <style type="text/css">
        #icwpVideoHelpBadge {
            background-color: #8dc63f;
            bottom: 36px;
            border-radius: 3px;
            box-sizing: content-box;
            box-shadow: 1px 1px 1px rgba( 0,0,0,0.1 );
            color: #000000;
            height: 60px;
            line-height: 30px;
            min-width: 145px;
            width: auto;
            padding: 10px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            right: 9px;
            opacity: 0.5;
            position: fixed;
            z-index: 10000;
            -webkit-transition: width 0.5s;
            transition: width 0.5s;
            text-shadow: 1px -1px 0 rgba(255,255,255,0.3);
        }
        #icwpVideoHelpBadge:hover {
            opacity: 1.0;
        }
        #icwpVideoHelpBadge a {
            border: 0 none;
            box-sizing: inherit;
            color: inherit !important;
            display: inline-block;
            padding: 0;
            text-decoration: none !important;
        }
        #icwpVideoHelpBadge .dashicons {
            animation: pulse 5s infinite;
            color: darkorange;
            display: inline-block;
            font-size: 64px;
            width: 54px;
        }
        #icwpVideoHelpBadge a:hover {
            text-decoration: none;
        }
        a#icwpWpsfCloseButton {
            background-color: #ffffff;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 10px;
            height: 10px;
            right: -2px;
            line-height: 6px;
            padding: 2px 1px 0 2px !important;
            position: absolute;
            text-align: center;
            top: -3px;
            width: 10px;
            z-index: 1001;
        }
        #icwpWpsfCloseButton:hover {
            cursor: pointer;
        }
        @media (max-width: 600px) {
            #icwpVideoHelpBadge {
                display: none
            }
            #icwpVideoHelpBadge .dashicons {
                font-size: 36px;
            }
        }
        @keyframes pulse {
             0% {
                 color: #FF8C00;
             }
             50% {
                 color: #FF4136;
             }
             100% {
                 color: #FF8C00;
             }
         }
    </style>
    <div id="icwpVideoHelpBadge">
        <a id="icwpWpsfCloseButton" onclick="getElementById('icwpVideoHelpBadge').remove();">x</a>
        <a href="#" target="_blank" data-featherlight="#<?php echo $help_video[ 'display_id' ]; ?>">
			<?php echo $sPluginName; ?> <?php echo $sFeatureName; ?><br /><?php echo $strings['see_help_video']; ?>
        </a>
        <span class="dashicons dashicons-controls-play"></span>
    </div>
<?php endif; ?>