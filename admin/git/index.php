<?php
require '../common2.php';
if (!is_admin()) {
    header('Location: ../index.php');
    exit;
}

if($nframework->is_ajax()){
    $action = $_POST['action'] ?? '';
    if ($action === 'pull') {
        // Execute git pull command
        $output = [];
        $return_var = 0;
        exec('git pull 2>&1', $output, $return_var);
        if ($return_var === 0) {
            echo json_encode(['success' => true, 'message' => implode("\n", $output)]);
        } else {
            echo json_encode(['success' => false, 'message' => implode("\n", $output)]);
        }
    }
    exit;
}

?>
<div class="container">
    <div class="box shadow-large">
        <div class="box-title">Git Repository Management</div>
        <p>To update the codebase, you can use the following commands:</p>
        <button class="button op ">Pull Latest Changes</button> 
        
    </div>
</div>