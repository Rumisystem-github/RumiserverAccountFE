<?php
try {
	require("http://plain-cdn.rumia.me/LIB/SQL.php?V=LATEST");
	require("http://plain-cdn.rumia.me/LIB/SnowFlake.php?V=LATEST");
	require("http://plain-cdn.rumia.me/LIB/DISCORD.php?V=LATEST");

	//JSONを名乗る
	header("Content-Type: application/json");

	//環境設定
	$ENV = file_get_contents($_SERVER['DOCUMENT_ROOT']."/ENV.json");
	if($ENV === false){
		echo json_encode(array("STATUS" => false, "ERR" => "ENV ERR"));
		exit;
	}else{
		$ENV = json_decode($ENV, true);
		if(json_last_error() !== 0){
			echo json_encode(array("STATUS" => false, "ERR" => "ENV ERR"));
			exit;
		}
	}

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

	if (isset($_GET["ID"])) {
		$SQL_RESULT = SQL_RUN($PDO, "SELECT * FROM `MAIL_VERIFY` WHERE `ID` = BINARY :ID;", array(
			array(
				"KEY" => "ID",
				"VAL" => $_GET["ID"]
			)
		));

		if ($SQL_RESULT["STATUS"]) {
			if (count($SQL_RESULT["RESULT"]) === 1) {
				$REGIST_ADDRESS_TYPE = $SQL_RESULT["RESULT"][0]["ADDRESS_TYPE"];
				$REGIST_DATA = json_decode($SQL_RESULT["RESULT"][0]["REGIST_DATA"], true);
				$ID = GenSnowFlake();

				$SQL_RESULT = SQL_RUN($PDO, "INSERT INTO `ACCOUNT` (`ID`, `UID`, `NAME`, `DESCRIPTION`, `PASS`, `PASS_TYPE`, `REGIST_DATE`, `OFFICIAL`, `SEX`, `LOCATION`, `LANGUAGE`, `BIRTHDAY`, `STATUS`, `PARENT_ID`, `TOTP_KEY`) VALUES 
					(:ID, :UID, :NAME, '説明を入力してね', :PASS, :PASS_TYPE, :REGIST_DATE, 0, 'NEUT', '地球', 'JP', :BIRTHDAY, 0, NULL, NULL);",
					array(
						array(
							"KEY" => "ID",
							"VAL" => $ID
						),
						array(
							"KEY" => "UID",
							"VAL" => $REGIST_DATA["UID"]
						),
						array(
							"KEY" => "NAME",
							"VAL" => $REGIST_DATA["NAME"]
						),
						array(
							"KEY" => "PASS",
							"VAL" => $REGIST_DATA["PASS"]
						),
						array(
							"KEY" => "PASS_TYPE",
							"VAL" => $REGIST_DATA["PASS_TYPE"]
						),
						array(
							"KEY" => "REGIST_DATE",
							"VAL" => $REGIST_DATA["REGIST_DATE"]
						),
						array(
							"KEY" => "BIRTHDAY",
							"VAL" => $REGIST_DATA["TANZHOOBI"]
						)
					)
				);

				//連絡情報を登録
				$MAIL_SQL_RESULT = SQL_RUN($PDO, "INSERT INTO `CONTACT` (`ID`, `UID`, `ADDRESS`, `TYPE`, `PRIMARY`, `SECURE`, `NOTIFY`, `BACKUP`) VALUES (:ID, :UID, :ADDRESS, :TYPE, 1, 1, 0, 0)", array(
					array(
						"KEY" => "ID",
						"VAL" => GenSnowFlake()
					),
					array(
						"KEY" => "UID",
						"VAL" => $ID
					),
					array(
						"KEY" => "ADDRESS",
						"VAL" => $REGIST_DATA["MAIL"]
					),
					array(
						"KEY" => "TYPE",
						"VAL" => $REGIST_ADDRESS_TYPE
					)
				));

				//登録できたか
				if ($SQL_RESULT["STATUS"] && $MAIL_SQL_RESULT["STATUS"]) {
					//メール認証情報を削除
					SQL_RUN($PDO, "DELETE FROM `MAIL_VERIFY` WHERE `ID` = BINARY :ID;", array(
						array(
							"KEY" => "ID",
							"VAL" => $_GET["ID"]
						)
					));

					try {
						SEND_DISCORD_LOG(
							"アカウントの本登録がされました\n".
							"名前:".$REGIST_DATA["NAME"]."\n".
							"UID：".$REGIST_DATA["UID"],
							"https://discord.com/api/webhooks/1317024282647203921/kpBtIhq8DczG7twa0xpFaCU5PieZ5QlsBGQkDgGn2n0xkwLW4M92u4yFgUxYClYvrXQW"
						);
					} catch(\Exception $EX) {
						//何もしない
						exit;
					} catch (\Throwable $EX) {
						//何もしない
						exit;
					}

					echo json_encode(array("STATUS" => true));
					exit;
				}

			} else {
				echo json_encode(array("STATUS" => false, "ERR" => "SYSTEM_ERR"));
				http_response_code(500);
			}
		} else {
			echo json_encode(array("STATUS" => false, "ERR" => "SYSTEM_ERR"));
			http_response_code(500);
		}
	} else {
		echo json_encode(array("STATUS" => false));
		http_response_code(400);
	}
} catch(\Exception $EX) {
	echo json_encode(array("STATUS" => false, "ERR" => "SYSTEM_ERR", "EX" => "LINE[".$EX->getLine()."]\n".$EX->getMessage()));
	http_response_code(500);
	exit;
} catch(\Throwable $EX) {
	echo json_encode(array("STATUS" => false, "ERR" => "SYSTEM_ERR", "EX" => "LINE[".$EX->getLine()."]\n".$EX->getMessage()));
	http_response_code(500);
	exit;
}
?>