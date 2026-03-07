<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>.env Creator</title>
    <style>
        body {
            background: linear-gradient(to right, #a4508b, #5d3f7e);
            font-family: Arial, sans-serif;
            color: white;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        form {
            background: rgba(0, 0, 0, 0.5);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            width: 400px;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: none;
            border-radius: 5px;
        }
        button {
            background: #5d3f7e;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background: #4b2f5e;
        }
        #countdown {
            margin-top: 20px;
            font-size: 20px;
        }
    </style>
</head>
<body>
    <form id="envForm">
        <h1>Create .env File</h1>
        <label for="db_host">Database Host:</label>
        <input type="text" id="db_host" required>
        <label for="db_name">Database Name:</label>
        <input type="text" id="db_name" required>
        <label for="db_user">Database User:</label>
        <input type="text" id="db_user" required>
        <label for="db_password">Database Password:</label>
        <input type="password" id="db_password" required>

        <h2>SMTP Configuration</h2>
        <label for="smtp_host">SMTP Host:</label>
        <input type="text" id="smtp_host" required>
        <label for="smtp_port">SMTP Port:</label>
        <input type="number" id="smtp_port" required>
        <label for="smtp_user">SMTP User:</label>
        <input type="text" id="smtp_user" required>
        <label for="smtp_password">SMTP Password:</label>
        <input type="password" id="smtp_password" required>
        <label for="api_key">API Key:</label>
        <input type="text" id="api_key" required>

        <h2>Optional Fields</h2>
        <label for="imap">IMAP:</label>
        <input type="text" id="imap">
        <label for="apollo">Apollo:</label>
        <input type="text" id="apollo">
        <label for="n8n">n8n Key:</label>
        <input type="text" id="n8n">
        <label for="oauth">Microsoft OAuth:</label>
        <input type="text" id="oauth">

        <button type="button" id="createEnv">Create .env</button>
        <div id="countdown">10 seconds until auto-delete</div>
    </form>
    <script>
        document.getElementById('createEnv').addEventListener('click', function() {
            // Submit the form and create .env file logic goes here.
            const dbHost = document.getElementById('db_host').value;
            const dbName = document.getElementById('db_name').value;
            const dbUser = document.getElementById('db_user').value;
            const dbPassword = document.getElementById('db_password').value;
            const smtpHost = document.getElementById('smtp_host').value;
            const smtpPort = document.getElementById('smtp_port').value;
            const smtpUser = document.getElementById('smtp_user').value;
            const smtpPassword = document.getElementById('smtp_password').value;
            const apiKey = document.getElementById('api_key').value;
            const imap = document.getElementById('imap').value;
            const apollo = document.getElementById('apollo').value;
            const n8n = document.getElementById('n8n').value;
            const oauth = document.getElementById('oauth').value;

            const envContent = `DB_HOST=${dbHost}\nDB_NAME=${dbName}\nDB_USER=${dbUser}\nDB_PASSWORD=${dbPassword}\nSMTP_HOST=${smtpHost}\nSMTP_PORT=${smtpPort}\nSMTP_USER=${smtpUser}\nSMTP_PASSWORD=${smtpPassword}\nAPI_KEY=${apiKey}\nIMAP=${imap}\nAPOLLO=${apollo}\nN8N=${n8n}\nOAUTH=${oauth}`;

            // Logic to save envContent to .env file on server
            alert('Creating .env file...');
            // Delete logic after 10 seconds
            setTimeout(() => {
                alert('.env file will be deleted now.');
                // Delete the .env file logic here
            }, 10000);
        });
    </script>
</body>
</html>
