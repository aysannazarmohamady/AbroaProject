<?php
header('Content-Type: application/json');

$dataFile = "user_data.json";

if (!file_exists($dataFile)) {
    echo json_encode(['error' => 'Data file not found']);
    exit;
}

$jsonData = file_get_contents($dataFile);
$data = json_decode($jsonData, true);

$totalUsers = count($data);
$activeProfiles = 0;
$cvUploads = 0;
$countryDistribution = [];
$fieldOfStudyDistribution = [];
$educationLevelDistribution = [];
$userInfo = [];

foreach ($data as $userId => $userProfile) {
    if (!empty($userProfile['first_name']) && !empty($userProfile['email'])) {
        $activeProfiles++;
    }
    if (isset($userProfile['cv_file_id'])) {
        $cvUploads++;
    }
    if (isset($userProfile['country'])) {
        $countryDistribution[$userProfile['country']] = ($countryDistribution[$userProfile['country']] ?? 0) + 1;
    }
    if (isset($userProfile['field'])) {
        $fieldOfStudyDistribution[$userProfile['field']] = ($fieldOfStudyDistribution[$userProfile['field']] ?? 0) + 1;
    }
    if (isset($userProfile['education_level'])) {
        $educationLevelDistribution[$userProfile['education_level']] = ($educationLevelDistribution[$userProfile['education_level']] ?? 0) + 1;
    }

    $userInfo[$userId] = [
        'first_name' => $userProfile['first_name'] ?? '',
        'last_name' => $userProfile['last_name'] ?? '',
        'email' => $userProfile['email'] ?? '',
        'phone' => $userProfile['phone'] ?? '',
        'residence' => $userProfile['residence'] ?? '',
        'language_certificate' => $userProfile['language_certificate'] ?? '',
        'field' => $userProfile['field'] ?? '',
        'country' => $userProfile['country'] ?? '',
        'education_level' => $userProfile['education_level'] ?? ''
    ];
}

// Calculate new users in the last 30 days
$newUsers = 0;
$thirtyDaysAgo = strtotime('-30 days');
foreach ($data as $userProfile) {
    if (isset($userProfile['registration_date'])) {
        $registrationDate = strtotime($userProfile['registration_date']);
        if ($registrationDate >= $thirtyDaysAgo) {
            $newUsers++;
        }
    }
}

$response = [
    'totalUsers' => $totalUsers,
    'activeProfiles' => $activeProfiles,
    'newUsers' => $newUsers,
    'cvUploads' => $cvUploads,
    'countryDistribution' => $countryDistribution,
    'fieldOfStudyDistribution' => $fieldOfStudyDistribution,
    'educationLevelDistribution' => $educationLevelDistribution,
    'userInfo' => $userInfo
];

echo json_encode($response);