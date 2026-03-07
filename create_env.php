<?php
// create_env.php

// Set the name of the .env file
$envFile = '.env';

// Write the desired environment variables to the .env file
file_put_contents($envFile, "DB_HOST=localhost\nDB_NAME=my_database\nDB_USER=my_user\nDB_PASS=my_password\n");

// Confirm the .env file has been created
if (file_exists($envFile)) {
    echo "The .env file has been created successfully!";
} else {
    echo "Failed to create the .env file.";
}

// Automatically delete the create_env.php file after the .env file is created
if (file_exists(__FILE__)) {
    unlink(__FILE__);
}