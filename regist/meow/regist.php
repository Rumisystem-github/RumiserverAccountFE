<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
//↑なにこれ

require("http://plain-cdn.rumia.me/LIB/SQL.php?V=LATEST");
require("http://plain-cdn.rumia.me/LIB/RPL.php?V=LATEST");
require("http://plain-cdn.rumia.me/LIB/DISCORD.php?V=LATEST");
require("http://plain-cdn.rumia.me/LIB/AJAX.php?V=LATEST");
require(__DIR__."/../../passgen.php");

//PHPMailer
require(__DIR__."/PHPMailer/PHPMailer.php");
require(__DIR__."/PHPMailer/Exception.php");
require(__DIR__."/PHPMailer/SMTP.php");

try {
	//設定
	mb_language("uni");
	mb_internal_encoding("UTF-8");

	//JSONを名乗る
	header("Content-Type: application/json");

	//メソッドはPOSTか
	if ($_SERVER["REQUEST_METHOD"] !== "POST") {
		echo json_encode(["STATUS" => false, "ERR" => "METHOD_GA_OKASHII"]);
		http_response_code(400);
	}
	$body = json_decode(file_get_contents("php://input"), true);

	if (check_value($body) == false) {
		echo json_encode(["STATUS" => false, "ERR" => "POST_KEY_GA_TALINAI"]);
		http_response_code(400);
		exit;
	}

	//環境設定
	$env = file_get_contents($_SERVER['DOCUMENT_ROOT']."/ENV.json");
	if($env === false){
		echo json_encode(["STATUS" => false, "ERR" => "ENV ERR"]);
		exit;
	}else{
		$env = json_decode($env, true);
		if(json_last_error() !== 0){
			echo json_encode(["STATUS" => false, "ERR" => "ENV ERR"]);
			exit;
		}
	}

	//変数用意
	$cft_result = $body["CFT"];
	$user_id = $body["ID"];
	$user_name = $body["NAME"];
	$user_password = $body["PASSWORD"];
	$user_address = $body["ADDRESS"];
	$user_birthday = $body["BIRTHDAY"];

	//CFT
	if (check_cft($cft_result) == false) {
		echo json_encode(["STATUS" => false, "ERR" => "POST_KEY_GA_TALINAI"]);
		http_response_code(400);
		exit;
	}

	//SQL
	$pdo = new PDO(
		"mysql:host=192.168.0.130;dbname=RumiServer;",
		$env["SQL_UID"],
		$env["SQL_PASS"],
		[PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
	);

	//指定されたユーザーIDを使っている人はいるか？
	if (count (SQL_RUN($pdo, "SELECT `UID` FROM `ACCOUNT` WHERE `UID` = :UID", [["KEY" => "UID", "VAL" => $user_id]])["RESULT"]) != 0) {
		echo json_encode(["STATUS" => false, "ERR" => "USER_ID_CONFLICT"]);
		http_response_code(409);
		exit;
	}

	//指定されたアドレスを使っている人はいるか？
	if (count (SQL_RUN($pdo, "SELECT `ID` FROM `CONTACT` WHERE `ADDRESS` = :ADDRESS", [["KEY" => "ADDRESS", "VAL" => $user_address]])["RESULT"]) != 0) {
		echo json_encode(["STATUS" => false, "ERR" => "MAIL_ADDR_CONFLICT"]);
		http_response_code(409);
		exit;
	}

	$address_type = 0;

	//アドレスはAPか？
	if (str_starts_with($body["ADDRESS"], "@")) {
		//AP
		$address_type = 1;
	}

	$regist_id = uniqid();
	$regist_data = [
		"UID" => $user_id,
		"NAME" => $user_name,
		"MAIL" => $user_address,
		"PASS" => PASSGEN($user_password),
		"PASS_TYPE" => "R_4",
		"REGIST_DATE" => date("Y-m-d H:i:s"),
		"TANZHOOBI" => $user_birthday
	];

	$regist_sql = SQL_RUN($pdo, "INSERT INTO `MAIL_VERIFY` (`ID`, `REGIST_DATA`, `ADDRESS_TYPE`) VALUES (:ID, :DATA, :AT);", [
		[
			"KEY" => "ID",
			"VAL" => $regist_id
		],
		[
			"KEY" => "DATA",
			"VAL" => json_encode($regist_data)
		],
		[
			"KEY" => "AT",
			"VAL" => $address_type
		]
	]);

	if (!$regist_sql["STATUS"]) {
		echo json_encode(["STATUS" => false, "ERR" => "SYSTEM_ERR", "EX" => "SQLエラー"]);
		http_response_code(500);
		exit;
	}

	$mail_body = "あなたのアカウントは正常に登録されました！\n";
	$mail_body .= "以下のリンクを開き、アドレスの認証をしてください。\n";
	$mail_body .= "https://account.rumiserver.com/regist/verify/?ID=".$regist_id."\n";

	if ($address_type == 1) {
		//AP
		$misskey_host = "https://".$env["ACTIVITYPUB"]["HOST"];
		$token = $env["ACTIVITYPUB"]["TOKEN"];
		$address_uid = explode("@", ltrim($user_address, "@"))[0];
		$address_host = explode("@", ltrim($user_address, "@"))[1];

		//MisskeyからユーザーIDを持ってくる(Dmするために)
		$user_info = json_decode(FETCH_POST(
			$misskey_host."/api/users/show",
			["Content-Type: application/json"],
			json_encode(["i" => $token, "username" => $address_uid, "host" => $address_host])
		), true);

		if (isset($user_id["error"])) {
			echo json_encode(["STATUS" => false, "ERR" => "SYSTEM_ERR", "EX" => "ユーザーなし"]);
			http_response_code(404);
			exit;
		}

		FETCH_POST($misskey_host."/api/notes/create", ["Content-Type: application/json"],
			json_encode([
				"i" => $token,
				"cw" => null,
				"localOnly" => false,
				"reactionAcceptance" => null,
				"visibility" => "specified",
				"visibleUserIds" => [$user_info["id"]],
				"text" => $user_address." ".$mail_body
			])
		);
	} else {
		//SMTP
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
		$smtp->Subject = "るみさーばー 登録成功";
		$smtp->Body = $mail_body;

		$smtp->send();
	}

	echo json_encode(array("STATUS" => true));
} catch(\Exception $ex) {
	echo json_encode(["STATUS" => false, "ERR" => "SYSTEM_ERR", "EX" => json_encode([
			"message" => $ex->getMessage(),
			"file" => $ex->getFile(),
			"line" => $ex->getLine(),
			"trace" => $ex->getTraceAsString()
		])]);
	http_response_code(500);
	exit;
} catch(\Throwable $ex) {
	echo json_encode(["STATUS" => false, "ERR" => "SYSTEM_ERR", "EX" => json_encode([
			"message" => $ex->getMessage(),
			"file" => $ex->getFile(),
			"line" => $ex->getLine(),
			"trace" => $ex->getTraceAsString()
		])]);
	http_response_code(500);
	exit;
}

function check_value($body) {
	if (!isset($body["CFT"])) {
		return false;
	}

	if (!isset($body["ID"])) {
		return false;
	}

	if (!preg_match("/^[A-Za-z_]+$/", $body["ID"])) {
		return false;
	}

	if (!isset($body["NAME"])) {
		return false;
	}

	if (!isset($body["PASSWORD"])) {
		return false;
	}

	if (!preg_match("/^[\x20-\x7E]+$/", $body["PASSWORD"])) {
		return false;
	}

	if (!isset($body["BIRTHDAY"])) {
		return false;
	}

	if (!isset($body["ADDRESS"])) {
		return false;
	}

	//アドレスチェック
	if (str_starts_with($body["ADDRESS"], "@")) {
		if (!check_apub_address(explode("@", $body["ADDRESS"])[2])) {
			return false;
		}
	} else {
		if (!check_mail_address(explode("@", $body["ADDRESS"])[1])) {
			return false;
		}
	}

	return true;
}

function check_apub_address($host) {
	$allow_list = json_decode(file_get_contents(__DIR__."/apub.json"), true);

	foreach ($allow_list as $row) {
		if ($row == $host) {
			return true;
		}
	}

	return false;
}

function check_mail_address($host) {
	$allow_list = json_decode(file_get_contents(__DIR__."/mail.json"), true);

	foreach ($allow_list as $row) {
		if ($row == $host) {
			return true;
		}
	}

	return false;
}

function check_cft($cft) {
	global $env;

	$ajax = curl_init("https://challenges.cloudflare.com/turnstile/v0/siteverify");
	curl_setopt($ajax, CURLOPT_POST, true);
	curl_setopt($ajax, CURLOPT_POSTFIELDS, json_encode(["secret" => $env["CFT"]["SIKRET_KEY"], "response" => $cft]));
	curl_setopt($ajax, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ajax, CURLOPT_HTTPHEADER, [
		"Content-Type: application/json"
	]);
	$result = json_decode(curl_exec($ajax), true);
	curl_close($ajax);

	if ($result["success"]) {
		return true;
	} else {
		return false;
	}
}
