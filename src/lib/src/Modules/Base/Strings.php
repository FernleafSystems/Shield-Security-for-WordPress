<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Strings {

	use ModConsumer;

	/**
	 * @return string[]
	 */
	public function getDisplayStrings() {
		$oCon = $this->getCon();

		$aProFeatures = [
			__( 'Customer Support', 'wp-simple-firewall' ),
			__( 'More Scans', 'wp-simple-firewall' ),
			__( 'Malware Scanner', 'wp-simple-firewall' ),
			__( 'Scan Every Hour', 'wp-simple-firewall' ),
			__( 'White Label', 'wp-simple-firewall' ),
			__( 'Import/Export', 'wp-simple-firewall' ),
			__( 'Better Bot Detection', 'wp-simple-firewall' ),
			__( 'Password Policies', 'wp-simple-firewall' ),
			__( 'WooCommerce Support', 'wp-simple-firewall' ),
		];
		$aProFeaturesDisplay = array_intersect_key( $aProFeatures, array_flip( array_rand( $aProFeatures, 6 ) ) );
		$aProFeaturesDisplay[] = __( 'and much more!' );

		return Services::DataManipulation()->mergeArraysRecursive(
			[
				'see_help_video'    => __( 'Watch Help Video' ),
				'btn_save'          => __( 'Save Options' ),
				'btn_options'       => __( 'Options' ),
				'btn_help'          => __( 'Help' ),
				'btn_wizards'       => $this->getMod()->hasWizard() ? __( 'Wizards' ) : __( 'No Wizards' ),
				'go_to_settings'    => __( 'Settings', 'wp-simple-firewall' ),
				'on'                => __( 'On', 'wp-simple-firewall' ),
				'off'               => __( 'Off', 'wp-simple-firewall' ),
				'yes'               => __( 'Yes' ),
				'no'                => __( 'No' ),
				'never'             => __( 'Never', 'wp-simple-firewall' ),
				'time_until'        => __( 'Until', 'wp-simple-firewall' ),
				'time_since'        => __( 'Since', 'wp-simple-firewall' ),
				'more_info'         => __( 'Info', 'wp-simple-firewall' ),
				'logged_in'         => __( 'Logged-In', 'wp-simple-firewall' ),
				'username'          => __( 'Username' ),
				'blog'              => __( 'Blog', 'wp-simple-firewall' ),
				'save_all_settings' => sprintf( __( 'Save %s Settings', 'wp-simple-firewall' ), $oCon->getHumanName() ),
				'plugin_name'       => $oCon->getHumanName(),
				'options_title'     => __( 'Options', 'wp-simple-firewall' ),
				'options_summary'   => __( 'Configure Module', 'wp-simple-firewall' ),
				'actions_title'     => __( 'Actions and Info', 'wp-simple-firewall' ),
				'actions_summary'   => __( 'Perform actions for this module', 'wp-simple-firewall' ),
				'help_title'        => __( 'Help', 'wp-simple-firewall' ),
				'help_summary'      => __( 'Learn More', 'wp-simple-firewall' ),
				'ip_address'        => __( 'IP Address', 'wp-simple-firewall' ),
				'select'            => __( 'Select' ),
				'filters_clear'     => __( 'Clear Filters', 'wp-simple-firewall' ),
				'filters_apply'     => __( 'Apply Filters', 'wp-simple-firewall' ),
				'jump_to_option'    => __( 'Find Plugin Option', 'wp-simple-firewall' ),
				'type_below_search' => __( 'Type below to search all plugin options', 'wp-simple-firewall' ),
				'pro_only_option'   => __( 'Pro Only', 'wp-simple-firewall' ),
				'pro_only_feature'  => __( 'This is a pro-only feature', 'wp-simple-firewall' ),
				'go_pro'            => __( 'Go Pro!', 'wp-simple-firewall' ),
				'go_pro_option'     => sprintf( '<a href="%s" target="_blank">%s</a>',
					'https://shsec.io/shieldgoprofeature', __( 'Please upgrade to Pro to control this option', 'wp-simple-firewall' ) ),

				'description'                  => __( 'Description', 'wp-simple-firewall' ),
				'loading'                      => __( 'Loading', 'wp-simple-firewall' ),
				'aar_title'                    => __( 'Plugin Access Restricted', 'wp-simple-firewall' ),
				'aar_what_should_you_enter'    => __( 'This security plugin is restricted to administrators with the Security Access Key.', 'wp-simple-firewall' ),
				'aar_must_supply_key_first'    => __( 'Please provide the Security Access Key to manage this plugin.', 'wp-simple-firewall' ),
				'aar_to_manage_must_enter_key' => __( 'To manage this plugin you must enter the access key.', 'wp-simple-firewall' ),
				'aar_enter_access_key'         => __( 'Enter Access Key', 'wp-simple-firewall' ),
				'aar_submit_access_key'        => __( 'Submit Security Admin Key', 'wp-simple-firewall' ),
				'aar_forget_key'               => __( "Forgotten Key", 'wp-simple-firewall' ),
				'supply_password'              => __( 'Supply Password', 'wp-simple-firewall' ),
				'confirm_password'             => __( 'Confirm Password', 'wp-simple-firewall' ),
				'show_help_video_section'      => __( 'Show help video for this section', 'wp-simple-firewall' ),

				'offense' => __( 'offense', 'wp-simple-firewall' ),
				'debug'   => __( 'Debug', 'wp-simple-firewall' ),

				'privacy_policy_agree'   => __( 'Agree To Privacy Policy', 'wp-simple-firewall' ),
				'privacy_policy_confirm' => __( "I confirm that I've read and I agree to the Privacy Policy", 'wp-simple-firewall' ),
				'privacy_policy_gdpr'    => __( 'We treat your information under our strict, and GDPR-compliant, privacy policy.', 'wp-simple-firewall' ),
				'privacy_policy'         => __( 'Privacy Policy', 'wp-simple-firewall' ),
				'privacy_never_spam'     => __( 'We never SPAM and you can remove yourself at any time.', 'wp-simple-firewall' ),

				'pro_features'       => __( 'Pro features include', 'wp-simple-firewall' ),
				'join_thousands_H'   => __( "Join The 1,000s Who've Already Upgraded Their WordPress Security To Better Protect Their Sites.", 'wp-simple-firewall' ),
				'join_thousands_P'   => implode( ', ', $aProFeaturesDisplay ),
				'get_pro_protection' => __( 'Upgrade To Pro Protection', 'wp-simple-firewall' ),
			],
			$this->getAdditionalDisplayStrings()
		);
	}

	/**
	 * @return string[]
	 */
	protected function getAdditionalDisplayStrings() {
		return [];
	}

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		return [];
	}

	/**
	 * @param string $sKey
	 * @return string[]
	 */
	public function getAuditMessage( $sKey ) {
		$aMsg = $this->getAuditMessages();
		return isset( $aMsg[ $sKey ] ) ? $aMsg[ $sKey ] : [];
	}

	/**
	 * @param string $sOptKey
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( $sOptKey ) {
		throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sOptKey ) );
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( $sSectionSlug ) {

		switch ( $sSectionSlug ) {

			case 'section_user_messages' :
				$sTitle = __( 'User Messages', 'wp-simple-firewall' );
				$sTitleShort = __( 'Messages', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Customize the messages displayed to the user.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use this section if you need to communicate to the user in a particular manner.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Hint', 'wp-simple-firewall' ), sprintf( __( 'To reset any message to its default, enter the text exactly: %s', 'wp-simple-firewall' ), 'default' ) )
				];
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}
}