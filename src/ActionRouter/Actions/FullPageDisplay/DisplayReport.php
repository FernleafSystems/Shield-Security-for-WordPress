<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	BaseAction,
	Traits\AuthNotRequired,
	Traits\NonceVerifyNotRequired
};

class DisplayReport extends BaseAction {

	use AuthNotRequired;
	use NonceVerifyNotRequired;

	public const SLUG = 'display_full_page_report';

	protected function exec() {
		$reportID = \trim( (string)( $this->action_data[ 'report_unique_id' ] ?? '' ) );
		$redirectURL = $this->isReportIDValid( $reportID ) ?
			self::con()->plugin_urls->reportView( $reportID )
			: self::con()->plugin_urls->reportsHome();

		$this->response()
			 ->setPayloadRedirectNextStep( $redirectURL )
			 ->setPayloadSuccess( true );
	}

	private function isReportIDValid( string $reportID ) :bool {
		return \preg_match(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
			$reportID
		) === 1;
	}
}
