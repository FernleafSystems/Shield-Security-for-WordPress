<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay;

/**
 * Use this to render full page HTML without echo'ing, issuing HTTP headers, and die()-ing
 */
class FullPageDisplayNonTerminating extends BaseFullPageDisplay {

	public const SLUG = 'display_full_page_non_terminating';

	protected function postExec() {
		/** Do nothing: prevent headers, echo & die() */
	}
}