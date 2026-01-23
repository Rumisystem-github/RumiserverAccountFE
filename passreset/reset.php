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
				try {
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
	if (!isset($_GET["ID"])) {
		echo "パラメーターエラー";
		return;
	}

	//IDからパスリセのセッションを取得
	$stmt = $pdo->prepare("SELECT `UID` FROM `PASSWORD_RESET` WHERE `ID` = :ID;");
	$stmt->bindValue(":ID", $_GET["ID"], PDO::PARAM_STR);
	$stmt->execute();
	$result = $stmt->fetchAll();

	if (count($result) == 0) {
		echo "セッション切れです、再度やり直してください。";
		return;
	}

	$user_id = $result[0]["UID"];
	$method = $_SERVER["REQUEST_METHOD"];

	if ($method == "GET") {
		//GETならフォームを出す
		form();
	} elseif ($method == "POST") {
		//POSTなので変更処理
		change($pdo, $_GET["ID"], $user_id);
	} else {
		echo "不明なメソッドを検知";
		return;
	}
}

function form() {
	?>
	<FORM METHOD="POST">
		<INPUT TYPE="PASSWORD" NAME="PASSWORD" PLACEHOLDER="新しいパスワード">
		<BUTTON>変更する</BUTTON>
	</FORM>
	<?php
}

function change(PDO $pdo, string $session_id, string $user_id) {
	//POSTチェック
	if (!isset($_POST["PASSWORD"])) {
		echo "値エラー";
		return;
	}

	$input_password = $_POST["PASSWORD"];

	//パスワードの文字種をチェックする
	if (!preg_match('/^[\x20-\x7E]+$/', $input_password)) {
		echo "使用できない文字が含まれています！<BR>";
		echo "パスワードは全てASCII印字可能文字である必要があります。";
		return;
	}

	//パスワードのハッシュを生成
	$password_type = "R_4";
	$password_hash = $input_password;
	for ($i = 0; $i < 10000; $i++) {
		$password_hash = hash("SHA3-512", $password_hash);
	}

	//パスワード変更作業
	try {
		$pdo->beginTransaction();

		//UPDATE
		$stmt = $pdo->prepare("UPDATE `ACCOUNT` SET `PASS` = :PASSWORD WHERE `ACCOUNT`.`ID` = :ID;");
		$stmt->bindValue(":PASSWORD", $password_hash, PDO::PARAM_STR);
		$stmt->bindValue(":ID", $user_id, PDO::PARAM_STR);
		$stmt->execute();

		$stmt = $pdo->prepare("UPDATE `ACCOUNT` SET `PASS_TYPE` = :TYPE WHERE `ACCOUNT`.`ID` = :ID;");
		$stmt->bindValue(":TYPE", $password_type, PDO::PARAM_STR);
		$stmt->bindValue(":ID", $user_id, PDO::PARAM_STR);
		$stmt->execute();

		//DELETE
		$stmt = $pdo->prepare("DELETE FROM `PASSWORD_RESET` WHERE `ID` = :ID;");
		$stmt->bindValue(":ID", $session_id, PDO::PARAM_STR);
		$stmt->execute();

		$pdo->commit();

		?>
		変更しました！<BR>
		<A HREF="/login">ログインし直す</A>
		<?php
	} catch(\Throwable $ex) {
		$pdo->rollBack();
	}
}
?>