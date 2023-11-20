import { BaseComponent } from "../BaseComponent";
import { MfaUserProfileRender } from "./MfaUserProfileRender";
import { RemoveAllProviders } from "./RemoveAllProviders";

export class UserProfileHandler extends BaseComponent {

	init() {
		this.exec()
	}

	run() {
		new RemoveAllProviders( this._base_data );
		new MfaUserProfileRender( this._base_data );
	}
}