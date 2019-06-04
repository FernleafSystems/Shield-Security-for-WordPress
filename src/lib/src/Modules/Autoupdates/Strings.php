<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @param string $sSectionSlug
	 * @return array
	 * @throws \Exception
	 */
	public function loadStrings_SectionTitles( $sSectionSlug ) {
		$sModName = $this->getMod()->getMainFeatureName();
		$sPlugName = $this->getCon()->getHumanName();

		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_automatic_updates_control' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $sModName );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Automatic Updates lets you manage the WordPress automatic updates engine so you choose what exactly gets updated automatically.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Automatic Updates', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_disable_all_wordpress_automatic_updates' :
				$sTitle = __( 'Disable ALL WordPress Automatic Updates', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'If you never want WordPress to automatically update anything on your site, turn on this option.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Do not turn on this option unless you really need to block updates.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Turn Off', 'wp-simple-firewall' );
				break;

			case 'section_automatic_plugin_self_update' :
				$sTitle = __( 'Automatic Plugin Self-Update', 'wp-simple-firewall' );
				$aSummary = [
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
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Control how automatic updates for each WordPress component is handled.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'You should at least allow minor updates for the WordPress core.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'WordPress Components', 'wp-simple-firewall' );
				break;

			case 'section_options' :
				$sTitle = __( 'Auto-Update Options', 'wp-simple-firewall' );
				$sTitleShort = __( 'Auto-Update Options', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Make adjustments to how automatic updates are handled on your site.', 'wp-simple-firewall' ) ),
				];
				break;

			default:
				return parent::loadStrings_SectionTitles( $sSectionSlug );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $sOptKey
	 * @return array
	 * @throws \Exception
	 */
	public function loadStrings_Options( $sOptKey ) {
		$sModName = $this->getMod()->getMainFeatureName();
		$sPlugName = $this->getCon()->getHumanName();

		switch ( $sOptKey ) {

			case 'enable_autoupdates' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $sModName );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $sModName );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $sModName );
				break;

			case 'enable_autoupdate_disable_all' :
				$sName = __( 'Disable All', 'wp-simple-firewall' );
				$sSummary = __( 'Completely Disable WordPress Automatic Updates', 'wp-simple-firewall' );
				$sDescription = __( 'When selected, regardless of any other settings, all WordPress automatic updates on this site will be completely disabled!', 'wp-simple-firewall' );
				break;

			case 'autoupdate_plugin_self' :
				$sName = __( 'Auto Update Plugin', 'wp-simple-firewall' );
				$sSummary = __( 'Always Automatically Update This Plugin', 'wp-simple-firewall' );
				$sDescription = sprintf(
					__( 'Regardless of any other settings, automatically update the "%s" plugin.', 'wp-simple-firewall' ),
					$sPlugName
				);
				break;

			case 'autoupdate_core' :
				$sName = __( 'WordPress Core Updates', 'wp-simple-firewall' );
				$sSummary = __( 'Decide how the WordPress Core will automatically update, if at all', 'wp-simple-firewall' );
				$sDescription = __( 'At least automatically upgrading minor versions is recommended (and is the WordPress default).', 'wp-simple-firewall' );
				break;

			case 'enable_autoupdate_translations' :
				$sName = __( 'Translations', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Update Translations', 'wp-simple-firewall' );
				$sDescription = __( 'Note: Automatic updates for translations are enabled on WordPress by default.', 'wp-simple-firewall' );
				break;

			case 'enable_autoupdate_plugins' :
				$sName = __( 'Plugins', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Update All Plugins', 'wp-simple-firewall' );
				$sDescription = __( 'Note: Automatic updates for plugins are disabled on WordPress by default.', 'wp-simple-firewall' );
				break;

			case 'enable_individual_autoupdate_plugins' :
				$sName = __( 'Individually Select Plugins', 'wp-simple-firewall' );
				$sSummary = __( 'Select Individual Plugins To Automatically Update', 'wp-simple-firewall' );
				$sDescription = __( 'Turning this on will provide an option on the plugins page to select whether a plugin is automatically updated.', 'wp-simple-firewall' );
				break;

			case 'enable_autoupdate_themes' :
				$sName = __( 'Themes', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Update Themes', 'wp-simple-firewall' );
				$sDescription = __( 'Note: Automatic updates for themes are disabled on WordPress by default.', 'wp-simple-firewall' );
				break;

			case 'enable_autoupdate_ignore_vcs' :
				$sName = __( 'Ignore Version Control', 'wp-simple-firewall' );
				$sSummary = __( 'Ignore Version Control Systems Such As GIT and SVN', 'wp-simple-firewall' );
				$sDescription = __( 'If you use SVN or GIT and WordPress detects it, automatic updates are disabled by default. Check this box to ignore version control systems and allow automatic updates.', 'wp-simple-firewall' );
				break;

			case 'enable_upgrade_notification_email' :
				$sName = __( 'Send Report Email', 'wp-simple-firewall' );
				$sSummary = __( 'Send email notices after automatic updates', 'wp-simple-firewall' );
				$sDescription = __( 'You can turn on/off email notices from automatic updates by un/checking this box.', 'wp-simple-firewall' );
				break;

			case 'override_email_address' :
				$sName = __( 'Report Email Address', 'wp-simple-firewall' );
				$sSummary = __( 'Where to send upgrade notification reports', 'wp-simple-firewall' );
				$sDescription = __( 'If this is empty, it will default to the Site Admin email address', 'wp-simple-firewall' );
				break;

			case 'update_delay' :
				$sName = __( 'Update Delay', 'wp-simple-firewall' );
				$sSummary = __( 'Delay Automatic Updates For Period Of Stability', 'wp-simple-firewall' );
				$sDescription = sprintf( __( '%s will delay upgrades until the new update has been available for the set number of days.', 'wp-simple-firewall' ), $sPlugName )
								.'<br />'.__( "This helps ensure updates are more stable before they're automatically applied to your site.", 'wp-simple-firewall' );
				break;

			default:
				return parent::loadStrings_Options( $sOptKey );
		}

		return [
			'name'        => $sName,
			'summary'     => $sSummary,
			'description' => $sDescription,
		];
	}
}