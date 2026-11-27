<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

abstract class PageScansBase extends PageModeLandingBase {

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title'      => sprintf( '%s: %s', CommonDisplayStrings::get( 'help_label' ), __( 'Scans', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/article/452-a-complete-guide-to-the-shield-security-scans',
			'new_window' => true,
		];
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_ACTIONS;
	}

	protected function hasOperatorModeParent() :bool {
		return true;
	}

	/**
	 * Scan pages belong to the Actions Queue, so their breadcrumb takes the colour of the queue's most severe
	 * item as the queue's own does: red only for something critical, amber for to-dos, green for nothing to do.
	 */
	protected function getLandingChromeColorKey() :string {
		return self::con()->comps->site_query->attention()[ 'summary' ][ 'severity' ];
	}
}
