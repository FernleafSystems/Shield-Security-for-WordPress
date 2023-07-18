<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\DismissAdminNotice,
	Actions\Render\BaseRender,
	Actions\Traits\SecurityAdminNotRequired
};
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;

class AdminNotice extends BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'render_admin_notice';
	public const TEMPLATE = '/snippets/prerendered.twig';

	protected function getRenderData() :array {
		$notice = ( new NoticeVO() )->applyFromArray( $this->action_data[ 'raw_notice_data' ] );

		$data = $notice->render_data;

		if ( empty( $data[ 'notice_classes' ] ) || !\is_array( $data[ 'notice_classes' ] ) ) {
			$data[ 'notice_classes' ] = [];
		}
		$data[ 'notice_classes' ][] = $notice->type;
		if ( !\in_array( 'error', $data[ 'notice_classes' ] ) ) {
			$data[ 'notice_classes' ][] = 'updated';
		}
		$data[ 'notice_classes' ][] = 'notice-'.$notice->id;
		$data[ 'notice_classes' ] = \implode( ' ', \array_unique( $data[ 'notice_classes' ] ) );

		$data[ 'unique_render_id' ] = uniqid( (string)$notice->id );
		$data[ 'notice_id' ] = $notice->id;

		$data[ 'ajax' ][ 'dismiss_admin_notice' ] = ActionData::BuildJson( DismissAdminNotice::class, true, [
			'notice_id' => $notice->id,
			'hide'      => 1,
		] );

		return $data;
	}

	protected function getRenderTemplate() :string {
		return ( new NoticeVO() )->applyFromArray( $this->action_data[ 'raw_notice_data' ] )->template;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'raw_notice_data'
		];
	}
}