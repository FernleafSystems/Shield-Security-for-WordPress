<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class AjaxHandler {

	use ModConsumer;

	/**
	 * @param ModCon|mixed $mod
	 */
	public function __construct( $mod ) {
		$this->setMod( $mod );
		add_filter( $mod->getCon()->prefix( 'ajax_handlers' ),
			function ( array $ajaxHandlers, bool $isAuth ) {
				return \array_merge( $ajaxHandlers, $this->getAjaxActionCallbackMap( $isAuth ) );
			},
			10, 2
		);
	}

	/**
	 * @return callable[]
	 */
	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$map = [];
		if ( $isAuth ) {
			$map[ 'dismiss_admin_notice' ] = [ $this, 'ajaxExec_DismissAdminNotice' ];
		}
		return $map;
	}

	public function ajaxExec_DismissAdminNotice() :array {
		$ajaxResponse = [];
		$noticeID = sanitize_key( Services::Request()->query( 'notice_id', '' ) );

		$notices = $this->getMod()->getAdminNotices();
		foreach ( $notices->getAdminNotices() as $notice ) {
			if ( $noticeID == $notice->id ) {
				$notices->setNoticeDismissed( $notice );
				$ajaxResponse = [
					'success'   => true,
					'message'   => 'Admin notice dismissed', //not currently rendered
					'notice_id' => $notice->id,
				];
				break;
			}
		}

		// leave response empty if it doesn't apply here, so other modules can process it.
		return $ajaxResponse;
	}
}