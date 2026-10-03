/**
 * icon_formとheader_formの共通化できる箇所をまとめたものです。
 */

/**
 * ホイールのズーム機構
 * 
 * @param delta_y wheelイベントのdeltaY
 * @param x 現在のx座標
 * @param y 現在のy座標
 * @param w 現在のw座標
 * @param h 現在のh座標
 * @param image_width 画像の横幅
 * @param image_height 画像の縦幅
 * @param target_width 目標の横幅
 * @param target_height 目標の縦幅
 * 
 * @return x y w hの順です。
 */
export function wheel_zoom(delta_y: number, x: number, y: number, w: number, h: number, image_width: number, image_height: number, target_width: number, target_height: number): number[] {
	const zoom_bairicu = (delta_y / 4) * -1;

	const target_x = target_width / 2;
	const target_y = target_height / 2;
	const target_soutai_x = (target_x - x) / w;
	const target_soutai_y = (target_y - y) / h;

	const new_w = w + zoom_bairicu;
	const new_h = aspect_calc(new_w, image_width, image_height);
	const new_x = target_x - (target_soutai_x * new_w);
	const new_y = target_y - (target_soutai_y * new_h);

	return [
		Math.floor(new_x),
		Math.floor(new_y),
		Math.floor(new_w),
		Math.floor(new_h)
	]
}

/**
 * 比率を計算して高さを出す
 * 
 * @param target_w 目標の横幅
 * @param original_w 元の横幅
 * @param original_h 元の縦幅
 */
export function aspect_calc(target_w: number, original_w: number, original_h: number): number {
	return Math.floor(target_w / (original_w / original_h));
}

/**
 * プレビューの描画をします
 * 
 * @param ctx 「getContext("2d")」関数の返り値
 * @param x X
 * @param y Y
 * @param w W
 * @param h H
 * @param draw_guide ガイドを描画するか
 * @param target_width 目標の横幅
 * @param target_height 目標の縦幅
 * @param image 画像
 */
export function preview_draw(ctx: CanvasRenderingContext2D, x: number, y: number, w: number, h: number, draw_guide: boolean, target_width: number, target_height: number, image: HTMLImageElement) {
	ctx.clearRect(0, 0, target_width, target_height);

	//画像を描画
	ctx.drawImage(image, x, y, w, h);

	if (draw_guide) {
		//画像のガイド
		ctx.strokeStyle = "red";
		ctx.strokeRect(x - 1, y - 1, w + 1, h + 1);

		//キャンバスの中央
		ctx.strokeStyle = "black";
		ctx.lineWidth = 1;
		ctx.beginPath();
		ctx.moveTo(target_width / 2, 0);
		ctx.lineTo(target_width / 2, target_height);
		ctx.moveTo(0, target_height / 2);
		ctx.lineTo(target_width, target_height / 2);
		ctx.stroke();
	}
}

/**
 * 画像として合成しエクスポートする
 * 
 * @param x X
 * @param y Y
 * @param w W
 * @param h H
 * @param target_width 目標の横幅
 * @param target_height 目標の縦幅
 * @param image 画像
 */
export function export_image_binary(x: number, y: number, w: number, h: number, target_width: number, target_height: number, image: HTMLImageElement): Uint8Array<ArrayBuffer> {
	let canvas = document.createElement("CANVAS") as HTMLCanvasElement;
	const ctx = canvas.getContext("2d")!;

	canvas.width = target_width;
	canvas.height = target_height;

	ctx.fillStyle = "white";
	ctx.fillRect(0, 0, target_width, target_height);
	ctx.drawImage(image, x, y, w, h);

	//画像に変換
	const data_url = canvas.toDataURL();
	const base64_data = data_url.split(",")[1];
	const binary_string = atob(base64_data);

	//バイト配列に変換
	const binary = new Uint8Array(binary_string.length);
	for (let i = 0; i < binary_string.length; i++) {
		binary[i] = binary_string.charCodeAt(i);
	}

	//後処理
	canvas.remove();

	return binary;
}