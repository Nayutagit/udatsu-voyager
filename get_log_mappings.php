<?php
if (($_GET['key'] ?? '') !== 'udatsu2026diag') { http_response_code(403); exit('Forbidden'); }

$logFile = __DIR__ . '/log/analysis_log.txt';
if (!file_exists($logFile)) {
    exit("Log file not found");
}

$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$currentJob = null;
$mappings = [];

foreach ($lines as $line) {
    if (strpos($line, '[run_analysis_for_post]') !== false) {
        // Parse uid, audioPath, jobId
        preg_match('/uid=([a-zA-Z0-9_-]+)\s+audioPath=([^\s]+)\s+jobId=([a-zA-Z0-9_-]+)/', $line, $matches);
        if ($matches) {
            $uid = $matches[1];
            $audioPath = $matches[2];
            $jobId = $matches[3];
            if ($uid === 'YvD0Ab05RJZxSeNEAqAgurEzMEh2') {
                $currentJob = [
                    'jobId' => $jobId,
                    'audioPath' => $audioPath,
                    'title' => null,
                ];
            } else {
                $currentJob = null;
            }
        }
    } elseif ($currentJob && strpos($line, 'DONE:') !== false) {
        $parts = explode('DONE:', $line);
        if (isset($parts[1])) {
            $title = trim($parts[1]);
            $currentJob['title'] = $title;
            $mappings[$currentJob['jobId']] = $currentJob;
            $currentJob = null;
        }
    } elseif ($currentJob && (strpos($line, 'ERROR:') !== false || strpos($line, '[run_analysis_for_post]') !== false)) {
        $currentJob = null;
    }
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode(array_values($mappings), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
