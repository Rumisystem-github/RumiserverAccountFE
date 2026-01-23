<?php
include("http://plain-cdn.rumia.me/LIB/AJAX.php?V=LATEST");
include("http://plain-cdn.rumia.me/LIB/RMD.php");
include("http://plain-cdn.rumia.me/LIB/OGP.php?V=LATEST");
include("http://plain-cdn.rumia.me/LIB/SERVICE_LOGIN.php");

$BASE_URL = "/user";
$BASE_URI = "/RumiServerAccount/user";
$REQUEST_URI = str_replace($BASE_URI."/", "", parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));
$TITLE = "qa";
$FOLLOWBTN = false;
$FOLLOW = false;
$FOLLOWER = false;
$BLOCK = false;
$BLOCKER = false;
$OS = new RS_SERVICE_LOGIN_SYSTEM();
$LOGIN_RESULT = $OS->MAIN();

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

$HEADER = [];

//ログインしているならヘッダーにトークンを
if ($LOGIN_RESULT !== false) {
	$HEADER = [
		"TOKEN: ".$_COOKIE["SESSION"]
	];
}

$RESULT = json_decode(FETCH($ENV["RSV"]["ACCOUNT"]."User?UID=".$REQUEST_URI, $HEADER), true);
$ACCOUNT = null;
if ($RESULT["STATUS"]) {
	$ACCOUNT = $RESULT["ACCOUNT"];

	$TITLE = htmlspecialchars($ACCOUNT["NAME"]);

	//ログインしている場合の処理を入れる
	if ($LOGIN_RESULT !== false){
		//フォローボタン
		if ($REQUEST_URI !== $LOGIN_RESULT["UID"]) {
			$FOLLOWBTN = true;

			//フォロー
			$FOLLOW = $RESULT["FOLLOW"];

			//フォロワー
			$FOLLOWER = $RESULT["FOLLOWER"];

			//ブロック済み
			if ($RESULT["BLOCK"]) {
				$TITLE = "ブロック済みのアカウント";
				$BLOCK = true;
			}

			//ブロックされてる
			if ($RESULT["BLOCKER"]) {
				http_response_code(400);
				$TITLE = "アクセス拒否";
				$ACCOUNT = null;
				$BLOCKER = true;
			}
		}
	}
} else {
	http_response_code(404);
	$TITLE = "存在しないアカウント";
}
?>
<!DOCTYPE html>
<HTML>
	<HEAD>
		<TITLE><?=$TITLE?></TITLE>

		<LINK REL="stylesheet" HREF="https://cdn.rumia.me/CSS/reset.css">
		<LINK REL="stylesheet" HREF="https://cdn.rumia.me/CSS/font.css">
		<LINK REL="stylesheet" HREF="https://cdn.rumia.me/CSS/DEFAULT.css">
		<LINK REL="stylesheet" HREF="https://cdn.rumia.me/CSS/icon.css">

		<META name="theme-color" content="#FED6E3" />

		<LINK REL="stylesheet" HREF="<?=$BASE_URL?>/STYLE/Main.css">
		<LINK REL="stylesheet" HREF="<?=$BASE_URL?>/STYLE/RENKEI_ACCOUNT.css">

		<?php
			$OGP = new OGP_PHP();
			$OGP->SET_TYPE(false);

			$OGP->SET_PAGENAME("るみさーばー");
			$OGP->SET_TITLE($TITLE);
			if ($ACCOUNT != null) {
				$OGP->SET_DESC(htmlspecialchars($ACCOUNT["NAME"])."のプロフィール");
			} else {
				$OGP->SET_DESC("?");
			}
			$OGP->BUILD();
		?>
	</HEAD>
	<BODY>
		<DIV CLASS="PROF PLATE PLATE_CENTER">
			<?php
			if ($ACCOUNT != null) {
				?>
				<DIV CLASS="INFO">
					<IMG CLASS="ICON ICON_<?=htmlspecialchars($ACCOUNT["ICON"])?>" SRC="https://account.rumiserver.com/api/Icon?ID=<?=htmlspecialchars($ACCOUNT["ID"])?>">
					<SPAN CLASS="NAME"><?=htmlspecialchars($ACCOUNT["NAME"])?></SPAN>

					<!--フォローボタン-->
					<?php
					if ($FOLLOWBTN) {
						$FOLLOWBTN_STATE = "none";
						$FOLLOWBTN_TEXT = "フォロー";

						if ($FOLLOW) {
							$FOLLOWBTN_STATE = "following";
							$FOLLOWBTN_TEXT = "フォロー解除";
						}

						?>
						<BUTTON onclick="FollowClick(this);" CLASS="FOLLOW_BUTTON" data-state="<?=$FOLLOWBTN_STATE?>"><?=$FOLLOWBTN_TEXT?></BUTTON>
						<?php
					}
					?>

					<!--ブロック-->
					<?php
					if ($BLOCK) {
						echo "<FONT COLOR=\"RED\">ブロック済みです</FONT>";
					}
					?>

					<!--バッジ-->
					<SPAN CLASS="BADGE">
						<?php
							foreach ($ACCOUNT["BADGE"] as $BADGE) {
								$ALT = "あ";

								switch ($BADGE["TYPE"]) {
									case "ROOT"; {
										$ALT = "運営もとい開発者";
										break;
									}

									case "ADMIN"; {
										$ALT = "モデレーター";
										break;
									}

									case "DEVELOP"; {
										$ALT = "るみ鯖の開発に関与しています";
										break;
									}

									case "NATION_STAFF"; {
										$ALT = "この人はどこかの国のスタッフです";
										break;
									}

									case "BAN"; {
										$ALT = "この人はBANされる予定があります";
										break;
									}
								}

								echo "<IMG SRC=\"/Asset/BADGE/".$BADGE["TYPE"].".svg\" WIDTH=\"25\" HEIGHT=\"25\" ALT=\"".$ALT."\">";
							}
						?>
					</SPAN>
				</DIV>

				<!--フォロワー？-->
				<?php
				if ($FOLLOWER) {
					?>
					<DIV>フォロワー</DIV>
					<?php
				}
				?>

				<!--プロフ-->
				<DIV CLASS="DESC"><?=RMD_CONV(htmlspecialchars($ACCOUNT["DESCRIPTION"]))?></DIV>

				<!--場所-->
				<DIV><?=htmlspecialchars($ACCOUNT["LOCATION"])?></DIV>

				<!--連携-->
				<DIV CLASS="RENKEI_LIST">
					<?php
					foreach ($ACCOUNT["RENKEI"] as $RENKEI) {
						?>
						<DIV CLASS="RENKEI_ITEM">
							<DIV CLASS="SERVICE_INFO">
								<IMG SRC="<?=htmlspecialchars($RENKEI["SERVICE_ICON"])?>">
								<SPAN><?=htmlspecialchars($RENKEI["SERVICE_NAME"])?></SPAN>
							</DIV>
							<DIV CLASS="ACCOUNT_INFO">
								<A HREF="<?=htmlspecialchars($RENKEI["ACCOUNT_URL"])?>" TARGET="_blank"><?=htmlspecialchars($RENKEI["ACCOUNT_NAME"])?></A>
							</DIV>
							<DIV CLASS="RENKEI_INFO">
								<DIV CLASS="DATE">追加日:<?=htmlspecialchars($RENKEI["DATE"])?></DIV>
								<DIV CLASS="UPDATE">更新日:<?=htmlspecialchars($RENKEI["UPDATE"])?></DIV>
							</DIV>
						</DIV>
						<?php
					}
					?>
				</DIV>

				<!--登録日-->
				<DIV><?=htmlspecialchars($ACCOUNT["REGIST_DATE"])?>に作成</DIV>
			<?php
		} else {
			if (!$BLOCKER) {
				echo "アカウントが無いです";
			} else {
				echo "ブロックされています";
			}
		}
		?>

		<!--ブロックボタン-->
		<?php
			$BLOCKBTN_TEXT = "ブロックする";

			if ($BLOCK) {
				$BLOCKBTN_TEXT = "ブロック解除する";
			}

			?>
			<BUTTON onclick="BlockClick(this);"><?=$BLOCKBTN_TEXT?></BUTTON>
			<?php
		?>
		</DIV>
	</BODY>

	<SCRIPT>
		const UID = "<?=$ACCOUNT["UID"]?>";
		const SESSION = "<?=$_COOKIE["SESSION"]?>";
		let FOLLOW = <?php if ($FOLLOW) { echo "true"; } else { echo "false"; } ?>;
		let BLOCK = <?php if ($BLOCK) { echo "true"; } else { echo "false"; } ?>;

		async function FollowClick(BTN) {
			let METHOD = "POST";

			if (FOLLOW) {
				METHOD = "DELETE";
			}

			//読み込み中を示す
			BTN.innerText = "・・・";
			//無効化
			BTN.setAttribute("disabled", true);

			let AJAX = await fetch("https://account.rumiserver.com/api/Follow?UID=" + UID, {
				method: METHOD,
				headers:{
					"TOKEN":SESSION
				}
			});

			const RESULT = await AJAX.json();

			//有効化
			BTN.removeAttribute("disabled")

			if(RESULT.STATUS){
				if (FOLLOW) {
					FOLLOW = false;
					BTN.innerText = "フォロー";
					BTN.dataset.state = "none";
				} else {
					FOLLOW = true;
					BTN.innerText = "フォロー解除";
					BTN.dataset.state = "following";
				}

			} else {
				BTN.innerText = "失敗";
			}
		}

		async function BlockClick(BTN) {
			let METHOD = "POST";

			if (BLOCK) {
				METHOD = "DELETE";
			}

			//読み込み中を示す
			BTN.innerText = "・・・";
			//無効化
			BTN.setAttribute("disabled", true);

			let AJAX = await fetch("https://account.rumiserver.com/api/Block?UID=" + UID, {
				method: METHOD,
				headers:{
					"TOKEN":SESSION
				}
			});

			const RESULT = await AJAX.json();
			if(RESULT.STATUS){
				window.location.reload();
			} else {
				BTN.innerText = "失敗";
			}
		}
	</SCRIPT>
</HTML>