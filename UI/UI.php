<?php
$token = "";
$apiUrl = "https://api.telegram.org/bot$token/";
$channelUsername = "@JetApply";
$dataFile = "user_data.json";

$update = file_get_contents("php://input");
$updateArray = json_decode($update, true);

if (isset($updateArray['message'])) {
    $chatId = $updateArray['message']['chat']['id'];
    $message = $updateArray['message']['text'] ?? '';
    $userId = $updateArray['message']['from']['id'];

    switch ($message) {
        case "/start":
            sendMainMenu($chatId);
            break;
        case "🔍 Search Opportunities":
            sendMessage($chatId, "You selected: Search Opportunities");
            break;
        case "👤 User Profile":
            requestUserProfile($chatId, $userId);
            break;
        case "🤖 AI Assistant":
            sendAIAssistantMenu($chatId);
            break;
        case "❓ Help and Support":
            sendMessage($chatId, "You selected: Help and Support");
            break;
        case "📋 View/Edit Profile":
            viewEditProfile($chatId, $userId);
            break;
        case "🔙 Back to Main Menu":
            sendMainMenu($chatId);
            break;
        case "🧠 Prompting":
            sendPromptingMenu($chatId);
            break;
        case "📝 Resume Writing":
        case "✉️ Cover Letter Writing":
        case "📧 Email Writing":
            handlePromptingOption($chatId, $message);
            break;
        default:
            handleProfileData($chatId, $message, $userId, $updateArray);
            break;
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
    sendMessage($chatId, "Please choose an option from the main menu:", $keyboard);
}

function sendAIAssistantMenu($chatId) {
    $keyboard = [
        "keyboard" => [
            [["text" => "🧠 Prompting"]],
            [["text" => "🔙 Back to Main Menu"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "AI Assistant Menu:", $keyboard);
}

function sendPromptingMenu($chatId) {
    $keyboard = [
        "keyboard" => [
            [["text" => "📝 Resume Writing"], ["text" => "✉️ Cover Letter Writing"]],
            [["text" => "📧 Email Writing"]],
            [["text" => "🔙 Back to Main Menu"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "Prompting Menu:", $keyboard);
}

function handlePromptingOption($chatId, $option) {
    switch ($option) {
        case "📝 Resume Writing":
            $message = "I have a job posting for the role of [Job Title] at [Company Name]. Please review the key requirements and responsibilities listed in the job description below, and then rewrite/update my existing resume to highlight the most relevant skills, experiences, and accomplishments that align with what the employer is looking for in an ideal candidate.\n\n[Paste the full job description text here]\n\nHere is my current resume:\n[Paste your existing resume content here]\n\nWhen rewriting my resume, please:\n- Update the resume summary/objective to speak directly to this role\n- Reorder and tweak the experience/skills sections to prioritize the most relevant qualifications \n- Use keyword phrases pulled from the job description where applicable\n- Quantify achievements with metrics/numbers where possible\n- Keep the resume concise, focusing on only the most pertinent details for this role\n\nThe goal is to create a tailored version of my resume that clearly showcases why I'm a strong fit for this particular position based on the stated requirements. Please maintain a professional tone throughout.";
            sendMessage($chatId, $message);
            break;
        case "✉️ Cover Letter Writing":
            $message = "Please write a cover letter for an academic position using the following information:\n\nApplicant's Resume:\n[Insert full resume of the applicant here]\nAcademic Position Description:\n[Insert full description of the academic position here]\n\nPlease write a compelling cover letter that:\n\n- Addresses the hiring committee or department chair\n- Expresses enthusiasm for the position\n- Highlights how the applicant's qualifications match the job requirements\n- Demonstrates knowledge of and interest in the institution\n- Explains how the applicant's research and teaching experience align with the position\n- Concludes with a strong statement of interest and availability for an interview\n\nThe cover letter should be professional, concise, and tailored to the specific position and institution. It should be approximately 1 page in length (3-4 paragraphs).\n\nThe system should:\n\n- Extract key information from the resume, including educational background, research experience, publications, teaching experience, awards, and technical skills\n- Identify key requirements and preferences from the position description\n- Extract information about the institution from the position description\n- Use this information to write a personalized and relevant cover letter.";
            sendMessage($chatId, $message);
            break;
        case "📧 Email Writing":
            sendMessage($chatId, "You selected Email Writing. Please provide details for your email.");
            break;
    }
}

function requestUserProfile($chatId, $userId) {
    sendMessage($chatId, "Please enter your email (optional):");
    $data = loadData();
    $data[$userId]['step'] = 1;
    saveData($data);
}

function handleProfileData($chatId, $message, $userId, $updateArray) {
    $data = loadData();
    if (!isset($data[$userId]) || !isset($data[$userId]['step'])) {
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
                sendEducationLevelKeyboard($chatId);
            }
            break;
        case 3.1:
            $data[$userId]['country'] = $message;
            $data[$userId]['step'] = 4;
            sendEducationLevelKeyboard($chatId);
            break;
        case 4:
            $data[$userId]['education_level'] = $message;
            $data[$userId]['step'] = 5;
            sendCVUploadOption($chatId);
            break;
        case 5:
            if ($message == "Yes") {
                sendMessage($chatId, "Please upload your CV file.");
                $data[$userId]['step'] = 6;
            } elseif ($message == "No") {
                unset($data[$userId]['step']);
                saveData($data);
                sendMessage($chatId, "Your profile has been updated.");
                sendMainMenu($chatId);
            }
            break;
        case 6:
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
    sendMessage($chatId, "Please choose your field of study:", $keyboard);
}

function sendCountryKeyboard($chatId) {
    $keyboard = [
        "keyboard" => [
            [["text" => "🇺🇸 USA"], ["text" => "🇬🇧 UK"]],
            [["text" => "🇨🇦 Canada"], ["text" => "🇦🇺 Australia"]],
            [["text" => "🇩🇪 Germany"], ["text" => "🇫🇷 France"]],
            [["text" => "🇯🇵 Japan"], ["text" => "🇨🇳 China"]],
            [["text" => "🇮🇳 India"], ["text" => "🇧🇷 Brazil"]],
            [["text" => "🌍 Other"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "Please choose your preferred country:", $keyboard);
}

function sendEducationLevelKeyboard($chatId) {
    $keyboard = [
        "keyboard" => [
            [["text" => "Bachelor's"], ["text" => "Master's"]],
            [["text" => "PhD"], ["text" => "Postdoc"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "Please choose your highest level of education:", $keyboard);
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

function loadData() {
    global $dataFile;
    if (file_exists($dataFile)) {
        return json_decode(file_get_contents($dataFile), true);
    }
    return [];
}

function saveData($data) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($data));
}
