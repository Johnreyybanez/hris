<?php
$config = require 'config.php';
$apiKey = $config['openai_key'];
// chatbot.php
header('Content-Type: application/json');

// Your OpenAI API Key


// Read user message
$input = json_decode(file_get_contents("php://input"), true);
$message = $input['message'] ?? '';

if (!$message) {
    echo json_encode(["response" => "Please type a message."]);
    exit;
}

// Send request to OpenAI API
$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $apiKey"
]);

$data = [
    "model" => "gpt-4o-mini", // lightweight, fast AI model
    "messages" => [
        ["role" => "system", "content" => "You are an HRIS support chatbot. Help employees with login, password, and system-related issues."],
        ["role" => "user", "content" => $message]
    ],
    "max_tokens" => 200
];

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
curl_close($ch);

if ($response) {
    $result = json_decode($response, true);
    $reply = $result['choices'][0]['message']['content'] ?? "Sorry, I couldn't get a response.";
    echo json_encode(["response" => $reply]);
} else {
    echo json_encode(["response" => "Error: Unable to connect to AI service."]);
}
