import { BaseService } from "./BaseService";
import { MfaUserProfileRender } from "./userprofile/MfaUserProfileRender";
import { RemoveAllProviders } from "./userprofile/RemoveAllProviders";

export class UserProfileHandler extends BaseService {

	init() {
		this.exec()
	}

	run() {
		new RemoveAllProviders( this._base_data );
		new MfaUserProfileRender( this._base_data );
	}
}