<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Итроник Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        
        .container { display: flex; height: 100vh; }
        
        .sidebar {
            width: 300px;
            background: #1a1a2e;
            color: white;
            padding: 20px;
            overflow-y: auto;
        }
        
        .sidebar h2 {
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
        }
        
        .stat-card h4 {
            font-size: 12px;
            opacity: 0.7;
            margin-bottom: 5px;
        }
        
        .stat-card .value {
            font-size: 24px;
            font-weight: bold;
        }
        
        .conversation-list { margin-top: 20px; }
        
        .conversation-item {
            background: #16213e;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .conversation-item:hover { background: #0f3460; }
        .conversation-item.active { background: linear-gradient(135deg, #667eea, #764ba2); }
        
        .conversation-item .user {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .conversation-item .preview {
            font-size: 12px;
            opacity: 0.7;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .conversation-item .meta {
            font-size: 11px;
            opacity: 0.5;
            margin-top: 5px;
        }
        
        .main {
            flex: 1;
            background: #f5f7fa;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            background: white;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-controls {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .btn-success { background: #4CAF50; color: white; }
