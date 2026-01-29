<?php
//環境設定
$env = file_get_contents($_SERVER['DOCUMENT_ROOT']."/ENV.json");
if($env === false){
	exit;
}else{
	$env = json_decode($env, true);
	if(json_last_error() !== 0){
		exit;
	}
}

$sql = new PDO(
	"mysql:host=".$env["SQL_HOST"].";dbname=RumiServer;",
	$env["SQL_UID"],
	$env["SQL_PASS"],
	[PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$user_id;
$user_uid;
$user_name;

$parts = explode("/", $_SERVER["REQUEST_URI"]);
if (count($parts) > 3) {
	$user_uid = $parts[3];

	$stmt = $sql->prepare("SELECT `ID`, `NAME` FROM `ACCOUNT` WHERE `UID` = :UID;");
	$stmt->bindValue(":UID", $user_uid, PDO::PARAM_STR);
	$stmt->execute();
	$user = $stmt->fetch();

	if ($user == false) {
		http_response_code(404);
		echo file_get_contents(__DIR__."/../../ErrorPage/404/index.html");
		exit;
	}

	$user_id = $user["ID"];
	$user_name = $user["NAME"];
}
?>
<!DOCTYPE html>
<HTML>
	<HEAD>
		<TITLE><?=htmlspecialchars($user_name)?></TITLE>

		<META PROPERTY="og:type" CONTENT="website" />
		<META PROPERTY="og:url" CONTENT="https://account.rumiserver.com/user/" />
		<META PROPERTY="og:title" CONTENT="<?=htmlspecialchars($user_name)?>" />
		<META PROPERTY="og:description" CONTENT="ユーザー" />
		<META PROPERTY="og:site_name" CONTENT="るみさーばー" />
		<META PROPERTY="og:image" CONTENT="https://account.rumiserver.com/api/Icon?ID=<?=htmlspecialchars($user_id)?>" />

		<LINK REL="stylesheet" HREF="https://cdn.rumia.me/CSS/reset.css">
		<LINK REL="stylesheet" HREF="https://cdn.rumia.me/CSS/DEFAULT.css">
		<LINK REL="stylesheet" HREF="https://cdn.rumia.me/CSS/font.css">
		<LINK REL="stylesheet" HREF="https://cdn.rumia.me/CSS/icon.css">

		<STYLE>
			body{
				background: var(--RSV_DEFAULT_BG);
				width: 100vw;
				height: 100vh;
			}

			.USER{
				width: 50vw;
				min-width: 500px;

				min-height: 150px;
			}

			.RR_MESSAGE{
				position: absolute;
				top: 10px;
				left: 10px;

				padding: 5px;

				background-color: rgba(0, 0, 0, 0.5);
				color: white;

				border-radius: 5px;
			}

			.USER > .HEADER{
				position: absolute;
				top: 20px;
				z-index: -1;

				background-color: red;

				width: calc(100% - 32px);
				height: 150px;
			}

			.USER > .NAME{
				margin: 10px;
				margin-top: 100px;
			}

			.USER >.NAME > img{
				width: 128px;
				height: 128px;

				vertical-align: middle;
			}

			.USER >.NAME > span{
				margin-left: 10px;

				vertical-align: bottom;
			}

			.USER >.NAME > span > span{
				font-size: 30px;
			}

			.USER > .DESCRIPTION{
				width: 100%;

				margin-top: -83px;
				padding-top: 80px;
				padding-left: 10px;
				padding-bottom: 10px;

				background-color: white;
			}

			.MENU{
				width: fit-content;
			}
		</STYLE>
	</HEAD>
	<BODY>
		<DIV CLASS="PLATE PLATE_CENTER">
			<DIV CLASS="USER">
				<DIV CLASS="HEADER">
					<DIV CLASS="RR_MESSAGE" ID="RR_MESSAGE" STYLE="display: none;"></DIV>
				</DIV>

				<DIV CLASS="NAME">
					<IMG CLASS="ICON_HEXAGON" ID="USER_ICON">
					<SPAN>
						<SPAN ID="USER_NAME">名前</SPAN>
						<BUTTON ID="FOLLOW_BUTTON">フォロー</BUTTON>
						<BUTTON ID="MENU_BUTTON">︙</BUTTON>
					</SPAN>
				</DIV>
				<DIV CLASS="DESCRIPTION">
					<DIV ID="USER_DESCRIPTION"></DIV>
				</DIV>
			</DIV>
		</DIV>

		<DIV CLASS="PLATE MENU" ID="MENU" STYLE="display: none;">
			<BUTTON ID="MENU_BLOCK_BUTTON">ブロック</BUTTON>
		</DIV>
	</BODY>

	<SCRIPT SRC="https://cdn.rumia.me/LIB/Login.js?V=LATEST"></SCRIPT>
	<SCRIPT SRC="https://cdn.rumia.me/LIB/COOKIE.js?V=LATEST"></SCRIPT>
	<SCRIPT default>
		const user_id = "<?=$user_id?>";
		let session;
		let self_user;

		let is_login = true;
		let is_self = false;

		let user;
		let followed = false;
		let follower = false;
		let blocked = false;
		let blocker = false;

		let mel = {
			icon: document.getElementById("USER_ICON"),
			name: document.getElementById("USER_NAME"),
			description: document.getElementById("USER_DESCRIPTION"),
			follow_button:document.getElementById("FOLLOW_BUTTON"),
			menu_button:document.getElementById("MENU_BUTTON"),
			menu: {
				parent: document.getElementById("MENU"),
				block_button:document.getElementById("MENU_BLOCK_BUTTON")
			},
			rr_message: document.getElementById("RR_MESSAGE")
		};

		window.addEventListener("load", async function (){
			session = ReadCOOKIE().SESSION;
			if (session == null) is_login = false;

			self_user = await LOGIN(session);
			if (self_user == false) is_login = false;

			let ajax = await fetch("/api/User?ID=" + user_id, {
				method: "GET",
				headers: {
					"TOKEN": session,
					"Accept": "application/json"
				}
			});

			const result = await ajax.json();
			if (result.ACCOUNT.ID == self_user.ID) is_self = true;

			user = result.ACCOUNT;

			mel.icon.src = user.ICON_RAW_URL;
			mel.icon.className = "ICON_" + user.ICON;
			mel.name.innerText = user.NAME;
			mel.description.innerText = user.DESCRIPTION;

			if (is_self || !is_login) {
				mel.follow_button.style.display = "none";
				mel.menu_button.style.display = "none";
			}

			if (is_login) {
				followed = result.FOLLOW;
				follower = result.FOLLOWER;
				blocked = result.BLOCK;
				blocker = result.BLOCKER;

				update_follow_button_text();
				update_block_button_text();
			}

			if (blocker) {
				mel.follow_button.style.display = "none";
				mel.menu_button.style.display = "none";
				show_rr_message("ブロックされています");
			}

			if (follower) {
				show_rr_message("フォローされています");
			}
		});

		function show_rr_message(message) {
			mel.rr_message.style.display = "block";
			mel.rr_message.innerText = message;
		}

		function update_follow_button_text() {
			if (followed) {
				mel.follow_button.innerText = "フォロー解除";
			} else {
				if (follower) {
					mel.follow_button.innerText = "フォローバック";
				} else {
					mel.follow_button.innerText = "フォロー";
				}
			}
		}

		function update_block_button_text() {
			if (blocked) {
				mel.menu.block_button.innerText = "ブロック解除";
			} else {
				if (blocker) {
					mel.menu.block_button.innerText = "ブロックバック";
				} else {
					mel.menu.block_button.innerText = "ブロック";
				}
			}
		}

		mel.follow_button.addEventListener("click", async function() {
			if (blocked || blocker) return;

			let method = "";
			if (followed) {
				method = "DELETE";
			} else {
				method = "POST";
			}

			let ajax = await fetch("/api/Follow?UID=" + user.UID, {
				method: method,
				headers: {
					"TOKEN": session,
					"Accept": "application/json"
				}
			});
			const result = await ajax.json();

			if (result.STATUS) {
				followed = !followed;
				update_follow_button_text();
			} else {
				mel.follow_button.innerText = "エラー";
			}
		});

		mel.menu_button.addEventListener("click", function() {
			if (blocked || blocker) return;

			if (mel.menu.parent.style.display == "none") {
				mel.menu.parent.style.display = "block";
			} else {
				mel.menu.parent.style.display = "none";
			}
		});

		mel.menu.block_button.addEventListener("click", async function() {
			let method = "";
			if (followed) {
				method = "DELETE";
			} else {
				method = "POST";
			}

			let ajax = await fetch("/api/Block?UID=" + user.UID, {
				method: method,
				headers: {
					"TOKEN": session,
					"Accept": "application/json"
				}
			});
			const result = await ajax.json();

			if (result.STATUS) {
				window.location.reload();
			}
		});
	</SCRIPT>
</HTML>