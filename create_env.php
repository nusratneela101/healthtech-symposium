<?php

function createEnvFile($data) {
    $filename = '.env';
    $backupFilename = '.env.bak';

    // Create a backup of the existing .env file
    if (file_exists($filename)) {
        copy($filename, $backupFilename);
    }

    // Create a new .env file with the provided data
    file_put_contents($filename, http_build_query($data, '', '\n'));
    chmod($filename, 0600); // Set permissions to read/write for owner only
}

// Function to validate form data
function validateFormData($data) {
    $errors = [];
    if (empty($data['db_host'])) { $errors[] = 'Database host is required.'; }
    if (empty($data['db_name'])) { $errors[] = 'Database name is required.'; }
    if (empty($data['db_user'])) { $errors[] = 'Database user is required.'; }
    if (empty($data['db_pass'])) { $errors[] = 'Database password is required.'; }
    // Add more validation as needed
    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = $_POST;
    $validationErrors = validateFormData($formData);
    
    if (empty($validationErrors)) {
        createEnvFile($formData);
        echo '<div>Success! Your .env file has been created.</div>';
        echo '<div>Your n8n key: ' . $formData['n8n_key'] . '</div>';
        echo '<div id="countdown">10</div>';
        echo '<script>
            let countdown = 10;
            const countdownInterval = setInterval(() => {
                countdown--;
                document.getElementById("countdown").innerText = countdown;
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = "installer.php";
                }
            }, 1000);
        </script>';
    } else {
        foreach ($validationErrors as $error) {
            echo '<div>' . $error . '</div>';
        }
    }
}
?>
<style>
    body { background: linear-gradient(to right, #6a11cb, #2575fc); color: white; font-family: Arial, sans-serif; }
    /* Add other styles as needed for UI */
</style>
<form id="config-form" method="POST" action="" onsubmit="return validateForm();">
    <label for="db_host">Database Host <span>*</span></label>
    <input type="text" name="db_host" required>

    <label for="db_name">Database Name <span>*</span></label>
    <input type="text" name="db_name" required>

    <label for="db_user">Database User <span>*</span></label>
    <input type="text" name="db_user" required>

    <label for="db_pass">Database Password <span>*</span></label>
    <input type="password" name="db_pass" required>

    <label for="smtp_host">SMTP Host</label>
    <input type="text" name="smtp_host">

    <label for="smtp_port">SMTP Port</label>
    <input type="text" name="smtp_port">

    <label for="smtp_user">SMTP User</label>
    <input type="text" name="smtp_user">

    <label for="smtp_pass">SMTP Password</label>
    <input type="password" name="smtp_pass">

    <label for="api_key">API Key</label>
    <input type="text" name="api_key">

    <label for="from_email">From Email</label>
    <input type="email" name="from_email">

    <label for="n8n_key">n8n Key</label>
    <input type="text" name="n8n_key">

    <input type="submit" value="Submit">
</form>
<script>
    function validateForm() {
        // Add your form validation logic here
        return true;
    }
</script>