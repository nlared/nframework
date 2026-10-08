<?php
require '../common2.php';

if($nframework->isAjax()){
    $action = $_POST['action'] ?? '';
    if ($action === 'pull') {
        $output = [];
        $return_var = 0;
        exec('git -C ' . escapeshellarg(dirname(__DIR__, 2)) . ' pull 2>&1', $output, $return_var);
        $result = ['success' => $return_var === 0, 'message' => implode("\n", $output)];
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