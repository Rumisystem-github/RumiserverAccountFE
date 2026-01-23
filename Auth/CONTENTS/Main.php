<DIV ID="MAIN">
	<?=htmlspecialchars($APP["NAME"])?>が貴方のアカウントへのアクセスを要求しています！<BR>
	要求している権限は次のとおりです<BR>
	<BR>
	<?php
	foreach (array_keys($PERMISSION) as $NAME) {
		echo $NAME;

		switch ($PERMISSION[$NAME]) {
			case "read": {
				echo "読み取り";
				break;
			}

			case "write": {
				echo "書き込み";
				break;
			}

			case "full": {
				echo "フルアクセス";
				break;
			}
		}

		echo "<BR>";
	}
	?>
	<BR>
	<BUTTON onclick="APPLY();">許可する</BUTTON>
</DIV>
<DIV ID="RESULT"></DIV>

<SCRIPT SRC="https://cdn.rumia.me/LIB/COOKIE.js?V=LATEST"></SCRIPT>
<SCRIPT SRC="https://cdn.rumia.me/LIB/DIALOG.js?V=LATEST"></SCRIPT>
<SCRIPT>
	const dialog = new DIALOG_SYSTEM();

	async function APPLY() {
		const COOKIE = ReadCOOKIE();

		const LOADING = dialog.SHOW_LOAD();

		let AJAX = await fetch("/api/AUTH", {
			method: "POST",
			headers: {
				TOKEN: COOKIE.SESSION,
				"Accept": "application/json",
				"Content-Type": "application/json"
			},
			body: JSON.stringify({
				APP: APP_ID,
				SESSION: SESSION,
				PERMISSION: PERMISSION
			})
		});
		const RESULT = await AJAX.json();

		dialog.CLOSE_LOAD(LOADING);
		document.getElementById("MAIN").style.display = "none";

		if (RESULT.STATUS) {
			//成功
			if (CALLBACK != null) {
				window.location.href = CALLBACK + "?SESSION=" + SESSION;
				document.getElementById("RESULT").innerText = "アプリへ移動しています";
			} else {
				document.getElementById("RESULT").innerText = "成功";
			}
		} else {
			//失敗
			document.getElementById("RESULT").innerText = "エラー";
		}
	}
</SCRIPT>