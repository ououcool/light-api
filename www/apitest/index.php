<?php
$whiteList = ['101.81.15.143'];
$clientIP = $_SERVER['REMOTE_ADDR'];
if (!in_array($clientIP, $whiteList) && filter_var(
		$clientIP,
		FILTER_VALIDATE_IP,
		FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE
	)) {
	header('HTTP/1.0 403 Forbidden');
	die('禁止外网访问');
}
?>
<head>
<meta http-equiv=Content-Type content="text/html;charset=utf-8">
<title>Api Test Client</title>
<style type="text/css">
input {
	width: 100%;
	height: 40px;
	font-size: 20px;
	padding-left: 8px;
}

pre {
	white-space: pre-wrap; /* css-3 */
	white-space: -moz-pre-wrap; /* Mozilla, since 1999 */
	white-space: -pre-wrap; /* Opera 4-6 */
	white-space: -o-pre-wrap; /* Opera 7 */
	word-wrap: break-word; /* Internet Explorer 5.5+ */
}

#box {
	width: 85%;
	margin: 0 auto;
	padding: 25px;
}

#sendRequest {
	width: 100%;
	height: 55px;
	font-size: 20px;
	padding-left: 8px;
}

#tblMain {
	width: 99%;
	border: 1px solid gray;
	margin: auto 15px auto 15px;
	padding: 25px;
}

#tblMain th.one {
	width: 12%;
}

#tblMain th.two {
	width: 88%;
}

tr {
	height: 50px;
}

#subTitle {
	font-size: 35px;
}

#RESPONSE {
	width: 99%;
	min-height: 150px;
	border: 1px solid silver;
	padding: 5px;
}
</style>
<script type="text/javascript" src="js/md5.min.js"></script>
<script type="text/javascript" src="js/JsonUti.js"></script>
<script type="text/javascript" src="js/jquery-1.11.1.min.js"></script>
<script type="text/javascript">
	var API_URL = window.location.href.replace("apitest/", "index.php");
	var CALLCLIENT_UA = "LOCAL_DEV_CLIENT";
	var CALLCLIENT_SIGNKEY = "LOCAL_DEV_REQUEST_SIGN_KEY";

	function sendRequest() {
		var call = document.getElementById("call").value;
		if (call.length < 5) {
			alert("请求路径输入有误, 请重新输入...");
			return false;
		}

		var args = document.getElementById("args").value;
		if (args.length < 2) {
			alert("JSON参数输入有误, 请重新输入...");
			return false;
		}

		window.localStorage.setItem('call', call);
		window.localStorage.setItem('args', args);

		//生成请求签名
		var signKey = CALLCLIENT_UA + CALLCLIENT_SIGNKEY + CALLCLIENT_UA;
		signKey = md5(signKey + call + signKey + args + signKey);

		$("#RESPONSE").html('');
		var request = $.ajax({
			url : API_URL,
			type : "POST",
			data : {
				'call' : call,
				'args' : args,
				'sign' : signKey,
				"ua" : CALLCLIENT_UA
			},
			success : function(data) {
				try {
					var jsonStr = JsonUti.convertToString(eval("(" + data + ")"))
					$("#RESPONSE").css({'color':'darkgreen', "border-color":"green"}).html(jsonStr);
				} catch (exception) {
					$("#RESPONSE").css({'color':'black', "border-color":"red"}).html(data);
				}
			},
			error: function (XMLHttpRequest, textStatus, errorThrown) {
			    var message = "Response Info:<br/>";
			    message += "   HTTP_STATUS: " + XMLHttpRequest.status + "; Type: " + textStatus + "; More: " + errorThrown;
			    message += "<br/>Response Data:<br/>";
			    var data = XMLHttpRequest.responseText;
			    if( data!=null && data.length>0 ){
			    	try {
			    		message += JsonUti.convertToString(eval("(" + data + ")"));
					} catch (exception) {
						message += data;
					}
			    }
				$("#RESPONSE").css({'color':'red', "border-color":"red"}).html(message);
			}
		});

	}

	$(function() {
		if (window.localStorage.getItem('call'))
			$("#call").val(window.localStorage.getItem('call'));
		if (window.localStorage.getItem('args'))
			$("#args").val(window.localStorage.getItem('args'));

		$("#sendRequest").click(sendRequest);
	});
</script>
</head>
<body>

	<center id="box">
		<table id="tblMain">
			<tr>
				<th colspan="2"><span id="subTitle">Api Test Client</span>
				<hr></th>
			</tr>

			<tr>
				<th class="one">Api名称:</th>
				<td class="two"><input type="text" id="call"
					value="Demo.Demo.test" /></td>
			</tr>
			<tr>
				<th class="one">JSON参数:</th>
				<td class="two"><input type="text" id="args"
					value='{}' /></td>
			</tr>

			<tr>
				<td colspan="2"><input type="button" id="sendRequest"
					value="确认并发送请求"></td>
			</tr>
			<tr>
				<th valign="top">返回结果:</th>
				<td><pre id="RESPONSE"></pre></td>
			</tr>

		</table>

	</center>

</body>
</html>
