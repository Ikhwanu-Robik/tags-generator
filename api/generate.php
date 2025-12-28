<?php

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (str_contains($contentType, 'application/json')) {
	$data = json_decode(file_get_contents('php://input'), true);
	$text = $data['text'];
} else {
	$text = $_POST['text'];
}

if (!isset($text) || empty($text)) {
	echo "INPUT ERROR: You must specify a text";
	throw new Exception("You must specify a text");
}

// appending newline, because it will be passed as stdin, and stdin recognize one line as a string ending with \n
$text .= "\n";

// -1 means get as much labels as possible
// 0.5 means get only labels with confidence score >= 0.5
$model_exe_path = "fasttext predict /usr/local/share/fasttext-models/gemini-generated-shuffled.bin - -1 0.5";
$descriptorspec = [
	0 => ['pipe', 'r'],
	1 => ['pipe', 'w'],
	2 => ['pipe', 'w']
];

$process = proc_open($model_exe_path, $descriptorspec, $pipes);

fwrite($pipes[0], $text);
fclose($pipes[0]);

$output = stream_get_contents($pipes[1]);
fclose($pipes[1]);

$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);

$exitcode = proc_close($process);

if ($exitcode !== 0) {
	echo "the machine learning model failed $exitcode";
	throw new RuntimeException("the machine learning model failed $exitcode : " . trim($stderr));
}

// if there's no result, it will return "\n", NOT '\n'
if ($output === "\n") {
	// TODO: save the $text for labelling later
	// and also report to some slack channel

	echo json_encode(["other"]);
	exit();
}

$clean_output = str_replace("\n", '', str_replace('__label__', '', $output));
$result = explode(' ', $clean_output);

echo json_encode($result);
