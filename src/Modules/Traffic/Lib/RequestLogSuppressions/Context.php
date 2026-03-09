<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogSuppressions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\{
	PluginNavs,
	PluginRequest
};
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequest;
use FernleafSystems\Wordpress\Services\Services;

class Context {

	private ThisRequest $req;

	/**
	 * @var mixed
	 */
	private $batchRequests;

	public function __construct( ThisRequest $req ) {
		$this->req = $req;
		$this->batchRequests = $this->requestParam( 'requests' );
	}

	public function execute() :string {
		return (string)$this->requestParam( ActionData::FIELD_EXECUTE );
	}

	/**
	 * @return mixed
	 */
	public function batchRequests() {
		return $this->batchRequests;
	}

	public function isAjax() :bool {
		return $this->req->wp_is_ajax;
	}

	public function isLoggedIn() :bool {
		return Services::WpUsers()->isUserLoggedIn();
	}

	public function isSecurityAdmin() :bool {
		return $this->req->is_security_admin;
	}

	public function isAdmin() :bool {
		return $this->req->wp_is_admin;
	}

	public function isPluginAdminPage() :bool {
		return PluginRequest::IsPluginAdminPage();
	}

	public function isShieldTransport() :bool {
		return $this->requestAction() === ActionData::FIELD_SHIELD;
	}

	public function method() :string {
		return \strtoupper( (string)$this->req->method );
	}

	public function path() :string {
		return $this->req->path;
	}

	public function scriptName() :string {
		return $this->req->script_name;
	}

	public function nav() :string {
		return PluginNavs::GetNav();
	}

	public function subNav() :string {
		return PluginNavs::GetSubNav();
	}

	public function queryKeys() :array {
		$keys = \array_map(
			'strval',
			\array_keys( \is_array( $this->req->request->query ) ? $this->req->request->query : [] )
		);
		\sort( $keys );
		return $keys;
	}

	public function renderSlug() :string {
		return (string)$this->requestParam( 'render_slug' );
	}

	public function restRoute() :string {
		return $this->req->getRestRoute();
	}

	public function requestAction() :string {
		return (string)$this->requestParam( ActionData::FIELD_ACTION );
	}

	public function screenId() :string {
		return \sanitize_key( (string)$this->requestParam( 'screen_id' ) );
	}

	/**
	 * @return mixed
	 */
	private function requestParam( string $key ) {
		$request = $this->req->request;
		return $request->post[ $key ] ?? $request->query[ $key ] ?? null;
	}
}
