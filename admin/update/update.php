<?php
// Define the repository details
$repoOwner = 'nlared';
$repoName = 'nframework5';
$branch = 'master'; // Replace with the branch name you want to update

// Define your GitHub Personal Access Token
$accessToken = 'YOUR_PERSONAL_ACCESS_TOKEN';

// GitHub API URL for the repository's latest commit
$apiUrl = "https://api.github.com/repos/$repoOwner/$repoName/commits/$branch";

// cURL initialization
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: token $accessToken",
    "User-Agent: PHP Script"
));

// Execute the API request
$response = curl_exec($ch);
curl_close($ch);

// Decode the JSON response
$commitData = json_decode($response, true);

// Extract the latest commit hash
$latestCommit = $commitData['sha'];

// Command to update the repository
$updateCommand = "cd ".substr($nframework->include_path0,-9)." && git fetch && git reset --hard $latestCommit";

// Execute the update command
exec($updateCommand, $output, $return_var);

// Output the result
if ($return_var === 0) {
    echo "Repository updated successfully to commit $latestCommit.";
} else {
    echo "Failed to update repository.";
}
?>
