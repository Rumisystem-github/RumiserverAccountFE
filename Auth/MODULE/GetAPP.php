<?php
function GetAPP($PDO, $ID) {
	$SQL_RESULT = SQL_RUN($PDO,
	"
		SELECT
			A.*
		FROM
			`APP` AS A
		WHERE
			A.ID = :ID
	", [
		[
			"KEY" => "ID",
			"VAL" => $ID
		]
	]);
	
	if ($SQL_RESULT["STATUS"]) {
		if (isset($SQL_RESULT["RESULT"][0])) {
			return $SQL_RESULT["RESULT"][0];
		} else {
			return null;
		}
	} else {
		return null;
	}
}
?>