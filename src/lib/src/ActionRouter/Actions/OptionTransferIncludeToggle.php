<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class OptionTransferIncludeToggle extends BaseAction {

	public const SLUG = 'option_transfer_include_toggle';

	protected function exec() {
		$excluded = self::con()->comps->opts_lookup->getXferExcluded();
		if ( $this->action_data[ 'status' ] === 'exclude' ) {
			$excluded[] = $this->action_data[ 'key' ];
			$msg = __( 'Option excluded from import/export' );
		}
		else {
			$excluded = \array_diff( $excluded, [ $this->action_data[ 'key' ] ] );
			$msg = __( 'Option included in import/export' );
		}

		self::con()->opts->optSet( 'xfer_excluded', \array_unique( $excluded ) );

		$this->response()->action_response_data = [
			'success'     => true,
			'page_reload' => false,
			'message'     => $msg
		];
	}
}