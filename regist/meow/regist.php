<?php
//PHPMailer
require(__DIR__."/PHPMailer/PHPMailer.php");
require(__DIR__."/PHPMailer/Exception.php");
require(__DIR__."/PHPMailer/SMTP.php");
use PHPMailer\PHPMailer;

if ($_SERVER["REQUEST_METHOD"] != "POST") {
	error_return("0x4000", "メソッドが異常です。", __LINE__);
}

$body = json_decode(file_get_contents("php://input"), true);

//環境設定を読む
$json = file_get_contents($_SERVER['DOCUMENT_ROOT']."/ENV.json");
if ($json == false) error_return("0x5000", "環境エラー", __LINE__);
$env = json_decode($json, true);
if (json_last_error() != 0) error_return("0x5000", "環境エラー", __LINE__);

try {
	regist();
} catch(\Throwable $ex) {
	error_return("0x5000", $ex->getMessage(), $ex->getLine());
	exit;
}

/**
 * 登録シーケンス
 * @return void
 */
function regist() {
	global $body, $env;

	//値チェック
	check_value();

	//CFT
	check_cft();

	//値を整理
	$regist_id = uniqid();
	$user_id = $body["ID"];
	$user_name = $body["NAME"];
	$user_password = $body["PASSWORD"];
	$user_address = $body["ADDRESS"];
	$user_birthday = $body["BIRTHDAY"];
	$address_type = 0;

	if (str_starts_with($user_address, "@")) $address_type = 1;

	//SQLを開く
	$sql = new PDO("mysql:host=192.168.0.130;dbname=RumiServer;", $env["SQL_UID"], $env["SQL_PASS"], [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

	//ユーザーIDが被っていないか？
	$stmt = $sql->prepare("SELECT `ID` FROM `ACCOUNT` WHERE `UID` = :uid LIMIT 1;");
	$stmt->bindValue(":uid", $user_id, PDO::PARAM_STR);
	$stmt->execute();
	if ($stmt->fetch() != false) {
		error_return("0x4003", "そのユーザーIDは使えません、被ってます", __LINE__);
	}
	$stmt->closeCursor();

	//すでにアドレスが使われているかどうかをチェック
	$stmt = $sql->prepare("SELECT `ID` FROM `CONTACT` WHERE `ADDRESS` = :address LIMIT 1;");
	$stmt->bindValue(":address", $user_address, PDO::PARAM_STR);
	$stmt->execute();
	if ($stmt->fetch() != false) {
		error_return("0x4003", "アドレスがすでに使われています！\nサブアカウントを作成する場合は[サブアカウントの作成]で行ってください。", __LINE__);
	}
	$stmt->closeCursor();

	//パスワードをハッシュ化
	$password_hash = $user_password;
	for ($i = 0; $i < 10000; $i++) {
		$password_hash = hash("SHA3-512", $password_hash);
	}

	//登録データ
	$regist_data = [
		"UID" => $user_id,
		"NAME" => $user_name,
		"MAIL" => $user_address,
		"PASS" => $password_hash,
		"PASS_TYPE" => "R_4",
		"REGIST_DATE" => date("Y-m-d H:i:s"),
		"TANZHOOBI" => $user_birthday
	];

	//SQLに登録
	$stmt = $sql->prepare("INSERT INTO `MAIL_VERIFY` (`ID`, `REGIST_DATA`, `ADDRESS_TYPE`) VALUES (:ID, :DATA, :AT);");
	$stmt->bindValue(":ID", $regist_id, PDO::PARAM_STR);
	$stmt->bindValue(":DATA", json_encode($regist_data), PDO::PARAM_STR);
	$stmt->bindValue(":AT", $address_type, PDO::PARAM_INT);
	$stmt->execute();

	//メール本文
	$mail_body = "--------------------[ rumiserver.com ]--------------------\n";
	$mail_body .= "あなたのアカウントは正常に登録されました！\n";
	$mail_body .= "以下のリンクを開き、アドレスの認証をしてください。\n";
	$mail_body .= "https://account.rumiserver.com/regist/verify/?ID=".$regist_id."\n";
	$mail_body .= "\n";
	$mail_body .= "登録した記憶がない場合→見なかったことにしてください\n";
	$mail_body .= "----------------------------------------------------------\n";
	$mail_body .= "お問い合わせ: https://form.rumia.me/contact/?to=RSV\n";
	$mail_body .= "運営会社:     合同会社るみしすてむ / https://rumishistem.su/\n";

	send_message($address_type, $user_address, $mail_body);

	echo json_encode(["STATUS" => true]);
}

/**
 * POSTされた値をチェック
 * @return void
 */
function check_value() {
	global $body;

	//そもそもPOSTされているか
	if (empty($body["ID"]) || empty($body["NAME"]) || empty($body["PASSWORD"]) || empty($body["ADDRESS"]) || empty($body["BIRTHDAY"]) || empty($body["CFT"])) {
		error_return("0x4000", "値が足りません", __LINE__);
	}

	//IDチェック
	if (!preg_match("/^[A-Za-z_]+$/", $body["ID"])) {
		error_return("0x4000", "IDが不正", __LINE__);
	}

	//パスワードチェック
	if (!preg_match("/^[\x20-\x7E]+$/", $body["PASSWORD"])) {
		error_return("0x4000", "パスワードが不正", __LINE__);
	}

	//アドレスチェック
	if (str_starts_with($body["ADDRESS"], "@")) {
		//Fediverseアドレス
		$host = explode("@", $body["ADDRESS"])[2];
		$allow_list = json_decode(file_get_contents(__DIR__."/apub.json"), true);
		foreach ($allow_list as $row) {
			if ($row == $host) return;
		}
		error_return("0x4000", "Fediverseアドレスが不正", __LINE__);
	} else {
		//メールアドレス
		$host = explode("@", $body["ADDRESS"])[1];
		$allow_list = json_decode(file_get_contents(__DIR__."/mail.json"), true);
		foreach ($allow_list as $row) {
			if ($row == $host) return;
		}
		error_return("0x4000", "メールアドレスが不正", __LINE__);
	}
}

function check_cft() {
	global $env, $body;

	$ajax = curl_init("https://challenges.cloudflare.com/turnstile/v0/siteverify");
	curl_setopt($ajax, CURLOPT_POST, true);
	curl_setopt($ajax, CURLOPT_POSTFIELDS, json_encode(["secret" => $env["CFT"]["SEACRET_KEY"], "response" => $body["CFT"]]));
	curl_setopt($ajax, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ajax, CURLOPT_HTTPHEADER, [
		"Content-Type: application/json"
	]);
	$result = json_decode(curl_exec($ajax), true);
	curl_close($ajax);

	if (!$result["success"]) {
		error_return("0x4000", "CFT", __LINE__);
	}
}

function send_message(int $address_type, string $user_address, string $mail_body) {
	global $env;

	if ($address_type == 1) {
		$url = "https://".$env["ACTIVITYPUB"]["HOST"];
		$token = $env["ACTIVITYPUB"]["TOKEN"];
		$username = explode("@", ltrim($user_address, "@"))[0];
		$host = explode("@", ltrim($user_address, "@"))[1];

		//acctからユーザーIDを取得する
		$ajax = curl_init($url."/api/users/show");
		curl_setopt($ajax, CURLOPT_POST, true);
		curl_setopt($ajax, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json; charset=UTF-8",
			"Accept: application/json; charset=UTF-8"
		]);
		curl_setopt($ajax, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ajax, CURLOPT_POSTFIELDS, json_encode([
			"i" => $token,
			"username" => $username,
			"host" => $host
		]));
		$result = json_decode(curl_exec($ajax), true);
		if (isset($result["error"])) {
			echo json_encode(["STATUS" => false, "ERR" => "SYSTEM_ERR", "EX" => "ユーザーなし"]);
			return;
		}

		$fedi_user_id = $result["id"];

		//DMを送信
		$ajax = curl_init($url."/api/notes/create");
		curl_setopt($ajax, CURLOPT_POST, true);
		curl_setopt($ajax, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json; charset=UTF-8",
			"Accept: application/json; charset=UTF-8"
		]);
		curl_setopt($ajax, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ajax, CURLOPT_POSTFIELDS, json_encode([
			"i" => $token,
			"cw" => null,
			"localOnly" => false,
			"reactionAcceptance" => null,
			"visibility" => "specified",
			"visibleUserIds" => [$fedi_user_id],
			"text" => $user_address." ".$mail_body
		]));
		$result = json_decode(curl_exec($ajax), true);
		if (isset($result["error"])) {
			echo json_encode(["STATUS" => false, "ERR" => "SYSTEM_ERR", "EX" => "ユーザーなし"]);
			return;
		}
	} else {
		$smtp = new PHPMailer(true);
		$smtp->isSMTP();
		$smtp->Host = $env["SMTP"]["IP"];
		$smtp->Port = $env["SMTP"]["PORT"];

		//セキュリティ破壊
		$smtp->SMTPAutoTLS = false;
		$smtp->SMTPAuth = false;
		$smtp->SMTPSecure = false;

		//メールデータ作成
		$smtp->setFrom("noreply@rumiserver.com", "るみさーばー");
		$smtp->addAddress($user_address, "YOU");
		$smtp->Sender = "noreply@rumiserver.com";
		$smtp->CharSet = "UTF-8";
		$smtp->Subject = "るみさーばー / rumiserver.com";
		$smtp->Body = $mail_body;

		$smtp->send();
	}
}

/**
 * エラーを応答する
 * @param string $code エラーコード
 * @param string $message エラーメッセージ
 * @param string $trace トレース
 * @return never
 */
function error_return(string $code, string $message, string $trace) {
	echo json_encode(
		[
			"STATUS" => false,
			"ERROR" => [
				"CODE" => $code,
				"MESSAGE" => $message,
				"TRACE" => $trace
			]
		]
	);
	http_response_code(400);
	exit;
}