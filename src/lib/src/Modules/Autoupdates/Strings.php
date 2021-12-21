<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {
		$sModName = $this->getMod()->getMainFeatureName();
		$sPlugName = $this->getCon()->getHumanName();

		switch ( $section ) {

			case 'section_enable_plugin_feature_automatic_updates_control' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $sModName );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Automatic Updates lets you manage the WordPress automatic updates engine so you choose what exactly gets updated automatically.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Automatic Updates', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_disable_all_wordpress_automatic_updates' :
				$sTitle = __( 'Disable ALL WordPress Automatic Updates', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'If you never want WordPress to automatically update anything on your site, turn on this option.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Do not turn on this option unless you really need to block updates.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Turn Off', 'wp-simple-firewall' );
				break;

			case 'section_automatic_plugin_self_update' :
				$sTitle = __( 'Automatic Plugin Self-Update', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s',
						__( 'Purpose', 'wp-simple-firewall' ),
						sprintf( __( 'Allows the %s plugin to automatically update itself when an update is available.', 'wp-simple-firewall' ), $sPlugName )
					),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep this option turned on.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Self-Update', 'wp-simple-firewall' );
				break;

			case 'section_automatic_updates_for_wordpress_components' :
				$sTitle = __( 'Automatic Updates For WordPress Components', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Control how automatic updates for each WordPress component is handled.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'You should at least allow minor updates for the WordPress core.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'WordPress Components', 'wp-simple-firewall' );
				break;

			case 'section_options' :
				$sTitle = __( 'Auto-Update Options', 'wp-simple-firewall' );
				$sTitleShort = __( 'Auto-Update Options', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Make adjustments to how automatic updates are handled on your site.', 'wp-simple-firewall' ) ),
				];
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => $summary,
		];
	}

	/**
	 * @throws \Exception
	 */
	public function getOptionStrings( string $key ) :array {
		$modName = $this->getMod()->getMainFeatureName();
		$pluginName = $this->getCon()->getHumanName();

		switch ( $key ) {

			case 'enable_autoupdates' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$description = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName );
				break;

			case 'enable_autoupdate_disable_all' :
				$name = __( 'Disable All', 'wp-simple-firewall' );
				$summary = __( 'Completely Disable WordPress Automatic Updates', 'wp-simple-firewall' );
				$description = __( 'When selected, regardless of any other settings, all WordPress automatic updates on this site will be completely disabled!', 'wp-simple-firewall' );
				break;

			case 'autoupdate_plugin_self' :
				$name = __( 'Auto Update Plugin', 'wp-simple-firewall' );
				$summary = __( 'Always Automatically Update This Plugin', 'wp-simple-firewall' );
				$description = [
					sprintf(
						__( 'Regardless of any other settings, automatically update the "%s" plugin.', 'wp-simple-firewall' ),
						$pluginName
					),
					__( 'The plugin will normally automatically update after approximately 2 days, if left to decide.', 'wp-simple-firewall' )
				];
				break;

			case 'autoupdate_core' :
				$name = __( 'WordPress Core Updates', 'wp-simple-firewall' );
				$summary = __( 'Decide how the WordPress Core will automatically update, if at all', 'wp-simple-firewall' );
				$description = __( 'At least automatically upgrading minor versions is recommended (and is the WordPress default).', 'wp-simple-firewall' );
				break;

			case 'enable_autoupdate_translations' : // REMOVED 8.6.2
				$name = __( 'Translations', 'wp-simple-firewall' );
				$summary = __( 'Automatically Update Translations', 'wp-simple-firewall' );
				$description = __( 'Note: Automatic updates for translations are enabled on WordPress by default.', 'wp-simple-firewall' );
				break;

			case 'enable_autoupdate_plugins' :
				$name = __( 'Plugins', 'wp-simple-firewall' );
				$summary = __( 'Automatically Update All Plugins', 'wp-simple-firewall' );
				$description = __( 'Note: Automatic updates for plugins are disabled on WordPress by default.', 'wp-simple-firewall' );
				break;

			case 'enable_autoupdate_themes' :
				$name = __( 'Themes', 'wp-simple-firewall' );
				$summary = __( 'Automatically Update Themes', 'wp-simple-firewall' );
				$description = __( 'Note: Automatic updates for themes are disabled on WordPress by default.', 'wp-simple-firewall' );
				break;

			case 'enable_autoupdate_ignore_vcs' : // REMOVED 8.6.2
				$name = __( 'Ignore Version Control', 'wp-simple-firewall' );
				$summary = __( 'Ignore Version Control Systems Such As GIT and SVN', 'wp-simple-firewall' );
				$description = __( 'If you use SVN or GIT and WordPress detects it, automatic updates are disabled by default. Check this box to ignore version control systems and allow automatic updates.', 'wp-simple-firewall' );
				break;

			case 'enable_upgrade_notification_email' :
				$name = __( 'Send Report Email', 'wp-simple-firewall' );
				$summary = __( 'Send email notices after automatic updates', 'wp-simple-firewall' );
				$description = __( 'You can turn on/off email notices from automatic updates by un/checking this box.', 'wp-simple-firewall' );
				break;

			case 'override_email_address' :
				$name = __( 'Report Email Address', 'wp-simple-firewall' );
				$summary = __( 'Where to send upgrade notification reports', 'wp-simple-firewall' );
				$description = __( 'If this is empty, it will default to the Site Admin email address', 'wp-simple-firewall' );
				break;

			case 'update_delay' :
				$name = __( 'Update Delay', 'wp-simple-firewall' );
				$summary = __( 'Delay Automatic Updates For Period Of Stability', 'wp-simple-firewall' );
				$description = sprintf( __( '%s will delay upgrades until the new update has been available for the set number of days.', 'wp-simple-firewall' ), $pluginName )
								.'<br />'.__( "This helps ensure updates are more stable before they're automatically applied to your site.", 'wp-simple-firewall' );
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $name,
			'summary'     => $summary,
			'description' => $description,
		];
	}
}