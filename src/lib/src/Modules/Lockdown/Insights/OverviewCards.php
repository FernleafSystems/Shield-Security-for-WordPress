<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;

class OverviewCards extends Modules\Base\Insights\OverviewCards {

	protected function buildModCards() :array {
		/** @var Modules\Lockdown\ModCon $mod */
		$mod = $this->getMod();
		/** @var Modules\Lockdown\Options $opts */
		$opts = $this->getOptions();

		$cards = [];

		if ( $mod->isModOptEnabled() ) {
			$bUserCanEdit = current_user_can( 'edit_plugins' );

			if ( !$bUserCanEdit ) {
				$cards[ 'editing' ] = [
					'name'    => __( 'File Editing via WP', 'wp-simple-firewall' ),
					'state'   => 1,
					'summary' => __( 'File editing from within WordPress admin is disabled', 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToOption( 'disable_file_editing' ),
				];
			}
			else {
				$bEditOptSet = $opts->isOptFileEditingDisabled();
				$cards[ 'editing' ] = [
					'name'    => __( 'File Editing via WP', 'wp-simple-firewall' ),
					'state'   => $bEditOptSet ? -2 : -1,
					'summary' => $bEditOptSet ?
						__( "File editing is allowed even though you've switched it off", 'wp-simple-firewall' )
						: __( "File editing from within the WP admin should be disabled", 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToOption( 'disable_file_editing' ),
					'help'    => $bEditOptSet ?
						__( 'Another plugin or theme is interfering with this setting.', 'wp-simple-firewall' )
						: __( 'WP Plugin file editing should be disabled wherever possible.', 'wp-simple-firewall' )
				];
			}

			$bXml = $opts->isXmlrpcDisabled();
			$cards[ 'xml' ] = [
				'name'    => __( 'XML-RPC', 'wp-simple-firewall' ),
				'state'   => $bXml ? 1 : -1,
				'summary' => $bXml ?
					__( 'XML-RPC is disabled', 'wp-simple-firewall' )
					: __( "XML-RPC access is allowed", 'wp-simple-firewall' ),
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

		return $cards;
	}

	protected function getSectionTitle() :string {
		return __( 'WordPress Lockdown', 'wp-simple-firewall' );
	}

	protected function getSectionSubTitle() :string {
		return __( 'Restrict WP Functionality e.g. XMLRPC & REST API', 'wp-simple-firewall' );
	}
}