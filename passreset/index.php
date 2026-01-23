<!DOCTYPE html>
<HTML>
	<HEAD>
		<TITLE>パスワードリセット</TITLE>

		<LINK REL="stylesheet" HREF="https://cdn.rumia.me/CSS/reset.css">
		<LINK REL="stylesheet" HREF="https://cdn.rumia.me/CSS/DEFAULT.css">
		<LINK REL="stylesheet" HREF="https://cdn.rumia.me/CSS/font.css">

		<STYLE>
			body{
				background: var(--RSV_DEFAULT_BG);
				width: 100vw;
				height: 100vh;
			}
		</STYLE>
	</HEAD>
	<BODY>
		<DIV CLASS="PLATE PLATE_CENTER">
			<H3>パスワードリセット</H3>
			<DIV>
				<?php
				use PHPMailer\PHPMailer\PHPMailer;

				try {
					//PHPMailer
					require(__DIR__.'/PHPMailer/PHPMailer.php');
					require(__DIR__.'/PHPMailer/Exception.php');
					require(__DIR__.'/PHPMailer/SMTP.php');

					main();
				} catch(\Exception $ex) {
					echo "システムエラー！<BR>";
					echo "<PRE>";
					echo $ex;
					echo "</PRE>";
				} catch(\Throwable $ex) {
					echo "システムエラー！<BR>";
					echo "<PRE>";
					echo $ex;
					echo "</PRE>";
				}
				?>
			</DIV>
		</DIV>
	</BODY>

	<SCRIPT SRC="https://challenges.cloudflare.com/turnstile/v0/api.js" defer></SCRIPT>
</HTML>
<?php
function main() {
	//環境設定
	$env = file_get_contents($_SERVER['DOCUMENT_ROOT']."/ENV.json");
	if($env === false){
		echo "システムエラー！「ENV false」";
		return;
	}else{
		$env = json_decode($env, true);
		if(json_last_error() !== 0){
			echo "システムエラー！「ENV JSON」";
			return;
		}
	}

	//SQL
	$pdo = new PDO("mysql:host=192.168.0.130;dbname=RumiServer;", $env["SQL_UID"], $env["SQL_PASS"], [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

	//あるか
	if (!isset($_GET["UID"])) {
		echo "パラメーターエラー";
		return;
	}

	$user_id = $_GET["UID"];
	$method = $_SERVER["REQUEST_METHOD"];

	if ($method == "GET") {
		//GETならフォームを出す
		cft();
	} elseif ($method == "POST") {
		//POSTなので変更処理
		send($pdo, $user_id, $env);
	} else {
		echo "不明なメソッドを検知";
		return;
	}
}

function cft() {
	?>
	<FORM METHOD="POST">
		<DIV class="cf-turnstile" data-sitekey="0x4AAAAAAAVxc04rq9pveBbK" data-callback="cft_ok" data-language="ja"></DIV>
		<BUTTON>続行する</BUTTON>
	</FORM>
	<?php
}

function send(PDO $pdo, string $user_id, array $env) {
	if (!isset($_POST["cf-turnstile-response"])) {
		echo "?";
		return;
	}

	//CFT
	$cft_response = $_POST["cf-turnstile-response"];
	if ($cft_response == "" || !check_cft($cft_response, $env)) {
		echo "おまえロボットだな！！";
		return;
	}

	$stmt = $pdo->prepare("
		SELECT
			a.ID,
			c.`ADDRESS`,
			c.`TYPE`
		FROM
			`ACCOUNT` AS a
		LEFT JOIN
			`CONTACT` AS c ON a.ID = c.UID AND c.SECURE = 1
		WHERE
			a.UID = :UID;
	");
	$stmt->bindValue(":UID", $user_id, PDO::PARAM_STR);
	$stmt->execute();
	$result = $stmt->fetchAll();

	if (count($result) == 0) {
		echo "そのユーザーにはセキュリティ用のアドレスがありません。";
		return;
	}

	$user_internal_id = $result[0]["ID"];
	$address = $result[0]["ADDRESS"];
	$address_type = $result[0]["TYPE"];
	$session_id = uniqid();
	$mail_body = "";

	//メール本文を作る
	$mail_body .= "パスワードリセットが申請されました\n";
	$mail_body .= "(心当たりがない場合は無視してください)\n";
	$mail_body .= "\n";
	$mail_body .= "パスワードリセットは下記のURLから行うことができます。\n";

	if ($address_type == 0) {
		$mail_body .= "https://account.rumiserver.com/passreset/reset.php?ID=".$session_id."\n";
	} else {
		$mail_body .= "$[blur https://account.rumiserver.com/passreset/reset.php?ID=".$session_id."]\n";
	}

	if ($address_type == 0) {
		//メール
		$smtp = new PHPMailer(true);

		//SMTP鯖の設定
		$smtp->isSMTP();
		$smtp->Host = $env["SMTP"]["IP"];
		$smtp->Port = $env["SMTP"]["PORT"];

		//セキュリティ破壊
		$smtp->SMTPAutoTLS = false;
		$smtp->SMTPAuth = false;
		$smtp->SMTPSecure = false;

		//メール本体
		$smtp->setFrom("noreply@rumiserver.com", "るみさーばー");
		$smtp->addAddress($address, "YOU");
		$smtp->Sender = "noreply@rumiserver.com";
		$smtp->CharSet = "UTF-8";
		$smtp->Subject = "るみさーばー パスワードリセット";
		$smtp->Body = $mail_body;

		$smtp->send();

		echo "あなたの設定しているメールアドレスにメールを送信しました";
	} else {
		//ActivityPub
		$url = "https://".$env["ACTIVITYPUB"]["HOST"];
		$token = $env["ACTIVITYPUB"]["TOKEN"];
		$username = explode("@", ltrim($address, "@"))[0];
		$host = explode("@", ltrim($address, "@"))[1];

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
			echo "ユーザー取得でMisskeyにてエラーが発生しました、しばらく待ってから再度お試しください...<BR><PRE>\n".json_encode($result)."\n</PRE>";
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
			"text" => $address." ".$mail_body
		]));
		$result = json_decode(curl_exec($ajax), true);
		if (isset($result["error"])) {
			echo "DM送信でMisskeyにてエラーが発生しました、しばらく待ってから再度お試しください...";
			return;
		}

		echo "あなたのFediverseアカウントにDMを送信しました！<BR>";
	}

	echo "本文の指示に従い、パスワードをリセットしてください";

	$stmt = $pdo->prepare("INSERT INTO `PASSWORD_RESET` (`ID`, `UID`, `DATE`) VALUES (:ID, :UID, NOW());");
	$stmt->bindValue(":ID", $session_id, PDO::PARAM_STR);
	$stmt->bindValue(":UID", $user_internal_id, PDO::PARAM_STR);
	$stmt->execute();
}

function check_cft($reponse, $env) {
	$ajax = curl_init("https://challenges.cloudflare.com/turnstile/v0/siteverify");
	curl_setopt($ajax, CURLOPT_POST, true);
	curl_setopt($ajax, CURLOPT_POSTFIELDS, json_encode(["secret" => $env["CFT"]["SIKRET_KEY"], "response" => $reponse]));
	curl_setopt($ajax, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ajax, CURLOPT_HTTPHEADER, [
		"Content-Type: application/json"
	]);
	$result = json_decode(curl_exec($ajax), true);

	if ($result["success"]) {
		return true;
	} else {
		return false;
	}
}

?>