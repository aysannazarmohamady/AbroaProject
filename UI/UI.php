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

    // Check if the user is a member of the channel
    if (!isChannelMember($userId, $channelUsername)) {
        inviteToChannel($chatId);
        exit;
    }

    switch ($message) {
        case "/start":
            sendMainMenu($chatId);
            break;
        case "🔍 Search Opportunities":
            sendSearchOpportunitiesMenu($chatId);
            break;
        case "📊 Display Based on Your Profile":
            searchOpportunities($chatId, $userId);
            break;
        case "🔎 New Search":
            sendNewSearchMenu($chatId);
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
        case "🧑‍🏫 Search Supervisors":
            handleSearchSupervisors($chatId);
            break;
        case "📅 Latest opportunities":
            handleLatestOpportunities($chatId);
            break;
        case "🌍 Global Search":
            handleGlobalSearch($chatId);
            break;
        case "🔬 Advanced Search":
            handleAdvancedSearch($chatId);
            break;
        default:
            handleProfileData($chatId, $message, $userId);
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
            [["text" => "📋 View/Edit Profile"], ["text" => "🧑‍🏫 Search Supervisors"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "Please choose an option from the main menu:", $keyboard);
}

function sendSearchOpportunitiesMenu($chatId) {
    $keyboard = [
        "keyboard" => [
            [["text" => "📊 Display Based on Your Profile"], ["text" => "🔎 New Search"]],
            [["text" => "🔙 Back to Main Menu"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "Please choose an option for searching opportunities:", $keyboard);
}

function sendNewSearchMenu($chatId) {
    $keyboard = [
        "keyboard" => [
            [["text" => "📅 Latest opportunities"], ["text" => "🌍 Global Search"]],
            [["text" => "🔬 Advanced Search"]],
            [["text" => "🔙 Back to Main Menu"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "Please choose a search option:", $keyboard);
}

function handleLatestOpportunities($chatId) {
    sendMessage($chatId, "Searching for the latest opportunities...");
    // Implement the logic to fetch and display the latest opportunities
}

function handleGlobalSearch($chatId) {
    sendMessage($chatId, "Please enter your global search criteria:");
    // Implement the logic for global search
}

function handleAdvancedSearch($chatId) {
    sendMessage($chatId, "Advanced Search: Please specify your detailed search criteria.");
    // Implement the logic for advanced search
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
            $message = "I have a job posting for the role of [Job Title] at [Company Name]. Please review the key requirements and responsibilities listed in the job description below, and then rewrite/update my existing resume to highlight the most relevant skills, experiences, and accomplishments that align with what the employer is looking for in an ideal candidate.

[Paste the full job description text here]

Here is my current resume:
[Paste your existing resume content here]

When rewriting my resume, please:
- Update the resume summary/objective to speak directly to this role
- Reorder and tweak the experience/skills sections to prioritize the most relevant qualifications 
- Use keyword phrases pulled from the job description where applicable
- Quantify achievements with metrics/numbers where possible
- Keep the resume concise, focusing on only the most pertinent details for this role

The goal is to create a tailored version of my resume that clearly showcases why I'm a strong fit for this particular position based on the stated requirements. Please maintain a professional tone throughout.";
            sendMessage($chatId, $message);
            break;
        case "✉️ Cover Letter Writing":
            $message = "Please write a cover letter for an academic position using the following information:
Applicant's Resume:
[Insert full resume of the applicant here]
Academic Position Description:
[Insert full description of the academic position here]
Please write a compelling cover letter that:
Addresses the hiring committee or department chair
Expresses enthusiasm for the position
Highlights how the applicant's qualifications match the job requirements
Demonstrates knowledge of and interest in the institution
Explains how the applicant's research and teaching experience align with the position
Concludes with a strong statement of interest and availability for an interview
The cover letter should be professional, concise, and tailored to the specific position and institution. It should be approximately 1 page in length (3-4 paragraphs).
The system should:
Extract key information from the resume, including educational background, research experience, publications, teaching experience, awards, and technical skills
Identify key requirements and preferences from the position description
Extract information about the institution from the position description
Use this information to write a personalized and relevant cover letter";
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

function handleProfileData($chatId, $message, $userId) {
    $data = loadData();
    if (!isset($data[$userId]) || !isset($data[$userId]['step'])) {
        return;
    }
    
    if ($data[$userId]['step'] == 'search_supervisors') {
        // Process supervisor search request
        $searchInfo = parseSearchInfo($message);
        $result = searchSupervisors($searchInfo);
        sendMessage($chatId, $result);
        unset($data[$userId]['step']);
        saveData($data);
        sendMainMenu($chatId);
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

function sendEducationLevelKeyboard($chatId) {
    $keyboard = [
        "keyboard" => [
            [["text" => "🎓 PhD"], ["text" => "📚 Master's"]],
            [["text" => "🔬 Post-Doc"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "Please choose your preferred education level:", $keyboard);
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
        $message .= "Education Level: " . ($profile['education_level'] ?? "Not set") . "\n";
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

function isChannelMember($userId, $channelUsername) {
    global $apiUrl;
    $url = $apiUrl . "getChatMember?chat_id=" . $channelUsername . "&user_id=" . $userId;
    $response = file_get_contents($url);
    $result = json_decode($response, true);
    
    if ($result['ok'] && in_array($result['result']['status'], ['member', 'administrator', 'creator'])) {
        return true;
    }
    return false;
}

function inviteToChannel($chatId) {
    global $channelUsername;
    $message = "To use this bot, you need to be a member of our channel. Please join " . $channelUsername . " and then start the bot again.";
    $keyboard = [
        "inline_keyboard" => [
            [["text" => "Join Channel", "url" => "https://t.me/" . ltrim($channelUsername, '@')]]
        ]
    ];
    sendMessage($chatId, $message, $keyboard);
}

function handleSearchSupervisors($chatId) {
    $message = "To search for supervisors, please provide the following information:

1. Field of study (e.g., Computer Science, Biology, etc.)
2. University or institution (optional)
3. Research topic or keywords (optional)

Please enter this information in the format:
Field: [Your field]
University: [University name] (optional)
Topic: [Research topic or keywords] (optional)

For example:
Field: Computer Science
University: MIT
Topic: Machine Learning, Artificial Intelligence";

    sendMessage($chatId, $message);
    
    $data = loadData();
    $data[$chatId]['step'] = 'search_supervisors';
    saveData($data);
}

function parseSearchInfo($message) {
    $lines = explode("\n", $message);
    $info = [];
    foreach ($lines as $line) {
        if (strpos($line, "Field:") !== false) {
            $info['field'] = trim(substr($line, 6));
        } elseif (strpos($line, "University:") !== false) {
            $info['university'] = trim(substr($line, 11));
        } elseif (strpos($line, "Topic:") !== false) {
            $info['topic'] = trim(substr($line, 6));
        }
    }
    return $info;
}

function searchSupervisors($info) {
    // Implement your search logic here
    // This is a placeholder function
    return "Search results for supervisors:\nField: " . ($info['field'] ?? 'Not specified') . "\nUniversity: " . ($info['university'] ?? 'Not specified') . "\nTopic: " . ($info['topic'] ?? 'Not specified');
}

function searchOpportunities($chatId, $userId) {
    $userData = loadData();
    if (!isset($userData[$userId])) {
        sendMessage($chatId, "Please complete your profile first by selecting 'User Profile' from the main menu.");
        return;
    }

    $userProfile = $userData[$userId];
    $title = clearDate($userProfile['field'] ?? '');
    $country = clearDate($userProfile['country'] ?? '');
    $url = 'https://jet.aysan.dev/api.php';
    $data = array(
        'action' => 'search',
        'keyword' => trim($title),
        'country' => trim($country)
    );
    
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data)
        )
    );

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE) {
        sendMessage($chatId, "An error occurred while searching for opportunities. Please try again later.");
    } else {
        $response = json_decode($result, true);
        sendSearchResults($chatId, $response);
    }
}

function sendSearchResults($chatId, $results) {
    if (empty($results)) {
        sendMessage($chatId, "No opportunities found matching your profile. Try updating your profile or broadening your search criteria.");
        return;
    }

    foreach ($results as $opportunity) {
        $message = "🎓 Opportunity Found:\n\n";
        $message .= "📌 Title: " . ($opportunity['Title'] ?? "N/A") . "\n";
        $message .= "🌍 Country: " . ($opportunity['Country'] ?? "N/A") . "\n";
        $message .= "🏛️ University: " . ($opportunity['University'] ?? "N/A") . "\n";
        $message .= "🔬 Branch/Department: " . ($opportunity['Branch Or Department'] ?? "N/A") . "\n";
        $message .= "👨‍🏫 Supervisor: " . ($opportunity['Supervisor'] ?? "N/A") . "\n";
        $message .= "📧 Email: " . ($opportunity['Email'] ?? "N/A") . "\n";
        $message .= "📅 Application deadline: " . ($opportunity['Application deadline'] ?? "N/A") . "\n";
        $message .= "👥 Supervisors: " . ($opportunity['Supervisors'] ?? "N/A") . "\n";
        $message .= "📨 Emails: " . ($opportunity['Emails'] ?? "N/A") . "\n";
        $message .= "📧 Supervisor Emails: " . ($opportunity['Supervisor Emails'] ?? "N/A") . "\n";
        $message .= "🔬 Fields: " . ($opportunity['Fields'] ?? "N/A") . "\n";
        $message .= "ℹ️ More Websites or Contacts: " . ($opportunity['More Websites or Contacts'] ?? "N/A") . "\n";

        sendMessage($chatId, $message);
    }
}

function clearDate($data)
{
    $data = trim($data);
    $data = explode(" ",$data);
    $data[0] = null;
    $data = implode(" ",$data);
    return $data;
}
