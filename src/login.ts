import { XMLBuilder, XMLParser } from "fast-xml-parser";

const redirect_table: Record<string, string> = {
	"ilanes": "https://ilanes.rumiserver.com/",
	"ilanes_admin": "https://ilanes.rumiserver.com/admin/",
	"storage": "https://storage.rumiserver.com/",
	"rumichat": "https://chat.rumiserver.com/",
	"welcome": "https://account.rumiserver.com/welcome"
};

let mel = {
	text_box: document.getElementById("TEXT_BOX")! as HTMLInputElement,
	error: document.getElementById("ERROR")! as HTMLDivElement
};
let user_id: string|null = null;
let password: string|null = null;
let totp: string|null = null;

window.addEventListener("load", function() {
	//スマホか？
	const ua_check = /Android|iPhone|iPad|iPod|Windows Phone/i.test(navigator.userAgent);
	const touch_check = ('ontouchstart' in window || navigator.maxTouchPoints > 0) && window.innerWidth <= 768;
	if (ua_check || touch_check) {
		window.location.href = "smartphone.html";
		return;
	}

	init();
});

/**
 * 初期化
*/
function init() {
	user_id = null;
	password = null;
	totp = null;

	change_prompt("ユーザーID");
	mel.text_box.name = "username";
	mel.text_box.onkeydown = function(e) {
		if (e.key === "Enter") {
			userid_done();
		}
	};
}

/**
 * ユーザーIDを入力後
*/
function userid_done() {
	if (mel.text_box.value == "") return;
	user_id = mel.text_box.value;

	change_prompt("パスワード");
	mel.text_box.type = "password";
	mel.text_box.name = "password";
	mel.text_box.onkeydown = function(e) {
		if (e.key === "Enter") {
			password_done();
		}
	};
}

/**
 * パスワード入力後
*/
function password_done() {
	if (mel.text_box.value == "") return;
	password = mel.text_box.value;
	login();
}

async function login() {
	change_prompt("お待ちください。");
	mel.text_box.type = "text";
	mel.text_box.name = "wait";
	mel.text_box.onkeydown = null;
	mel.text_box.disabled = true;

	const xml_builder = new XMLBuilder({ ignoreAttributes: false, format: true, suppressEmptyNode: true });
	let request_xml: string;
	
	if (totp == null) {
		request_xml = xml_builder.build(
			{
				REQUEST: {
					"USER_ID": user_id,
					"PASSWORD": password
				}
			}
		);
	} else {
		request_xml = xml_builder.build(
			{
				REQUEST: {
					"USER_ID": user_id,
					"PASSWORD": password,
					"TOTP": totp
				}
			}
		);
	}

	//APIへログイン要請
	let ajax = await fetch(`https://${window.location.host}/api/Session`, {
		method: "POST",
		headers: {
			"Content-Type": "application/xml; charset=UTF-8",
			"Accept": "application/xml; charset=UTF-8"
		},
		body: request_xml
	});

	const xml_parser = new XMLParser({ ignoreAttributes: false, attributeNamePrefix: "@_" });
	const result = xml_parser.parse(await ajax.text())["RESULT"];

	if (result.STATUS == false) {
		mel.error.innerHTML = `ログイン失敗！<A HREF="/login_issue/">もう無理だ</A>`;
		init();
		return;
	}

	//追加の認証情報
	if (result.REQUIRE != null) {
		switch (result.REQUIRE) {
			case "TOTP": {
				change_prompt("二段階認証");
				mel.text_box.disabled = false;
				mel.text_box.onkeydown = function(e) {
					if (e.key === "Enter") {
						totp = mel.text_box.value;
						login();
					}
				};
				return;
			}
		}
	}

	//クッキーにセッションを登録
	let cookie_date = new Date();
	cookie_date.setTime(cookie_date.getTime() + 400 * 24 * 60 * 60 * 1000);
	document.cookie = `SESSION=${result.TOKEN}; path=/; domain=rumiserver.jp; expires=${cookie_date.toUTCString()}; Secure; SameSite=Lax`;

	//リダイレクトする
	const redirect_select = new URLSearchParams(window.location.search).get("rd");
	if (redirect_select == null) {
		window.location.href = "/";
	} else {
		if (redirect_table[redirect_select] != null) {
			window.location.href = redirect_table[redirect_select];
		} else {
			window.location.href = redirect_select;
		}
	}
}

function change_prompt(placeholder: string) {
	mel.text_box.placeholder = placeholder;
	mel.text_box.value = "";
}