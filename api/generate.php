<?php

echo handle();

function handle(): string 
{
	$text = get_input('text');
	$model_output = call_model($text);
	return json_encode(to_array($model_output));
}

function get_input(string $key): string
{
	$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

	if (str_contains($contentType, 'application/json')) {
		$data = json_decode(file_get_contents('php://input'), true);
		$input = $data[$key];
	} else {
		$input = $_POST[$key];
	}

	if (!isset($input) || empty($input)) {
		echo "INPUT ERROR: You must specify a $key";
		throw new Exception("You must specify a $key");
	}

	return $input;
}

function call_model(string $text): string
{
	// appending newline, because it will be passed as stdin,
	// and stdin recognize one line as a string ending with \n
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

	// if there's no result, fasttext will return "\n", with "" NOT '\n'
	if ($output === "\n") {
		// TODO: save the $text for labelling later
		// and also report to some slack channel

		return "other";
	}

	// we also do cleaning here because it's model-specific
	$clean_output = trim(str_replace('__label__', '', $output));
	return $clean_output;
}

function to_array(string $data): array
{
	return explode(' ', $data);
}

