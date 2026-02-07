// PIWZ HUNTER ADDITIONAL JAVASCRIPT

// WEBSOCKET CONNECTION FOR REAL-TIME UPDATES
function connectWebSocket() {
    const ws = new WebSocket(`wss://${window.location.hostname}/ws`);
    
    ws.onopen = function() {
        console.log('WebSocket connected');
        document.getElementById('connectionStatus').innerHTML = '● CONNECTED';
        document.getElementById('connectionStatus').style.color = '#0f0';
    };
    
    ws.onmessage = function(event) {
        const data = JSON.parse(event.data);
        handleRealTimeData(data);
    };
    
    ws.onclose = function() {
        console.log('WebSocket disconnected');
        document.getElementById('connectionStatus').innerHTML = '● DISCONNECTED';
        document.getElementById('connectionStatus').style.color = '#f00';
        // Reconnect after 5 seconds
        setTimeout(connectWebSocket, 5000);
    };
}

// HANDLE REAL-TIME DATA
function handleRealTimeData(data) {
    const terminal = document.getElementById('terminalOutput');
    
    switch(data.type) {
        case 'device_connected':
            terminal.innerHTML += `<div class="terminal-line">
                <span class="prompt">>>></span> Device connected: ${data.device_id}
            </div>`;
            loadDeviceData(); // Refresh device list
            break;
            
        case 'data_received':
            terminal.innerHTML += `<div class="terminal-line">
                <span class="prompt">>>></span> Data from ${data.device_id}: ${data.data_type}
            </div>`;
            break;
            
        case 'command_executed':
            terminal.innerHTML += `<div class="terminal-line">
                <span class="prompt">>>></span> Command executed: ${data.command}
            </div>`;
            break;
    }
    
    terminal.scrollTop = terminal.scrollHeight;
}

// EXPORT DATA FUNCTION
function exportData(type) {
    fetch(`api.php?action=export&type=${type}`)
        .then(response => response.json())
        .then(data => {
            const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `piwz-hunter-${type}-${Date.now()}.json`;
            a.click();
            window.URL.revokeObjectURL(url);
        });
}

// SEND CUSTOM COMMAND
function sendCustomCommand(deviceId, command, params = {}) {
    fetch('api.php?action=send_command', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            device_id: deviceId,
            command: command,
            params: params
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            alert(`Command "${command}" sent to ${deviceId}`);
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// DELETE DEVICE
function deleteDevice(deviceId) {
    if(confirm(`Delete device ${deviceId}? This action cannot be undone.`)) {
        fetch(`api.php?action=delete_device&device_id=${deviceId}`)
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    loadDeviceData();
                    alert(`Device ${deviceId} deleted`);
                }
            });
    }
}

// CLEAR LOGS
function clearLogs() {
    if(confirm('Clear all logs?')) {
        fetch('api.php?action=clear_logs')
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    document.getElementById('logContent').textContent = 'Logs cleared';
                    alert('Logs cleared successfully');
                }
            });
    }
}

// SYSTEM COMMANDS
const systemCommands = {
    help: () => "Available commands: help, clear, stats, devices, export, time",
    clear: () => { document.getElementById('terminalOutput').innerHTML = ''; return "Terminal cleared"; },
    stats: () => `Online: ${document.getElementById('onlineDevices').textContent} devices`,
    devices: () => "Loading device list...",
    export: (type) => { exportData(type || 'all'); return `Exporting ${type || 'all'} data...`; },
    time: () => new Date().toLocaleString()
};

// INITIALIZE
document.addEventListener('DOMContentLoaded', function() {
    // Add scanline effect
    const scanline = document.createElement('div');
    scanline.className = 'scanline';
    document.body.appendChild(scanline);
    
    // Initialize WebSocket
    connectWebSocket();
    
    // Auto-refresh every 30 seconds
    setInterval(loadDeviceData, 30000);
    
    // Generate random session ID
    document.getElementById('sessionId').textContent = 
        Math.random().toString(36).substr(2, 12).toUpperCase();
});
