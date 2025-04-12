<?php

declare(strict_types=1);

session_start();

/*
 * Database config
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'chat');
define('DB_USER', 'root');
define('DB_PASS', '');

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

if (isset($_POST['method'])) {
    try {
        $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        header('Content-type: application/json');

        function getMessages(PDO $db, string $timestamp): array
        {
            $sql = "SELECT * FROM `messages` WHERE `time` > :time ORDER BY `time` ASC";
            $query = $db->prepare($sql);
            $query->bindParam(':time', $timestamp, PDO::PARAM_STR);
            $query->execute();
            $messages = $query->fetchAll(PDO::FETCH_ASSOC);

            if ($messages) {
                $response['status_msg'] = true;
                $response['messages'] = $messages;
                $lastMessage = end($messages);
                $response['timestamp'] = $lastMessage['time'] ?? $timestamp;
            } else {
                $response['status_msg'] = true;
                $response['messages'] = [];
                $response['timestamp'] = $timestamp;
            }

            return $response;
        }

        function pushMessage(PDO $db, string $messageContent, string $userName): bool
        {
            $sql = "INSERT INTO `messages` (`message_content`, `user_name`) VALUES (:message_content, :user_name)";
            $query = $db->prepare($sql);
            $query->bindParam(':message_content', $messageContent, PDO::PARAM_STR);
            $query->bindParam(':user_name', $userName, PDO::PARAM_STR);
            return $query->execute();
        }

        function process(PDO $db, string $method): void
        {
            switch ($method) {
                case "get_messages":
                    if (isset($_POST['timestamp']) && !empty($_POST['timestamp'])) {
                        $messages = getMessages($db, $_POST['timestamp']);

                        if ($messages['status_msg']) {
                            if (!empty($messages['messages'])) {
                                $_SESSION['timestamp'] = $messages['timestamp'];
                            }
                            echo json_encode($messages);
                        } else {
                            echo json_encode(["status_msg" => false, "message" => "An error occurred, please try again later."]);
                        }
                    } else {
                        echo json_encode(["status_msg" => false, "message" => "An error occurred, please try again later."]);
                    }
                    break;

                case "push_message":
                    if (isset($_POST['msg']) && !empty($_POST['msg']) && isset($_POST['username']) && !empty($_POST['username'])) {
                        if (pushMessage($db, $_POST['msg'], $_POST['username'])) {
                            echo json_encode(["status_msg" => true, "message" => "Message sent successfully"]);
                        } else {
                            echo json_encode(["status_msg" => false, "message" => "An error occurred, please try again later."]);
                        }
                    } else {
                        echo json_encode(["status_msg" => false, "message" => "Please provide both a message and a username."]);
                    }
                    break;

                case "setUsername":
                    if (isset($_POST['user_name']) && !empty($_POST['user_name'])) {
                        $_SESSION['user_name'] = $_POST['user_name'];
                        header("Location: " . basename($_SERVER['PHP_SELF']));
                        exit;
                    } else {
                        // Optionally handle the case where user_name is not provided
                        header("Location: " . basename($_SERVER['PHP_SELF']));
                        exit;
                    }
                    break;

                default:
                    // Handle unknown method
                    http_response_code(400);
                    echo json_encode(["status_msg" => false, "message" => "Invalid method requested."]);
                    break;
            }
        }

        if (isset($_POST['method'])) {
            process($db, $_POST['method']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status_msg" => false, "message" => "Database error: " . $e->getMessage()]);
    }
    exit; // Ensure script stops after processing AJAX request
} elseif (empty($_SESSION['user_name'])) {
    ?>

    <form id="form" method="POST">
        <input type="text" name="user_name" class="form-control input-sm" placeholder="Type your username here..." required />
        <input type="hidden" name="method" value="setUsername" />
        <button type="submit" form="form" class="btn btn-warning btn-sm" id="btn-chat">Send</button>
    </form>

    <?php
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Chat Template with jQuery / Bootstrap 3</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/5.0.0/normalize.min.css">
        <link rel='stylesheet prefetch' href='https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css'>
        <style>
            a {
                cursor: pointer;
            }

            .chat {
                list-style: none;
                margin: 0;
                padding: 0;
            }

            .chat li {
                margin-bottom: 10px;
                padding-bottom: 5px;
                border-bottom: 1px dotted #B3A9A9;
            }

            .chat li .chat-body p {
                margin: 0;
                color: #777;
            }

            .chat .glyphicon,
            .panel .slidedown .glyphicon {
                margin-right: 5px;
            }

            .panel-body {
                overflow-y: scroll;
                height: 500px;
            }

            ::-webkit-scrollbar-track {
                -webkit-box-shadow: inset 0 0 6px rgba(0, 0, 0, .3);
                background-color: #F5F5F5;
            }

            ::-webkit-scrollbar {
                width: 12px;
                background-color: #F5F5F5;
            }

            ::-webkit-scrollbar-thumb {
                -webkit-box-shadow: inset 0 0 6px rgba(0, 0, 0, .3);
                background-color: #555;
            }
        </style>
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
                    <form id="form" method="POST">
                        <div class="panel-footer">
                            <div class="input-group">
                                <input id="msg" type="text" class="form-control input-sm" placeholder="Type your message here..." required />
                                <span class="input-group-btn">
                                <button type="submit" form="form" class="btn btn-warning btn-sm" id="btn-chat">
                                    Send</button>
                                </span>
                            </div>
                        </div>
                        <input type="hidden" name="method" value="push_message">
                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8'); ?>">
                    </form>
                    <form id="en" method="GET">
                        Encryption key <input id="enc" type="text">
                        <button type="submit" form="en">Set</button>
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

        $(document).ready(function () {
            $("#refresh").click(function () {
                chat.get_messages();
            }), $("#clear").click(function () {
                chat.lastmsg_timestamp = "-1", $("#chat").empty()
            }), $("#en").submit(function (s) {
                s.preventDefault(s), encryptionkeys = $("#enc").val(), console.log("Encryption key set.")
            }), $("#form").submit(function (s) {
                s.preventDefault(s), chat.msg_contents = CryptoJS.AES.encrypt($("#msg").val(), encryptionkeys).toString(), lastClick >= Date.now() - delay || (lastClick = Date.now(), chat.msg_contents && ($("#msg").val(""), $.ajax({
                    url: "index.php",
                    type: "post",
                    data: {
                        method: "push_message",
                        msg: chat.msg_contents,
                        username: chat.username
                    },
                    success: function (s) {
                        1 != s.status_msg ? $("#errors_box .error").html(s.message) : (console.log("Message sent successfully"), chat.get_messages(), $("#errors_box .error").html(""))
                    }
                })))
            })
        });
        var chat = {};
        chat.get_messages = function () {
            $.ajax({
                url: "index.php",
                type: "POST",
                data: {
                    method: "get_messages",
                    timestamp: chat.lastmsg_timestamp
                },
                success: function (s) {
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
    <script type="text/javascript"> chat.username = "<?php echo htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8'); ?>";</script>
    </body>
    </html>
    <?php
}
?>
