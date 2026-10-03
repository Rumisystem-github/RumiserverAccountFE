import { get_token, get_user, init } from "./main";

const TARGET_WIDTH = 256;
const TARGET_HEIGHT = 256;

let mouse_click = false;
let image = new Image();

let mel = {
	wait: document.getElementById("WAIT")! as HTMLDivElement,
	main: document.getElementById("MAIN")! as HTMLDivElement,
	display: document.getElementById("DISPLAY")! as HTMLCanvasElement,
	ctx: (document.getElementById("DISPLAY")! as HTMLCanvasElement).getContext("2d")!,
	controle: {
		file: document.getElementById("INPUT_FILE")! as HTMLInputElement,
		draw_guide: document.getElementById("INPUT_GUIDE")! as HTMLInputElement,
		x: document.getElementById("INPUT_X")! as HTMLInputElement,
		y: document.getElementById("INPUT_Y")! as HTMLInputElement,
		w: document.getElementById("INPUT_W")! as HTMLInputElement,
		h: document.getElementById("INPUT_H")! as HTMLInputElement,
		reset: document.getElementById("RESET_BTN")! as HTMLButtonElement,
		apply: document.getElementById("APPLY_BTN")! as HTMLButtonElement
	}
};

window.addEventListener("load", async function() {
	await init();

	//キャンバスを初期化
	mel.display.width = TARGET_WIDTH;
	mel.display.height = TARGET_HEIGHT;

	//イベントハンドラー
	mel.controle.file.addEventListener("change", function() {
		load_file(mel.controle.file.files![0]);
	});

	mel.controle.draw_guide.addEventListener("change", function() {
		draw();
	});

	mel.controle.x.addEventListener("change", function() {
		draw();
	});

	mel.controle.y.addEventListener("change", function() {
		draw();
	});

	mel.controle.w.addEventListener("change", function() {
		draw();
	});

	mel.controle.h.addEventListener("change", function() {
		draw();
	});

	mel.controle.reset.addEventListener("click", function() {
		reset();
		draw();
	});

	mel.controle.apply.addEventListener("click", function() {
		apply();
	});

	//値を初期化
	mel.controle.x.value = "0";
	mel.controle.y.value = "0";
	mel.controle.w.value = "0";
	mel.controle.h.value = "0";

	mel.wait.style.display = "none";
	mel.main.style.display = "flex";
});

//マウスクリック
mel.display.addEventListener("mouseup", ()=>{
	mouse_click = false;
});

mel.display.addEventListener("mousedown", ()=>{
	mouse_click = true;
});

mel.display.addEventListener("mouseleave", ()=>{
	mouse_click = false;
});

//移動
mel.display.addEventListener("mousemove", (e)=>{
	if (mouse_click == false) return;
	mel.controle.x.value = (Number.parseInt(mel.controle.x.value) + e.movementX).toString();
	mel.controle.y.value = (Number.parseInt(mel.controle.y.value) + e.movementY).toString();

	draw();
});

//ホイール
mel.display.addEventListener("wheel", (e)=>{
	const zoom_bairicu = (e.deltaY / 4) * -1;

	const x = Number.parseInt(mel.controle.x.value);
	const y = Number.parseInt(mel.controle.y.value);
	const w = Number.parseInt(mel.controle.w.value);
	const h = Number.parseInt(mel.controle.h.value);

	const target_x = TARGET_WIDTH / 2;
	const target_y = TARGET_HEIGHT / 2;
	const target_soutai_x = (target_x - x) / w;
	const target_soutai_y = (target_y - y) / h;

	const new_w = w + zoom_bairicu;
	const new_h = icon_hiricu_calc(image.width, image.height, new_w);
	const new_x = target_x - (target_soutai_x * new_w);
	const new_y = target_y - (target_soutai_y * new_h);

	mel.controle.x.value = Math.floor(new_x).toString();
	mel.controle.y.value = Math.floor(new_y).toString();
	mel.controle.w.value = Math.floor(new_w).toString();
	mel.controle.h.value = Math.floor(new_h).toString();

	draw();
});

//画像ロード
image.addEventListener("load", ()=>{
	reset();
	draw();
});

//ファイル選択
function load_file(file: File) {
	if (file == null) return;
	const reader = new FileReader();
	reader.onload = function() {
		image.src = reader.result as string;
	}
	reader.readAsDataURL(file);
}

function reset() {
	mel.controle.w.value = TARGET_WIDTH.toString();

	const original_width = image.width;
	const original_height = image.height;
	//const aspect_ratio = original_width / original_height;

	const new_width = Number.parseInt(mel.controle.w.value);
	const new_height = icon_hiricu_calc(original_width, original_height, new_width);
	const new_x = Number.parseInt(mel.controle.x.value);
	const new_y = Math.floor((TARGET_WIDTH - new_height) / 2);

	mel.controle.x.value = new_x.toString();
	mel.controle.y.value = new_y.toString();
	mel.controle.w.value = new_width.toString();
	mel.controle.h.value = new_height.toString();
}

//比率を計算して高さを出す
function icon_hiricu_calc(original_w: number, original_h: number, target_w: number) {
	return Math.floor(target_w / (original_w / original_h));
}

//描画
function draw() {
	mel.ctx.clearRect(0, 0, TARGET_WIDTH, TARGET_HEIGHT);

	const x = Number.parseInt(mel.controle.x.value);
	const y = Number.parseInt(mel.controle.y.value);
	const w = Number.parseInt(mel.controle.w.value);
	const h = Number.parseInt(mel.controle.h.value);

	//画像を描画
	mel.ctx.drawImage(image, x, y, w, h);

	if (mel.controle.draw_guide.checked) {
		//画像のガイド
		mel.ctx.strokeStyle = "red";
		mel.ctx.strokeRect(x - 1, y - 1, w + 1, h + 1);

		//キャンバスの中央
		mel.ctx.strokeStyle = "black";
		mel.ctx.lineWidth = 1;
		mel.ctx.beginPath();
		mel.ctx.moveTo(TARGET_WIDTH / 2, 0);
		mel.ctx.lineTo(TARGET_WIDTH / 2, TARGET_HEIGHT);
		mel.ctx.moveTo(0, TARGET_HEIGHT / 2);
		mel.ctx.lineTo(TARGET_WIDTH, TARGET_HEIGHT / 2);
		mel.ctx.stroke();
	}
}

//適用
async function apply() {
	//描画
	const x = Number.parseInt(mel.controle.x.value);
	const y = Number.parseInt(mel.controle.y.value);
	const w = Number.parseInt(mel.controle.w.value);
	const h = Number.parseInt(mel.controle.h.value);
	mel.ctx.fillStyle = "white";
	mel.ctx.fillRect(0, 0, TARGET_WIDTH, TARGET_HEIGHT);
	mel.ctx.drawImage(image, x, y, w, h);

	//ロード
	const data_url = mel.display.toDataURL();
	const base64_data = data_url.split(",")[1];
	const binary_data = atob(base64_data);

	//バイト配列に変換
	const byte_array = new Uint8Array(binary_data.length);
	for (let i = 0; i < binary_data.length; i++) {
		byte_array[i] = binary_data.charCodeAt(i);
	}

	let ajax = await fetch("/api/Icon", {
		method: "PATCH",
		headers: {
			TOKEN: get_token(),
			"Accept": "application/xml"
		},
		body: byte_array
	});
	const result = await ajax.text();

	alert("変更しました、たぶん。");
}