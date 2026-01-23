<?php
require("http://plain-cdn.rumia.me/LIB/SERVICE_LOGIN.php");
require("http://plain-cdn.rumia.me/LIB/SQL.php?V=LATEST");
require(__DIR__."/MODULE/GetAPP.php");

//URLパラメーターをチェックする
if (!isset($_GET["ID"]) || !isset($_GET["SESSION"]) || !isset($_GET["PERMISSION"])) {
	header("Content-Type: text/plain; charset=UTF-8");
	echo "エラー:URIパラメーターが足りません";
	http_response_code(400);
	exit;
}

//権限
$PERMISSION = [];
foreach (explode(",", $_GET["PERMISSION"]) as $ROW) {
	$NAME = explode(":", $ROW)[0];
	$USE = explode(":", $ROW)[1];

	if ($USE !== "read" && $USE !== "write") {
		header("Content-Type: text/plain; charset=UTF-8");
		echo "エラー:「".htmlspecialchars($NAME)."」の使用範囲がおかしいです、readかwriteしか許可されません";
		http_response_code(400);
		exit;
	}

	switch ($NAME) {
		case "account": {
			$PERMISSION[$NAME] = $USE;
			break;
		}

		default: {
			header("Content-Type: text/plain; charset=UTF-8");
			echo "エラー:権限設定がおかしいです、主に「".htmlspecialchars($NAME)."」が";
			http_response_code(400);
			exit;
		}
	}
}

//ログイン
$OS = new RS_SERVICE_LOGIN_SYSTEM();
$LOGIN_RESULT = $OS->MAIN();

if ($LOGIN_RESULT === false){
	header("Location: /Login?rd=".urlencode("/Auth?ID=".$_GET["ID"]."&SESSION=".$_GET["SESSION"]."&PERMISSION=".$_GET["PERMISSION"]));
	exit;
}

//環境設定
$ENV = file_get_contents($_SERVER['DOCUMENT_ROOT']."/ENV.json");
if($ENV === false){
	exit;
}else{
	$ENV = json_decode($ENV, true);
	if(json_last_error() !== 0){
		exit;
	}
}

//SQL
try {
	//PDOインスタンスを生成
	$PDO = new PDO(
		//ホスト名、データベース名
		"mysql:host=192.168.0.130;dbname=RumiServer;",
		//ユーザー名
		$ENV["SQL_UID"],
		//パスワード
		$ENV["SQL_PASS"],
		//レコード列名をキーとして取得させる
		[PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
	);
} catch (PDOException $e) {
	//強制終了
	exit;
}
?>
<!DOCTYPE html>
<HTML>
	<HEAD>
		<TITLE>認証</TITLE>

		<LINK REL="stylesheet" HREF="https://cdn.rumia.me/CSS/reset.css">
		<LINK REL="stylesheet" HREF="https://cdn.rumia.me/CSS/font.css">
		<LINK REL="stylesheet" HREF="https://cdn.rumia.me/CSS/DEFAULT.css">

		<LINK REL="stylesheet" HREF="./STYLE/Main.css">
	</HEAD>
	<BODY>
		<DIV CLASS="MAIN">
			<?php
				$APP = GetAPP($PDO, $_GET["ID"]);
				if (isset($APP)) {
					//アプリのIDとか
					echo "<SCRIPT>const APP_ID = \"".str_replace("\"", "%22", $_GET["ID"])."\";</SCRIPT>\n";
					echo "<SCRIPT>const SESSION = \"".str_replace("\"", "%22", $_GET["SESSION"])."\";</SCRIPT>\n";
					echo "<SCRIPT>const PERMISSION = ".json_encode($PERMISSION).";</SCRIPT>\n";

					//コールバックURLが指定されていれば使う
					if (isset($_GET["CALLBACK"])) {
						echo "<SCRIPT>const CALLBACK = \"".str_replace("\"", "%22", $_GET["CALLBACK"])."\";</SCRIPT>\n";
					} else {
						echo "<SCRIPT>const CALLBACK = null;</SCRIPT>\n";
					}

					include(__DIR__."/CONTENTS/Main.php");
				} else {
					include(__DIR__."/CONTENTS/NTFApp.html");
				}
			?>
		</DIV>
	</BODY>
</HTML>