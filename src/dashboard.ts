import { get_user, init } from "./main";

let mel = {
	user: {
		icon: document.getElementById("USER_ICON")! as HTMLImageElement
	},
	icon_editor: document.getElementById("ICON_EDITOR")! as HTMLDivElement
};

window.addEventListener("load", async function() {
	await init();

	mel.user.icon.src = get_user().ICON_URL;

	mel.user.icon.onclick = function() {
		mel.icon_editor.style.display = "block";
	};
});