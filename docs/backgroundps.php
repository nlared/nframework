<?php
include 'common.php';

// Define the background process
// We use a simple PHP script that prints progress for 20 seconds.
$processParams = [
    'id' => 'demo_process',
    'cmd' => 'php ' . __DIR__ . '/tmp/long_process.php',
    'logfile' => __DIR__ . '/tmp/demo_process.log'
];

$bg = new bgprocess($processParams);

?>
<div class="container">
    <h1>Background Process Example</h1>
    <p>This example demonstrates how to use the <code>bgprocess</code> class to manage background tasks.</p>

    <h3>Code Usage</h3>
    <pre class="stay-on"><code class="language-php">
$processParams = [
    'id' => 'demo_process',
    'cmd' => 'php ' . __DIR__ . '/tmp/long_process.php',
    'logfile' => __DIR__ . '/tmp/demo_process.log'
];

$bg = new bgprocess($processParams);

// Render the dashboard control
echo $bg->renderDashboard();
    </code></pre>

    <h3>Live Dashboard</h3>
    <div class="p-4 border bd-default">
        <?php echo $bg->renderDashboard(); ?>
    </div>

    <div class="mt-4">
        <h5>About the Demo Process</h5>
        <p>The background process runs a simple PHP script that counts to 20, sleeping for 1 second between each count.
            You can see the output in the log file (status) when you start it.</p>
    </div>
</div>