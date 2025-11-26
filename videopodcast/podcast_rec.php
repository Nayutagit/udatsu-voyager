<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>収録モード - ビデオポッドキャスト制作ツール</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;700;900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: #000;
            color: #ffffff;
            height: 100vh;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 60px;
            padding: 20px;
        }
        
        /* 左側：縦画面（9:16） */
        .recording-vertical {
            width: min(360px, 30vw);
            height: min(640px, 80vh);
            background: linear-gradient(135deg, #3a3a3a 0%, #555555 50%, #4d4d4d 100%);
            border: 3px solid #fcc800;
            border-radius: 15px;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 0 40px rgba(252, 200, 0, 0.4);
        }
        
        /* 右側：横画面（16:9） */
        .recording-horizontal {
            width: min(640px, 50vw);
            height: min(360px, 45vh);
            background: linear-gradient(135deg, #3a3a3a 0%, #555555 50%, #4d4d4d 100%);
            border: 3px solid #fcc800;
            border-radius: 15px;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 0 40px rgba(252, 200, 0, 0.4);
        }
        
        /* カメラワイプ - ドラッグ可能 */
        .recording-cam {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 80px;
            height: 80px;
            border: 2px solid #fcc800;
            border-radius: 12px;
            background: rgba(252, 200, 0, 0.1);
            box-shadow: 0 0 20px rgba(252, 200, 0, 0.5);
            z-index: 1000;
            overflow: hidden;
            cursor: move;
            user-select: none;
            transition: all 0.3s ease;
        }
        
        .recording-cam:hover {
            box-shadow: 0 0 30px rgba(252, 200, 0, 0.8);
            transform: scale(1.05);
        }
        
        .recording-cam.dragging {
            opacity: 0.8;
            z-index: 1500;
            transform: scale(1.1);
            transition: none;
        }
        
        .recording-cam video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .camera-placeholder {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #fcc800;
            font-size: 8px;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(252, 200, 0, 0.8);
            text-align: center;
            z-index: 10;
        }
        
        .recording-slide-content {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 15% 20% 15% 25%;
            text-align: center;
            box-sizing: border-box;
        }
        
        .recording-slide-content.horizontal {
            padding: 20% 25% 20% 25%;
        }
        
        /* 収録状態インジケーター */
        .recording-indicator {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(244, 67, 54, 0.9);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: none;
            animation: recordingPulse 1.5s infinite;
            z-index: 2000;
        }
        
        .recording-indicator.show {
            display: block;
        }
        
        @keyframes recordingPulse {
            0%, 100% { box-shadow: 0 0 10px rgba(244, 67, 54, 0.5); }
            50% { box-shadow: 0 0 20px rgba(244, 67, 54, 0.8); }
        }
        
        /* コントロールパネル - ドラッグ可能 */
        .recording-controls {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 2000;
            background: rgba(0, 0, 0, 0.95);
            padding: 15px 25px;
            border-radius: 50px;
            border: 2px solid #fcc800;
            box-shadow: 0 0 30px rgba(252, 200, 0, 0.3);
            cursor: move;
            user-select: none;
            backdrop-filter: blur(10px);
        }
        
        .recording-controls:hover {
            box-shadow: 0 0 40px rgba(252, 200, 0, 0.5);
        }
        
        .recording-controls.dragging {
            opacity: 0.8;
            transform: none !important;
            transition: none;
        }
        
        .drag-handle {
            color: #fcc800;
            font-size: 16px;
            margin-right: 10px;
            cursor: move;
        }
        
        .control-btn {
            background: rgba(252, 200, 0, 0.9);
            color: #000;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            min-width: 120px;
        }
        
        .control-btn:hover {
            background: #fcc800;
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(252, 200, 0, 0.5);
        }
        
        .control-btn.recording {
            background: linear-gradient(45deg, #f44336, #ff6b6b);
            color: #fff;
            animation: recordingPulse 1.5s infinite;
        }
        
        .control-btn:disabled {
            background: rgba(100, 100, 100, 0.5);
            color: #666;
            cursor: not-allowed;
            transform: none;
            animation: none;
        }
        
        /* カメラ調整トグルボタン */
        .camera-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(252, 200, 0, 0.9);
            color: #000;
            border: none;
            padding: 12px;
            border-radius: 50%;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            z-index: 2200;
            width: 50px;
            height: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 0 20px rgba(252, 200, 0, 0.3);
        }
        
        .camera-toggle:hover {
            background: #fcc800;
            transform: scale(1.1);
            box-shadow: 0 0 30px rgba(252, 200, 0, 0.5);
        }
        
        /* カメラ調整パネル */
        .camera-controls {
            position: fixed;
            top: 80px;
            right: 20px;
            background: rgba(0, 0, 0, 0.95);
            border: 2px solid #fcc800;
            border-radius: 15px;
            padding: 20px;
            z-index: 2100;
            backdrop-filter: blur(10px);
            min-width: 250px;
            display: none;
            box-shadow: 0 0 30px rgba(252, 200, 0, 0.3);
        }
        
        .camera-controls.show {
            display: block;
            animation: slideInRight 0.3s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .camera-controls h3 {
            color: #fcc800;
            font-size: 16px;
            margin-bottom: 15px;
            text-align: center;
            text-shadow: 0 0 10px rgba(252, 200, 0, 0.8);
        }
        
        .camera-slider-container {
            margin: 12px 0;
        }
        
        .camera-slider-label {
            color: #e0e0e0;
            font-size: 12px;
            margin-bottom: 8px;
            display: block;
        }
        
        .camera-slider {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #666;
            outline: none;
            -webkit-appearance: none;
        }
        
        .camera-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fcc800;
            cursor: pointer;
        }
        
        .camera-btn {
            background: rgba(252, 200, 0, 0.8);
            color: #000;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            margin: 5px;
            width: calc(50% - 10px);
            transition: all 0.3s ease;
        }
        
        .camera-btn:hover {
            background: #fcc800;
            transform: scale(1.05);
        }
        
        .camera-btn.full-width {
            width: calc(100% - 10px);
        }
        
        /* スライドスタイル */
        h1 {
            font-size: 1.6em;
            font-weight: 900;
            color: #fcc800;
            text-shadow: 0 0 30px rgba(252, 200, 0, 0.8);
            margin-bottom: 20px;
            line-height: 1.2;
        }
        
        h2 {
            font-size: 1.2em;
            font-weight: 700;
            color: #fcc800;
            text-shadow: 0 0 20px rgba(252, 200, 0, 0.6);
            margin-bottom: 15px;
            line-height: 1.3;
        }
        
        .quote {
            font-style: italic;
            font-size: 0.9em;
            color: #fcc800;
            border-left: 4px solid #fcc800;
            padding-left: 12px;
            margin: 15px 0;
            text-shadow: 0 0 15px rgba(252, 200, 0, 0.6);
            line-height: 1.4;
        }
        
        .highlight {
            color: #fcc800;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(252, 200, 0, 0.8);
        }
        
        .main-text {
            font-size: 0.85em;
            line-height: 1.5;
            color: #e0e0e0;
            margin: 15px 0;
            text-shadow: 0 0 5px rgba(255, 255, 255, 0.2);
        }
        
        .glow-box {
            background: rgba(252, 200, 0, 0.1);
            border: 2px solid #fcc800;
            border-radius: 10px;
            padding: 15px;
            margin: 12px 0;
            box-shadow: 0 0 20px rgba(252, 200, 0, 0.3);
            width: 100%;
            max-width: 95%;
        }
        
        /* 横画面用のフォントサイズ調整 */
        .recording-horizontal h1 {
            font-size: 2.2em !important;
        }
        
        .recording-horizontal h2 {
            font-size: 1.8em !important;
        }
        
        .recording-horizontal .main-text {
            font-size: 1.1em !important;
        }
        
        /* 保存ダイアログ */
        .save-dialog {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, #2a2a2a 0%, #3d3d3d 100%);
            border: 3px solid #fcc800;
            border-radius: 15px;
            padding: 30px;
            z-index: 20000;
            box-shadow: 0 0 50px rgba(252, 200, 0, 0.5);
            display: none;
            min-width: 400px;
        }
        
        .save-dialog h3 {
            color: #fcc800;
            margin-bottom: 20px;
            text-align: center;
            font-size: 18px;
        }
        
        .save-dialog input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            background: rgba(0, 0, 0, 0.4);
            border: 2px solid #fcc800;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
        }
        
        .save-dialog-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .save-dialog-btn {
            background: rgba(252, 200, 0, 0.8);
            color: #000;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .save-dialog-btn:hover {
            background: #fcc800;
            transform: scale(1.05);
        }
        
        .save-dialog-btn.cancel {
            background: rgba(100, 100, 100, 0.8);
            color: #fff;
        }
        
        /* レスポンシブ調整 */
        @media (max-width: 1400px) {
            body {
                flex-direction: column;
                gap: 30px;
            }
            
            .recording-vertical {
                width: min(300px, 80vw);
                height: min(533px, 45vh);
            }
            
            .recording-horizontal {
                width: min(500px, 80vw);
                height: min(281px, 30vh);
            }
        }
        
        @media (max-width: 768px) {
            body {
                gap: 20px;
            }
            
            .recording-vertical {
                width: min(250px, 90vw);
                height: min(444px, 40vh);
            }
            
            .recording-horizontal {
                width: min(400px, 90vw);
                height: min(225px, 25vh);
            }
            
            .recording-cam {
                width: 60px;
                height: 60px;
                top: 10px;
                left: 10px;
            }
            
            .recording-controls {
                bottom: 15px;
                gap: 10px;
                padding: 12px 20px;
            }
            
            .control-btn {
                padding: 10px 15px;
                font-size: 12px;
                min-width: 100px;
            }
        }
        
        /* メッセージ表示 */
        .message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 25px;
            border-radius: 8px;
            font-weight: bold;
            z-index: 10000;
            max-width: 400px;
            display: none;
            text-align: center;
        }
        
        .message.success {
            background: linear-gradient(45deg, #4caf50, #fcc800);
            color: white;
        }
        
        .message.error {
            background: linear-gradient(45deg, #f44336, #ff6b6b);
            color: white;
        }
        
        .message.info {
            background: linear-gradient(45deg, #2196f3, #fcc800);
            color: white;
        }
    </style>
</head>
<body>
    <!-- 左側：縦画面（9:16） -->
    <div class="recording-vertical">
        <div class="recording-indicator" id="recordingIndicatorVertical">
            ● REC <span id="recordingTimeVertical">00:00</span>
        </div>
        <div class="recording-cam" id="verticalCam" data-screen="vertical">
            <video id="verticalVideo" autoplay muted playsinline style="display: none;"></video>
            <div class="camera-placeholder" id="verticalPlaceholder">
                FACE CAM<br><small>ドラッグで移動</small>
            </div>
        </div>
        <div class="recording-slide-content" id="verticalSlideContent">
            <h1>収録モード</h1>
            <div class="main-text">📹 カメラを開始して収録開始</div>
            <div class="glow-box">
                <div style="font-size: 0.8em; color: #fcc800;">📱 縦画面 (9:16) - モバイル向け</div>
            </div>
        </div>
    </div>
    
    <!-- 右側：横画面（16:9） -->
    <div class="recording-horizontal">
        <div class="recording-indicator" id="recordingIndicatorHorizontal">
            ● REC <span id="recordingTimeHorizontal">00:00</span>
        </div>
        <div class="recording-cam" id="horizontalCam" data-screen="horizontal">
            <video id="horizontalVideo" autoplay muted playsinline style="display: none;"></video>
            <div class="camera-placeholder" id="horizontalPlaceholder">
                FACE CAM<br><small>ドラッグで移動</small>
            </div>
        </div>
        <div class="recording-slide-content horizontal" id="horizontalSlideContent">
            <h1>収録モード</h1>
            <div class="main-text">📹 カメラを開始して収録開始</div>
            <div class="glow-box">
                <div style="font-size: 0.8em; color: #fcc800;">🖥️ 横画面 (16:9) - デスクトップ向け</div>
            </div>
        </div>
    </div>
    
    <!-- カメラ調整トグルボタン -->
    <button class="camera-toggle" onclick="toggleCameraControls()" title="カメラ調整 (T)">⚙️</button>
    
    <!-- カメラ調整パネル -->
    <div class="camera-controls" id="cameraControls">
        <h3>📹 カメラ調整</h3>
        
        <div class="camera-slider-container">
            <label class="camera-slider-label">明るさ: <span id="recBrightnessValue">0</span></label>
            <input type="range" class="camera-slider" id="brightnessSlider" min="-50" max="50" value="0">
        </div>
        
        <div class="camera-slider-container">
            <label class="camera-slider-label">コントラスト: <span id="recContrastValue">100</span>%</label>
            <input type="range" class="camera-slider" id="contrastSlider" min="50" max="200" value="100">
        </div>
        
        <div class="camera-slider-container">
            <label class="camera-slider-label">ズーム: <span id="recZoomValue">1.0</span>x</label>
            <input type="range" class="camera-slider" id="zoomSlider" min="1" max="3" step="0.1" value="1">
        </div>
        
        <div class="camera-slider-container">
            <label class="camera-slider-label">ワイプサイズ: <span id="wipeSizeValue">80</span>px</label>
            <input type="range" class="camera-slider" id="wipeSizeSlider" min="60" max="200" step="10" value="80">
        </div>
        
        <div class="camera-slider-container">
            <label class="camera-slider-label">角丸: <span id="wipeRoundValue">12</span>px</label>
            <input type="range" class="camera-slider" id="wipeRoundSlider" min="0" max="30" step="2" value="12">
        </div>
        
        <button class="camera-btn" onclick="flipRecCamera()">🔄 反転</button>
        <button class="camera-btn" onclick="resetCameraSettings()">🎯 リセット</button>
        <button class="camera-btn" onclick="setWipePreset('small')">📱 小</button>
        <button class="camera-btn" onclick="setWipePreset('large')">📺 大</button>
        <button class="camera-btn full-width" onclick="resetWipePositions()">📍 ワイプ位置リセット</button>
    </div>
    
    <!-- コントロールパネル -->
    <div class="recording-controls" id="recordingControls">
        <div class="drag-handle" title="ドラッグして移動">⋮⋮</div>
        <button class="control-btn" onclick="startCamera()" id="cameraBtn">📹 カメラ開始</button>
        <button class="control-btn" onclick="nextSlide()">Next ➡️</button>
        <button class="control-btn" onclick="prevSlide()">⬅️ 前</button>
        <button class="control-btn" onclick="toggleRecording()" id="recordBtn">🎥 収録開始</button>
        <button class="control-btn" onclick="closeRecordingMode()">❌ 終了</button>
    </div>

    <!-- 保存ダイアログ -->
    <div class="save-dialog" id="saveDialog">
        <h3>🎬 収録ファイル保存</h3>
        <input type="text" id="filenameInput" placeholder="ファイル名を入力（例: my-podcast）">
        <div style="margin: 15px 0; color: #e0e0e0; font-size: 12px;">
            📁 保存形式: WebM (VP9/Opus)<br>
            🎵 音声: マイク + PC音声ミックス<br>
            📺 両画面が同時に収録されます
        </div>
        <div class="save-dialog-buttons">
            <button class="save-dialog-btn cancel" onclick="cancelSave()">キャンセル</button>
            <button class="save-dialog-btn" onclick="confirmSave()">💾 保存</button>
        </div>
    </div>

    <div class="message" id="messageDiv"></div>

    <script>
        // グローバル変数
        let currentSlide = 0;
        let slides = [];
        let totalSlides = 1;
        let localStream = null;
        let isFlipped = false;
        let brightness = 0;
        let contrast = 100;
        let zoom = 1.0;
        let wipeSize = 80;
        let wipeRound = 12;
        
        // 収録関連変数
        let isRecording = false;
        let mediaRecorder = null;
        let recordedChunks = [];
        let recordingStartTime = 0;
        let recordingTimer = null;
        let mixedStream = null;
        let audioContext = null;
        let micSource = null;
        let desktopSource = null;
        let destination = null;
        
        // ドラッグ関連変数
        let isDragging = false;
        let dragTarget = null;
        let dragOffset = { x: 0, y: 0 };
        
        // ワイプ位置保存用
        let wipePositions = {
            vertical: { x: 15, y: 15 },
            horizontal: { x: 15, y: 15 }
        };

        // デフォルトスライド
        const defaultSlides = [
            {
                type: 'intro',
                title: '収録モード',
                content: 'カメラを開始して収録を開始してください',
                note: '両画面が同時に収録されます'
            }
        ];

        // ページ読み込み時の初期化
        document.addEventListener('DOMContentLoaded', function() {
            console.log('ページが読み込まれました');
            initializeApp();
        });

        function initializeApp() {
            console.log('アプリを初期化中...');
            
            // スライダーのイベントリスナー設定
            setupSliderEventListeners();
            
            // ドラッグ機能の初期化
            initializeDragFunctionality();
            
            // キーボードショートカット
            setupKeyboardShortcuts();
            
            // 初期スライド設定
            slides = [...defaultSlides];
            totalSlides = slides.length;
            displaySlide(currentSlide);
            
            console.log('初期化完了');
            showMessage('収録モードが準備完了しました', 'success');
        }

        // スライダーのイベントリスナー設定
        function setupSliderEventListeners() {
            const brightnessSlider = document.getElementById('brightnessSlider');
            const contrastSlider = document.getElementById('contrastSlider');
            const zoomSlider = document.getElementById('zoomSlider');
            const wipeSizeSlider = document.getElementById('wipeSizeSlider');
            const wipeRoundSlider = document.getElementById('wipeRoundSlider');

            if (brightnessSlider) {
                brightnessSlider.addEventListener('input', function() {
                    adjustRecBrightness(this.value);
                });
            }

            if (contrastSlider) {
                contrastSlider.addEventListener('input', function() {
                    adjustRecContrast(this.value);
                });
            }

            if (zoomSlider) {
                zoomSlider.addEventListener('input', function() {
                    adjustRecZoom(this.value);
                });
            }

            if (wipeSizeSlider) {
                wipeSizeSlider.addEventListener('input', function() {
                    adjustWipeSize(this.value);
                });
            }

            if (wipeRoundSlider) {
                wipeRoundSlider.addEventListener('input', function() {
                    adjustWipeRound(this.value);
                });
            }
        }

        // キーボードショートカット
        function setupKeyboardShortcuts() {
            document.addEventListener('keydown', function(e) {
                // Tキーでカメラ調整パネル開閉
                if (e.key === 't' || e.key === 'T') {
                    e.preventDefault();
                    toggleCameraControls();
                }
                // Spaceキーで収録開始/停止
                if (e.key === ' ') {
                    e.preventDefault();
                    toggleRecording();
                }
                // 矢印キーでスライド移動
                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    nextSlide();
                }
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    prevSlide();
                }
            });
        }

        // ドラッグ機能の初期化
        function initializeDragFunctionality() {
            console.log('ドラッグ機能を初期化中...');
            
            // コントロールパネルのドラッグ
            const controls = document.getElementById('recordingControls');
            if (controls) {
                controls.addEventListener('mousedown', startDrag);
                controls.addEventListener('touchstart', startDrag, { passive: false });
                console.log('コントロールパネルのドラッグを設定');
            }
            
            // ワイプのドラッグ（縦画面・横画面独立）
            const verticalCam = document.getElementById('verticalCam');
            const horizontalCam = document.getElementById('horizontalCam');
            
            if (verticalCam) {
                verticalCam.addEventListener('mousedown', startDrag);
                verticalCam.addEventListener('touchstart', startDrag, { passive: false });
                console.log('縦画面ワイプのドラッグを設定');
            }
            
            if (horizontalCam) {
                horizontalCam.addEventListener('mousedown', startDrag);
                horizontalCam.addEventListener('touchstart', startDrag, { passive: false });
                console.log('横画面ワイプのドラッグを設定');
            }
            
            // グローバルイベント
            document.addEventListener('mousemove', handleDrag);
            document.addEventListener('mouseup', endDrag);
            document.addEventListener('touchmove', handleDrag, { passive: false });
            document.addEventListener('touchend', endDrag);
            
            console.log('ドラッグ機能の初期化完了');
        }

        function startDrag(e) {
            console.log('ドラッグ開始');
            e.preventDefault();
            isDragging = true;
            dragTarget = e.currentTarget;
            dragTarget.classList.add('dragging');
            
            const clientX = e.clientX || (e.touches && e.touches[0].clientX);
            const clientY = e.clientY || (e.touches && e.touches[0].clientY);
            
            const rect = dragTarget.getBoundingClientRect();
            dragOffset.x = clientX - rect.left;
            dragOffset.y = clientY - rect.top;
            
            document.body.style.userSelect = 'none';
            console.log('ドラッグターゲット:', dragTarget.id || dragTarget.className);
        }

        function handleDrag(e) {
            if (!isDragging || !dragTarget) return;
            
            e.preventDefault();
            
            const clientX = e.clientX || (e.touches && e.touches[0].clientX);
            const clientY = e.clientY || (e.touches && e.touches[0].clientY);
            
            let newX = clientX - dragOffset.x;
            let newY = clientY - dragOffset.y;
            
            // 画面境界チェック
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;
            const elementWidth = dragTarget.offsetWidth;
            const elementHeight = dragTarget.offsetHeight;
            
            newX = Math.max(0, Math.min(windowWidth - elementWidth, newX));
            newY = Math.max(0, Math.min(windowHeight - elementHeight, newY));
            
            dragTarget.style.position = 'fixed';
            dragTarget.style.left = newX + 'px';
            dragTarget.style.top = newY + 'px';
            dragTarget.style.transform = 'none';
            
            // ワイプの場合は位置を保存
            if (dragTarget.classList.contains('recording-cam')) {
                const screen = dragTarget.dataset.screen;
                if (screen) {
                    wipePositions[screen] = { x: newX, y: newY };
                }
            }
        }

        function endDrag() {
            if (isDragging && dragTarget) {
                console.log('ドラッグ終了');
                dragTarget.classList.remove('dragging');
                document.body.style.userSelect = '';
                isDragging = false;
                dragTarget = null;
            }
        }

        // カメラ調整パネルの表示/非表示
        function toggleCameraControls() {
            console.log('カメラ調整パネルの表示/非表示');
            const panel = document.getElementById('cameraControls');
            if (panel) {
                panel.classList.toggle('show');
                const isVisible = panel.classList.contains('show');
                showMessage(isVisible ? 'カメラ調整パネルを開きました' : 'カメラ調整パネルを閉じました', 'info');
            } else {
                console.error('カメラ調整パネルが見つかりません');
            }
        }

        // カメラ調整機能
        function adjustRecBrightness(value) {
            brightness = parseInt(value);
            document.getElementById('recBrightnessValue').textContent = value;
            applyVideoFiltersToRec();
        }

        function adjustRecContrast(value) {
            contrast = parseInt(value);
            document.getElementById('recContrastValue').textContent = value;
            applyVideoFiltersToRec();
        }

        function adjustRecZoom(value) {
            zoom = parseFloat(value);
            document.getElementById('recZoomValue').textContent = value;
            applyVideoFiltersToRec();
        }

        function adjustWipeSize(value) {
            wipeSize = parseInt(value);
            document.getElementById('wipeSizeValue').textContent = value;
            applyWipeSize();
            showMessage(`ワイプサイズ: ${value}px`, 'info');
        }

        function adjustWipeRound(value) {
            wipeRound = parseInt(value);
            document.getElementById('wipeRoundValue').textContent = value;
            applyWipeRound();
            showMessage(`角丸: ${value}px`, 'info');
        }

        function applyWipeSize() {
            const wipes = [
                document.getElementById('verticalCam'),
                document.getElementById('horizontalCam')
            ];
            
            wipes.forEach(wipe => {
                if (wipe) {
                    wipe.style.width = wipeSize + 'px';
                    wipe.style.height = wipeSize + 'px';
                }
            });
        }

        function applyWipeRound() {
            const wipes = [
                document.getElementById('verticalCam'),
                document.getElementById('horizontalCam')
            ];
            
            wipes.forEach(wipe => {
                if (wipe) {
                    wipe.style.borderRadius = wipeRound + 'px';
                    
                    // ビデオ要素の角丸も調整
                    const video = wipe.querySelector('video');
                    if (video) {
                        video.style.borderRadius = (wipeRound - 2) + 'px';
                    }
                }
            });
        }

        function setWipePreset(size) {
            let newSize, newRound;
            
            switch(size) {
                case 'small':
                    newSize = 60;
                    newRound = 8;
                    break;
                case 'large':
                    newSize = 150;
                    newRound = 20;
                    break;
                default:
                    newSize = 80;
                    newRound = 12;
            }
            
            wipeSize = newSize;
            wipeRound = newRound;
            
            // スライダーの値を更新
            document.getElementById('wipeSizeSlider').value = newSize;
            document.getElementById('wipeRoundSlider').value = newRound;
            document.getElementById('wipeSizeValue').textContent = newSize;
            document.getElementById('wipeRoundValue').textContent = newRound;
            
            // 適用
            applyWipeSize();
            applyWipeRound();
            
            showMessage(`ワイプサイズ: ${size === 'small' ? '小' : '大'}に設定`, 'success');
        }

        function flipRecCamera() {
            isFlipped = !isFlipped;
            applyVideoFiltersToRec();
            showMessage('カメラを反転しました', 'info');
        }

        function resetCameraSettings() {
            brightness = 0;
            contrast = 100;
            zoom = 1.0;
            wipeSize = 80;
            wipeRound = 12;
            isFlipped = false;
            
            document.getElementById('recBrightnessValue').textContent = '0';
            document.getElementById('recContrastValue').textContent = '100';
            document.getElementById('recZoomValue').textContent = '1.0';
            document.getElementById('wipeSizeValue').textContent = '80';
            document.getElementById('wipeRoundValue').textContent = '12';
            
            // スライダーの値をリセット
            document.getElementById('brightnessSlider').value = 0;
            document.getElementById('contrastSlider').value = 100;
            document.getElementById('zoomSlider').value = 1.0;
            document.getElementById('wipeSizeSlider').value = 80;
            document.getElementById('wipeRoundSlider').value = 12;
            
            applyVideoFiltersToRec();
            applyWipeSize();
            applyWipeRound();
            showMessage('カメラ設定をリセットしました', 'success');
        }

        function resetWipePositions() {
            wipePositions = {
                vertical: { x: 15, y: 15 },
                horizontal: { x: 15, y: 15 }
            };
            
            const verticalCam = document.getElementById('verticalCam');
            const horizontalCam = document.getElementById('horizontalCam');
            
            if (verticalCam) {
                verticalCam.style.position = 'absolute';
                verticalCam.style.left = '15px';
                verticalCam.style.top = '15px';
                verticalCam.style.transform = '';
            }
            
            if (horizontalCam) {
                horizontalCam.style.position = 'absolute';
                horizontalCam.style.left = '15px';
                horizontalCam.style.top = '15px';
                horizontalCam.style.transform = '';
            }
            
            showMessage('ワイプ位置をリセットしました', 'success');
        }

        function applyVideoFiltersToRec() {
            const videos = [
                document.getElementById('verticalVideo'),
                document.getElementById('horizontalVideo')
            ];
            
            videos.forEach(video => {
                if (video) {
                    let filter = `brightness(${100 + brightness}%) contrast(${contrast}%) scale(${zoom})`;
                    if (isFlipped) {
                        filter += ' scaleX(-1)';
                    }
                    video.style.filter = filter.replace('scale(' + zoom + ')', '');
                    video.style.transform = `scale(${zoom}) ${isFlipped ? 'scaleX(-1)' : ''}`;
                }
            });
        }

        // カメラ開始
        async function startCamera() {
            try {
                showMessage('カメラを開始しています...', 'info');
                
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        width: { ideal: 1920 },
                        height: { ideal: 1080 },
                        facingMode: 'user'
                    },
                    audio: true
                });
                
                localStream = stream;
                
                // 両方の画面にビデオを表示
                const verticalVideo = document.getElementById('verticalVideo');
                const horizontalVideo = document.getElementById('horizontalVideo');
                const verticalPlaceholder = document.getElementById('verticalPlaceholder');
                const horizontalPlaceholder = document.getElementById('horizontalPlaceholder');
                
                if (verticalVideo) {
                    verticalVideo.srcObject = stream;
                    verticalVideo.style.display = 'block';
                    if (verticalPlaceholder) verticalPlaceholder.style.display = 'none';
                }
                
                if (horizontalVideo) {
                    horizontalVideo.srcObject = stream;
                    horizontalVideo.style.display = 'block';
                    if (horizontalPlaceholder) horizontalPlaceholder.style.display = 'none';
                }
                
                // フィルターを適用
                applyVideoFiltersToRec();
                applyWipeSize();
                applyWipeRound();
                
                // ボタンの状態を更新
                const cameraBtn = document.getElementById('cameraBtn');
                if (cameraBtn) {
                    cameraBtn.textContent = '📹 カメラ停止';
                    cameraBtn.onclick = stopCamera;
                }
                
                showMessage('カメラが開始されました', 'success');
                
            } catch (error) {
                console.error('カメラエラー:', error);
                showMessage('カメラの開始に失敗しました: ' + error.message, 'error');
            }
        }

        // カメラ停止
        function stopCamera() {
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
                localStream = null;
                
                // ビデオを非表示
                const verticalVideo = document.getElementById('verticalVideo');
                const horizontalVideo = document.getElementById('horizontalVideo');
                const verticalPlaceholder = document.getElementById('verticalPlaceholder');
                const horizontalPlaceholder = document.getElementById('horizontalPlaceholder');
                
                if (verticalVideo) {
                    verticalVideo.style.display = 'none';
                    verticalVideo.srcObject = null;
                }
                if (horizontalVideo) {
                    horizontalVideo.style.display = 'none';
                    horizontalVideo.srcObject = null;
                }
                if (verticalPlaceholder) verticalPlaceholder.style.display = 'block';
                if (horizontalPlaceholder) horizontalPlaceholder.style.display = 'block';
                
                // ボタンの状態を更新
                const cameraBtn = document.getElementById('cameraBtn');
                if (cameraBtn) {
                    cameraBtn.textContent = '📹 カメラ開始';
                    cameraBtn.onclick = startCamera;
                }
                
                showMessage('カメラを停止しました', 'info');
            }
        }

        // 収録開始/停止
        async function toggleRecording() {
            if (!isRecording) {
                await startRecording();
            } else {
                stopRecording();
            }
        }

        async function startRecording() {
            try {
                if (!localStream) {
                    showMessage('先にカメラを開始してください', 'error');
                    return;
                }
                
                showMessage('収録を開始しています...', 'info');
                
                // 画面キャプチャを取得
                const displayStream = await navigator.mediaDevices.getDisplayMedia({
                    video: {
                        width: 1920,
                        height: 1080,
                        frameRate: 30
                    },
                    audio: true
                });
                
                // オーディオコンテキストで音声をミックス
                audioContext = new AudioContext();
                destination = audioContext.createMediaStreamDestination();
                
                // マイク音声
                const micTrack = localStream.getAudioTracks()[0];
                if (micTrack) {
                    micSource = audioContext.createMediaStreamSource(new MediaStream([micTrack]));
                    micSource.connect(destination);
                }
                
                // デスクトップ音声
                const desktopAudioTrack = displayStream.getAudioTracks()[0];
                if (desktopAudioTrack) {
                    desktopSource = audioContext.createMediaStreamSource(new MediaStream([desktopAudioTrack]));
                    desktopSource.connect(destination);
                }
                
                // ビデオとミックスした音声を組み合わせ
                const videoTrack = displayStream.getVideoTracks()[0];
                const mixedAudioTrack = destination.stream.getAudioTracks()[0];
                
                mixedStream = new MediaStream([videoTrack, mixedAudioTrack]);
                
                // MediaRecorderで収録開始
                mediaRecorder = new MediaRecorder(mixedStream, {
                    mimeType: 'video/webm;codecs=vp9,opus',
                    videoBitsPerSecond: 2500000,
                    audioBitsPerSecond: 128000
                });
                
                recordedChunks = [];
                
                mediaRecorder.ondataavailable = function(event) {
                    if (event.data.size > 0) {
                        recordedChunks.push(event.data);
                    }
                };
                
                mediaRecorder.onstop = function() {
                    showSaveDialog();
                };
                
                mediaRecorder.start(1000); // 1秒間隔でチャンク作成
                
                isRecording = true;
                recordingStartTime = Date.now();
                
                // UI更新
                updateRecordingUI(true);
                startRecordingTimer();
                
                showMessage('収録を開始しました', 'success');
                
            } catch (error) {
                console.error('収録エラー:', error);
                showMessage('収録の開始に失敗しました: ' + error.message, 'error');
            }
        }

        function stopRecording() {
            if (mediaRecorder && isRecording) {
                mediaRecorder.stop();
                isRecording = false;
                
                // ストリーム停止
                if (mixedStream) {
                    mixedStream.getTracks().forEach(track => track.stop());
                }
                
                // オーディオコンテキスト停止
                if (audioContext) {
                    audioContext.close();
                }
                
                // UI更新
                updateRecordingUI(false);
                stopRecordingTimer();
                
                showMessage('収録を停止しました', 'success');
            }
        }

        function updateRecordingUI(recording) {
            const recordBtn = document.getElementById('recordBtn');
            const indicators = [
                document.getElementById('recordingIndicatorVertical'),
                document.getElementById('recordingIndicatorHorizontal')
            ];
            
            if (recordBtn) {
                if (recording) {
                    recordBtn.textContent = '⏹️ 収録停止';
                    recordBtn.classList.add('recording');
                } else {
                    recordBtn.textContent = '🎥 収録開始';
                    recordBtn.classList.remove('recording');
                }
            }
            
            indicators.forEach(indicator => {
                if (indicator) {
                    if (recording) {
                        indicator.classList.add('show');
                    } else {
                        indicator.classList.remove('show');
                    }
                }
            });
        }

        function startRecordingTimer() {
            recordingTimer = setInterval(() => {
                const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
                const minutes = Math.floor(elapsed / 60);
                const seconds = elapsed % 60;
                const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                const timeElements = [
                    document.getElementById('recordingTimeVertical'),
                    document.getElementById('recordingTimeHorizontal')
                ];
                
                timeElements.forEach(element => {
                    if (element) element.textContent = timeString;
                });
            }, 1000);
        }

        function stopRecordingTimer() {
            if (recordingTimer) {
                clearInterval(recordingTimer);
                recordingTimer = null;
                
                const timeElements = [
                    document.getElementById('recordingTimeVertical'),
                    document.getElementById('recordingTimeHorizontal')
                ];
                
                timeElements.forEach(element => {
                    if (element) element.textContent = '00:00';
                });
            }
        }

        // スライド操作
        function nextSlide() {
            if (currentSlide < totalSlides - 1) {
                currentSlide++;
                displaySlide(currentSlide);
                showMessage(`スライド ${currentSlide + 1}/${totalSlides}`, 'info');
            }
        }

        function prevSlide() {
            if (currentSlide > 0) {
                currentSlide--;
                displaySlide(currentSlide);
                showMessage(`スライド ${currentSlide + 1}/${totalSlides}`, 'info');
            }
        }

        function displaySlide(index) {
            if (index >= 0 && index < slides.length) {
                const slide = slides[index];
                updateSlideContent(slide);
            }
        }

        function updateSlideContent(slide) {
            const verticalContent = document.getElementById('verticalSlideContent');
            const horizontalContent = document.getElementById('horizontalSlideContent');
            
            const content = generateSlideHTML(slide);
            
            if (verticalContent) verticalContent.innerHTML = content;
            if (horizontalContent) horizontalContent.innerHTML = content;
        }

        function generateSlideHTML(slide) {
            let html = `<h1>${slide.title}</h1>`;
            
            if (slide.content) {
                html += `<div class="main-text">${slide.content}</div>`;
            }
            
            if (slide.note) {
                html += `<div class="glow-box">
                    <div style="font-size: 0.8em; color: #fcc800;">${slide.note}</div>
                </div>`;
            }
            
            return html;
        }

        // 保存ダイアログ
        function showSaveDialog() {
            const dialog = document.getElementById('saveDialog');
            const input = document.getElementById('filenameInput');
            
            if (dialog && input) {
                dialog.style.display = 'block';
                input.value = `podcast-${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}`;
                input.focus();
                input.select();
            }
        }

        function cancelSave() {
            const dialog = document.getElementById('saveDialog');
            if (dialog) {
                dialog.style.display = 'none';
            }
            recordedChunks = [];
        }

        function confirmSave() {
            const input = document.getElementById('filenameInput');
            const filename = input?.value?.trim() || 'recording';
            
            if (recordedChunks.length > 0) {
                const blob = new Blob(recordedChunks, { type: 'video/webm' });
                const url = URL.createObjectURL(blob);
                
                const a = document.createElement('a');
                a.href = url;
                a.download = `${filename}.webm`;
                a.click();
                
                URL.revokeObjectURL(url);
                showMessage(`${filename}.webm を保存しました`, 'success');
            }
            
            cancelSave();
        }

        // 終了処理
        function closeRecordingMode() {
            if (isRecording) {
                stopRecording();
            }
            
            if (localStream) {
                stopCamera();
            }
            
            showMessage('収録モードを終了します', 'info');
            
            // 実際のアプリケーションでは親ウィンドウに戻る処理
            setTimeout(() => {
                if (window.opener) {
                    window.close();
                } else {
                    window.location.reload();
                }
            }, 1500);
        }

        // メッセージ表示
        function showMessage(text, type = 'info') {
            const messageDiv = document.getElementById('messageDiv');
            if (messageDiv) {
                messageDiv.textContent = text;
                messageDiv.className = `message ${type}`;
                messageDiv.style.display = 'block';
                
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 3000);
            }
        }

        // エラーハンドリング
        window.addEventListener('error', function(e) {
            console.error('エラーが発生しました:', e.error);
            showMessage('エラーが発生しました: ' + e.error.message, 'error');
        });

        // ページを離れる前の確認
        window.addEventListener('beforeunload', function(e) {
            if (isRecording) {
                e.preventDefault();
                e.returnValue = '収録中です。ページを離れますか？';
                return e.returnValue;
            }
        });
    </script>
</body>
</html>