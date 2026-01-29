let dialog = new DIALOG_SYSTEM();
let session = null;
let self_user = null;
let self_icon = null;
let mel = {
	main: {
		user: {
			icon: document.getElementById("USER_ICON"),
			name: document.getElementById("USER_NAME")
		},
		follow: {
			followed: document.getElementById("FOLLOWED_COUNT"),
			follower: document.getElementById("FOLLOWER_COUNT")
		},
		controller: {
			profile_edit: document.getElementById("PROFILE_EDIT_BUTTON")
		},
		notify_list: {
			parent: document.getElementById("NOTIFY_LIST")
		}
	},
	profile_editor: {
		bg: document.getElementById("PROFILE_EDITOR_BG"),
		parent: document.getElementById("PROFILE_EDITOR"),
		icon: document.getElementById("PROFILE_ICON"),
		name: document.getElementById("PROFILE_NAME"),
		description: document.getElementById("PROFILE_DESCRIPTION"),
		icon_form: {
			parent: document.getElementById("ICON_EDITOR")
		}
	}
};

window.addEventListener("load", async (e)=>{
	session = ReadCOOKIE().SESSION;
	if (session == null) {
		window.location.href = "/login";
		return;
	}

	self_user = await LOGIN(session);
	if (self_user == false) {
		window.location.href = "/login";
		return;
	}

	//通知取得
	let ajax = await fetch("/api/Notify", {
		headers: {
			"TOKEN": session,
			"Content-Type": "application/json; charset=UTF-8",
			"Accept": "application/json"
		}
	});
	const result = await ajax.json();
	for (let i = 0; i < result.LIST.length; i++) {
		const notify = result.LIST[i];
		mel.main.notify_list.parent.appendChild(gen_notify_item(notify));
	}

	//アイコン取得
	await update_self_icon();

	//ゆーざーじょうほう
	mel.main.user.icon.src = self_icon;
	mel.main.user.icon.className = `ICON_${self_user.ICON}`;
	mel.main.user.name.innerText = self_user.NAME;

	//フォローワー数
	mel.main.follow.followed.innerText = self_user.FOLLOWED;
	mel.main.follow.follower.innerText = self_user.FOLLOWER;
});

mel.main.controller.profile_edit.addEventListener("click", (e)=>{
	open_profile_editor();
});

function gen_notify_item(notify) {
	let item = document.createElement("DIV");
	item.className = "NOTIFY_ITEM";
	item.dataset.id = notify.ID;

	//サービス
	let service = document.createElement("DIV");
	service.className = "SERVICE";
	item.appendChild(service);

	let service_icon = document.createElement("IMG");
	service_icon.src = notify.SERVICE.ICON_URL;
	service.appendChild(service_icon);

	let service_name = document.createElement("SPAN");
	service_name.innerText = `${notify.SERVICE.NAME} - ${notify.TITLE}`;
	service.appendChild(service_name);

	let text = document.createElement("DIV");
	text.className = "TEXT";
	text.innerText = notify.TEXT;
	item.appendChild(text);

	return item;
}