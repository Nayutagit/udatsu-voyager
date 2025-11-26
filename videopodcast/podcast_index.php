<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>音声文字起こし → ビデオポッドキャスト制作ツール</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;700;900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: linear-gradient(135deg, #3a3a3a 0%, #4d4d4d 100%);
            color: #ffffff;
            height: 100vh;
            display: grid;
            grid-template-columns: 320px 1fr 360px;
            gap: 20px;
            padding: 20px;
            overflow: hidden;
            margin-top: 60px;
            height: calc(100vh - 60px);
        }
        
        /* 左側：設定パネル */
        .setting-panel {
            background: linear-gradient(135deg, #2a2a2a 0%, #3d3d3d 100%);
            border: 2px solid #fcc800;
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(252, 200, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        /* 中央：プレビューパネル - 16:9と9:16を正確に表示 */
        .preview-panel {
            background: linear-gradient(135deg, #3a3a3a 0%, #555555 50%, #4d4d4d 100%);
            border: 2px solid #fcc800;
            border-radius: 10px;
            box-shadow: 0 0 50px rgba(252, 200, 0, 0.3);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 400px;
            min-height: 500px;
            padding: 20px;
        }
        
        /* 右側：編集パネル */
        .editor-panel {
            background: linear-gradient(135deg, #3a3a3a 0%, #555555 50%, #4d4d4d 100%);
            border: 2px solid #fcc800;
            border-radius: 10px;
            box-shadow: 0 0 50px rgba(252, 200, 0, 0.3);
            position: relative;
            width: 360px;
            overflow: hidden;
        }
        
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 8px;
        }
        
        /* プレビューパネル専用のiframe設定 */
        #previewFrame {
            width: 100%;
            height: 100%;
            min-height: 500px;
            border: none;
            border-radius: 8px;
        }
        
        .panel-header {
            background: rgba(252, 200, 0, 0.2);
            border: 1px solid #fcc800;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            color: #fcc800;
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 14px;
            position: absolute;
            top: 15px;
            left: 15px;
            right: 15px;
            z-index: 1000;
        }
        
        /* メイン画面のマージン調整 */
        body {
            margin-top: 60px;
            height: calc(100vh - 60px);
        }
        
        /* ナビゲーションバー */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 2px solid #fcc800;
            padding: 10px 20px;
            z-index: 10000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar h1 {
            color: #fcc800;
            font-size: 18px;
            font-weight: bold;
        }
        
        .navbar-buttons {
            display: flex;
            gap: 15px;
        }
        
        .nav-btn {
            background: rgba(252, 200, 0, 0.8);
            color: #000;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .nav-btn:hover {
            background: #fcc800;
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(252, 200, 0, 0.5);
        }
        
        .nav-btn.record {
            background: linear-gradient(45deg, #f44336, #ff6b6b);
            color: #fff;
        }
        
        .nav-btn.record:hover {
            background: linear-gradient(45deg, #d32f2f, #f44336);
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 1400px) {
            body {
                grid-template-columns: 280px 1fr 280px;
                gap: 15px;
                padding: 15px;
            }
            
            .preview-panel {
                min-height: 400px;
            }
        }
        
        @media (max-width: 1200px) {
            body {
                grid-template-columns: 1fr;
                grid-template-rows: auto 1fr auto;
                gap: 15px;
                padding: 15px;
            }
            
            .preview-panel {
                width: 100%;
                height: auto;
                min-height: 500px;
            }
            
            .editor-panel {
                width: 100%;
                height: auto;
                min-height: 400px;
            }
            
            .setting-panel {
                height: auto;
                min-height: 300px;
            }
            
            #previewFrame {
                min-height: 500px;
            }
        }
        
        /* ローディング画面 */
        .loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #fcc800;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
        }
        
        .spinner {
            border: 3px solid rgba(252, 200, 0, 0.3);
            border-top: 3px solid #fcc800;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- ナビゲーションバー -->
    <div class="navbar">
        <h1>🎙️ ビデオポッドキャスト制作ツール</h1>
        <div class="navbar-buttons">
            <a href="podcast_rec.php" class="nav-btn record" target="_blank">🎥 収録モード</a>
            <button class="nav-btn" onclick="refreshAll()">🔄 更新</button>
        </div>
    </div>
    
    <!-- 左側：設定パネル -->
    <div class="setting-panel">
        <div class="panel-header">⚙️ 設定・制御パネル</div>
        <iframe src="podcast_setting.php" id="settingFrame"></iframe>
    </div>
    
    <!-- 中央：プレビューパネル -->
    <div class="preview-panel">
        <div class="panel-header">👁️ プレビュー（縦・横同時表示）</div>
        <iframe src="podcast_preview.php?mode=both" id="previewFrame"></iframe>
    </div>
    
    <!-- 右側：編集パネル -->
    <div class="editor-panel">
        <div class="panel-header">✏️ スライド編集</div>
        <iframe src="podcast_editor.php" id="editorFrame"></iframe>
    </div>

    <script>
        // ページ読み込み時の初期化
        window.addEventListener('load', function() {
            console.log('🎙️ ビデオポッドキャスト制作ツール - メイン画面起動');
            
            // iframe間の通信設定
            setupCommunication();
            
            console.log('✅ システム準備完了');
        });

        // iframe間の通信設定
        function setupCommunication() {
            // 設定変更の監視
            window.addEventListener('message', function(event) {
                const data = event.data;
                
                switch(data.type) {
                    case 'slideUpdate':
                        // スライド更新をプレビューに送信
                        document.getElementById('previewFrame').contentWindow.postMessage(data, '*');
                        break;
                        
                    case 'cameraUpdate':
                        // カメラ状態更新をプレビューに送信
                        document.getElementById('previewFrame').contentWindow.postMessage(data, '*');
                        break;
                        
                    case 'settingUpdate':
                        // 設定更新を全パネルに送信
                        document.getElementById('previewFrame').contentWindow.postMessage(data, '*');
                        document.getElementById('editorFrame').contentWindow.postMessage(data, '*');
                        break;
                        
                    case 'editorUpdate':
                        // 編集内容をプレビューに送信
                        document.getElementById('previewFrame').contentWindow.postMessage(data, '*');
                        break;
                }
            });
        }

        // 全体更新
        function refreshAll() {
            document.getElementById('settingFrame').src = document.getElementById('settingFrame').src;
            document.getElementById('previewFrame').src = document.getElementById('previewFrame').src;
            document.getElementById('editorFrame').src = document.getElementById('editorFrame').src;
            
            setTimeout(() => {
                setupCommunication();
            }, 1000);
        }

        // キーボードショートカット
        document.addEventListener('keydown', function(e) {
            switch(e.key) {
                case 'F5':
                    e.preventDefault();
                    refreshAll();
                    break;
                case 'F11':
                    e.preventDefault();
                    window.open('podcast_rec.php', '_blank');
                    break;
            }
        });

        // ウィンドウサイズ変更時の調整
        window.addEventListener('resize', function() {
            // iframe高さの再計算
            const frames = document.querySelectorAll('iframe');
            frames.forEach(frame => {
                frame.style.height = '100%';
            });
        });
    </script>
</body>
</html>