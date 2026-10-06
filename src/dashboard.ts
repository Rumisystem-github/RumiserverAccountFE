import { get_user, init } from "./main";

let mel = {
	user: {
		header: document.getElementById("USER_HEADER")! as HTMLImageElement,
		icon: document.getElementById("USER_ICON")! as HTMLImageElement,
		name: document.getElementById("USER_NAME")! as HTMLDivElement,
		description: document.getElementById("USER_DESCRIPTION")! as HTMLDivElement
	},
	icon_editor: {
		bg :document.getElementById("ICON_EDITOR_BG")! as HTMLDivElement,
		editor: document.getElementById("ICON_EDITOR")! as HTMLDivElement
	},
	header_editor: {
		bg :document.getElementById("HEADER_EDITOR_BG")! as HTMLDivElement,
		editor: document.getElementById("HEADER_EDITOR")! as HTMLDivElement
	}
};

window.addEventListener("load", async function() {
	await init();

	mel.user.icon.src = get_user().ICON_URL;
	mel.user.header.src = get_user().HEADER_URL;
	mel.user.name.innerText = get_user().NAME;
	mel.user.description.innerText = get_user().DESCRIPTION;

	mel.user.header.ondblclick = function() {
		mel.header_editor.bg.style.display = "block";
		mel.header_editor.editor.style.display = "block";

		mel.header_editor.bg.onclick = function() {
			mel.header_editor.bg.style.display = "none";
			mel.header_editor.editor.style.display = "none";
		}
	}

	mel.user.icon.ondblclick = function() {
		mel.icon_editor.bg.style.display = "block";
		mel.icon_editor.editor.style.display = "block";

		mel.icon_editor.bg.onclick = function() {
			mel.icon_editor.bg.style.display = "none";
			mel.icon_editor.editor.style.display = "none";
		}
	}
});