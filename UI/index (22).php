<?php

$token = "7246212096:AAEKY1RJSrLbYKPCV_cBteJ-7cBtNr5drec";
$apiUrl = "https://api.telegram.org/bot$token/";
$channelUsername = "@JetApply";
$dataFile = "user_data.json";

// Check if data file is writable
if (!is_writable($dataFile)) {
    error_log("Data file is not writable: $dataFile");
}

$update = file_get_contents("php://input");
$updateArray = json_decode($update, true);

if (isset($updateArray['message'])) {
    $chatId = $updateArray['message']['chat']['id'];
    $message = $updateArray['message']['text'];
    $userId = $updateArray['message']['from']['id'];

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
        case "❓ Help and Support":
            sendHelpAndSupport($chatId);
            break;
        case "📋 View/Edit Profile":
            sendEditProfileMenu($chatId, $userId);
            break;
        case "🔙 Back to Main Menu":
            sendMainMenu($chatId);
            break;
        case "🧑‍🏫 Search Supervisors":
            handleSearchSupervisors($chatId);
            break;
        case "📅 Latest opportunities":
            handleLatestOpportunities($chatId);
            break;
        case "🌍 Global Search":
            handleGlobalSearch($chatId, $userId, $message);
            break;
        default:
            handleProfileData($chatId, $message, $userId, $updateArray);
            break;
    }
}

function sendMessage($chatId, $message, $keyboard = null, $parseHtml = false) {
    global $apiUrl;
    $url = $apiUrl . "sendMessage?chat_id=" . $chatId . "&text=" . urlencode($message);
    
    if ($keyboard) {
        $url .= "&reply_markup=" . urlencode(json_encode($keyboard));
    }
    
    if ($parseHtml) {
        $url .= "&parse_mode=HTML";
    }
    
    file_get_contents($url);
}

