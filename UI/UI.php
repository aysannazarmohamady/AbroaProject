<?php
$token = "7011288395:AAGw0LntfB4s3ItqaT_buL4eIusRF2TZUi8";
$apiUrl = "https://api.telegram.org/bot$token/";
$channelUsername = "@JetApply";
$dataFile = "user_data.json";

$update = file_get_contents("php://input");
$updateArray = json_decode($update, true);

if (isset($updateArray['message'])) {
    $chatId = $updateArray['message']['chat']['id'];
    $message = $updateArray['message']['text'];
    $userId = $updateArray['message']['from']['id'];

    if ($message == "/start") {
        sendMainMenu($chatId);
    } elseif ($message == "🔍 Search Opportunities") {
        sendMessage($chatId, "You selected: Search Opportunities");
    } elseif ($message == "👤 User Profile") {
        requestUserProfile($chatId, $userId);
    } elseif ($message == "🤖 AI Assistant") {
        sendMessage($chatId, "You selected: AI Assistant");
    } elseif ($message == "❓ Help and Support") {
        sendMessage($chatId, "You selected: Help and Support");
    } elseif ($message == "📋 View/Edit Profile") {
        viewEditProfile($chatId, $userId);
    } else {
        handleProfileData($chatId, $message, $userId);
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
            [["text" => "🔍 Search Opportunities"], ["text" => "👤 User Profile"]],
            [["text" => "🤖 AI Assistant"], ["text" => "❓ Help and Support"]],
            [["text" => "📋 View/Edit Profile"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "Please choose an option from the menu:", $keyboard);
}

function requestUserProfile($chatId, $userId) {
    sendMessage($chatId, "Please enter your email (optional):");
    $data = loadData();
    $data[$userId]['step'] = 1;
    saveData($data);
}

function handleProfileData($chatId, $message, $userId) {
    $data = loadData();
    if (!isset($data[$userId])) {
        return;
    }
    switch ($data[$userId]['step']) {
        case 1:
            $data[$userId]['email'] = $message;
            $data[$userId]['step'] = 2;
            sendFieldOfStudyKeyboard($chatId);
            break;
        case 2:
            if ($message == "🌍 Other") {
                sendMessage($chatId, "Please enter your preferred field of study:");
                $data[$userId]['step'] = 2.1;
            } else {
                $data[$userId]['field'] = $message;
                $data[$userId]['step'] = 3;
                sendCountryKeyboard($chatId);
            }
            break;
        case 2.1:
            $data[$userId]['field'] = $message;
            $data[$userId]['step'] = 3;
            sendCountryKeyboard($chatId);
            break;
        case 3:
            if ($message == "🌍 Other") {
                sendMessage($chatId, "Please enter your favorite country manually:");
                $data[$userId]['step'] = 3.1;
            } else {
                $data[$userId]['country'] = $message;
                $data[$userId]['step'] = 4;
                sendCVUploadOption($chatId);
            }
            break;
        case 3.1:
            $data[$userId]['country'] = $message;
            $data[$userId]['step'] = 4;
            sendCVUploadOption($chatId);
            break;
        case 4:
            if ($message == "Yes") {
                sendMessage($chatId, "Please upload your CV file.");
                $data[$userId]['step'] = 5;
            } elseif ($message == "No") {
                unset($data[$userId]['step']);
                saveData($data);
                sendMessage($chatId, "Your profile has been updated.");
                sendMainMenu($chatId);
            }
            break;
        case 5:
            if (isset($updateArray['message']['document'])) {
                $data[$userId]['cv_file_id'] = $updateArray['message']['document']['file_id'];
                unset($data[$userId]['step']);
                saveData($data);
                sendMessage($chatId, "Your CV has been uploaded and your profile has been updated.");
                sendMainMenu($chatId);
            } else {
                sendMessage($chatId, "Please upload a file for your CV.");
            }
            break;
    }
    saveData($data);
}

function sendFieldOfStudyKeyboard($chatId) {
    $keyboard = [
        "keyboard" => [
            [["text" => "🧬 Biology"], ["text" => "💻 Computer Science"]],
            [["text" => "🔬 Physics"], ["text" => "🧪 Chemistry"]],
            [["text" => "📊 Mathematics"], ["text" => "📚 Literature"]],
            [["text" => "🎨 Arts"], ["text" => "🏛️ History"]],
            [["text" => "💼 Business"], ["text" => "⚖️ Law"]],
            [["text" => "🌍 Other"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "Please choose your preferred field of study:", $keyboard);
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

function sendCVUploadOption($chatId) {
    $keyboard = [
        "keyboard" => [
            [["text" => "Yes"], ["text" => "No"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "Would you like to upload your CV?", $keyboard);
}

function viewEditProfile($chatId, $userId) {
    $data = loadData();
    if (isset($data[$userId])) {
        $profile = $data[$userId];
        $message = "Your Profile:\n\n";
        $message .= "Email: " . ($profile['email'] ?? "Not set") . "\n";
        $message .= "Field of Study: " . ($profile['field'] ?? "Not set") . "\n";
        $message .= "Preferred Country: " . ($profile['country'] ?? "Not set") . "\n";
        $message .= "CV: " . (isset($profile['cv_file_id']) ? "Uploaded" : "Not uploaded") . "\n\n";
        $message .= "To edit your profile, select 'User Profile' from the main menu.";
        sendMessage($chatId, $message);
    } else {
        sendMessage($chatId, "You haven't created a profile yet. Select 'User Profile' from the main menu to create one.");
    }
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
