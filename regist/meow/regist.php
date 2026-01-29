<?php
//PHPMailer
require(__DIR__."/PHPMailer/PHPMailer.php");
require(__DIR__."/PHPMailer/Exception.php");
require(__DIR__."/PHPMailer/SMTP.php");

use PHPMailer\PHPMailer;
use PHPMailer\Exception;

class Regist {
	private array $env;
	private array $body;

	public function __construct() {
		//環境設定を読む
		$json = file_get_contents($_SERVER['DOCUMENT_ROOT']."/ENV.json");
		if ($json == false) {
			echo json_encode(["STATUS" => false, "ERR" => "ENV ERR"]);
			exit;
		}
		$this->env = json_decode($json, true);

		if (json_last_error() != 0) {
			echo json_encode(["STATUS" => false, "ERR" => "ENV ERR"]);
			exit;
		}
	}

	public function main() {
		if ($_SERVER["REQUEST_METHOD"] !== "POST") {
			echo json_encode(["STATUS" => false, "ERR" => "METHOD_GA_OKASHII"]);
			http_response_code(400);
			exit;
		}

		$this->body = json_decode(file_get_contents("php://input"), true);

		if ($this->check_value($this->body) == false) {
			echo json_encode(["STATUS" => false, "ERR" => "POST_KEY_GA_TALINAI"]);
			http_response_code(400);
			exit;
		}

		$cft_result = $this->body["CFT"];

		$regist_id = uniqid();
		$regist_data = [];
		$user_id = $this->body["ID"];
		$user_name = $this->body["NAME"];
		$user_password = $this->body["PASSWORD"];
		$user_address = $this->body["ADDRESS"];
		$user_birthday = $this->body["BIRTHDAY"];
		$address_type = 0;

		//CFT
		if (check_cft($cft_result) == false) {
			echo json_encode(["STATUS" => false, "ERR" => "POST_KEY_GA_TALINAI"]);
			http_response_code(400);
			exit;
		}

		//SQL
		$sql = new PDO("mysql:host=192.168.0.130;dbname=RumiServer;", $this->env["SQL_UID"], $this->env["SQL_PASS"], [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

		//ユーザーIDは既に使われているか？
		$stmt = $sql->prepare("SELECT `UID` FROM `ACCOUNT` WHERE `UID` = :UID;");
		$stmt->bindValue(":UID". $user_id, PDO::PARAM_STR);
		$stmt->execute();
		if ($stmt->fetch() != false) {
			echo json_encode(["STATUS" => false, "ERR" => "USER_ID_CONFLICT"]);
			http_response_code(409);
			exit;
		}

		//既にアドレスを使っているか？
		$stmt = $sql->prepare("SELECT `ID` FROM `CONTACT` WHERE `ADDRESS` = :ADDRESS;");
		$stmt->bindValue(":ADDRESS". $user_address, PDO::PARAM_STR);
		$stmt->execute();
		if ($stmt->fetch() != false) {
			echo json_encode(["STATUS" => false, "ERR" => "MAIL_ADDR_CONFLICT"]);
			http_response_code(409);
			exit;
		}

		//APのアドレス？
		if (str_starts_with($user_address, "@")) $address_type = 1;

		//パスワードをハッシュ化
		$password_hash = $user_password;
		for ($i = 0; $i < 10000; $i++) {
			$password_hash = hash("SHA3-512", $password_hash);
		}

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
		$stmt->bindValue(":ID". $regist_id, PDO::PARAM_STR);
		$stmt->bindValue(":DATA". json_encode($regist_data), PDO::PARAM_STR);
		$stmt->bindValue(":AT". $address_type, PDO::PARAM_INT);
		$stmt->execute();

		$mail_body = "あなたのアカウントは正常に登録されました！\n";
		$mail_body .= "以下のリンクを開き、アドレスの認証をしてください。\n";
		$mail_body .= "https://account.rumiserver.com/regist/verify/?ID=".$regist_id."\n";

		//メール/Fedi送信
		if ($address_type == 1) {
			$url = "https://".$this->env["ACTIVITYPUB"]["HOST"];
			$token = $this->env["ACTIVITYPUB"]["TOKEN"];
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
			$smtp->Host = $this->env["SMTP"]["IP"];
			$smtp->Port = $this->env["SMTP"]["PORT"];

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
	}

	private function check_value($body) {
		if (!isset($body["CFT"])) return false;
		if (!isset($body["ID"])) return false;
		if (!preg_match("/^[A-Za-z_]+$/", $body["ID"])) return false;
		if (!isset($body["NAME"])) return false;
		if (!isset($body["PASSWORD"])) return false;
		if (!preg_match("/^[\x20-\x7E]+$/", $body["PASSWORD"])) return false;
		if (!isset($body["BIRTHDAY"])) return false;
		if (!isset($body["ADDRESS"])) return false;
		if (str_starts_with($body["ADDRESS"], "@")) {
			if (!check_apub_address(explode("@", $body["ADDRESS"])[2])) return false;
		} else {
			if (!check_mail_address(explode("@", $body["ADDRESS"])[1])) return false;
		}
		return true;
	}

	private function check_apub_address($host) {
		$allow_list = json_decode(file_get_contents(__DIR__."/apub.json"), true);
		foreach ($allow_list as $row) {
			if ($row == $host) return true;
		}
		return false;
	}

	private function check_mail_address($host) {
		$allow_list = json_decode(file_get_contents(__DIR__."/mail.json"), true);
		foreach ($allow_list as $row) {
			if ($row == $host) return true;
		}
		return false;
	}

	private function check_cft($cft) {
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
}

$c = new Regist();
$c->main();