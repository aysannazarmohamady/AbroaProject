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
            sendHelpAndSupport($chatId);
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
            handleGlobalSearch($chatId, $userId,$message);
            break;
        default:
            handleProfileData($chatId, $message, $userId);
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

function handleGlobalSearch($chatId, $userId,$message) {
    requestGlobalSearchKeyword($chatId, $userId,$message);
}

function requestGlobalSearchKeyword($chatId, $userId,$message) {
    sendMessage($chatId, "Please enter a keyword for the global search:");
    $data = loadData();
    $data[$userId]['step'] = 'global_search';
    saveData($data);
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
            $message = "
**Subject Options:**

1. Application for PhD Position in [Specific Field]
2. Inquiry About PhD Supervision in [Specific Research Area]
3. Interest in PhD Research Under Your Supervision
4. Potential PhD Research Collaboration

**Body:**

Dear Professor [Supervisor's Last Name],

My name is [Your Full Name], and I am currently [Your Current Position/Status] at [Your Current Institution or Workplace]. I am writing to express my interest in pursuing a PhD under your supervision in the field of [Field of Study].

I have a strong background in [Your Relevant Experience or Education], where I [briefly describe your relevant experience or education]. I am particularly interested in your work on [Mention Specific Research or Publications of the Supervisor] and would like to contribute to this area through my research.

Attached to this email, you will find my resume and a brief research proposal that outlines my ideas and how they align with your current research projects. I would be honored to discuss this opportunity further and explore how my background and interests could contribute to your team.

Thank you for considering my application. I look forward to the possibility of working with you and contributing to your research.

Sincerely,

[Your Full Name]  
[Your Contact Information]  
[Your LinkedIn Profile, if applicable]  
[Attachment: Resume, Research Proposal]";
            sendMessage($chatId, $message);
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
        $result = searchSupervisors($chatId,$userId,$message);
        sendMessage($chatId, $result);
        unset($data[$userId]['step']);
        saveData($data);
        sendMainMenu($chatId);
        return;
    }
    if ($data[$userId]['step'] == 'global_search') {
        $keyword = $message;
        sendMessage($chatId, "Searching globally for: $keyword");
        searchGlobal($chatId, $userId,$message);
        unset($data[$userId]['step']);
        saveData($data);
        sendMainMenu($chatId);
        return;
    }
    
    switch ($data[$userId]['step']) {
        case 1:
            $data[$userId]['email'] = $message;
            $data[$userId]['step'] = 2;
            sendMessage($chatId, "Please enter your phone number:");
            break;
        case 2:
            $data[$userId]['phone'] = $message;
            $data[$userId]['step'] = 3;
            sendMessage($chatId, "Please enter your country of residence:");
            break;
        case 3:
            $data[$userId]['residence'] = $message;
            $data[$userId]['step'] = 4;
            sendMessage($chatId, "Do you have a language certificate? (Yes/No)");
            break;
        case 4:
            $data[$userId]['language_certificate'] = $message;
            $data[$userId]['step'] = 5;
            sendFieldOfStudyKeyboard($chatId);
            break;
        case 5:
            if ($message == "🌍 Other") {
                sendMessage($chatId, "Please enter your preferred field of study:");
                $data[$userId]['step'] = 5.1;
            } else {
                $data[$userId]['field'] = $message;
                $data[$userId]['step'] = 6;
                sendCountryKeyboard($chatId);
            }
            break;
        case 5.1:
            $data[$userId]['field'] = $message;
            $data[$userId]['step'] = 6;
            sendCountryKeyboard($chatId);
            break;
        case 6:
            if ($message == "🌍 Other") {
                sendMessage($chatId, "Please enter your favorite country manually:");
                $data[$userId]['step'] = 6.1;
            } else {
                $data[$userId]['country'] = $message;
                $data[$userId]['step'] = 7;
                sendEducationLevelKeyboard($chatId);
            }
            break;
        case 6.1:
            $data[$userId]['country'] = $message;
            $data[$userId]['step'] = 7;
            sendEducationLevelKeyboard($chatId);
            break;
case 7:
            $data[$userId]['education_level'] = $message;
            $data[$userId]['step'] = 8;
            sendCVUploadOption($chatId);
            break;
        case 8:
            if ($message == "Yes") {
                sendMessage($chatId, "Please upload your CV file.");
                $data[$userId]['step'] = 9;
            } elseif ($message == "No") {
                unset($data[$userId]['step']);
                saveData($data);
                sendMessage($chatId, "Your profile has been updated.");
                sendMainMenu($chatId);
            }
            break;
        case 9:
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
        case 'edit_email':
            $data[$userId]['email'] = $message;
            sendMessage($chatId, "Your email has been updated.");
            sendEditProfileMenu($chatId, $userId);
            break;
        case 'edit_phone':
            $data[$userId]['phone'] = $message;
            sendMessage($chatId, "Your phone number has been updated.");
            sendEditProfileMenu($chatId, $userId);
            break;
        case 'edit_residence':
            $data[$userId]['residence'] = $message;
            sendMessage($chatId, "Your country of residence has been updated.");
            sendEditProfileMenu($chatId, $userId);
            break;
        case 'edit_language_certificate':
            $data[$userId]['language_certificate'] = $message;
            sendMessage($chatId, "Your language certificate status has been updated.");
            sendEditProfileMenu($chatId, $userId);
            break;
        case 'edit_field':
            $data[$userId]['field'] = $message;
            sendMessage($chatId, "Your field of study has been updated.");
            sendEditProfileMenu($chatId, $userId);
            break;
        case 'edit_country':
            $data[$userId]['country'] = $message;
            sendMessage($chatId, "Your preferred country has been updated.");
            sendEditProfileMenu($chatId, $userId);
            break;
        case 'edit_education_level':
            $data[$userId]['education_level'] = $message;
            sendMessage($chatId, "Your education level has been updated.");
            sendEditProfileMenu($chatId, $userId);
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
            [["text" => "🇬🇧 UK"], ["text" => "🇩🇪 Germany"]],
            [["text" => "🇨🇦 Canada"], ["text" => "🇦🇺 Australia"]],
            [["text" => "🇳🇿 New Zealand"], ["text" => "🇫🇷 France"]],
            [["text" => "🇮🇪 Ireland"], ["text" => "🇺🇸 USA"]],
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

function viewEditProfile($chatId, $userId) {
    $data = loadData();
    if (isset($data[$userId])) {
        sendEditProfileMenu($chatId, $userId);
    } else {
        sendMessage($chatId, "You haven't created a profile yet. Select 'User Profile' from the main menu to create one.");
    }
}

function sendEditProfileMenu($chatId, $userId) {
    $data = loadData();
    $profile = $data[$userId];
    
    $keyboard = [
        "inline_keyboard" => [
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
    $message .= "📧 <b>Email:</b> " . ($profile['email'] ?? "Not set") . "\n";
    $message .= "📱 <b>Phone:</b> " . ($profile['phone'] ?? "Not set") . "\n";
    $message .= "🏠 <b>Country of Residence:</b> " . ($profile['residence'] ?? "Not set") . "\n";
    $message .= "🗣️ <b>Language Certificate:</b> " . ($profile['language_certificate'] ?? "Not set") . "\n";
    $message .= "🎓 <b>Field of Study:</b> " . ($profile['field'] ?? "Not set") . "\n";
    $message .= "🌍 <b>Preferred Country:</b> " . ($profile['country'] ?? "Not set") . "\n";
    $message .= "📚 <b>Education Level:</b> " . ($profile['education_level'] ?? "Not set") . "\n";
    $message .= "📄 <b>CV:</b> " . (isset($profile['cv_file_id']) ? "Uploaded" : "Not uploaded") . "\n\n";
    $message .= "Select a field to edit:";

    sendMessage($chatId, $message, $keyboard, true);
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
    sendMessage($chatId, $result);
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
    sendMessage($chatId, $result);
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
                if (json_last_error() === JSON_ERROR_NONE && is_array($links) && !empty($links)) {$output .= "• <a href='" . htmlspecialchars($links[0], ENT_QUOTES) . "'>$name</a>\n";
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
        sendMessage($chatId, "No opportunities found matching your profile. Try updating your profile or broadening your search criteria.");
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
    $message = "Welcome to the Help and Support section!\n\n"
             . "This bot helps you search for academic opportunities and manage your profile. Here's a quick guide:\n\n"
             . "🔍 Search Opportunities: Find opportunities based on your profile or perform a new search.\n"
             . "👤 User Profile: Set up or edit your personal information.\n"
             . "🤖 AI Assistant: Get help with resume writing, cover letters, and emails.\n"
             . "📋 View/Edit Profile: Review or update your existing profile.\n"
             . "🧑‍🏫 Search Supervisors: Find potential supervisors based on your research interests.\n\n"
             . "We're here to help you in your academic journey!";

    $keyboard = [
        "inline_keyboard" => [
            [["text" => "Contact Support", "url" => "https://t.me/Aysan_dev"]]
        ]
    ];

    sendMessage($chatId, $message, $keyboard);
}

// Handle callback queries for inline keyboards
if (isset($updateArray['callback_query'])) {
    $callbackQuery = $updateArray['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $userId = $callbackQuery['from']['id'];
    $data = $callbackQuery['data'];

    switch ($data) {
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
            $userData[$userId]['step'] = 9;  // Reuse the CV upload step
            saveData($userData);
            break;
        case 'back_to_main':
            sendMainMenu($chatId);
            break;
    }
}
?>
