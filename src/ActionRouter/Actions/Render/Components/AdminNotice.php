<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;

class AdminNotice extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'render_admin_notice';
	public const TEMPLATE = '/snippets/prerendered.twig';

	protected function getRenderData() :array {
		$con = self::con();

		$notice = ( new NoticeVO() )->applyFromArray( $this->action_data[ 'raw_notice_data' ] );

		$data = $notice->render_data;

		if ( empty( $data[ 'notice_classes' ] ) || !\is_array( $data[ 'notice_classes' ] ) ) {
			$data[ 'notice_classes' ] = [];
		}
		$data[ 'notice_classes' ][] = $notice->type;
		$data[ 'notice_classes' ][] = $this->getWpNoticeClass( (string)$notice->type );
		$data[ 'notice_classes' ][] = 'notice-'.$notice->id;
		$data[ 'notice_classes' ] = \implode( ' ', \array_unique( $data[ 'notice_classes' ] ) );

		$data[ 'unique_render_id' ] = $notice->id.wp_generate_password( 12, false );
		$data[ 'notice_id' ] = $notice->id;
		$data[ 'can_dismiss' ] = $notice->can_dismiss ?? true;

		$data[ 'imgs' ] = [
			'icon_shield' => $con->svgs->raw( 'shield-shaded.svg' ),
		];

		return $data;
	}

	private function getWpNoticeClass( string $type ) :string {
		switch ( $type ) {
			case 'error':
				$wpNoticeClass = 'notice-error';
				break;
			case 'warning':
				$wpNoticeClass = 'notice-warning';
				break;
			case 'updated':
			case 'success':
				$wpNoticeClass = 'notice-success';
				break;
			default:
				$wpNoticeClass = 'notice-info';
				break;
		}
		return $wpNoticeClass;
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
