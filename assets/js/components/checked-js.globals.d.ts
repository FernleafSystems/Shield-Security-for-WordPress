declare const ajaxurl: string;

declare const shieldServices: {
	notification() :{
		showMessage( message: string, success?: boolean ) :void;
	};
};

declare const shieldStrings: {
	string( key: string ) :string;
};

declare const shieldAppMain: {
	components?: Record<string, any>;
}|undefined;

declare const shieldEventsHandler_Main: {
	add_Click( selector: string, callback: ( targetEl: Element, evt: Event ) => void, suppress?: boolean ) :void;
	add_Mouseover( selector: string, callback: ( targetEl: Element, evt: Event ) => void, suppress?: boolean ) :void;
	addHandler( eventName: string, selector: string, callback: ( targetEl: Element, evt: Event ) => void, suppress?: boolean ) :void;
};

declare module "*.scss";

type ShieldPluginOnboardingStepData = {
	selector: string;
	title: string;
	intro: string;
	position: string;
	required: boolean;
};

type ShieldPluginOnboardingVideoModalData = {
	is_enabled: boolean;
	embed_url: string;
	modal_title: string;
	video_title: string;
	body_copy: string;
	continue_label: string;
	skip_label: string;
};

type ShieldPluginOnboardingTourData = {
	key: string;
	is_available: boolean;
	steps: ShieldPluginOnboardingStepData[];
	options: Record<string, any>;
	video_modal: ShieldPluginOnboardingVideoModalData;
};

type ShieldPluginOnboardingBaseData = {
	ajax: {
		finished: Record<string, any>;
	};
	vars: {
		tour: ShieldPluginOnboardingTourData;
	};
};

type ShieldPluginOnboardingGlobal = {
	comps?: {
		plugin_onboarding?: ShieldPluginOnboardingBaseData;
	};
};

interface Window {
	shieldAppMain?: {
		components?: Record<string, any>;
	};
	shield_vars_plugin_onboarding?: ShieldPluginOnboardingGlobal;
	Vimeo?: {
		Player: new ( iframe: HTMLIFrameElement ) => {
			destroy?: () => Promise<void>;
			unload?: () => Promise<void>;
		};
	};
}

interface JQuery<TElement = HTMLElement> {
	select2( ...args: any[] ) :JQuery<TElement>;
}

interface JQueryStatic {
	fn: {
		select2?: ( ...args: any[] ) => any;
		[key: string]: any;
	};
}
