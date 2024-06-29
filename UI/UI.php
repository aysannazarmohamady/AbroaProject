<?php
$token = "";
$apiUrl = "https://api.telegram.org/bot$token/";
$channelUsername = "@JetApply";
$dataFile = "user_data.json";

$update = file_get_contents("php://input");
$updateArray = json_decode($update, true);

if (isset($updateArray['message'])) {
    $chatId = $updateArray['message']['chat']['id'];
    $message = $updateArray['message']['text'];

    if ($message == "/start") {
        sendMainMenu($chatId);
    } elseif ($message == "Search Opportunities") {
        sendMessage($chatId, "You selected: Search Opportunities");
    } elseif ($message == "User Profile") {
        requestUserProfile($chatId);
    } elseif ($message == "AI Assistant") {
        sendMessage($chatId, "You selected: AI Assistant");
    } elseif ($message == "Help and Support") {
        sendMessage($chatId, "You selected: Help and Support");
    } else {
        handleProfileData($chatId, $message);
    }
}

function sendMessage($chatId, $message, $keyboard = null) {
    global $apiUrl;
    $url = $apiUrl . "sendMessage?chat_id=" . $chatId . "&text=" . urlencode($message);
    if ($keyboard) {
        $url .= "&reply_markup=" . urlencode(json_encode($keyboard));
    }
    file_get_contents($url);
}

function sendMainMenu($chatId) {
    $keyboard = [
        "keyboard" => [
            [["text" => "Search Opportunities"], ["text" => "User Profile"]],
            [["text" => "AI Assistant"], ["text" => "Help and Support"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "Please choose an option from the menu:", $keyboard);
}

function requestUserProfile($chatId) {
    sendMessage($chatId, "Please enter your email (optional):");
    $data = loadData();
    $data[$chatId]['step'] = 1;
    saveData($data);
}

function handleProfileData($chatId, $message) {
    $data = loadData();
    if (!isset($data[$chatId])) {
        return;
    }
    switch ($data[$chatId]['step']) {
        case 1:
            $data[$chatId]['email'] = $message;
            $data[$chatId]['step'] = 2;
            sendMessage($chatId, "Please enter your preferred field of study:");
            break;
        case 2:
            $data[$chatId]['field'] = $message;
            $data[$chatId]['step'] = 3;
            sendCountryKeyboard($chatId);
            break;
        case 3:
            if ($message == "🌍 Other") {
                sendMessage($chatId, "Please enter your favorite country manually:");
                $data[$chatId]['step'] = 3.1;
            } else {
                $data[$chatId]['country'] = $message;
                $data[$chatId]['step'] = 4;
                sendEducationLevelKeyboard($chatId);
            }
            break;
        case 3.1:
            $data[$chatId]['country'] = $message;
            $data[$chatId]['step'] = 4;
            sendEducationLevelKeyboard($chatId);
            break;
        case 4:
            $data[$chatId]['education_level'] = $message;
            unset($data[$chatId]['step']);
            saveData($data);
            sendMessage($chatId, "Your profile has been updated.");
            break;
    }
    saveData($data);
}

function sendCountryKeyboard($chatId) {
    $keyboard = [
        "keyboard" => [
            [["text" => "🇺🇸 USA"], ["text" => "🇨🇦 Canada"]],
            [["text" => "🇬🇧 UK"], ["text" => "🇦🇺 Australia"]],
            [["text" => "🇫🇮 Finland"], ["text" => "🇳🇱 Netherlands"]],
            [["text" => "🇦🇹 Austria"], ["text" => "🌍 Other"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "Please choose your preferred country:", $keyboard);
}

function sendEducationLevelKeyboard($chatId) {
    $keyboard = [
        "keyboard" => [
            [["text" => "PhD"], ["text" => "Master's"]],
            [["text" => "Post-Doc"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "Please choose your preferred education level:", $keyboard);
}

function loadData() {
    global $dataFile;
    if (!file_exists($dataFile)) {
        return [];
    }
    $json = file_get_contents($dataFile);
    return json_decode($json, true);
}

function saveData($data) {
    global $dataFile;
    $json = json_encode($data);
    file_put_contents($dataFile, $json);
}
?>

