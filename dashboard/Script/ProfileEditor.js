async function update_self_icon() {
	let ajax = await fetch(`/api/Icon?UID=${self_user.UID}`);
	const result = await ajax.blob();

	const reader = new FileReader();
	return new Promise((resolve, reject) => {
		reader.addEventListener("loadend", (e)=>{
			self_icon = reader.result;
			resolve();
		});
		reader.readAsDataURL(result);
	});
}

async function open_profile_editor() {
	//プロフィール編集にいれる
	mel.profile_editor.icon.src = self_icon;
	mel.profile_editor.name.value = self_user.NAME;
	mel.profile_editor.description.value = self_user.DESCRIPTION;

	mel.profile_editor.bg.style.display = "block";
	mel.profile_editor.parent.style.display = "block";
}

function close_profile_editor() {
	mel.profile_editor.bg.style.display = "none";
	mel.profile_editor.parent.style.display = "none";
}

async function profile_apply() {
	const l = dialog.SHOW_LOAD();

	let ajax = await fetch("/api/User", {
		method: "PATCH",
		headers: {
			TOKEN: session,
			"Content-Type": "application/json; charset=UTF-8",
			"Accept": "application/json"
		},
		body: JSON.stringify({
			"NAME": mel.profile_editor.name.value,
			"DESC": mel.profile_editor.description.value
		})
	});
	const result = await ajax.json();
	dialog.CLOSE_LOAD(l);

	if (result.STATUS == false) {
		dialog.DIALOG("エラー");
	}

	//アイコン更新
	await update_self_icon();
	mel.main.user.icon.src = self_icon;

	close_profile_editor();
}

function open_icon_editor() {
	mel.profile_editor.icon_form.parent.style.display = "block";
}

async function __update_icon_success() {
	await update_self_icon();

	mel.profile_editor.icon.src = self_icon;
	mel.profile_editor.icon_form.parent.style.display = "none";
}

function __update_icon_failed() {
	dialog.DIALOG("エラー");

	mel.profile_editor.icon_form.parent.style.display = "none";
}