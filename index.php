<?php

session_start();

/*
    Database config
*/

define('DB_HOST', 'localhost');
define('DB_NAME', 'chat');
define('DB_USER', 'root');
define('DB_PASS', '2fFucyc7Nw');

if (isset($_GET['logout'])) {
    
    session_destroy();
    header("Location: " . basename($_SERVER['PHP_SELF']));
}

if (isset($_POST['method'])) {
    
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    header('Content-type: application/json');
    
function process() {
    
    switch ($_POST['method']) {
        
        case "get_messages":
            
            if (isset($_POST['timestamp']) && !empty($_POST['timestamp'])) {
                
                $messages = getMessages($_POST['timestamp']);
                
                if ($messages['status_msg']) {
                    
                    $key = end($messages['messages']);        
                    $messages['timestamp'] = $key['time'];
                    
                    if (empty($messages['timestamp'])) {
                        
                        $messages['timestamp'] = $_SESSION['timestamp'];
                    } else {
                        
                        $_SESSION['timestamp'] = $messages['timestamp'];
                    }
                    
                    echo json_encode($messages);
                    
                } else {
                    
                    $status = [ "status_msg" => false, "message" => "An error ocurred, please try again later." ];
                    echo json_encode($status);
                }
                
            } else {
                
                $status = [ "status_msg" => false, "message" => "An error ocurred, please try again later." ];
                echo json_encode($status);
            }
            break;
            
        case "push_message":
            
            if (isset($_POST['msg']) && !empty($_POST['msg'])) {
                
                if(pushMessage($_POST['msg'], $_POST['username'])) {
                    
                    $status = [ "status_msg" => true, "message" => "Message sent sucefully" ];
                    echo json_encode($status);
                    
                } else {
                    
                    $status = [ "status_msg" => false, "message" => "An error ocurred, please try again later." ];
                    echo json_encode($status);
                }
                
            } else {
                
                $status = [ "status_msg" => false, "message" => "You need to write something in order to post a message." ];
                echo json_encode($status);
            }
            
            break;
            
            case "setUsername":
                $_SESSION['user_name'] = $_POST['user_name'];
                header("Location: " . basename($_SERVER['PHP_SELF']));
                break;
    }
}


function getMessages($timestamp) {
    
    global $db;
    
    $sql = "SELECT * FROM `messages` WHERE `time` > :time";
    $query = $db->prepare($sql);
    $query->execute(array("time" => $timestamp));
    $query_msg = $query->fetchAll(PDO::FETCH_ASSOC);
    
    if ($query) {
        
        $messages['status_msg'] = true;
        $messages['messages'] = $query_msg;
        return $messages;
    } else {
        
        $messages['status_msg'] = false;
        return $messages;
    }
}

function pushMessage($message_content, $user_name) {
    
    global $db;
    
    $sql = "INSERT INTO `messages` (`message_content`, `user_name`) VALUES (:message_content, :user_name)";
    $query = $db->prepare($sql);
    $query->execute(array(':message_content' => $message_content, "user_name" => $user_name));
    //var_dump($query->errorInfo());
    return ($query ? true : false);
}

process($_POST['method']);

    
} elseif (empty($_SESSION['user_name']) || !isset($_SESSION['user_name'])) {
?>

<form id="form" method="POST">
    <input type="text" name="user_name" class="form-control input-sm" placeholder="Type your user_name here..." />
    <input type="hidden" name="method" value="setUsername" />
    <button type="submit" form="form" class="btn btn-warning btn-sm" id="btn-chat">Send</button>
</form>
  
<?php 
} else {

?><!DOCTYPE html>
<html >
	<head>
		<meta charset="UTF-8">
		<title>Chat Template with jQuery / Bootstrap 3</title>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/5.0.0/normalize.min.css">
		<link rel='stylesheet prefetch' href='https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css'>
		<style>a{cursor:pointer}.chat{list-style:none;margin:0;padding:0}.chat li{margin-bottom:10px;padding-bottom:5px;border-bottom:1px dotted #B3A9A9}.chat li .chat-body p{margin:0;color:#777}.chat .glyphicon,.panel .slidedown .glyphicon{margin-right:5px}.panel-body{overflow-y:scroll;height:500px}::-webkit-scrollbar-track{-webkit-box-shadow:inset 0 0 6px rgba(0,0,0,.3);background-color:#F5F5F5}::-webkit-scrollbar{width:12px;background-color:#F5F5F5}::-webkit-scrollbar-thumb{-webkit-box-shadow:inset 0 0 6px rgba(0,0,0,.3);background-color:#555}</style>
	</head>
	<body>
		<div class="container">
			<div class="row">
				<div class="col-md-12">
					<div class="panel panel-primary">
						<div class="panel-heading">
							<span class="glyphicon glyphicon-comment"></span> Chat
							<div class="btn-group pull-right">
								<button type="button" class="btn btn-default btn-xs dropdown-toggle" data-toggle="dropdown">
								<span class="glyphicon glyphicon-chevron-down"></span>
								</button>
								<ul class="dropdown-menu slidedown">
									<li><a id="refresh"><span class="glyphicon glyphicon-refresh"></span>Refresh</a></li>
									<li><a id="clear"><span class="glyphicon glyphicon-trash"></span>Clear</a></li>
									<li class="divider"></li>
									<li><a href="?logout"><span class="glyphicon glyphicon-off"></span>Sign Out</a></li>
								</ul>
							</div>
						</div>
						<div class="panel-body">
							<ul id="chat" class="chat">
							</ul>
						</div>
						<form id="form" method="GET">
							<div class="panel-footer">
								<div class="input-group">
									<input id="msg" type="text" class="form-control input-sm" placeholder="Type your message here..." />
									<span class="input-group-btn">
									<button type="submit" form="form" class="btn btn-warning btn-sm" id="btn-chat">
									Send</button>
									</span>
								</div>
							</div>
						</form>
						<form id="en" method="GET">
							Encryption key <input id="enc" type="text">
							<button type="submit" form="en" >Set</button>
						</form>
					</div>
				</div>
			</div>
		</div>
		<script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.0/jquery.min.js'></script>
		<script src='https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/js/bootstrap.min.js'></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/rollups/aes.js"></script>
		<script type='text/javascript'>
		
    		    var lastClick = 0,
                delay = 300,
                encryptionkeys;
                        
                $(document).ready(function() {
                    $("#refresh").click(function() {
                        chat.get_messages();
                    }), $("#clear").click(function() {
                        chat.lastmsg_timestamp = "-1", $("#chat").empty()
                    }), $("#en").submit(function(s) {
                        s.preventDefault(s), encryptionkeys = $("#enc").val(), console.log("Encryption key set.")
                    }), $("#form").submit(function(s) {
                        s.preventDefault(s), chat.msg_contents = CryptoJS.AES.encrypt($("#msg").val(), encryptionkeys).toString(), lastClick >= Date.now() - delay || (lastClick = Date.now(), chat.msg_contents && ($("#msg").val(""), $.ajax({
                            url: "index.php",
                            type: "post",
                            data: {
                                method: "push_message",
                                msg: chat.msg_contents,
                                username: chat.username
                            },
                            success: function(s) {
                                1 != s.status_msg ? $("#errors_box .error").html(s.message) : (console.log("Message sent sucessfully"), chat.get_messages(), $("#errors_box .error").html(""))
                            }
                        })))
                    })
                });
                var chat = {};
                chat.get_messages = function() {
                    $.ajax({
                        url: "index.php",
                        type: "POST",
                        data: {
                            method: "get_messages",
                            timestamp: chat.lastmsg_timestamp
                        },
                        success: function(s) {
                            if (1 != s.status_msg) $("#errors_box .error").html(s.message);
                            else {
                                
                                console.log("Chat has been updated"), chat.lastmsg_timestamp = s.timestamp;
                                for (var e = 0; e < s.messages.length; e++) { 
                                    
                                    var message_content_decrypt = CryptoJS.AES.decrypt(s.messages[e].message_content, encryptionkeys).toString(CryptoJS.enc.Utf8);
                                    
                                    if (message_content_decrypt === "") {
                                        
                                        message_content_decrypt = "You have the wrong encryption key";
                                    }
                                    
                                    s.messages[e].user_name == chat.username ? $("#chat").append('<li class="right clearfix"><div class="chat-body clearfix"><div class="header"><small class="text-muted"><span class="glyphicon glyphicon-time"></span>' + s.messages[e].time + '</small> <strong class="pull-right primary-font"> ' + s.messages[e].user_name + "</strong></div><p>" + message_content_decrypt + "</p></div></li>") : $("#chat").append('<li class="left clearfix"><div class="chat-body clearfix"><div class="header"><strong class="primary-font">' + s.messages[e].user_name + '</strong> <small class="pull-right text-muted"><span class="glyphicon glyphicon-time"></span>' + s.messages[e].time + "</small></div><p>" + CryptoJS.AES.decrypt(s.messages[e].message_content, encryptionkeys).toString(CryptoJS.enc.Utf8) + "</p></div></li>");
                                }
                            }
                        }
                    })
                }, chat.interval = setInterval(chat.get_messages, 4500), chat.lastmsg_timestamp = "-1", encryptionkeys = "default", chat.get_messages();
                
        </script>
		<script type="text/javascript"> chat.username = "<?php echo $_SESSION['user_name'] ?>";</script>
	</body>
</html>
<?php } ?>