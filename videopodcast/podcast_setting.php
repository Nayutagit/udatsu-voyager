<?php
// session_start(); // セッションは使用しないのでコメントアウト
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>設定パネル</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;700;900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: transparent;
            color: #ffffff;
            padding: 60px 20px 20px 20px;
            overflow-y: auto;
            height: 100vh;
        }
        
        .control-section {
            background: rgba(252, 200, 0, 0.1);
            border: 1px solid #fcc800;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .control-section h3 {
            color: #fcc800;
            font-size: 16px;
            margin-bottom: 15px;
            text-shadow: 0 0 10px rgba(252, 200, 0, 0.8);
        }
        
        .transcript-input {
            width: 100%;
            min-height: 120px;
            background: rgba(0, 0, 0, 0.4);
            border: 2px solid #fcc800;
            border-radius: 8px;
            color: #fff;
            padding: 12px;
            font-size: 12px;
            resize: vertical;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .transcript-input::placeholder {
            color: #888;
        }
        
        .control-btn {
            background: rgba(252, 200, 0, 0.8);
            color: #000;
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            margin: 5px;
            width: calc(50% - 10px);
            transition: all 0.3s ease;
        }
        
        .control-btn:hover {
            background: #fcc800;
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(252, 200, 0, 0.5);
        }
        
        .control-btn:disabled {
            background: rgba(100, 100, 100, 0.5);
            color: #666;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .control-btn.full-width {
            width: calc(100% - 10px);
        }
        
        .control-btn.generate {
            background: linear-gradient(45deg, #ff6b6b, #fcc800);
            color: #fff;
            font-weight: bold;
        }
        
        .control-btn.generate:hover {
            background: linear-gradient(45deg, #ff5252, #ffb300);
        }
        
        .slider-container {
            margin: 10px 0;
        }
        
        .slider-label {
            color: #e0e0e0;
            font-size: 12px;
            margin-bottom: 5px;
            display: block;
        }
        
        .slider {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #666;
            outline: none;
            -webkit-appearance: none;
        }
        
        .slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fcc800;
            cursor: pointer;
        }
        
        /* メッセージ表示 */
        .message {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 20px 30px;
            border-radius: 10px;
            font-weight: bold;
            z-index: 10000;
            display: none;
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
    <div class="control-section">
        <h3>📝 文字起こしテキスト入力</h3>
        <textarea class="transcript-input" id="transcriptText" placeholder="ここに音声文字起こしのテキストを貼り付けてください。

例：
今日は声のトレーニングについてお話しします。私はテレビやラジオのナレーターをしています。多くの方が声で悩んでいると思います。声が小さい、聞き返される、長時間話すと疲れる、などです。でも正しい発声法を覚えれば必ず改善できます。まず腹式呼吸が基本です。お腹に手を当てて、息を吸うときにお腹が膨らむように意識してください。次に口の形です。しっかりと開けて、明瞭に発音することが大切です..."></textarea>
        <button class="control-btn full-width generate" onclick="generateSlidesFromTranscript()">🚀 AIでスライド生成</button>
    </div>
    
    <div class="control-section">
        <h3>📹 カメラ制御</h3>
        <button class="control-btn" onclick="startCamera()">カメラ開始</button>
        <button class="control-btn" onclick="stopCamera()" disabled id="stopBtn">カメラ停止</button>
        <button class="control-btn" onclick="flipCamera()">映像反転</button>
        <button class="control-btn" onclick="testCamera()">カメラテスト</button>
    </div>
    
    <div class="control-section">
        <h3>💾 スライド保存・読み込み</h3>
        <button class="control-btn" onclick="saveSlides()">💾 保存</button>
        <button class="control-btn" onclick="loadSlides()">📁 読み込み</button>
        <button class="control-btn" onclick="exportSlides()">📤 エクスポート</button>
        <button class="control-btn" onclick="loadSlidesFromFile()">📂 ファイル選択</button>
        <button class="control-btn full-width" onclick="clearSlides()">🗑️ 全クリア</button>
        <input type="file" id="fileInput" accept=".json" style="display: none;" onchange="handleFileLoad(event)">
    </div>
    
    <div class="control-section">
        <h3>🎬 ナビゲーション制御</h3>
        <button class="control-btn full-width" onclick="nextSlide()">Next ➡️</button>
        <button class="control-btn full-width" onclick="prevSlide()">⬅️ 前のスライド</button>
        <button class="control-btn full-width" onclick="resetPresentation()">🔄 最初に戻る</button>
        <button class="control-btn full-width" onclick="openRecordingMode()">🎥 収録モード</button>
    </div>
    
    <div class="control-section">
        <h3>🎨 映像調整</h3>
        <div class="slider-container">
            <label class="slider-label">明るさ: <span id="brightnessValue">0</span></label>
            <input type="range" class="slider" min="-50" max="50" value="0" oninput="adjustBrightness(this.value)">
        </div>
        
        <div class="slider-container">
            <label class="slider-label">コントラスト: <span id="contrastValue">100</span>%</label>
            <input type="range" class="slider" min="50" max="200" value="100" oninput="adjustContrast(this.value)">
        </div>
        
        <div class="slider-container">
            <label class="slider-label">ズーム: <span id="zoomValue">1.0</span>x</label>
            <input type="range" class="slider" min="1" max="3" step="0.1" value="1" oninput="adjustZoom(this.value)">
        </div>
    </div>

    <div class="message" id="messageDiv"></div>

    <script>
        // グローバル変数
        let localStream = null;
        let isFlipped = false;
        let brightness = 0;
        let contrast = 100;
        let zoom = 1.0;
        let currentSlides = [];

        // 親ウィンドウへの通信
        function sendToParent(type, data) {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type, ...data }, '*');
            }
        }

        // スライド生成
        async function generateSlidesFromTranscript() {
            const transcriptText = document.getElementById('transcriptText').value.trim();
            
            if (!transcriptText) {
                showMessage('文字起こしテキストを入力してください。', 'error');
                return;
            }
            
            const generateBtn = document.querySelector('.control-btn.generate');
            const originalText = generateBtn.textContent;
            startGeneratingEffect(generateBtn);
            
            try {
                // デモスライドデータを生成
                await new Promise(resolve => setTimeout(resolve, 2000));
                const slideData = getDemoSlides();
                
                // 親ウィンドウに送信
                sendToParent('slideUpdate', { slides: slideData });
                
                // スライドデータを保存
                currentSlides = slideData;
                
                showMessage(`${slideData.length}枚のスライドが生成されました！`, 'success');
                
            } catch (error) {
                console.error('スライド生成エラー:', error);
                showMessage('スライド生成に失敗しました', 'error');
            } finally {
                stopGeneratingEffect(generateBtn, originalText);
            }
        }

        // デモスライドデータ
        function getDemoSlides() {
            return [
                { type: 'title', title: '声のトレーニング講座', subtitle: 'プロから学ぶ発声の極意' },
                { type: 'points', title: 'こんな悩みありませんか？', points: ['声が小さくて聞き返される', '長時間話すと喉が痛くなる', 'プレゼンで声が震える'], summary: '正しい発声法で全て解決できます' },
                { type: 'simple', title: '発声の基本', message: '腹式呼吸が全ての土台です' },
                { type: 'points', title: '基本練習メニュー', points: ['腹式呼吸の習得', '口の形を意識', '舌の位置を正しく', '毎日5分の継続'], summary: '継続が上達の鍵' },
                { type: 'quote', title: 'プロからのアドバイス', quote: '声は技術です。正しい方法で練習すれば必ず上達します', speaker: 'ベテランナレーター' },
                { type: 'simple', title: 'まとめ', message: '今日から始めて、素敵な声を手に入れましょう！' }
            ];
        }

        // カメラ制御
        async function startCamera() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
                    audio: false
                });
                
                document.querySelector('button[onclick="startCamera()"]').disabled = true;
                document.getElementById('stopBtn').disabled = false;
                
                // カメラ状態を親ウィンドウに送信
                sendToParent('cameraUpdate', { 
                    status: 'started', 
                    stream: localStream,
                    brightness, 
                    contrast, 
                    zoom, 
                    isFlipped 
                });
                
                showMessage('カメラが開始されました', 'success');
                
            } catch (error) {
                console.error('カメラアクセスエラー:', error);
                showMessage('カメラにアクセスできませんでした', 'error');
            }
        }

        function stopCamera() {
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
                localStream = null;
            }
            
            document.querySelector('button[onclick="startCamera()"]').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            
            sendToParent('cameraUpdate', { status: 'stopped' });
            showMessage('カメラが停止されました', 'success');
        }

        function flipCamera() {
            isFlipped = !isFlipped;
            sendToParent('cameraUpdate', { 
                setting: 'flip', 
                value: isFlipped 
            });
        }

        function testCamera() {
            if (localStream) {
                showMessage('カメラは正常に動作中です', 'success');
            } else {
                showMessage('カメラが開始されていません', 'error');
            }
        }

        // スライド保存・読み込み機能
        function saveSlides() {
            if (currentSlides.length === 0) {
                showMessage('保存するスライドがありません', 'error');
                return;
            }
            
            try {
                localStorage.setItem('podcastSlides', JSON.stringify(currentSlides));
                sessionStorage.setItem('podcastSlides', JSON.stringify(currentSlides));
                showMessage(`${currentSlides.length}枚のスライドを保存しました`, 'success');
            } catch (error) {
                console.error('保存エラー:', error);
                showMessage('保存に失敗しました', 'error');
            }
        }

        function loadSlides() {
            try {
                const savedSlides = localStorage.getItem('podcastSlides');
                if (savedSlides) {
                    const slideData = JSON.parse(savedSlides);
                    sendToParent('slideUpdate', { slides: slideData });
                    currentSlides = slideData;
                    showMessage(`${slideData.length}枚のスライドを読み込みました`, 'success');
                } else {
                    showMessage('保存されたスライドがありません', 'error');
                }
            } catch (error) {
                console.error('読み込みエラー:', error);
                showMessage('読み込みに失敗しました', 'error');
            }
        }

        function exportSlides() {
            if (currentSlides.length === 0) {
                showMessage('エクスポートするスライドがありません', 'error');
                return;
            }
            
            try {
                const dataStr = JSON.stringify(currentSlides, null, 2);
                const dataBlob = new Blob([dataStr], {type: 'application/json'});
                const url = URL.createObjectURL(dataBlob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `podcast-slides-${new Date().toISOString().slice(0, 10)}.json`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
                showMessage('スライドをエクスポートしました', 'success');
            } catch (error) {
                console.error('エクスポートエラー:', error);
                showMessage('エクスポートに失敗しました', 'error');
            }
        }

        function clearSlides() {
            if (confirm('すべてのスライドを削除しますか？')) {
                currentSlides = [];
                localStorage.removeItem('podcastSlides');
                sessionStorage.removeItem('podcastSlides');
                
                const defaultSlide = [{
                    type: 'intro',
                    title: '音声文字起こしからスライド生成',
                    content: '左側に文字起こしテキストを入力してAI生成ボタンを押してください',
                    note: 'ビデオポッドキャスト用のスライドが自動で作成されます'
                }];
                
                sendToParent('slideUpdate', { slides: defaultSlide });
                showMessage('スライドをクリアしました', 'success');
            }
        }

        function loadSlidesFromFile() {
            document.getElementById('fileInput').click();
        }

        function handleFileLoad(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const slideData = JSON.parse(e.target.result);
                    if (Array.isArray(slideData) && slideData.length > 0) {
                        sendToParent('slideUpdate', { slides: slideData });
                        currentSlides = slideData;
                        showMessage(`${slideData.length}枚のスライドをインポートしました`, 'success');
                    } else {
                        showMessage('無効なファイル形式です', 'error');
                    }
                } catch (error) {
                    console.error('ファイル読み込みエラー:', error);
                    showMessage('ファイルの読み込みに失敗しました', 'error');
                }
            };
            reader.readAsText(file);
            event.target.value = '';
        }

        // ナビゲーション制御
        function nextSlide() {
            sendToParent('slideUpdate', { action: 'next' });
        }

        function prevSlide() {
            sendToParent('slideUpdate', { action: 'prev' });
        }

        function resetPresentation() {
            sendToParent('slideUpdate', { action: 'reset' });
        }

        function openRecordingMode() {
            window.open('podcast_rec.php', '_blank');
        }

        // 映像調整
        function adjustBrightness(value) {
            brightness = parseInt(value);
            document.getElementById('brightnessValue').textContent = value;
            sendToParent('cameraUpdate', { setting: 'brightness', value: brightness });
        }

        function adjustContrast(value) {
            contrast = parseInt(value);
            document.getElementById('contrastValue').textContent = value;
            sendToParent('cameraUpdate', { setting: 'contrast', value: contrast });
        }

        function adjustZoom(value) {
            zoom = parseFloat(value);
            document.getElementById('zoomValue').textContent = value;
            sendToParent('cameraUpdate', { setting: 'zoom', value: zoom });
        }

        // エフェクト関数
        function startGeneratingEffect(button) {
            button.disabled = true;
            let dots = '';
            const updateDots = () => {
                dots = dots.length >= 3 ? '' : dots + '.';
                button.textContent = `🤖 AI生成中${dots}`;
            };
            updateDots();
            button.dotInterval = setInterval(updateDots, 500);
        }

        function stopGeneratingEffect(button, originalText) {
            button.disabled = false;
            button.textContent = originalText;
            if (button.dotInterval) {
                clearInterval(button.dotInterval);
                button.dotInterval = null;
            }
        }

        function showMessage(message, type) {
            const messageDiv = document.getElementById('messageDiv');
            messageDiv.textContent = message;
            messageDiv.className = `message ${type}`;
            messageDiv.style.display = 'block';
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 3000);
        }

        // 初期化
        window.addEventListener('load', function() {
            console.log('設定パネル起動完了');
            
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                document.querySelector('button[onclick="startCamera()"]').disabled = true;
                showMessage('カメラAPIが非対応です', 'error');
            }
            
            // 保存されたスライドを自動読み込み
            try {
                const savedSlides = localStorage.getItem('podcastSlides');
                if (savedSlides) {
                    const slideData = JSON.parse(savedSlides);
                    currentSlides = slideData;
                    console.log(`保存されたスライド ${slideData.length}枚を発見`);
                }
            } catch (error) {
                console.warn('保存されたスライドの読み込みに失敗:', error);
            }
        });

        // 親ウィンドウからのメッセージ受信
        window.addEventListener('message', function(event) {
            const data = event.data;
            switch(data.type) {
                case 'refresh':
                    location.reload();
                    break;
            }
        });

        // ページ離脱時のクリーンアップ
        window.addEventListener('beforeunload', function() {
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
            }
        });
    </script>
</body>
</html>