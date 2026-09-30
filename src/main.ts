import { XMLParser } from "fast-xml-parser";
import type { User } from "./Type/user";

let token: string|null = null;
let user: User;

export async function init() {
	await login();
}

async function login() {
	const cookie_list = document.cookie.split(";");
	for (const c of cookie_list) {
		const split = c.split("=");
		const key = split[0];
		const value = split[1];
		if (key == "SESSION") {
			token = value;
		}
	}

	if (token == null) {
		window.location.href = "/login.html";
		return;
	}

	let ajax = await fetch("/api/Session", {
		headers: {
			"Accept": "application/xml",
			"Token": token
		}
	});

	const xml_parser = new XMLParser({ ignoreAttributes: false, attributeNamePrefix: "@_" });
	const result = xml_parser.parse(await ajax.text())["RESULT"];
	user = result.USER as User;
}

export function get_token(): string {
	return token!;
}

export function get_user(): User {
	return user;
}