<?php
require '../common2.php';

// Define the repository details
$repoOwner = 'nlared';
$repoName = 'nframework5';
$branch = 'master'; // Replace with the branch name you want to update

// GitHub Personal Access Token (configurable en $config['github_token'])
$accessToken = $config['github_token'] ?? '';

// GitHub API URL for the repository's latest commit
$apiUrl = "https://api.github.com/repos/$repoOwner/$repoName/commits/$branch";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array_filter([
    $accessToken !== '' ? "Authorization: token $accessToken" : null,
    "User-Agent: PHP Script"
]));
$response = curl_exec($ch);
curl_close($ch);

$commitData = json_decode((string) $response, true);
$latestCommit = $commitData['sha'] ?? '';

// El hash se valida antes de usarlo en un comando de shell.
if (!preg_match('/^[0-9a-f]{40}$/', $latestCommit)) {
    echo "Failed to get latest commit.";
    return;
}

$updateCommand = 'cd ' . escapeshellarg(dirname(__DIR__, 2)) . ' && git fetch && git reset --hard ' . escapeshellarg($latestCommit);
exec($updateCommand, $output, $return_var);

if ($return_var === 0) {
    echo "Repository updated successfully to commit $latestCommit.";
} else {
    echo "Failed to update repository.";
}
