<?php
ini_set("session.gc_maxlifetime", 7*24*60*60);
ini_set("session.cookie_lifetime", 7*24*60*60);
session_start();
@session_destroy();
session_start();
?>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>Login</title>
	<meta name=viewport content="width=device-width, initial-scale=1, maximum-scale=1">
</head>
<style>
	body{
	  font-family: "Avenir", sans-serif;
	  background: black;
	  color: #fff;
	}
	.loginform{
		display: flex;
		flex-direction: column;
		align-items: center;
		background: #111;
		padding: 1em;
		padding-top: 0;
		position: fixed;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		border: solid #333;
	}
	input, button {
		background-color: #222;
		border: thin solid #333;
		color: white;
		padding: 0.25em 1em;
		margin: 0.25em;
		text-align: center;
		text-decoration: none;
		display: inline-block;
		font-size: 1em;
	}
	button:hover{
	    cursor: pointer;
	    background-color: #333;
	}
</style>
<?php
require_once __DIR__."/../env.php";
if(!empty($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS'])){
	$uri = 'https://';
	$ssl = true;
}else{
	$uri = 'http://';
	$ssl = false;
	if(!isset($_ENV["FORCE_ALLOW_HTTP"]) || $_ENV["FORCE_ALLOW_HTTP"] !== true){
		echo("Login is not allowed from http!<br>");
		echo("<a style=\"color: white\" href=\"#\" onclick=\"window.location='https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].(isset($_GET['destination']) ? "?destination=".$_GET['destination'] : "")."'\">CLICK HERE TO USE HTTPS ADRESS.</a>");
		exit;
	}
}
$uri .= $_SERVER['HTTP_HOST'].dirname($_SERVER['REQUEST_URI'], 2);
?>
<body>
	<div class="loginform">
		<h3>Login</h3>
			<input placeholder="Username" id="username" type="username">
			<input placeholder="Password" id="password" type="password">
			<div id="msg"><?php if(isset($_GET['msg'])){?><span style="color: red"><?php echo($_GET['msg']); ?></span><?php } ?></div>
		<button id="submit" name="submit">Login</button>
	</div>
</body>
<script type="text/javascript">
	document.getElementById('submit').onclick = logon;
document.getElementById("password").addEventListener("keyup", function(event){
    if(event.keyCode == 13){
        logon();
    }
});
document.getElementById("username").addEventListener("keyup", function(event){
    if(event.keyCode == 13){
        logon();
    }
});
function logon(){
	var xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = function(){
		if(this.readyState == 4 && this.status == 200){
			if(this.responseText.indexOf('success') == -1){
				document.getElementById("msg").innerHTML = '<span style="color: red">' + this.responseText + '</span>';
			}else{
				window.location.replace("<?php echo(isset($_GET['destination']) ? ($ssl ? str_replace("http://", "https://", $_GET['destination']) : $_GET['destination']) : $uri) ?>");
			}
		}
	};
	xhttp.open("POST", "logon.php", true);
	xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	xhttp.send("username="+document.getElementById('username').value + "&passwd="+document.getElementById('password').value);
}
</script>