function sendMainMenu($chatId) {
    $keyboard = [
        "keyboard" => [
            [["text" => "🔍 Search Opportunities"], ["text" => "👤 User Profile"]],
            [["text" => "❓ Help and Support"]],
            [["text" => "📋 View/Edit Profile"], ["text" => "🧑‍🏫 Search Supervisors"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "Hello! Welcome to the Abroadin bot! 🌟

We're here to support you on your academic journey. Please choose an option from the main menu and let's get started together! It is better to complete your user profile first from the «User Profile» section. 😊", $keyboard);
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
            [["text" => "🔙 Back to Main Menu"]]
        ],
        "resize_keyboard" => true
    ];
    sendMessage($chatId, "Please choose a search option:", $keyboard);
}

function handleLatestOpportunities($chatId) {
    sendMessage($chatId, "Searching for the latest opportunities...");
    LatestPosts($chatId);
}

function handleGlobalSearch($chatId, $userId, $message) {
    requestGlobalSearchKeyword($chatId, $userId, $message);
}

function requestGlobalSearchKeyword($chatId, $userId, $message) {
    sendMessage($chatId, "Please enter a keyword for the global search:");
    $data = loadData();
    $data[$userId]['step'] = 'global_search';
    saveData($data);
}

function requestUserProfile($chatId, $userId) {
    sendMessage($chatId, "Please enter your first name:");
    $data = loadData();
    $data[$userId]['step'] = 1;
    saveData($data);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhoneNumber($phone) {
    return preg_match('/^\+?[1-9]\d{1,14}$/', $phone);
}

function handleProfileData($chatId, $message, $userId, $updateArray = null) {
    $data = loadData();
    if (!isset($data[$userId]) || !isset($data[$userId]['step'])) {
        return;
    }
    
    if ($data[$userId]['step'] == 'search_supervisors') {
        $result = searchSupervisors($chatId, $userId, $message);
        sendMessage($chatId, $result);
        unset($data[$userId]['step']);
        saveData($data);
        sendMainMenu($chatId);
        return;
    }
    if ($data[$userId]['step'] == 'global_search') {
        $keyword = $message;
        sendMessage($chatId, "Searching globally for: $keyword");
        searchGlobal($chatId, $userId, $message);
        unset($data[$userId]['step']);
        saveData($data);
        sendMainMenu($chatId);
        return;
    }
    
    // Handle profile editing
    if (strpos($data[$userId]['step'], 'edit_') === 0) {
        $field = str_replace('edit_', '', $data[$userId]['step']);
        $updateSuccess = updateUserProfile($userId, $field, $message);
        if ($updateSuccess) {
            sendMessage($chatId, "Your $field has been updated successfully.");
            // Reload data after update
            $data = loadData();
        } else {
            sendMessage($chatId, "There was an error updating your $field. Please try again.");
        }
        unset($data[$userId]['step']);
        saveData($data);
        sendEditProfileMenu($chatId, $userId);
        return;
    }
    
    switch ($data[$userId]['step']) {
        case 1:
            if (empty($message)) {
                sendMessage($chatId, "First name cannot be empty. Please enter your first name:");
                return;
            }
            $data[$userId]['first_name'] = $message;
            $data[$userId]['step'] = 2;
            sendMessage($chatId, "Please enter your last name:");
            break;
        case 2:
            if (empty($message)) {
                sendMessage($chatId, "Last name cannot be empty. Please enter your last name:");
                return;
            }
            $data[$userId]['last_name'] = $message;
            $data[$userId]['step'] = 3;
            sendMessage($chatId, "Please enter your email:");
            break;
        case 3:
            if (!validateEmail($message)) {
                sendMessage($chatId, "Invalid email format. Please enter a valid email address:");
                return;
            }
            $data[$userId]['email'] = $message;
            $data[$userId]['step'] = 4;
            sendMessage($chatId, "Please enter your phone number (with country code, e.g. +1234567890):");
            break;
        case 4:
            if (!validatePhoneNumber($message)) {
                sendMessage($chatId, "Invalid phone number format. Please enter a valid phone number with country code:");
                return;
            }
            $data[$userId]['phone'] = $message;
            $data[$userId]['step'] = 5;
            sendMessage($chatId, "Please enter your country of residence:");
            break;
        case 5:
            if (empty($message)) {
                sendMessage($chatId, "Country of residence cannot be empty. Please enter your country of residence:");
                return;
            }
            $data[$userId]['residence'] = $message;
            $data[$userId]['step'] = 6;
            sendMessage($chatId, "Do you have a language certificate? (Yes/No)");
            break;
        case 6:
            if ($message != "Yes" && $message != "No") {
                sendMessage($chatId, "Please answer with 'Yes' or 'No'. Do you have a language certificate?");
                return;
            }
            $data[$userId]['language_certificate'] = $message;
            $data[$userId]['step'] = 7;
            sendFieldOfStudyKeyboard($chatId);
            break;
        case 7:
            if ($message == "🌍 Other") {
                sendMessage($chatId, "Please enter your preferred field of study:");
                $data[$userId]['step'] = 7.1;
            } else {
                $data[$userId]['field'] = $message;
                $data[$userId]['step'] = 8;
                sendCountryKeyboard($chatId);
            }
            break;
        case 7.1:
            if (empty($message)) {
                sendMessage($chatId, "Field of study cannot be empty. Please enter your preferred field of study:");
                return;
            }
            $data[$userId]['field'] = $message;
            $data[$userId]['step'] = 8;
            sendCountryKeyboard($chatId);
            break;
        case 8:
            if ($message == "🌍 Other") {
                sendMessage($chatId, "Please enter your favorite country manually:");
                $data[$userId]['step'] = 8.1;
            } else {
                $data[$userId]['country'] = $message;
                $data[$userId]['step'] = 9;
                sendEducationLevelKeyboard($chatId);
            }
            break;
        case 8.1:
            if (empty($message)) {
                sendMessage($chatId, "Country cannot be empty. Please enter your favorite country:");
                return;
            }
            $data[$userId]['country'] = $message;
            $data[$userId]['step'] = 9;
            sendEducationLevelKeyboard($chatId);
            break;
        case 9:
            if (empty($message)) {
                sendMessage($chatId, "Education level cannot be empty. Please choose your preferred education level:");
                return;
            }
            $data[$userId]['education_level'] = $message;
            $data[$userId]['step'] = 10;
            sendCVUploadOption($chatId);
            break;
        case 10:
            if ($message == "Yes") {
                sendMessage($chatId, "Please upload your CV file.");
                $data[$userId]['step'] = 11;
            } elseif ($message == "No") {
                unset($data[$userId]['step']);
                saveData($data);
                sendMessage($chatId, "Your profile has been updated.");
                sendMainMenu($chatId);
            } else {
                sendMessage($chatId, "Please answer with 'Yes' or 'No'. Would you like to upload your CV?");
                return;
            }
            break;
        case 11:
            if ($updateArray !== null && isset($updateArray['message']['document'])) {
                $document = $updateArray['message']['document'];
                $fileId = $document['file_id'];
                $fileName = $document['file_name'];
                $mimeType = $document['mime_type'];
                
                if ($mimeType === 'application/pdf' || strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'pdf') {
                    $data[$userId]['cv_file_id'] = $fileId;
                    unset($data[$userId]['step']);
                    saveData($data);
                    sendMessage($chatId, "Your CV has been successfully uploaded and your profile has been updated.");
                    sendMainMenu($chatId);
                } else {
                    sendMessage($chatId, "Please upload a PDF file for your CV.");
                }
            } else {
                sendMessage($chatId, "Please upload a PDF file for your CV.");
            }
            break;
    }
    saveData($data);
}

// Handle callback queries for inline keyboards
if (isset($updateArray['callback_query'])) {
    $callbackQuery = $updateArray['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $userId = $callbackQuery['from']['id'];
    $data = $callbackQuery['data'];

    switch ($data) {
        case 'edit_first_name':
        case 'edit_last_name':
        case 'edit_email':
        case 'edit_phone':
        case 'edit_residence':
        case 'edit_language_certificate':
        case 'edit_field':
        case 'edit_country':
        case 'edit_education_level':
            $fieldName = str_replace('edit_', '', $data);
            sendMessage($chatId, "Please enter your new $fieldName:");
            $userData = loadData();
            $userData[$userId]['step'] = $data;
            saveData($userData);
            break;
        case 'upload_cv':
            sendMessage($chatId, "Please upload your new CV file.");
            $userData = loadData();
            $userData[$userId]['step'] = 11;  // Reuse the CV upload step
            saveData($userData);
            break;
        case 'back_to_main':
            sendMainMenu($chatId);
            break;
    }
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
            [["text" => "🇬🇧 United Kingdom"], ["text" => "🇩🇪 Germany"]],
            [["text" => "🇨🇦 Canada"], ["text" => "🇦🇺 Australia"]],
            [["text" => "🇳🇿 New Zealand"], ["text" => "🇫🇷 France"]],
            [["text" => "🇮🇪 Ireland"], ["text" => "🇺🇸 United States"]],
            [["text" => "🇳🇱 Netherlands"], ["text" => "🇨🇭 Switzerland"]],
            [["text" => "🇸🇪 Sweden"], ["text" => "🇳🇴 Norway"]],
            [["text" => "🇩🇰 Denmark"], ["text" => "🇫🇮 Finland"]],
            [["text" => "🇮🇹 Italy"], ["text" => "🇪🇸 Spain"]],
            [["text" => "🇵🇹 Portugal"], ["text" => "🇦🇹 Austria"]],
            [["text" => "🇧🇪 Belgium"], ["text" => "🇯🇵 Japan"]],
            [["text" => "🇸🇬 Singapore"], ["text" => "🇰🇷 South Korea"]],
            [["text" => "🇨🇳 China"], ["text" => "🇮🇳 India"]],
            [["text" => "🌍 Other"]]
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
    sendMessage($chatId, "⚠️ Currently, only PhD opportunities are available!");
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

function sendEditProfileMenu($chatId, $userId) {
    $data = loadData();
    $profile = $data[$userId];
    
    // Add log for debugging
    error_log("Profile data for user $userId: " . json_encode($profile));
    
    $keyboard = [
        "inline_keyboard" => [
            [["text" => "Edit First Name", "callback_data" => "edit_first_name"]],
            [["text" => "Edit Last Name", "callback_data" => "edit_last_name"]],
            [["text" => "Edit Email", "callback_data" => "edit_email"]],
            [["text" => "Edit Phone", "callback_data" => "edit_phone"]],
            [["text" => "Edit Country of Residence", "callback_data" => "edit_residence"]],
            [["text" => "Edit Language Certificate", "callback_data" => "edit_language_certificate"]],
            [["text" => "Edit Field of Study", "callback_data" => "edit_field"]],
            [["text" => "Edit Preferred Country", "callback_data" => "edit_country"]],
            [["text" => "Edit Education Level", "callback_data" => "edit_education_level"]],
            [["text" => "Upload New CV", "callback_data" => "upload_cv"]],
            [["text" => "Back to Main Menu", "callback_data" => "back_to_main"]]
        ]
    ];

    $message = "<b>Your Profile</b>\n\n";
    $message .= "👤 <b>First Name:</b> " . htmlspecialchars($profile['first_name'] ?? "Not set") . "\n";
    $message .= "👤 <b>Last Name:</b> " . htmlspecialchars($profile['last_name'] ?? "Not set") . "\n";
    $message .= "📧 <b>Email:</b> " . htmlspecialchars($profile['email'] ?? "Not set") . "\n";
    $message .= "📱 <b>Phone:</b> " . htmlspecialchars($profile['phone'] ?? "Not set") . "\n";
    $message .= "🏠 <b>Country of Residence:</b> " . htmlspecialchars($profile['residence'] ?? "Not set") . "\n";
    $message .= "🗣️ <b>Language Certificate:</b> " . htmlspecialchars($profile['language_certificate'] ?? "Not set") . "\n";
    $message .= "🎓 <b>Field of Study:</b> " . htmlspecialchars($profile['field'] ?? "Not set") . "\n";
    $message .= "🌍 <b>Preferred Country:</b> " . htmlspecialchars($profile['country'] ?? "Not set") . "\n";
    $message .= "📚 <b>Education Level:</b> " . htmlspecialchars($profile['education_level'] ?? "Not set") . "\n";
    $message .= "📄 <b>CV:</b> " . (isset($profile['cv_file_id']) ? "Uploaded" : "Not uploaded") . "\n\n";
    $message .= "Select a field to edit:";

    sendMessage($chatId, $message, $keyboard, true);
}

function updateUserProfile($userId, $field, $value) {
    $data = loadData();
    if (isset($data[$userId])) {
        $data[$userId][$field] = $value;
        saveData($data);
        // Add log for debugging
        error_log("Updated profile for user $userId: $field = $value");
        return true;
    }
    return false;
}

function loadData() {
    global $dataFile;
    if (!file_exists($dataFile)) {
        return [];
    }
    $json = file_get_contents($dataFile);
    return json_decode($json, true) ?: [];
}

function saveData($data) {
    global $dataFile;
    $json = json_encode($data);
    $result = file_put_contents($dataFile, $json);
    // Add log for debugging
    error_log("Saving data: " . ($result !== false ? "Success" : "Failed"));
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
    sendMessage($chatId, "To see a list of supervisors, please enter a keyword related to your research interest:");
    
    $data = loadData();
    $data[$chatId]['step'] = 'search_supervisors';
    saveData($data);
}

function searchSupervisors($chatId, $userId, $message) {
    $url = 'https://jet.aysan.dev/api_v2.php';
    $data = array(
        'action' => 'all',
        'keywords' => $message
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
        sendSearchResultsSupervisor($chatId, $response);
    }
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
    $url = 'https://jet.aysan.dev/api_v2.php';
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

function searchGlobal($chatId, $userId, $message) {
    $url = 'https://jet.aysan.dev/api_v2.php';
    $data = array(
        'action' => 'all',
        'keywords' => $message
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

    foreach ($results as $researcher) {
        $output = "<b>🎓 Full Information of The Position</b>\n\n";

        if (!empty($researcher['title'])) {
            $output .= "👤 <b>" . htmlspecialchars($researcher['title'], ENT_NOQUOTES) . "</b>\n\n";
        }

        $fields = [
            'level' => '🏅 Level: ',
            'country' => '🌍 Country: ',
            'university' => '🏛 University: ',
            'branch' => '🔬 Branch: '
        ];

        foreach ($fields as $field => $label) {
            if (!empty($researcher[$field])) {
                $output .= $label . htmlspecialchars($researcher[$field], ENT_NOQUOTES) . "\n";
            }
        }

        $output .= "\n";

        if (!empty($researcher['overview'])) {
            $output .= "📝 <b>Overview:</b>\n" . htmlspecialchars($researcher['overview'], ENT_NOQUOTES) . "\n\n";
        }

        $jsonFields = [
            'supervisors' => '👨‍🏫 <b>Supervisor(s):</b>',
            'tags' => '🏷 <b>Related Fields:</b>',
            'email' => '📧 <b>Email(s):</b>'
        ];

        foreach ($jsonFields as $field => $label) {
            if (!empty($researcher[$field])) {
                $cleaned_string = stripslashes($researcher[$field]);
                $items = json_decode($cleaned_string, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($items) && !empty($items)) {
                    $output .= "$label\n";
                    foreach ($items as $item) {
                        $item = str_replace("Supervisors: ","",trim($item));
                        $output .= "• " . htmlspecialchars(trim($item), ENT_NOQUOTES) . "\n";
                    }
                    $output .= "\n";
                }
            }
        }

        $output .= "🔗 <b>Online Profiles:</b>\n";
        $profiles = [
            'linkedin_link' => 'LinkedIn Supervisor Page',
            'scholar_link' => 'Google Scholar Supervisor Page',
            'researchgate_link' => 'ResearchGate Supervisor Page',
        ];

        foreach ($profiles as $key => $name) {
            if (!empty($researcher[$key])) {
                $cleaned_string = stripslashes($researcher[$key]);
                $links = json_decode($cleaned_string, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($links) && !empty($links)) {
                    $output .= "• <a href='" . htmlspecialchars($links[0], ENT_QUOTES) . "'>$name</a>\n";
                    $output .= "\n";
                }
            }
        }

        if (!empty($researcher['institution_link'])) {
            $output .= "• <a href='" . htmlspecialchars($researcher['institution_link'], ENT_QUOTES) . "'>🌐 <b>Institution Page</b></a>\n\n";
        }

        if (!empty($researcher['funds'])) {
            $cleaned_string = stripslashes($researcher['funds']);
            $funds = json_decode($cleaned_string, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($funds) && !empty($funds)) {
                $output .= "💰 <b>About Fund/Fee:</b>\n";
                foreach ($funds as $fund) {
                    if (!empty($fund)) {
                        $output .= "• " . htmlspecialchars(trim($fund), ENT_NOQUOTES) . "\n";
                    }
                }
                $output .= "\n";
            }
        }

        if (!empty($researcher['extra'])) {
            $cleaned_string = stripslashes($researcher['extra']);
            $extra = json_decode($cleaned_string, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($extra) && !empty($extra)) {
                $output .= "📌 <b>More Information:</b>\n";
                foreach ($extra as $info) {
                    if (!empty($info)) {
                        $output .= "• " . htmlspecialchars(trim($info), ENT_NOQUOTES) . "\n";
                    }
                }
                $output .= "\n";
            }
        }

        sendMessage($chatId, $output, null, true);
    }
}

function sendSearchResultsSupervisor($chatId, $results) {
    if (empty($results)) {
        sendMessage($chatId, "No supervisors found matching your search criteria. Try broadening your search.");
        return;
    }

    foreach ($results as $researcher) {
        $output = "<b>👨‍🏫 Supervisor Information</b>\n\n";

        if (!empty($researcher['supervisors'])) {
            $cleaned_string = stripslashes($researcher['supervisors']);
            $supervisors = json_decode($cleaned_string, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($supervisors) && !empty($supervisors)) {
                foreach ($supervisors as $supervisor) {
                    $output .= "• " . htmlspecialchars(trim($supervisor), ENT_NOQUOTES) . "\n";
                }
                $output .= "\n";
            }
        }

        $output .= "<b>🎓 Details of Open Research Position</b>\n\n";

        if (!empty($researcher['title'])) {
            $output .= "👤 <b>" . htmlspecialchars($researcher['title'], ENT_NOQUOTES) . "</b>\n\n";
        }

$fields = [
            'level' => '🏅 Level: ',
            'country' => '🌍 Country: ',
            'university' =>'🏛 University: ',
            'branch' => '🔬 Branch: '
        ];

        foreach ($fields as $field => $label) {
            if (!empty($researcher[$field])) {
                $output .= $label . htmlspecialchars($researcher[$field], ENT_NOQUOTES) . "\n";
            }
        }

        $output .= "\n";

        if (!empty($researcher['overview'])) {
            $output .= "📝 <b>Overview:</b>\n" . htmlspecialchars($researcher['overview'], ENT_NOQUOTES) . "\n\n";
        }

        $jsonFields = [
            'tags' => '🏷 <b>Tags:</b>',
            'email' => '📧 <b>Email:</b>'
        ];

        foreach ($jsonFields as $field => $label) {
            if (!empty($researcher[$field])) {
                $cleaned_string = stripslashes($researcher[$field]);
                $items = json_decode($cleaned_string, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($items) && !empty($items)) {
                    $output .= "$label\n";
                    foreach ($items as $item) {
                        $output .= "• " . htmlspecialchars(trim($item), ENT_NOQUOTES) . "\n";
                    }
                    $output .= "\n";
                }
            }
        }

        $output .= "🔗 <b>Online Profiles:</b>\n";
        $profiles = [
            'linkedin_link' => 'LinkedIn',
            'scholar_link' => 'Google Scholar',
            'researchgate_link' => 'ResearchGate',
        ];

        foreach ($profiles as $key => $name) {
            if (!empty($researcher[$key])) {
                $cleaned_string = stripslashes($researcher[$key]);
                $links = json_decode($cleaned_string, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($links) && !empty($links)) {
                    $output .= "• <a href='" . htmlspecialchars($links[0], ENT_QUOTES) . "'>$name</a>\n";
                    $output .= "\n";
                }
            }
        }

        if (!empty($researcher['institution_link'])) {
            $output .= "• <a href='" . htmlspecialchars($researcher['institution_link'], ENT_QUOTES) . "'>🌐 <b>Institution Page</b></a>\n\n";
        }

        if (!empty($researcher['funds'])) {
            $cleaned_string = stripslashes($researcher['funds']);
            $funds = json_decode($cleaned_string, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($funds) && !empty($funds)) {
                $output .= "💰 <b>Funds:</b>\n";
                foreach ($funds as $fund) {
                    if (!empty($fund)) {
                        $output .= "• " . htmlspecialchars(trim($fund), ENT_NOQUOTES) . "\n";
                    }
                }
                $output .= "\n";
            }
        }

        if (!empty($researcher['extra'])) {
            $cleaned_string = stripslashes($researcher['extra']);
            $extra = json_decode($cleaned_string, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($extra) && !empty($extra)) {
                $output .= "📌 <b>More Information:</b>\n";
                foreach ($extra as $info) {
                    if (!empty($info)) {
                        $output .= "• " . htmlspecialchars(trim($info), ENT_NOQUOTES) . "\n";
                    }
                }
                $output .= "\n";
            }
        }

        sendMessage($chatId, $output, null, true);
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

function LatestPosts($chatId)
{
    $url = 'https://jet.aysan.dev/api_v2.php';
    $data = array(
        'action' => 'getLatestPosts'
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

function sendHelpAndSupport($chatId) {
    $message = "Welcome to the Help and Support section of the Abroadin Bot!

Our mission is to make your journey to find academic opportunities and connect with potential supervisors as seamless as possible. Here's a quick guide to get you started:

🔍 Search Opportunities: Discover academic programs and positions tailored to your profile or start a new search based on your preferences.
👤 User Profile: Set up or update your personal information to get personalized recommendations.
📋 View/Edit Profile: Review or make changes to your existing profile to keep your information up-to-date.
🧑‍🏫 Search Supervisors: Find potential supervisors aligned with your research interests to advance your academic career.

We're here to support you in your academic journey and help you achieve your goals. If you have any questions or need further assistance, feel free to reach out.";

    $keyboard = [
        "inline_keyboard" => [
            [["text" => "Contact Support", "url" => "https://zil.ink/abroadin"]]
        ]
    ];

    sendMessage($chatId, $message, $keyboard);
}

?>