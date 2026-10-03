import { get_token, init } from "./main";
import { aspect_calc, export_image_binary, preview_draw, wheel_zoom } from "./Tool/IconHeaderForm";

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
	const x = Number.parseInt(mel.controle.x.value);
	const y = Number.parseInt(mel.controle.y.value);
	const w = Number.parseInt(mel.controle.w.value);
	const h = Number.parseInt(mel.controle.h.value);

	const zoom_result = wheel_zoom(e.deltaY, x, y, w, h, image.width, image.height, TARGET_WIDTH, TARGET_HEIGHT);

	mel.controle.x.value = zoom_result[0].toString();
	mel.controle.y.value = zoom_result[1].toString();
	mel.controle.w.value = zoom_result[2].toString();
	mel.controle.h.value = zoom_result[3].toString();

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
	const new_height = aspect_calc(new_width, original_width, original_height);
	const new_x = Number.parseInt(mel.controle.x.value);
	const new_y = Math.floor((TARGET_WIDTH - new_height) / 2);

	mel.controle.x.value = new_x.toString();
	mel.controle.y.value = new_y.toString();
	mel.controle.w.value = new_width.toString();
	mel.controle.h.value = new_height.toString();
}

//描画
function draw() {
	const x = Number.parseInt(mel.controle.x.value);
	const y = Number.parseInt(mel.controle.y.value);
	const w = Number.parseInt(mel.controle.w.value);
	const h = Number.parseInt(mel.controle.h.value);

	preview_draw(mel.ctx, x, y, w, h, mel.controle.draw_guide.checked, TARGET_WIDTH, TARGET_HEIGHT, image)
}

//適用
async function apply() {
	const x = Number.parseInt(mel.controle.x.value);
	const y = Number.parseInt(mel.controle.y.value);
	const w = Number.parseInt(mel.controle.w.value);
	const h = Number.parseInt(mel.controle.h.value);
	const binary = export_image_binary(x, y, w, h, TARGET_WIDTH, TARGET_HEIGHT, image);

	let ajax = await fetch("/api/Icon", {
		method: "PATCH",
		headers: {
			TOKEN: get_token(),
			"Accept": "application/xml"
		},
		body: binary
	});
	const result = await ajax.text();

	alert("変更しました、たぶん。");
}