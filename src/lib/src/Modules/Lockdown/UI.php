<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class UI extends Base\ShieldUI {

	public function getInsightsOverviewCards() :array {
		/** @var \ICWP_WPSF_FeatureHandler_Lockdown $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$cardSection = [
			'title'        => __( 'WordPress Lockdown', 'wp-simple-firewall' ),
			'subtitle'     => __( 'Restrict WP Functionality e.g. XMLRPC & REST API', 'wp-simple-firewall' ),
			'href_options' => $mod->getUrl_AdminPage()
		];

		$cards = [];

		if ( !$mod->isModOptEnabled() ) {
			$cards[ 'mod' ] = $this->getModDisabledCard();
		}
		else {
			$bEditingDisabled = $opts->isOptFileEditingDisabled() || !current_user_can( 'edit_plugins' );
			$cards[ 'editing' ] = [
				'name'    => __( 'File Editing via WP', 'wp-simple-firewall' ),
				'state'   => $bEditingDisabled ? 1 : -1,
				'summary' => $bEditingDisabled ?
					__( 'File editing is disabled', 'wp-simple-firewall' )
					: __( "File editing is permitted through WP admin", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'disable_file_editing' ),
			];

			$bXml = $opts->isXmlrpcDisabled();
			$cards[ 'xml' ] = [
				'name'    => __( 'XML-RPC', 'wp-simple-firewall' ),
				'state'   => $bXml ? 1 : -1,
				'summary' => $bXml ?
					__( 'XML-RPC is disabled', 'wp-simple-firewall' )
					: __( "XML-RPC is not blocked", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'disable_xmlrpc' ),
			];

			$bApi = $opts->isRestApiAnonymousAccessDisabled();
			$cards[ 'api' ] = [
				'name'    => __( 'REST API', 'wp-simple-firewall' ),
				'state'   => $bApi ? 1 : -1,
				'summary' => $bApi ?
					__( 'Anonymous REST API is disabled', 'wp-simple-firewall' )
					: __( "Anonymous REST API is allowed", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'disable_anonymous_restapi' ),
			];
		}

		$cardSection[ 'cards' ] = $cards;
		return [ 'lockdown' => $cardSection ];
	}

	/**
	 * @return array
	 */
	public function getInsightsNoticesData() :array {
		/** @var \ICWP_WPSF_FeatureHandler_Lockdown $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$notices = [
			'title'    => __( 'WP Lockdown', 'wp-simple-firewall' ),
			'messages' => []
		];

		{ //edit plugins
			$bEditingDisabled = $opts->isOptFileEditingDisabled() || !current_user_can( 'edit_plugins' );
			if ( !$bEditingDisabled ) { //assumes current user is admin
				$notices[ 'messages' ][ 'disallow_file_edit' ] = [
					'title'   => __( 'File Editing via WP', 'wp-simple-firewall' ),
					'message' => __( 'Direct editing of plugin/theme files is permitted.', 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToOption( 'disable_file_editing' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'WP Plugin file editing should be disabled.', 'wp-simple-firewall' )
				];
			}
		}

		return $notices;
	}
}